<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/** Finance Manager: read-only revenue, outstanding and AI-cost reporting. Never moves money. */
class FinanceManagerAgent extends Agent
{
    public static function intents(): array
    {
        return [
            'revenue' => ['revenue', 'subscription revenue', 'revenue report', 'income', 'monthly summary', 'financial', 'earnings', 'finance report'],
            'outstanding' => ['outstanding', 'unpaid', 'overdue invoice', 'receivable'],
            'reconcile' => ['reconcil', 'mismatch', 'refund analysis'],
            'ai_cost' => ['ai cost', 'ai usage', 'token', 'api cost'],
            'refund' => ['refund', 'transfer money', 'pay out', 'payout'],
            'customer_payment' => [], // chosen only by the customer routes, never by keywords
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        return match ($intent) {
            'outstanding' => $this->outstanding($ctx),
            'reconcile' => $this->reconcile($ctx),
            'ai_cost' => $this->aiCost($ctx),
            'refund' => $this->refused($ctx),
            'customer_payment' => $this->customerPayment($ctx),
            default => $this->revenue($ctx),
        };
    }

    /** A customer reports a payment problem. Reads no financial record, approves nothing, and passes it to staff. */
    protected function customerPayment(AgentContext $ctx): Report
    {
        $r = new Report('Payment issue');
        $r->verbatim = true;
        $r->handoff = 'payment_issue';
        $ticket = $this->ticketForCustomer($ctx, $r, 'payment', 'high', 'Payment issue from a customer');
        $reference = $ticket ? " (reference {$ticket})" : '';
        $r->finding($this->urdu($ctx->task->request)
            ? "Main yahan payments check, tabdeel ya refund approve nahi kar sakta. Maine yeh hamari team ko bhej diya hai{$reference}; team ka koi member yahin jawab dega."
            : "I can't check or change payments or approve refunds here. I've passed this to our team{$reference}, and a member of staff will reply here.");

        return $r;
    }

    protected function revenue(AgentContext $ctx): Report
    {
        $month = $ctx->task->input['month'] ?? now()->format('Y-m');
        $r = new Report('Subscription revenue — '.$month);
        $r->period = $month;
        $rev = $this->need($ctx, $r, 'payments.revenue', ['month' => $month]);
        if ($rev) {
            $r->finding("Succeeded payments: {$rev['succeeded_payments']} totalling PKR ".number_format($rev['succeeded_amount']).'.');
            $r->finding('Refunded in the month: PKR '.number_format($rev['refunded_amount'])."; payments pending or awaiting review: {$rev['pending_payments']}.");
            $r->table('By gateway', ['Gateway', 'PKR'], collect($rev['by_gateway'])->map(fn ($n, $g) => [$g, number_format($n)])->values()->all(), 'No payments.');
            $rev['test_payments_excluded'] && $r->limitation("{$rev['test_payments_excluded']} test payment(s) excluded.");
        }
        $subs = $this->need($ctx, $r, 'subscriptions.summary');
        $subs && $r->finding('Subscriptions by status: '.(collect($subs['by_status'])->map(fn ($n, $s) => "{$s} {$n}")->implode(', ') ?: 'none').'.');
        $r->limitation('Read-only report from payment and invoice records. No forecasts are made without stated assumptions.');

        return $r;
    }

    protected function outstanding(AgentContext $ctx): Report
    {
        $r = new Report('Outstanding payments');
        $o = $this->need($ctx, $r, 'payments.outstanding');
        if ($o) {
            $r->finding("{$o['unpaid_invoices']} unpaid invoice(s) worth PKR ".number_format($o['unpaid_amount'])." ({$o['overdue']} overdue); {$o['awaiting_review_payments']} payment(s) awaiting review.");
            $r->table('Unpaid invoices', ['Invoice', 'Dealer', 'PKR', 'Status', 'Due'], collect($o['invoices'])->map(fn ($i) => [$i['number'], $i['dealer_id'], number_format($i['amount']), $i['status'], $i['due']])->all(), 'None.');
            $o['awaiting_review_payments'] && $r->recommend('Review bank-transfer proofs in Admin → Payments.');
        }

        return $r;
    }

    protected function reconcile(AgentContext $ctx): Report
    {
        $month = $ctx->task->input['month'] ?? now()->format('Y-m');
        $r = new Report('Payment reconciliation — '.$month);
        $r->period = $month;
        $d = $this->need($ctx, $r, 'payments.reconciliation', ['month' => $month]);
        if ($d) {
            $r->finding("Paid invoices: {$d['paid_invoices']} (PKR ".number_format($d['paid_invoice_amount'])."); succeeded payments: {$d['succeeded_payments']} (PKR ".number_format($d['succeeded_amount']).').');
            $r->finding('Refunds recorded: PKR '.number_format($d['refunds_recorded']).'; credits: PKR '.number_format($d['credits_recorded']).'.');
            $r->table('Items to check', ['Reference', 'Issue'], collect($d['mismatches'])->map(fn ($m) => [$m['reference'], $m['issue']])->all(), 'Payments and invoices agree for this month.');
            count($d['mismatches']) && $r->recommend('Check the listed items in Admin → Payments. The agent changes nothing.');
            $d['test_records_excluded'] && $r->limitation("{$d['test_records_excluded']} test record(s) excluded.");
        }
        $r->limitation('Read-only comparison of recorded invoices and payments, not a bank statement reconciliation.');

        return $r;
    }

    protected function aiCost(AgentContext $ctx): Report
    {
        $r = new Report('AI usage and cost (30 days)');
        $u = $this->need($ctx, $r, 'ai.usage', ['days' => 30]);
        if ($u) {
            $r->finding("Model calls: {$u['model_calls']}; tokens in/out: ".number_format($u['tokens_in']).' / '.number_format($u['tokens_out']).'.');
            $r->finding($u['cost_usd'] === null ? $u['cost_note'] : 'Estimated cost: USD '.$u['cost_usd'].'. '.$u['cost_note']);
            $r->table('By provider', ['Provider', 'Requests', 'Tokens', 'Cost (USD)'], collect($u['by_provider'])->map(fn ($p) => [$p['provider'], $p['requests'], number_format($p['tokens']), $p['cost_usd'] ?? 'Not calculated'])->all(), 'No model calls.');
        }

        return $r;
    }

    protected function refused(AgentContext $ctx): Report
    {
        $r = new Report('Refunds and transfers');
        $t = $ctx->tool('payments.refund');
        $r->source('payments.refund');
        $r->finding('I cannot issue refunds or move money: '.$t['error'].' Refunds are handled by staff in Admin → Billing.');

        return $r;
    }
}
