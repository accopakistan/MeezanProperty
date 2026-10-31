@php $isSubs = $page === 'subscriptions'; @endphp
<x-layouts.dashboard :title="$isSubs ? 'Subscriptions' : 'Payments'" section="admin" :heading="$isSubs ? 'Subscriptions' : 'Payments'" :subheading="$isSubs ? 'Dealer plans and renewals.' : 'Transactions from the payment provider.'">
    <x-empty-state :icon="$isSubs ? 'credit-card' : 'file-text'" :title="$isSubs ? 'Subscription plans are not set up' : 'No payment provider is connected'">
        {{ $isSubs
            ? 'There are no plans or dealer subscriptions in the database yet. All dealers currently have launch access without billing.'
            : 'No payments have been processed. Payment records will appear here once a provider (for example JazzCash, Easypaisa or a card gateway) is integrated.' }}
    </x-empty-state>
    <div class="card card-body mt-6 text-sm text-muted">
        <p class="font-semibold text-ink">To enable {{ $isSubs ? 'subscriptions' : 'payments' }}</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Decide plan tiers, prices and listing limits.</li>
            <li>Choose a payment provider and obtain merchant credentials.</li>
            <li>Add plan, subscription and payment tables, and a provider service kept separate from the UI.</li>
            <li>Handle provider callbacks idempotently and record every transaction.</li>
        </ol>
    </div>
</x-layouts.dashboard>
