<x-layouts.dashboard title="Packages" section="admin" heading="Packages" subheading="Dealer packages, prices and limits. Edits apply to new subscriptions; existing ones keep the terms they started with.">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.packages.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="size-4" /> New package</a>
        <a href="{{ route('pricing') }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">View public page</a>
    </div>

    @unless ($hasDefault)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
            <p class="font-bold">No default plan is set.</p>
            <p class="mt-1">Dealers without a subscription currently have: {{ collect(config('meezan.billing.fallback_limits'))->map(fn ($v, $k) => ($v === null ? 'unlimited' : $v).' '.$k)->implode(', ') }} (config <code>meezan.billing.fallback_limits</code>). Mark a free package as the default plan to apply its limits instead.</p>
        </div>
    @endunless

    @if ($packages->isEmpty())
        <x-empty-state icon="credit-card" title="No packages yet">Create the packages dealers can choose from. Nothing is shown publicly until a package is active.</x-empty-state>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Package</th><th class="text-right">Price</th><th class="text-right">Listings</th><th class="text-right">Leads / month</th><th class="text-right">Featured</th><th class="text-right">Subscribers</th><th>Status</th><th class="sr-only">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($packages as $package)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.packages.edit', $package) }}" class="font-semibold hover:text-brand-700">{{ $package->name }}</a>
                                    <p class="mt-0.5 flex flex-wrap gap-1 text-xs">
                                        @if ($package->is_default)<span class="badge bg-brand-50 text-brand-700">Default plan</span>@endif
                                        @if ($package->is_recommended)<span class="badge bg-gold-100 text-gold-700">Recommended</span>@endif
                                        @if ($package->is_demo)<span class="badge bg-amber-50 text-amber-700">Draft / demo</span>@endif
                                        @if ($package->trial_days)<span class="badge bg-sky-50 text-sky-700">{{ $package->trial_days }}-day trial</span>@endif
                                    </p>
                                </td>
                                <td class="text-right whitespace-nowrap tabular-nums">{{ $package->isFree() ? 'Free' : $package->currency.' '.number_format((float) $package->price).' / '.$package->intervalLabel() }}</td>
                                @foreach (['listing_limit', 'lead_limit', 'featured_limit'] as $column)
                                    <td class="text-right tabular-nums">{{ $package->{$column} ?? '∞' }}</td>
                                @endforeach
                                <td class="text-right tabular-nums">{{ $package->running_count }}</td>
                                <td><x-status-badge :tone="$package->is_active ? 'success' : 'muted'" :label="$package->is_active ? 'Available' : 'Hidden'" /></td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.packages.toggle', $package) }}" class="inline" @if ($package->is_active) data-confirm="Hide {{ $package->name }} from dealers? Current subscribers keep it until it ends." @endif>
                                        @csrf @method('PATCH')
                                        <button class="btn btn-ghost btn-sm">{{ $package->is_active ? 'Hide' : 'Make available' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-4 text-xs text-muted">Packages are never deleted, so past subscriptions and invoices always keep their package. ∞ = no limit.</p>
    @endif
</x-layouts.dashboard>
