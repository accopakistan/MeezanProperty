<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionManager;
use App\Services\Leads\LeadRouter;
use Illuminate\Console\Command;

/**
 * Daily billing housekeeping: renewal invoices and reminders, overdue and
 * expired subscriptions, scheduled downgrades and cancellations, unpaid
 * checkouts, and buyer leads held because dealers were at their limit.
 * Safe to run more than once a day; never charges anyone.
 */
class BillingLifecycle extends Command
{
    protected $signature = 'meezan:billing-lifecycle';

    protected $description = 'Advance subscriptions (renewals, expiry, scheduled changes) and retry held leads';

    public function handle(SubscriptionManager $subscriptions, LeadRouter $router): int
    {
        $done = $subscriptions->runLifecycle();
        $done['held_leads_routed'] = $router->retryHeld();

        foreach ($done as $step => $count) {
            $this->line(str_pad(str_replace('_', ' ', $step), 22).$count);
        }

        return self::SUCCESS;
    }
}
