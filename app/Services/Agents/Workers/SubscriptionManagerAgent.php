<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Subscription Manager: renewals, expiries, allowance use, reminder drafts. No price or entitlement changes without approval. */
class SubscriptionManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'expiring' => ['expir', 'renewal', 'renew', 'past due', 'failed renewal', 'reminder'],
            'utilization' => ['utiliz', 'utilis', 'usage', 'limit', 'allowance', 'upgrade'],
            'summary' => ['subscription', 'package', 'plan', 'status'],
            'my_plan' => ['my subscription', 'my package', 'my plan', 'mera package', 'meri subscription'],
            'change' => ['change package', 'change subscription', 'downgrade', 'extend subscription'],
            'customer_plans' => [], // chosen only by the customer routes, never by keywords
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'expiring' => $this->expiring($ctx),
            'utilization' => $this->utilization($ctx),
            'my_plan' => $this->myPlan($ctx),
            'change' => $this->propose($ctx, 'subscriptions.change', 'Subscription change request'),
            'customer_plans' => $this->customerPlans($ctx),
            default => $this->summary($ctx),
        };
    }

    /** A customer asks about dealer packages. Public pricing only; changes nothing. */
    protected function customerPlans(AgentContext $ctx): Report
    {
        $r = new Report('Dealer packages');
        $r->verbatim = true;
        $this->publicAnswer($ctx, $r, 'dealer packages subscription pricing', $this->urdu($ctx->task->request)
            ? 'Dealer packages aur un ki qeemat hamari pricing page par hain.'
            : 'Our dealer packages and their prices are on the pricing page.');
        $r->finding('Pricing: '.route('pricing'));
        $r->finding('Reply "human" if you need help choosing or changing a package.');

        return $r;
    }

    protected function summary(AgentContext $ctx): Report
    {
        $r = new Report('Subscriptions');
        $s = $this->need($ctx, $r, 'subscriptions.summary');
        if ($s) {
            $r->table('By status', ['Status', 'Subscriptions'], collect($s['by_status'])->map(fn ($n, $k) => [$k, $n])->values()->all(), 'No subscriptions.');
            $r->table('Active by package', ['Package', 'Dealers'], collect($s['active_by_package'])->map(fn ($n, $k) => [$k, $n])->values()->all(), 'No active subscriptions.');
        }
        $this->expiringLine($ctx, $r);

        return $r;
    }

    protected function expiring(AgentContext $ctx): Report
    {
        $r = new Report('Expiring and past-due subscriptions');
        $e = $this->need($ctx, $r, 'subscriptions.expiring', ['days' => 14]);
        if ($e) {
            $r->finding("{$e['count']} subscription(s) end within 14 days or are past due.");
            $r->table('Subscriptions', ['ID', 'Dealer', 'Package', 'Status', 'Period end', 'Cancels'], collect($e['subscriptions'])->map(fn ($s) => [$s['subscription_id'], $s['dealer_id'], $s['package'], $s['status'], $s['period_end'], $s['cancels_at_period_end'] ? 'yes' : 'no'])->all(), 'None.');
            if ($e['count'] > 0) {
                $d = $ctx->tool('content.save_draft', ['type' => 'reminder', 'channel' => 'email', 'title' => 'Renewal reminder (draft)',
                    'body' => "Assalam o Alaikum,\n\nYour Meezan Property subscription renews soon. Please check your billing page to keep your listings and leads active.\n\n— Meezan Property"]);
                $r->source('content.save_draft');
                $d['ok'] && $r->recommend('A renewal reminder draft is ready (draft #'.$d['data']['draft_id'].'). Sending to dealers needs approval.');
            }
        }

        return $r;
    }

    protected function utilization(AgentContext $ctx): Report
    {
        $r = new Report('Package utilisation');
        $u = $this->need($ctx, $r, 'subscriptions.utilization', ['limit' => 50]);
        if ($u) {
            $rows = collect($u['dealers']);
            $r->table('Allowance use', ['Dealer', 'Package', 'Listings', 'Listing limit', 'Leads', 'Lead limit'], $rows->map(fn ($d) => [$d['dealer_id'], $d['package'], $d['listings'], $d['listing_limit'] ?? 'unlimited', $d['leads'], $d['lead_limit'] ?? 'unlimited'])->all(), 'No active dealers.');
            $near = $rows->filter(fn ($d) => $d['listing_limit'] && $d['listings'] >= 0.8 * $d['listing_limit'])->count();
            $near && $r->recommend("{$near} dealer(s) use 80%+ of their listing allowance: suggest an upgrade (changes need approval).");
        }

        return $r;
    }

    protected function myPlan(AgentContext $ctx): Report
    {
        $r = new Report('Your subscription');
        $s = $this->need($ctx, $r, 'dealer.self_summary');
        if ($s && $s['available']) {
            $r->finding("Package: {$s['package']}. Listings used: {$s['usage']['listings']}; leads this month: {$s['usage']['leads']}.".($s['subscription_ends'] ? ' Current period ends '.substr((string) $s['subscription_ends'], 0, 10).'.' : '').' Manage it here: '.route('dealer.subscription'));
        } elseif ($s) {
            $r->finding($s['reason']);
        }

        return $r;
    }

    protected function expiringLine(AgentContext $ctx, Report $r): void
    {
        $e = $this->need($ctx, $r, 'subscriptions.expiring', ['days' => 14]);
        $e && $r->finding("{$e['count']} subscription(s) end within 14 days or are past due.");
    }
}
