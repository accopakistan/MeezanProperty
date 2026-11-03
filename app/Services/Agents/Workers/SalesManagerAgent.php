<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Sales Manager: lead flow, follow-ups, pipeline, conversion; never contacts users or reassigns leads without approval. */
class SalesManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'follow_ups' => ['follow-up', 'follow up', 'followup', 'overdue', 'today', 'require follow'],
            'pipeline' => ['pipeline', 'stage', 'conversion', 'won', 'lost'],
            'leads' => ['lead', 'leads report', 'lead performance', 'sales report', 'qualified'],
            'my_leads' => ['my leads', 'meri leads', 'mere leads', 'my follow'],
            'new_lead' => ['new qualified lead', 'prioritise new lead'],
            'reassign' => ['reassign', 'move lead'],
            'patterns' => ['lost lead', 'lost-lead', 'why leads', 'lost reasons', 'dealer response', 'response time', 'appointment', 'viewings', 'prioriti'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'follow_ups' => $this->followUps($ctx),
            'pipeline' => $this->pipeline($ctx),
            'my_leads' => $this->myLeads($ctx),
            'new_lead' => $this->newLead($ctx),
            'reassign' => $this->propose($ctx, 'leads.reassign', 'Lead reassignment request'),
            'patterns' => $this->patterns($ctx),
            default => $this->leads($ctx),
        };
    }

    protected function leads(AgentContext $ctx): Report
    {
        $city = $this->city($ctx->task->request);
        $days = $this->days($ctx->task->request);
        $r = new Report('Lead performance'.($city ? " — {$city}" : ''));
        $r->period = "Last {$days} days";
        $s = $this->need($ctx, $r, 'leads.summary', array_filter(['days' => $days, 'city' => $city]));
        if ($s) {
            $r->finding("{$s['total']} lead(s) in the last {$days} days.");
            $r->table('By city', ['City', 'Leads'], collect($s['by_city'])->map(fn ($n, $c) => [$c, $n])->values()->all(), 'No leads.');
            $r->table('By status', ['Status', 'Leads'], collect($s['by_status'])->map(fn ($n, $c) => [$c, $n])->values()->all(), 'No leads.');
            $r->table('By source', ['Source', 'Leads'], collect($s['by_source'])->map(fn ($n, $c) => [$c, $n])->values()->all(), 'No leads.');
            ($s['by_status']['unrouted'] ?? 0) > 0 && $r->recommend(($s['by_status']['unrouted']).' lead(s) are unrouted: check dealer coverage and allowances.');
        }
        $this->followUpLine($ctx, $r);

        return $r;
    }

    protected function followUps(AgentContext $ctx): Report
    {
        $r = new Report('Follow-ups due today or overdue');
        $f = $this->need($ctx, $r, 'crm.follow_ups_due', ['limit' => 50]);
        if ($f) {
            $r->finding("{$f['count']} open follow-up(s) due by the end of today, {$f['overdue']} already overdue.");
            $r->table('Follow-ups (lead references only)', ['Follow-up', 'Lead', 'Dealer', 'Type', 'Priority', 'Due', 'Stage'],
                collect($f['follow_ups'])->map(fn ($x) => [$x['follow_up_id'], $x['lead_id'], $x['dealer_id'], $x['type'], $x['priority'], $x['due'], $x['stage']])->all(), 'Nothing is due.');
            $f['overdue'] > 0 && $r->recommend('Ask dealers to complete or reschedule overdue follow-ups in their CRM.');
        }

        return $r;
    }

    protected function pipeline(AgentContext $ctx): Report
    {
        $r = new Report('Sales pipeline');
        $p = $this->need($ctx, $r, 'crm.pipeline');
        if ($p) {
            $r->table('Active assignments by stage', ['Stage', 'Assignments'], collect($p['active_by_stage'])->map(fn ($n, $s) => [$s, $n])->values()->all(), 'No active assignments.');
            $r->finding("Won in the last 30 days: {$p['won_last_30_days']}; stale active assignments: {$p['stale_active']}.");
            $p['stale_active'] > 0 && $r->recommend('Review stale assignments; reassigning a lead needs staff approval.');
        }

        return $r;
    }

    protected function patterns(AgentContext $ctx): Report
    {
        $days = $this->days($ctx->task->request, 90);
        $r = new Report('Dealer response and lost-lead patterns');
        $r->period = "Last {$days} days";
        $o = $this->need($ctx, $r, 'crm.outcomes', ['days' => $days]);
        if ($o) {
            $r->finding($o['first_response_hours_median'] !== null
                ? "Median first response: {$o['first_response_hours_median']} hours across {$o['responded']} assignment(s)."
                : 'No assignment has a recorded first contact yet, so response time cannot be measured.');
            $r->finding("{$o['awaiting_first_contact']} active assignment(s) older than 24 hours have no first contact; {$o['appointments_next_7_days']} appointment(s) are scheduled in the next 7 days.");
            $r->table('Lost leads by reason', ['Reason', 'Assignments'], collect($o['lost_by_reason'])->map(fn ($n, $k) => [$k, $n])->values()->all(), 'No lost leads in this period.');
            $o['awaiting_first_contact'] > 0 && $r->recommend('Prioritise the assignments still waiting for a first contact.');
        }

        return $r;
    }

    /** A dealer asking about their own leads (website or WhatsApp). */
    protected function myLeads(AgentContext $ctx): Report
    {
        $r = new Report('Your leads');
        $s = $this->need($ctx, $r, 'dealer.self_summary');
        if ($s && $s['available']) {
            $r->finding("You have {$s['open_leads']} open lead(s) and {$s['follow_ups_due']} follow-up(s) due today. Open your CRM: ".route('dealer.crm'));
        } elseif ($s) {
            $r->finding($s['reason']);
        }

        return $r;
    }

    /** Event: a new lead was created. Note it for prioritisation (no contact is made). */
    protected function newLead(AgentContext $ctx): Report
    {
        $r = new Report('New lead #'.($ctx->task->input['lead_id'] ?? '?'));
        $p = $this->need($ctx, $r, 'crm.pipeline');
        $p && $r->finding('Active pipeline now: '.collect($p['active_by_stage'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ').'.');
        $r->limitation('The agent does not message buyers or dealers; routing already follows the lead rules and consent.');

        return $r;
    }

    protected function followUpLine(AgentContext $ctx, Report $r): void
    {
        $f = $this->need($ctx, $r, 'crm.follow_ups_due', ['limit' => 100]);
        if ($f && $f['overdue'] > 0) {
            $r->recommend("{$f['overdue']} CRM follow-up(s) are overdue.");
        }
    }
}
