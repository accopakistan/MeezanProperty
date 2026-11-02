@php $editing = $package->exists; @endphp
<x-layouts.dashboard :title="$editing ? 'Edit '.$package->name : 'New package'" section="admin" :heading="$editing ? 'Edit '.$package->name : 'New package'" subheading="Leave a limit empty for no limit. Changes apply to new subscriptions only.">
    <x-breadcrumbs :items="[['Packages', route('admin.packages.index')], [$editing ? $package->name : 'New package', null]]" class="mb-6" />

    <form method="POST" action="{{ $editing ? route('admin.packages.update', $package) : route('admin.packages.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
            <section class="card card-body">
                <h2 class="font-bold">Details</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Name" :value="$package->name" required maxlength="80" />
                    <x-field name="slug" label="URL name" :value="$package->slug" maxlength="80" hint="Letters, numbers and dashes. Generated from the name if empty." />
                    <x-field name="description" type="textarea" label="Short description" :value="$package->description" rows="2" maxlength="500" class="sm:col-span-2" />
                </div>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Price</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <x-field name="price" type="number" label="Price" :value="$package->price" step="0.01" min="0" required hint="0 for a free package." />
                    <x-field name="currency" label="Currency" :value="$package->currency" required maxlength="3" />
                    <x-field name="billing_interval" type="select" label="Billing" :value="$package->billing_interval" :options="\App\Models\Package::INTERVALS" required />
                    <x-field name="trial_days" type="number" label="Free trial days" :value="$package->trial_days" min="0" max="365" hint="First-time subscribers only." />
                </div>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Limits</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <x-field name="listing_limit" type="number" label="Active listings" :value="$package->listing_limit" min="0" hint="Live + in review." />
                    <x-field name="lead_limit" type="number" label="Buyer leads per month" :value="$package->lead_limit" min="0" />
                    <x-field name="featured_limit" type="number" label="Featured listings" :value="$package->featured_limit" min="0" hint="Needs “Featured listings” below." />
                    <x-field name="team_member_limit" type="number" label="Team members" :value="$package->team_member_limit" min="0" hint="Stored for later; not used yet." />
                    <x-field name="ai_request_limit" type="number" label="AI requests per month" :value="$package->ai_request_limit" min="0" hint="Stored for later; not used yet." />
                </div>
            </section>

            <section class="card card-body">
                <h2 class="font-bold">Features</h2>
                @php $chosen = old('features', $package->features ?? []); @endphp
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach (config('meezan.billing.features') as $key => $label)
                        <label class="flex items-center gap-3 rounded-lg border border-line p-3 text-sm">
                            <input type="checkbox" name="features[]" value="{{ $key }}" class="accent-brand-600" @checked(in_array($key, $chosen, true))>
                            <span>{{ $label }}@if (! in_array($key, ['priority_leads', 'featured_listings'], true))<span class="block text-xs text-muted">Shown on the package; not enforced yet</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card card-body space-y-3 text-sm">
                <h2 class="font-bold">Visibility</h2>
                @foreach ([
                    'is_active' => ['Available to dealers', 'Shown on the packages page and can be chosen.'],
                    'is_default' => ['Default plan', 'Applies to every dealer without a subscription. Must be free.'],
                    'is_recommended' => ['Recommended', 'Highlighted on the packages page (one at a time).'],
                    'is_demo' => ['Draft / demo', 'Shown with a “Draft” label: terms are not final.'],
                ] as $flag => [$label, $hint])
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="{{ $flag }}" value="1" class="mt-1 accent-brand-600" @checked(old($flag, $package->{$flag}))>
                        <span><span class="font-semibold">{{ $label }}</span><span class="block text-xs text-muted">{{ $hint }}</span></span>
                    </label>
                @endforeach
                @error('is_default')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <x-field name="sort_order" type="number" label="Display order" :value="$package->sort_order" min="0" max="1000" />
            </section>
            <button class="btn btn-primary w-full">{{ $editing ? 'Save package' : 'Create package' }}</button>
            @if ($editing)
                <p class="text-xs text-muted">Dealers already on {{ $package->name }} keep the price and limits they subscribed with until they change package.</p>
            @endif
        </aside>
    </form>
</x-layouts.dashboard>
