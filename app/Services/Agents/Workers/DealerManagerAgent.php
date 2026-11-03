<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Dealer Management: verification queue, documents, activity, performance, onboarding. */
class DealerManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'pending' => ['pending', 'verification', 'documents', 'not verified', 'onboarding', 'registration'],
            'performance' => ['performance', 'converting', 'response', 'best dealers', 'coverage'],
            'inactive' => ['inactive', 'not active', 'dormant'],
            'quality' => ['listing quality', 'profile quality', 'dealer quality', 'incomplete profile'],
            'new_dealer' => ['new dealer registered', 'review new dealer'],
            'verify' => ['recommend verification', 'verify dealer', 'approve dealer'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        $city = $this->city($ctx->task->request);

        return match ($intent) {
            'performance' => $this->performance($ctx, $city),
            'inactive' => $this->inactive($ctx),
            'quality' => $this->quality($ctx, $city),
            'new_dealer' => $this->newDealer($ctx),
            'verify' => $this->propose($ctx, 'dealers.verify', 'Dealer verification request'),
            default => $this->pending($ctx, $city),
        };
    }

    protected function pending(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Dealers awaiting verification'.($city ? " — {$city}" : ''));
        $data = $this->need($ctx, $r, 'dealers.pending_verification', array_filter(['city' => $city]));
        if ($data) {
            $r->finding("{$data['count']} dealer(s) are not verified yet.");
            $r->table('Verification queue', ['Dealer', 'Agency', 'City', 'Status', 'Submitted', 'Docs pending / approved / rejected'],
                collect($data['dealers'])->map(fn ($d) => [$d['dealer_id'], $d['agency'], $d['city'], $d['status'], $d['submitted'] ?? '—',
                    $d['documents']['pending'].' / '.$d['documents']['approved'].' / '.$d['documents']['rejected']])->all(), 'No dealers are waiting.');
            $noDocs = collect($data['dealers'])->whereNotNull('missing')->count();
            $noDocs && $r->recommend("{$noDocs} dealer(s) have no current documents: send them the document checklist.");
            collect($data['dealers'])->where('status', 'submitted')->count() && $r->recommend('Review submitted verifications in Admin → Dealers. Identity documents are only approved by staff.');
        }

        return $r;
    }

    protected function performance(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Dealer performance (30 days)');
        $data = $this->need($ctx, $r, 'dealers.performance', array_filter(['city' => $city, 'days' => 30]));
        if ($data) {
            $rows = collect($data['dealers']);
            $r->finding($rows->count().' verified dealer(s); '.$rows->sum('received').' lead(s) received, '.$rows->sum('won').' won.');
            $r->table('By dealer', ['Agency', 'City', 'Received', 'Contacted', 'Qualified', 'Won', 'Lost', 'Overdue follow-ups'],
                $rows->map(fn ($d) => [$d['agency'], $d['city'], $d['received'] ?? 0, $d['contacted'] ?? 0, $d['qualified'] ?? 0, $d['won'] ?? 0, $d['lost'] ?? 0, $d['followups_overdue'] ?? 0])->all(), 'No verified dealers.');
            $rows->where('followups_overdue', '>', 0)->count() && $r->recommend('Remind dealers with overdue follow-ups.');
        }

        return $r;
    }

    protected function inactive(AgentContext $ctx): Report
    {
        $r = new Report('Inactive verified dealers (30 days)');
        $data = $this->need($ctx, $r, 'dealers.inactive', ['days' => 30]);
        if ($data) {
            $r->finding("{$data['count']} verified dealer(s) had no listing updates or lead activity.");
            $r->table('Inactive', ['Dealer', 'Agency', 'City'], collect($data['dealers'])->map(fn ($d) => array_values($d))->all(), 'None.');
            $data['count'] && $r->recommend('Contact inactive dealers (with their consent settings) to re-engage them.');
        }

        return $r;
    }

    protected function quality(AgentContext $ctx, ?string $city): Report
    {
        $r = new Report('Dealer listing and profile quality');
        $data = $this->need($ctx, $r, 'dealers.listing_quality', array_filter(['city' => $city]));
        if ($data) {
            $rows = collect($data['dealers']);
            $r->finding($rows->count().' dealer(s) checked; '.$rows->sum('low_completeness').' live listing(s) score below '.$data['low_score_below'].' on completeness.');
            $r->table('By dealer', ['Dealer', 'Agency', 'City', 'Live listings', 'Average completeness', 'Low scoring', 'Profile gaps'],
                $rows->map(fn ($d) => [$d['dealer_id'], $d['agency'], $d['city'], $d['live_listings'], $d['average_completeness'] ?? '—', $d['low_completeness'], implode('; ', $d['profile_gaps']) ?: 'None'])->all(), 'No dealers.');
            $rows->where('low_completeness', '>', 0)->count() && $r->recommend('Ask dealers with low-scoring listings to add the missing items.');
            $r->limitation('Completeness measures how much information is given, not the quality of the property.');
        }

        return $r;
    }

    /** Event: a dealer registered. Prepare onboarding checks as a staff task. */
    protected function newDealer(AgentContext $ctx): Report
    {
        $id = (int) ($ctx->task->input['dealer_id'] ?? 0);
        $r = new Report('New dealer onboarding #'.$id);
        $data = $this->need($ctx, $r, 'dealers.pending_verification', []);
        $d = collect($data['dealers'] ?? [])->firstWhere('dealer_id', $id);
        $d ? $r->finding("Agency {$d['agency']} ({$d['city']}) — status {$d['status']}; ".($d['missing'] ?? 'documents uploaded').'.') : $r->missing('The dealer was not found in the verification queue.');
        if ($d) {
            $t = $ctx->tool('staff.create_task', ['title' => 'Onboard dealer #'.$id, 'description' => 'Check profile and documents; verification decisions stay with staff.', 'category' => 'dealer_onboarding', 'related_type' => 'dealer', 'related_id' => $id, 'due_in_days' => 2]);
            $r->source('staff.create_task');
            $t['ok'] && $r->recommend('Onboarding task created for staff (#'.$t['data']['staff_task_id'].').');
        }

        return $r;
    }
}
