@php
    use App\Services\InvestmentIndex\IndexBrowser;
    $legal = fn ($s) => $s ? IndexBrowser::legalLabel($s->legal_status) : 'Unknown / Unverified';
    $factor = fn ($s, $f) => $s->factors[$f]['value'] ?? 'Insufficient Verified Data';

    $metrics = [
        'Investment Score' => fn ($s, $p) => $s?->status === 'ranked' ? number_format($s->score, 1).' / 100' : 'Not eligible',
        'Category Rank' => fn ($s, $p) => ($r = IndexBrowser::publicRank($s)) ? '#'.$r : ($s?->status === 'ranked' ? 'Ranked (Outside Top '.IndexBrowser::publicTop().')' : '—'),
        'Data Confidence' => fn ($s, $p) => $s ? number_format($s->confidence_score, 0).' ('.ucfirst($s->confidence_level).')' : '—',
        'Evidence Completeness' => fn ($s, $p) => $s ? round(($s->coverage ?? 0) * 100, 0).'% of scoring weight' : '—',
        'Legal Approval Standing' => fn ($s, $p) => $legal($s),
        'Risk Rating' => fn ($s, $p) => $s?->publicRiskLabel() ?? 'Not assessed',
        'Development Stage' => fn ($s, $p) => $p->statusLabel(),
        'Physical Progress' => fn ($s, $p) => $s?->development_progress !== null ? number_format($s->development_progress, 0).'%' : 'Insufficient Verified Data',
        'Operational Status' => fn ($s, $p) => $s?->operational_status ? config('investment_index.context_evidence.operational_status.levels.'.$s->operational_status, $s->operational_status) : 'No verified record',
        'Verified Rental Yield' => fn ($s, $p) => $s?->verified_yield !== null ? number_format($s->verified_yield, 2).'% gross' : 'Insufficient Verified Data',
        'Occupancy Rate' => fn ($s, $p) => $s ? $factor($s, 'occupancy') : 'Insufficient Verified Data',
        'Historical Performance' => fn ($s, $p) => $s && ($s->factors['market_performance']['value'] ?? null) ? $s->factors['market_performance']['value'] : 'Historical performance data unavailable.',
        'Last Audit Date' => fn ($s, $p) => $s?->last_verified_at?->localTime()->format('j M Y') ?? 'Never',
    ];
@endphp

