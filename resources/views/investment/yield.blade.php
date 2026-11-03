@php use App\Support\Format; @endphp
<x-layouts.app title="Rental yield calculator" description="Estimate the gross rental yield of a property from its price and monthly rent, and net yield when you know the costs.">
    <section class="bg-gradient-to-r from-brand-900 to-brand-700">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Investment insights', route('investment.index')], ['Rental yield calculator', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-3xl text-white sm:text-4xl">Rental yield calculator</h1>
            <p class="mt-2 text-white/85">Gross yield = monthly rent × 12 ÷ property value × 100. Add yearly costs to see a net figure.</p>
        </div>
    </section>
    <div class="container-page grid gap-6 py-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <form method="GET" class="card card-body space-y-4" aria-label="Yield inputs">
            <x-field name="price" type="number" label="Property value (PKR)" required :value="$input['price'] ?? null" min="1" />
            <x-field name="rent" type="number" label="Monthly rent (PKR)" required :value="$input['rent'] ?? null" min="1" />
            <x-field name="costs" type="number" label="Yearly costs (PKR, optional)" :value="$input['costs'] ?? null" min="0" hint="Maintenance, management, vacancy allowance, property tax." />
            <button class="btn btn-primary">Calculate</button>
        </form>
        <section class="card card-body" aria-live="polite">
            <h2 class="text-lg font-bold">Result</h2>
            @if ($result)
                <dl class="mt-3 space-y-3">
                    <div><dt class="text-sm text-muted">Estimated gross yield</dt><dd class="font-serif text-4xl font-bold text-brand-700">{{ $result['gross'] }}%</dd>
                        <dd class="text-[13px] text-muted">{{ Format::price($result['annual_rent']) }} a year ÷ {{ Format::price($input['price']) }}</dd></div>
                    @if ($result['net'] !== null)
                        <div><dt class="text-sm text-muted">Estimated net yield</dt><dd class="text-2xl font-bold">{{ $result['net'] }}%</dd>
                            <dd class="text-[13px] text-muted">After {{ Format::price($input['costs']) }} of yearly costs</dd></div>
                    @endif
                </dl>
                <p class="mt-4 text-xs text-muted">Estimates from the figures you entered. Gross yield is not a net return; neither includes financing, capital gains or losses, or taxes on income.</p>
            @else
                <p class="mt-2 text-sm text-muted">Enter a property value and monthly rent.</p>
            @endif
        </section>
    </div>
    <div class="container-page pb-4">@include('investment.partials.disclaimer')</div>
</x-layouts.app>
