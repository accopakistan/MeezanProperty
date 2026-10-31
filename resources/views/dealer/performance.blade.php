@php
    use App\Services\DealerPerformance;
    $k = $report['kpis'];
    $hours = fn (?float $h) => $h === null ? '—' : ($h < 1 ? round($h * 60).' min' : ($h < 48 ? $h.' h' : round($h / 24, 1).' days'));
    $tracking = $report['trackingSince'];
    $partial = ! $tracking || $tracking->gt($report['since']);
@endphp
<x-layouts.dashboard title="Performance" section="dealer" heading="Listing & Lead Performance" subheading="How buyers respond to your listings, from your own Meezan Property records.">
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-md border border-line bg-white p-1">
                @foreach (DealerPerformance::PERIODS as $value => $label)
                    <a href="{{ route('dealer.performance', ['days' => $value]) }}" @class(['rounded px-3 py-1.5 text-sm font-medium', 'bg-brand-600 text-white' => $report['days'] === $value, 'text-muted hover:text-ink' => $report['days'] !== $value])>{{ $label }}</a>
                @endforeach
            </div>
            <a href="{{ route('dealer.performance.export', ['days' => $report['days']]) }}" class="btn btn-secondary btn-sm"><x-icon name="file-text" class="size-4" /> Export CSV</a>
        </div>
    </x-slot:actions>

    @if ($partial)
        <p class="mb-5 rounded-lg border border-line bg-white px-4 py-3 text-[13px] text-muted">
            <x-icon name="info" class="mr-1 inline size-4 text-brand-500" />
            Daily view counts are recorded {{ $tracking ? 'from '.$tracking->format('j M Y') : 'from today' }}, so earlier days in this period show no views. All-time view totals are shown per listing. Response times are measured for inquiries you update from now on.
        </p>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <x-stat-card label="Listing views" :value="number_format($k['views'])" icon="eye" :hint="$k['live'].' live listings'" />
        <x-stat-card label="Saves by buyers" :value="number_format($k['saves'])" icon="heart" tone="danger" />
        <x-stat-card label="Inquiries" :value="number_format($k['inquiries'])" icon="message" tone="gold" :href="route('dealer.leads')" />
        <x-stat-card label="Inquiries per 100 views" :value="$k['conversion'] ?? '—'" icon="target" tone="info" />
        <x-stat-card label="Inquiries answered" :value="$k['responseRate'] === null ? '—' : $k['responseRate'].'%'" icon="check" tone="success" hint="Marked contacted or closed" />
        <x-stat-card label="Median response time" :value="$hours($k['medianResponseHours'])" icon="clock" tone="warning" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section class="card card-body" aria-labelledby="trend-title">
            <h2 id="trend-title" class="font-bold">Views and inquiries, {{ strtolower(DealerPerformance::PERIODS[$report['days']]) }}</h2>
            <x-line-chart class="mt-3" :labels="$report['chart']['labels']" :series="$report['chart']['series']" label="Daily listing views and inquiries" />
        </section>

        <section class="card card-body" aria-labelledby="leads-title">
            <h2 id="leads-title" class="font-bold">Requirement leads</h2>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-page p-3"><dd class="text-xl font-bold">{{ $report['leads']['received'] }}</dd><dt class="text-xs text-muted">Received</dt></div>
                <div class="rounded-lg bg-page p-3"><dd class="text-xl font-bold">{{ $report['leads']['responded'] }}</dd><dt class="text-xs text-muted">Responded</dt></div>
                <div class="rounded-lg bg-page p-3"><dd class="text-xl font-bold">{{ $hours($report['leads']['medianResponseHours']) }}</dd><dt class="text-xs text-muted">Median response</dt></div>
            </dl>
            @if ($report['leads']['byStatus']->isNotEmpty())
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach ($report['leads']['byStatus'] as $status => $count)
                        <li class="flex justify-between"><span>{{ $status }}</span><span class="font-semibold">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-muted">{{ auth()->user()->isVerifiedDealer() ? 'No requirement leads in this period.' : 'Requirement leads start once your agency is verified.' }}</p>
            @endif
        </section>
    </div>

    @if ($report['actions']->isNotEmpty())
        <section class="card mt-6" aria-labelledby="improve-title">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 id="improve-title" class="font-bold">Improve your listings</h2>
                <span class="text-xs text-muted">{{ $report['actions']->count() }} {{ \Illuminate\Support\Str::plural('listing', $report['actions']->count()) }} with suggestions</span>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($report['actions']->take(8) as $row)
                    <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $row['property']->reference }} – {{ $row['property']->title }}</p>
                            <ul class="mt-1 flex flex-wrap gap-1.5">
                                @foreach ($row['issues'] as $issue)<li class="badge bg-amber-50 text-amber-800 ring-1 ring-amber-200">{{ $issue }}</li>@endforeach
                            </ul>
                        </div>
                        <div class="flex gap-2">
                            @if ($row['property']->canBeRenewed())
                                <form method="POST" action="{{ route('dealer.properties.renew', $row['property']) }}" data-confirm="Renew {{ $row['property']->reference }}? Only renew if it is still available at {{ $row['property']->priceLabel() }}.">@csrf
                                    <input type="hidden" name="still_available" value="1">
                                    <button class="btn btn-primary btn-sm">Renew</button>
                                </form>
                            @endif
                            @unless ($row['property']->status === \App\Enums\PropertyStatus::Archived)
                                <a href="{{ route('dealer.properties.edit', $row['property']) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card mt-6 overflow-hidden" aria-labelledby="listing-table-title">
        <h2 id="listing-table-title" class="border-b border-line px-5 py-4 font-bold">By listing</h2>
        @if ($report['rows']->isEmpty())
            <x-empty-state compact class="m-5" icon="layers" title="No listings yet">
                <x-slot:actions><a href="{{ route('dealer.properties.create') }}" class="btn btn-primary btn-sm">Add a listing</a></x-slot:actions>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table table-compact text-[13px]">
                    <thead>
                        <tr><th>Listing</th><th>Status</th><th class="text-right">Views</th><th class="text-right">All-time views</th><th class="text-right">Saves</th><th class="text-right">Inquiries</th><th class="text-right">Per 100 views</th><th class="text-right">Days live</th><th class="text-right">Price / marla vs area</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            @php $p = $row['property']; @endphp
                            <tr>
                                <td class="min-w-64">
                                    <a href="{{ route('properties.show', $p) }}" class="flex items-center gap-2.5 font-medium hover:text-brand-700">
                                        <img src="{{ $p->coverThumbUrl() ?? $p->placeholderUrl() }}" alt="" class="h-9 w-12 shrink-0 rounded object-cover">
                                        <span class="min-w-0"><span class="block max-w-64 truncate">{{ $p->title }}</span><span class="text-xs text-muted">{{ $p->reference }} · {{ $p->priceLabel() }}</span></span>
                                    </a>
                                </td>
                                <td><x-status-badge :tone="$p->status->tone()" :label="$p->status->label()" /></td>
                                <td class="text-right tabular-nums">{{ number_format($row['views']) }}</td>
                                <td class="text-right text-muted tabular-nums">{{ number_format($row['viewsAllTime']) }}</td>
                                <td class="text-right tabular-nums">{{ $row['saves'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['inquiries'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['conversion'] ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ $row['daysLive'] ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($row['vsArea'] === null)
                                        <span class="text-muted" title="Shown when the area has at least {{ \App\Services\AreaInsights::MIN_SAMPLE }} comparable sale listings">—</span>
                                    @else
                                        <span @class(['font-semibold', 'text-red-600' => $row['vsArea'] > 10, 'text-emerald-700' => $row['vsArea'] < -10])>{{ $row['vsArea'] > 0 ? '+' : '' }}{{ $row['vsArea'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-5 py-3 text-xs text-muted">“Price / marla vs area” compares your asking price per marla with the median asking price of the same property type for sale in the same area. It appears only when at least {{ \App\Services\AreaInsights::MIN_SAMPLE }} comparable listings exist, and compares asking prices, not sale prices.</p>
        @endif
    </section>
</x-layouts.dashboard>