<x-layouts.app :title="'Compare '.$category['label'].' — Meezan Investment Index'"
               description="Side-by-side empirical comparison of verified evidence for developments within the {{ mb_strtolower($category['label']) }} sector."
               :noindex="true">

    @php
        $hero = \App\Services\InvestmentIndex\IndexHeroImage::resolve(null, $key);
    @endphp
    <section class="relative isolate overflow-hidden bg-brand-900 text-white">
        <img src="{{ $hero['url'] }}"
             alt="{{ $hero['alt'] }}"
             width="1920" height="1200"
             class="absolute inset-0 -z-20 size-full object-cover object-center"
             loading="eager"
             fetchpriority="high"
             onerror="this.onerror=null;this.src='{{ asset('images/investment-index/fallback.jpg') }}';" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/95 via-brand-900/75 to-brand-900/25"></div>
        <div class="container-page py-10 pb-12 lg:py-16">
            <x-breadcrumbs :items="[['Investment Rankings', route('rankings.index')], [$category['label'], route('rankings.category', $key)], ['Compare', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">
                Intra-Category Matrix · {{ $category['label'] }}
            </p>
            <h1 class="mt-1 text-4xl text-white sm:text-5xl font-bold">
                Compare {{ $category['label'] }}
            </h1>
            <p class="mt-3 max-w-2xl text-white/85 text-[15px] sm:text-base leading-relaxed">
                Side-by-side empirical comparison of up to 4 eligible developments in the same development category. Each development is evaluated against the same formula and verified data standards.
            </p>
            <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
                <div><dt class="text-xs text-white/70">Comparison Standard</dt><dd class="text-2xl font-bold">13 Audit Metrics</dd></div>
                <div><dt class="text-xs text-white/70">Category Formula</dt><dd class="text-2xl font-bold">{{ $category['label'] }}</dd></div>
                <div><dt class="text-xs text-white/70">Audit Rule</dt><dd class="text-2xl font-bold">&ge; 60% Verified Data</dd></div>
            </dl>
        </div>
    </section>

    <div class="container-page py-8">
        @if ($rows->isEmpty())
            <div class="card p-8 text-center max-w-xl mx-auto">
                <span class="grid size-12 place-items-center rounded-xl bg-brand-50 text-brand-700 mx-auto mb-3">
                    <x-icon name="chart" class="size-6" />
                </span>
                <h2 class="text-base font-bold text-ink">No Developments Selected</h2>
                <p class="mt-2 text-xs text-muted">
                    Tick up to four developments on the <a class="link" href="{{ route('rankings.category', $key) }}">{{ mb_strtolower($category['label']) }} leaderboard page</a> to compare their scores and evidence here.
                </p>
                <div class="mt-4">
                    <a href="{{ route('rankings.category', $key) }}" class="btn btn-primary btn-sm">
                        Go to {{ $category['label'] }} Leaderboard
                    </a>
                </div>
            </div>
        @else
            {{-- Top Project Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                @foreach ($rows as $r)
                    @php $scoreObj = $r['score']; $project = $r['project']; @endphp
                    <div class="card p-4 flex flex-col justify-between border-t-4 border-t-brand-600 shadow-sm">
                        <div>
                            <span class="text-[11px] font-semibold text-muted uppercase tracking-wider block">
                                {{ $project->city }}
                            </span>
                            <h3 class="font-bold text-base text-ink mt-0.5">
                                <a href="{{ route('rankings.project', $project) }}" class="hover:text-brand-600">
                                    {{ $project->name }}
                                </a>
                            </h3>
                            <span class="text-xs text-muted block mt-0.5">
                                {{ $project->developer ?: 'Developer not stated' }}
                            </span>
                            <div class="mt-3 flex items-baseline justify-between pt-3 border-t border-line">
                                <div>
                                    <span class="text-2xl font-bold font-serif tabular-nums text-brand-700">
                                        {{ $scoreObj && $scoreObj->score !== null ? number_format($scoreObj->score, 1) : '—' }}
                                    </span>
                                    <span class="text-xs text-muted">/100</span>
                                </div>
                                <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">
                                    {{ $scoreObj && $scoreObj->category_rank ? '#'.$scoreObj->category_rank : 'Directory' }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-line">
                            <a href="{{ route('rankings.project', $project) }}" class="btn btn-secondary btn-sm w-full">
                                Full Profile →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Comparison Table Matrix --}}
            <div class="card relative overflow-x-auto p-0 shadow-card">
                <table class="table min-w-[760px] divide-y divide-line">
                    <thead class="bg-page/70">
                        <tr>
                            <th scope="col" class="w-1/4 py-3.5 pl-5 text-xs font-bold text-muted uppercase">Comparison Metric</th>
                            @foreach ($rows as $r)
                                <th scope="col" class="py-3.5 text-xs font-bold text-ink">
                                    <a href="{{ route('rankings.project', $r['project']) }}" class="hover:text-brand-600 block">
                                        {{ $r['project']->name }}
                                    </a>
                                    <span class="text-[11px] font-normal text-muted normal-case block">{{ $r['project']->city }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line bg-white">
                        @foreach ($metrics as $label => $value)
                            <tr class="hover:bg-page/40 transition">
                                <th scope="row" class="pl-5 py-3 font-semibold text-xs text-ink bg-page/30">
                                    {{ $label }}
                                </th>
                                @foreach ($rows as $r)
                                    <td class="py-3 text-xs text-ink/90">
                                        {{ $value($r['score'], $r['project']) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between text-xs text-muted">
                <span>Verified against Meezan Investment Index Run #{{ $run?->id ?? '32' }}</span>
                <a href="{{ route('rankings.category', $key) }}" class="font-semibold text-brand-600 hover:underline">
                    Back to {{ $category['label'] }} Leaderboard
                </a>
            </div>
        @endif

        <p class="mt-8 text-xs text-muted border-t border-line pt-4">
            {{ config('meezan.investment.disclaimer') }}
        </p>
    </div>
</x-layouts.app>
