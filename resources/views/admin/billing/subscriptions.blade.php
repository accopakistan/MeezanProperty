@php use App\Enums\SubscriptionStatus; @endphp
<x-layouts.dashboard title="Subscriptions" section="admin" heading="Subscriptions" subheading="Every dealer subscription, including ended ones. Nothing is deleted.">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none">
            <a href="{{ route('admin.subscriptions') }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => empty($filters['status']), 'text-muted hover:text-ink' => ! empty($filters['status'])])>All <span class="text-xs opacity-75">{{ $counts->sum() }}</span></a>
            @foreach (SubscriptionStatus::cases() as $status)
                <a href="{{ route('admin.subscriptions', ['status' => $status->value] + array_filter(['package' => $filters['package'] ?? null])) }}" @class(['shrink-0 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => ($filters['status'] ?? null) === $status->value, 'text-muted hover:text-ink' => ($filters['status'] ?? null) !== $status->value])>{{ $status->label() }} <span class="text-xs opacity-75">{{ $counts[$status->value] ?? 0 }}</span></a>
            @endforeach
        </div>
        <form method="GET" class="ml-auto flex flex-wrap gap-2">
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <select name="package" class="input w-44 py-2" aria-label="Package">
                <option value="">All packages</option>
                @foreach ($packages as $package)<option value="{{ $package->id }}" @selected(($filters['package'] ?? null) == $package->id)>{{ $package->name }}</option>@endforeach
            </select>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-52 py-2" placeholder="Dealer, agency or email" aria-label="Search">
            <button class="btn btn-secondary btn-sm">Filter</button>
        </form>
    </div>

    @if ($subscriptions->isEmpty())
        <x-empty-state icon="credit-card" title="No subscriptions found">Dealers without a subscription are on the default plan.</x-empty-state>
    @else
        <div class="card overflow-hidden"><div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Dealer</th><th>Package</th><th class="text-right">Amount</th><th>Status</th><th>Period ends</th><th>Started</th><th class="sr-only">Open</th></tr></thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td class="text-[13px]"><p class="font-semibold">{{ $subscription->user->dealerProfile?->agency_name ?? $subscription->user->name }}</p><p class="text-muted">{{ $subscription->user->email }}</p></td>
                            <td>{{ $subscription->name() }} <span class="text-xs text-muted">({{ $subscription->change_type }})</span> @if ($subscription->is_test)<span class="badge bg-amber-50 text-amber-700">Test</span>@endif</td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ $subscription->isPaid() ? $subscription->currency.' '.number_format((float) $subscription->amount).' / '.($subscription->billing_interval === 'year' ? 'yr' : 'mo') : 'Free' }}</td>
                            <td><x-status-badge :tone="$subscription->status->tone()" :label="$subscription->status->label()" />@if ($subscription->cancel_at_period_end)<p class="mt-1 text-xs text-muted">Cancels at period end</p>@elseif ($subscription->scheduled_package_id)<p class="mt-1 text-xs text-muted">Change scheduled</p>@endif</td>
                            <td class="whitespace-nowrap text-muted">{{ $subscription->renewsOrEndsAt()?->format('j M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-muted">{{ ($subscription->starts_at ?? $subscription->created_at)->format('j M Y') }}</td>
                            <td class="text-right"><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="btn btn-secondary btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-6">{{ $subscriptions->links() }}</div>
    @endif
</x-layouts.dashboard>
