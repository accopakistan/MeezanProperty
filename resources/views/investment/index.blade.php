@php
    use App\Services\Investment\InvestmentInsights;
    $f = $filters;
    $changeKey = $f['period'] === '2y' ? 'change_2y' : 'change_1y';
    $title = ($categories[$f['category']]['label'] ?? 'Investment rankings').(isset($f['city']) ? ' in '.$f['city'] : ' in Pakistan');
@endphp
<x-layouts.app :title="$title" description="Transparent, evidence-based property investment rankings for Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, with data confidence, sources and methodology."
               :robots="$calc && ! $calc->includes_demo && $rankings->total() ? null : 'noindex, follow'">
    <section class="bg-gradient-to-r from-brand-900 to-brand-700">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Investment insights', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <p class="mt-5 text-sm font-semibold tracking-wide text-gold-300 uppercase">Investment intelligence</p>
            <h1 class="mt-1 text-3xl text-white sm:text-4xl">{{ $title }}</h1>
            <p class="mt-3 max-w-3xl text-white/85">Rankings built only from reviewed and verified market records, with every score shown next to its data confidence, sources and methodology. Where the data is not enough, we say so instead of ranking.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('investment.methodology') }}" class="btn btn-on-dark btn-sm">How rankings work</a>
                <a href="{{ route('investment.yield') }}" class="btn btn-on-dark btn-sm">Rental yield calculator</a>
                <a href="{{ route('investment.compare', ['category' => $f['category'], 'asset' => $f['asset']]) }}" class="btn btn-on-dark btn-sm">Compare</a>
            </div>
        </div>
    </section>

    <div class="container-page py-8">
        @include('investment.partials.demo-notice')
        {{-- Investment goal --}}
        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Investment goal">
            @foreach ($categories as $key => $c)
                <a href="{{ route('investment.index', array_merge(request()->except(['category', 'page']), ['category' => $key])) }}"
                   @class(['shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $f['category'] === $key, 'bg-white text-ink ring-line hover:ring-brand-300' => $f['category'] !== $key])
                   @if ($f['category'] === $key) aria-current="page" @endif>{{ $c['label'] }}</a>
            @endforeach
        </nav>

        <div class="mt-6 grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <form method="GET" class="card card-body h-max space-y-4" aria-label="Ranking filters">
                <input type="hidden" name="category" value="{{ $f['category'] }}">
                <x-field name="city" type="select" label="City" placeholder="All seven cities" :value="$f['city'] ?? null" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
                @unless (isset($categories[$f['category']]['asset_class']))
                    <x-field name="asset" type="select" label="Asset category" :value="$f['asset']" :options="['residential' => 'Residential', 'commercial' => 'Commercial']" />
                @endunless
                <x-field name="level" type="select" label="Ranking level" placeholder="Cities, areas and projects" :value="$f['level'] ?? null" :options="\App\Models\InvestmentRanking::ENTITY_TYPES" />
                <x-field name="property_type" type="select" label="Property type" placeholder="Any type" :value="$f['property_type'] ?? null" :options="collect(\App\Enums\PropertyType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                <x-field name="risk" type="select" label="Risk preference" placeholder="Any measured risk" :value="$f['risk'] ?? null" :options="['low' => 'Low risk only', 'medium' => 'Up to medium risk', 'high' => 'Any measured risk']" />
                <x-field name="confidence" type="select" label="Data confidence" placeholder="Any confidence" :value="$f['confidence'] ?? null" :options="['high' => 'High only', 'medium' => 'Medium or better', 'limited' => 'Limited or better']" />
                <x-field name="period" type="select" label="Ranking period" :value="$f['period']" :options="['1y' => 'One year', '2y' => 'Two years']" />
                <x-field name="budget" type="number" label="Budget up to (PKR)" :value="$f['budget'] ?? null" min="100000" step="100000" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="min_yield" type="number" label="Min. yield %" :value="$f['min_yield'] ?? null" step="0.1" />
                    <x-field name="min_growth" type="number" label="Min. growth %" :value="$f['min_growth'] ?? null" step="0.1" />
                </div>
                <button class="btn btn-primary w-full">Apply filters</button>
            </form>

            <section aria-labelledby="results-title">
                <h2 id="results-title" class="sr-only">Rankings</h2>
                @if (! $calc)
                    <x-empty-state icon="chart" title="No published rankings yet">
                        Rankings appear here once verified market data (prices, rents and indicators from recorded sources) has been reviewed and a calculation is published. We do not rank without evidence.
                    </x-empty-state>
                @else
                    <p class="text-sm text-muted">
                        {{ number_format($rankings->total()) }} ranked {{ \Illuminate\Support\Str::plural('entry', $rankings->total()) }} ·
                        published {{ $calc->published_at?->localTime()->format('j M Y') }} · methodology v{{ $calc->methodology_version }}
                        @if ($insufficient) · {{ number_format($insufficient) }} not ranked (insufficient data) @endif
                    </p>
                    @if ($rankings->isEmpty())
                        <x-empty-state class="mt-4" icon="search" title="Nothing matches these filters">Try another city or relax the risk, confidence or budget filters.</x-empty-state>
                    @else
                        <form method="GET" action="{{ route('investment.compare') }}">
                            <input type="hidden" name="category" value="{{ $f['category'] }}">
                            <input type="hidden" name="asset" value="{{ $f['asset'] }}">
                            <ol class="mt-4 space-y-3">
                                @foreach ($rankings as $i => $r)
                                    @php $change = $r->metric($changeKey); $position = ($rankings->currentPage() - 1) * $rankings->perPage() + $i + 1; @endphp
                                    <li class="card card-body grid gap-4 sm:grid-cols-[56px_minmax(0,1fr)_auto] sm:items-center">
                                        <span class="grid size-12 place-items-center rounded-xl bg-brand-600 font-serif text-xl font-bold text-white" aria-label="Rank {{ $position }}">#{{ $position }}</span>
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-bold"><a href="{{ route('investment.show', [$r->entity_type, $r->entity_id, 'category' => $r->category, 'asset' => $r->asset_class]) }}" class="hover:text-brand-700">{{ $r->entity_name }}</a></h3>
                                            <p class="text-[13px] text-muted">{{ $r->entityTypeLabel() }} · {{ $r->city }} · {{ ucfirst($r->asset_class) }}</p>
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <x-investment-badge kind="confidence" :level="$r->confidence" />
                                                <x-investment-badge kind="risk" :level="$r->risk" />
                                            </div>
                                            <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-[13px] sm:grid-cols-3">
                                                <div><dt class="text-muted">{{ $f['period'] === '2y' ? 'Two-year' : 'One-year' }} change</dt>
                                                    <dd class="font-semibold">{{ $change ? InvestmentInsights::signed($change['percent']).'%' : 'Insufficient Historical Data' }}</dd></div>
                                                <div><dt class="text-muted">Est. gross yield</dt><dd class="font-semibold">{{ $r->metric('yield.gross') !== null ? $r->metric('yield.gross').'%' : 'No data' }}</dd></div>
                                                <div><dt class="text-muted">Latest price</dt><dd class="font-semibold">{{ $r->metric('current.label') ?? 'No data' }} @if ($r->metric('current.basis') === 'asking')<span class="font-normal text-muted">(asking)</span>@endif</dd></div>
                                            </dl>
                                        </div>
                                        <div class="flex items-center gap-4 sm:flex-col sm:items-end">
                                            <p class="text-right"><span class="font-serif text-3xl font-bold text-brand-700">{{ number_format($r->score, 1) }}</span><span class="text-sm text-muted">/100</span></p>
                                            <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="items[]" value="{{ $r->entity_type }}:{{ $r->entity_id }}" class="checkbox"> Compare</label>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <button class="btn btn-outline btn-sm">Compare selected (up to 4)</button>
                                {{ $rankings->links() }}
                            </div>
                        </form>
                    @endif
                @endif
                @include('investment.partials.disclaimer')
            </section>
        </div>
    </div>
</x-layouts.app>
