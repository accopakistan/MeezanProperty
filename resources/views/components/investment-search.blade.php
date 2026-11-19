@props(['city' => null, 'category' => null, 'filters' => [], 'active' => 'invest'])
{{--
    Investment Rankings hero search (owner's reference design, 19 Nov 2026): five tabs,
    Buy | Rent | Invest | Compare | AI Search, each its own form.
      Buy / Rent  → the marketplace listing search (/search, property mode)
      Invest      → a Meezan Investment Index leaderboard, with the filters it supports
                    (project search, city, category, completion status, risk, legal status)
      Compare     → a category's side-by-side comparison
      AI Search   → the AI Investment Advisor on this page (?ask=…), answered from published rankings
    Only real filters are offered: there is no budget or return filter because the Index has
    no sourced prices or yields for most projects. Without JavaScript the Invest form shows.
--}}
@php
    use App\Enums\PropertyType;
    use App\Models\Project;
    use App\Services\InvestmentIndex\IndexBrowser;
    use App\Support\Cities;
    use App\Support\Lookup;

    $id = 'inv-search-'.substr(md5(($city ?? '').($category ?? '')), 0, 6);
    $tabs = ['buy' => 'Buy', 'rent' => 'Rent', 'invest' => 'Invest', 'compare' => 'Compare', 'ai' => 'AI Search'];
    $categories = IndexBrowser::categories();
    $citySlug = $city ? Cities::slug($city) : 'pakistan';
    $placeholder = $category ? 'Search '.mb_strtolower($categories[$category]['label'] ?? 'projects').'…' : 'Search society, project or area…';

    $input = 'h-11 w-full rounded-[var(--radius-control)] border border-line bg-white text-[13.5px] text-ink focus:border-brand-400 focus:ring-3 focus:ring-brand-100 focus:outline-none';
    // A boxed select with its label above the value (reference: "Property Type / Shopping Mall").
    $box = 'relative block rounded-[var(--radius-control)] border border-line bg-white px-2.5 pt-1.5 pb-1 focus-within:border-brand-400 focus-within:ring-3 focus-within:ring-brand-100';
    $boxLabel = 'block text-[10.5px] leading-tight text-muted';
    $boxSelect = 'block w-full truncate border-0 bg-transparent p-0 pr-5 text-[13px] font-semibold text-ink focus:ring-0';
    $go = 'grid size-11 shrink-0 place-items-center rounded-[var(--radius-control)] bg-brand-600 text-white transition hover:bg-brand-700';
    $cityOptions = fn () => collect(Cities::names())->map(fn ($c) => '<option value="'.e(Cities::slug($c)).'"'.($c === $city ? ' selected' : '').'>'.e($c).'</option>')->implode('');
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }} data-investment-search>
    <div class="flex w-max max-w-full overflow-x-auto rounded-t-lg bg-white scrollbar-none" role="tablist" aria-label="Search for">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="{{ $id }}-tab-{{ $key }}" aria-controls="{{ $id }}-{{ $key }}" aria-selected="{{ $key === $active ? 'true' : 'false' }}" data-inv-tab="{{ $key }}"
                    class="border-r border-line px-4 py-2.5 text-[13.5px] font-semibold whitespace-nowrap text-ink transition last:border-0 hover:bg-brand-50 hover:text-brand-700 aria-selected:bg-brand-600 aria-selected:text-white aria-selected:shadow-[inset_0_-3px_0_var(--color-gold-500)] sm:px-6">{{ $label }}</button>
        @endforeach
    </div>

    <div class="rounded-tr-lg rounded-b-lg bg-white p-3 shadow-lift">
        {{-- Buy / Rent: marketplace listings --}}
        @foreach (['buy' => 'sale', 'rent' => 'rent'] as $key => $purpose)
            <form method="GET" action="{{ route('search') }}" id="{{ $id }}-{{ $key }}" role="tabpanel" aria-labelledby="{{ $id }}-tab-{{ $key }}" data-inv-panel="{{ $key }}" @if ($key !== $active) hidden @endif>
                <input type="hidden" name="mode" value="property"><input type="hidden" name="tab" value="{{ $purpose }}">
                <div class="flex gap-2">
                    <label class="relative min-w-0 flex-1">
                        <span class="sr-only">Location</span>
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink/60" />
                        <input name="location" maxlength="100" class="{{ $input }} pl-9 pr-3" placeholder="{{ $city ? 'Area, society or phase in '.$city : 'Search location, society or property…' }}">
                    </label>
                    <label class="w-28 shrink-0 sm:w-36"><span class="sr-only">City</span>
                        <select name="city" class="input h-11 text-[13.5px]">
                            <option value="">All cities</option>
                            @foreach (Cities::names() as $c)<option value="{{ $c }}" @selected($c === $city)>{{ $c }}</option>@endforeach
                        </select>
                    </label>
                    <button class="{{ $go }}" aria-label="Search {{ $key === 'buy' ? 'properties for sale' : 'properties for rent' }}"><x-icon name="search" class="size-5" /></button>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <label class="{{ $box }}"><span class="{{ $boxLabel }}">Property Type</span>
                        <select name="type" class="{{ $boxSelect }}">
                            <option value="">All Types</option>
                            @foreach ($key === 'buy' ? ['Homes' => PropertyType::inGroup('homes'), 'Plots' => PropertyType::inGroup('plots'), 'Commercial' => PropertyType::inGroup('commercial')] : ['Homes' => PropertyType::inGroup('homes'), 'Commercial' => PropertyType::inGroup('commercial')] as $group => $types)
                                <optgroup label="{{ $group }}">@foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                    </label>
                    <label class="{{ $box }}"><span class="{{ $boxLabel }}">{{ $key === 'buy' ? 'Budget Range' : 'Monthly Rent' }}</span>
                        <select name="max_price" class="{{ $boxSelect }}">
                            <option value="">{{ $key === 'buy' ? 'Any Budget' : 'Any Rent' }}</option>
                            @foreach (Lookup::priceOptions($purpose) as $value => $label)<option value="{{ $value }}">Up to {{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="{{ $box }}"><span class="{{ $boxLabel }}">Bedrooms</span>
                        <select name="beds" class="{{ $boxSelect }}">
                            <option value="">Any</option>
                            @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}">{{ $n }}+ Beds</option>@endforeach
                        </select>
                    </label>
                </div>
            </form>
        @endforeach

        {{-- Invest: Meezan Investment Index leaderboards --}}
        <form method="GET" action="{{ route('search') }}" id="{{ $id }}-invest" role="tabpanel" aria-labelledby="{{ $id }}-tab-invest" data-inv-panel="invest" @if ($active !== 'invest') hidden @endif>
            <input type="hidden" name="mode" value="investment"><input type="hidden" name="tab" value="invest">
            <div class="flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Search projects</span>
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink/60" />
                    <input name="q" maxlength="80" value="{{ $filters['q'] ?? '' }}" class="{{ $input }} pl-9 pr-3" placeholder="{{ $placeholder }}">
                </label>
                <label class="relative w-28 shrink-0 sm:w-36"><span class="sr-only">City</span>
                    <x-icon name="map-pin" class="pointer-events-none absolute top-1/2 left-2.5 z-10 size-4 -translate-y-1/2 text-brand-600" />
                    <select name="investment_city" class="input h-11 pl-8 text-[13.5px] font-semibold">
                        <option value="pakistan">Pakistan</option>{!! $cityOptions() !!}
                    </select>
                </label>
                <button class="{{ $go }}" aria-label="Search investment rankings"><x-icon name="search" class="size-5" /></button>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <label class="{{ $box }}"><span class="{{ $boxLabel }}">Property Type</span>
                    <select name="category" class="{{ $boxSelect }}">
                        <option value="">All Types</option>
                        @foreach ($categories as $k => $c)<option value="{{ $k }}" @selected($k === $category)>{{ $c['singular'] ?? $c['label'] }}</option>@endforeach
                    </select>
                </label>
                <label class="{{ $box }}"><span class="{{ $boxLabel }}">Completion Status</span>
                    <select name="development" class="{{ $boxSelect }}">
                        <option value="">All Status</option>
                        @foreach (Project::STATUSES as $k => $l)@continue($k === 'unverified')<option value="{{ $k }}" @selected(($filters['development'] ?? null) === $k)>{{ $l }}</option>@endforeach
                    </select>
                </label>
                <label class="{{ $box }}"><span class="{{ $boxLabel }}">Investment Risk</span>
                    <select name="risk" class="{{ $boxSelect }}">
                        <option value="">Any Risk</option>
                        @foreach (array_keys(config('investment_index.parameters.risk_classes')) as $k)<option value="{{ $k }}" @selected(($filters['risk'] ?? null) === $k)>{{ ucfirst($k) }} risk</option>@endforeach
                    </select>
                </label>
                <label class="{{ $box }}"><span class="{{ $boxLabel }}">Legal Status</span>
                    <select name="legal" class="{{ $boxSelect }}">
                        <option value="">Any Status</option>
                        @foreach (IndexBrowser::legalFilters() as $k => $l)<option value="{{ $k }}" @selected(($filters['legal'] ?? null) === $k)>{{ $l }}</option>@endforeach
                    </select>
                </label>
            </div>
        </form>

        {{-- Compare: side by side within one category --}}
        <form method="GET" action="{{ route('search') }}" id="{{ $id }}-compare" role="tabpanel" aria-labelledby="{{ $id }}-tab-compare" data-inv-panel="compare" @if ($active !== 'compare') hidden @endif>
            <input type="hidden" name="mode" value="investment"><input type="hidden" name="tab" value="compare">
            <p class="text-[13px] text-muted">Compare up to four ranked developments of one type side by side: score, legal status, risk and evidence.</p>
            <div class="mt-2 flex gap-2">
                <label class="{{ $box }} min-w-0 flex-1"><span class="{{ $boxLabel }}">What to compare</span>
                    <select name="category" class="{{ $boxSelect }}">
                        @foreach ($categories as $k => $c)<option value="{{ $k }}" @selected($k === ($category ?? 'housing-societies'))>{{ $c['label'] }}</option>@endforeach
                    </select>
                </label>
                <button class="btn btn-primary h-11 shrink-0 self-end px-5">Compare <x-icon name="arrow-right" class="size-4" /></button>
            </div>
        </form>

        {{-- AI Search: the advisor on this page --}}
        <form method="GET" action="{{ url()->current() }}#advisor-title" id="{{ $id }}-ai" role="tabpanel" aria-labelledby="{{ $id }}-tab-ai" data-inv-panel="ai" data-inv-ai @if ($active !== 'ai') hidden @endif>
            <div class="flex gap-2">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">Ask the AI Investment Advisor</span>
                    <x-icon name="bot" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-brand-600" />
                    <input name="ask" required minlength="3" maxlength="300" class="{{ $input }} pl-9 pr-3" placeholder="{{ 'e.g. Which '.($category ? mb_strtolower($categories[$category]['singular'] ?? 'project') : 'project').' is best for investment in '.($city ?? 'Lahore').'?' }}">
                </label>
                <button class="{{ $go }}" aria-label="Ask the advisor"><x-icon name="send" class="size-5" /></button>
            </div>
            <p class="mt-2 text-[12px] text-muted">Answered by the AI Investment Advisor from published Meezan Investment Index data only. It says when data is missing.</p>
        </form>
    </div>
</div>

@pushOnce('scripts')
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.querySelectorAll('[data-investment-search]').forEach(function (box) {
    var tabs = box.querySelectorAll('[data-inv-tab]');
    var select = function (tab) {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
        box.querySelectorAll('[data-inv-panel]').forEach(function (p) { p.hidden = p.dataset.invPanel !== tab.dataset.invTab; });
    };
    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { select(tab); });
        tab.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            select(next); next.focus();
        });
    });
    // AI Search: hand the question to the advisor on this page when it is there (answers in place).
    var ai = box.querySelector('[data-inv-ai]');
    ai && ai.addEventListener('submit', function (e) {
        var form = document.querySelector('[data-advisor-form]'), field = form && form.querySelector('[name="ask"]');
        if (!field) return;
        e.preventDefault();
        field.value = ai.querySelector('[name="ask"]').value;
        form.closest('[data-advisor]').scrollIntoView({ behavior: 'smooth', block: 'start' });
        form.requestSubmit();
    });
});
</script>
@endPushOnce
