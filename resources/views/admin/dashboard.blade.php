@php
    // Layout: public/Refrence/Meezan Property Admin Dashboard.png
    $donutTotal = max(1, collect($byType)->sum('count'));
    $offset = 0;
    $stops = collect($byType)->map(function ($row) use (&$offset, $donutTotal) {
        $from = $offset;
        $offset += $row['count'] / $donutTotal * 100;

        return $row['color'].' '.$from.'% '.$offset.'%';
    })->implode(', ');
    $areaMax = max(1, $byArea->max('total') ?? 1);
    $statusTone = ['published' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'draft' => 'neutral', 'archived' => 'muted', 'expired' => 'muted'];
@endphp
<x-layouts.dashboard title="Admin overview" section="admin">
    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
        @foreach ($stats as [$label, $value, $icon, $tone, $hint, $url])
            <a href="{{ $url }}" class="card flex items-start gap-4 p-4 transition hover:shadow-lift">
                <span class="grid size-14 shrink-0 place-items-center rounded-xl {{ $tone }} text-white shadow-sm"><x-icon :name="$icon" class="size-7" /></span>
                <span class="min-w-0">
                    <span class="block text-[13px] text-muted">{{ $label }}</span>
                    <span class="block text-[26px] leading-tight font-bold text-ink tabular-nums">{{ is_numeric($value) ? number_format($value) : $value }}</span>
                    <span class="mt-0.5 block text-xs text-muted">{{ $hint }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_minmax(0,0.75fr)]">
        <section class="card p-5" aria-labelledby="growth-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="growth-title" class="text-lg font-bold text-brand-700">Platform Growth</h2>
                <span class="rounded-md border border-line px-3 py-1.5 text-xs text-muted">Last 12 months</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-4 text-[13px]">
                @foreach ($growth['series'] as $name => $s)
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full" style="background: {{ $s['color'] }}"></span> {{ $name }}</span>
                @endforeach
            </div>
            <svg viewBox="-34 -10 610 220" class="mt-3 w-full" role="img" aria-label="Monthly new properties, users and inquiries over the last 12 months">
                @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                    <line x1="0" x2="560" y1="{{ 180 - $f * 160 }}" y2="{{ 180 - $f * 160 }}" stroke="#e8e3ef" />
                    <text x="-8" y="{{ 184 - $f * 160 }}" text-anchor="end" font-size="10" fill="#6e6878">{{ (int) round($growth['max'] * $f) }}</text>
                @endforeach
                @foreach ($growth['series'] as $name => $s)
                    <polyline points="{{ $s['points'] }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2.5" stroke-linejoin="round" />
                    @foreach (explode(' ', $s['points']) as $i => $pt)
                        @php [$x, $y] = explode(',', $pt); @endphp
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="3.5" fill="{{ $s['color'] }}"><title>{{ $name }} · {{ $growth['labels'][$i] }}: {{ $s['values'][$i] }}</title></circle>
                    @endforeach
                @endforeach
                @foreach ($growth['labels'] as $i => $label)
                    <text x="{{ $i * 560 / 11 }}" y="202" text-anchor="middle" font-size="10" fill="#6e6878">{{ $label }}</text>
                @endforeach
            </svg>
        </section>

        <section class="card p-5" aria-labelledby="location-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="location-title" class="text-lg font-bold text-brand-700">Properties by Location</h2>
                <a href="{{ route('admin.analytics') }}" class="text-[13px] font-semibold text-brand-600">Analytics</a>
            </div>
            @if ($byArea->isEmpty())
                <p class="mt-10 text-center text-sm text-muted">No published listings yet.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($byArea as $area)
                        <li class="text-sm">
                            <div class="flex justify-between"><span>{{ $area->name }}</span><span class="font-semibold tabular-nums">{{ $area->total }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-page"><div class="h-full rounded-full bg-brand-500" style="width: {{ $area->total / $areaMax * 100 }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card p-5" aria-labelledby="types-title">
            <h2 id="types-title" class="text-lg font-bold text-brand-700">Property Types</h2>
            @if ($byType)
                <div class="relative mx-auto mt-4 size-36 rounded-full" style="background: conic-gradient({{ $stops }})" role="img" aria-label="Share of published listings by type">
                    <div class="absolute inset-6 grid place-items-center rounded-full bg-white text-center">
                        <span><span class="block text-2xl font-bold">{{ number_format($donutTotal) }}</span><span class="text-xs text-muted">Total</span></span>
                    </div>
                </div>
                <ul class="mt-4 space-y-1.5 text-[13px]">
                    @foreach ($byType as $row)
                        <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span><span class="font-semibold">{{ round($row['count'] / $donutTotal * 100) }}%</span></li>
                    @endforeach
                </ul>
            @else
                <p class="mt-10 text-center text-sm text-muted">No published listings yet.</p>
            @endif
        </section>
    </div>

    {{-- Tables --}}
    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <section class="card overflow-hidden" aria-labelledby="listings-title">
            <div class="flex items-center justify-between px-5 pt-4 pb-2"><h2 id="listings-title" class="text-lg font-bold text-brand-700">Recent Property Listings</h2><a href="{{ route('admin.properties.index', ['status' => '']) }}" class="text-[13px] font-semibold text-brand-600 underline">View All</a></div>
            <div class="overflow-x-auto">
                <table class="table table-compact text-[13px]">
                    <thead><tr><th>Property</th><th>Price</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse ($recentListings as $p)
                            <tr>
                                <td><a href="{{ route('admin.properties.show', $p) }}" class="flex items-center gap-2.5 font-medium hover:text-brand-700"><img src="{{ $p->coverThumbUrl() ?? $p->placeholderUrl() }}" alt="" class="h-8 w-11 shrink-0 rounded object-cover"><span class="max-w-28 truncate 2xl:max-w-36">{{ $p->title }}</span></a></td>
                                <td class="whitespace-nowrap">{{ \App\Support\Format::compactPrice($p->price) }}</td>
                                <td><x-status-badge :tone="$statusTone[$p->status->value] ?? 'neutral'" :label="ucfirst($p->status->value)" /></td>
                                <td class="whitespace-nowrap text-muted">{{ $p->created_at->format('j M') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-muted">No listings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden" aria-labelledby="inquiries-title">
            <div class="flex items-center justify-between px-5 pt-4 pb-2"><h2 id="inquiries-title" class="text-lg font-bold text-brand-700">Recent Inquiries</h2><a href="{{ route('admin.leads.index') }}" class="text-[13px] font-semibold text-brand-600 underline">View All</a></div>
            <div class="overflow-x-auto">
                <table class="table table-compact text-[13px]">
                    <thead><tr><th>Name</th><th>Requirement</th><th>Location</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentInquiries as $inq)
                            <tr>
                                <td class="font-medium whitespace-nowrap">{{ $inq->name }}</td>
                                <td class="max-w-28 truncate">{{ $inq->property?->title ?? 'General enquiry' }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $inq->property?->area?->name ?? '—' }}</td>
                                <td><x-status-badge :tone="$inq->statusTone()" :label="ucfirst($inq->status)" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-muted">No inquiries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card overflow-hidden" aria-labelledby="dealers-title">
            <div class="flex items-center justify-between px-5 pt-4 pb-2"><h2 id="dealers-title" class="text-lg font-bold text-brand-700">Top Performing Dealers</h2><a href="{{ route('admin.dealers.index') }}" class="text-[13px] font-semibold text-brand-600 underline">View All</a></div>
            <div class="overflow-x-auto">
                <table class="table table-compact text-[13px]">
                    <thead><tr><th>Dealer</th><th class="text-right">Listings</th><th class="text-right">Inquiries</th><th class="text-right">Handled</th></tr></thead>
                    <tbody>
                        @forelse ($topDealers as $d)
                            <tr>
                                <td><span class="flex items-center gap-2"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">{{ $d->initials() }}</span><span class="max-w-24 truncate font-medium 2xl:max-w-32">{{ $d->dealerProfile?->agency_name ?? $d->name }}</span></span></td>
                                <td class="text-right tabular-nums">{{ $d->listings_count }}</td>
                                <td class="text-right tabular-nums">{{ $d->inquiries_count }}</td>
                                <td class="text-right tabular-nums">{{ $d->inquiries_count ? round($d->handled_count / $d->inquiries_count * 100).'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-muted">No dealers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2 2xl:grid-cols-4">
        <section class="card p-5" aria-labelledby="pending-title">
            <h2 id="pending-title" class="text-lg font-bold text-brand-700">Pending Actions</h2>
            <ul class="mt-3 space-y-1">
                @foreach ($pending as [$label, $count, $icon, $tone, $url])
                    <li><a href="{{ $url }}" class="flex items-center gap-3 rounded-md px-2 py-2.5 text-sm font-medium hover:bg-page">
                        <span class="grid size-8 place-items-center rounded-md {{ $tone }}"><x-icon :name="$icon" class="size-4" /></span>
                        <span class="flex-1 text-brand-700">{{ $label }}</span>
                        <span class="rounded-md px-2.5 py-0.5 text-xs font-bold {{ $tone }}">{{ $count }}</span>
                        <x-icon name="chevron-right" class="size-4 text-muted" />
                    </a></li>
                @endforeach
            </ul>
        </section>

        <section class="card overflow-hidden" aria-labelledby="registrations-title">
            <div class="flex items-center justify-between px-5 pt-4 pb-2"><h2 id="registrations-title" class="text-lg font-bold text-brand-700">Dealer Registrations</h2><a href="{{ route('admin.dealers.index') }}" class="text-[13px] font-semibold text-brand-600 underline">View All</a></div>
            <table class="table table-compact text-[13px]">
                <thead><tr><th>Dealer</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($registrations as $r)
                        <tr>
                            <td><a href="{{ route('admin.dealers.show', $r) }}" class="block max-w-40 truncate font-medium hover:text-brand-700">{{ $r->agency_name }}</a><span class="text-xs text-muted">{{ $r->user?->name }}</span></td>
                            <td><x-status-badge :tone="$r->verification_status->tone()" :label="ucfirst($r->verification_status->value)" /></td>
                            <td class="whitespace-nowrap text-muted">{{ $r->created_at->format('j M') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-muted">No registrations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="card p-5" aria-labelledby="revenue-title">
            <h2 id="revenue-title" class="text-lg font-bold text-brand-700">Revenue Overview</h2>
            <div class="mt-6 grid place-items-center rounded-lg border border-dashed border-brand-200 px-4 py-10 text-center">
                <x-icon name="credit-card" class="size-8 text-brand-400" />
                <p class="mt-2 text-sm font-semibold">No billing data</p>
                <p class="mt-1 text-xs text-muted">Revenue appears here once a payment provider is integrated.</p>
            </div>
        </section>

        <section class="card p-5" aria-labelledby="system-title">
            <h2 id="system-title" class="text-lg font-bold text-brand-700">System Status</h2>
            <ul class="mt-3 space-y-2.5 text-sm">
                @foreach ($system as [$label, $ok, $state])
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $label }}</span>
                        <span class="flex items-center gap-1.5 text-xs font-medium {{ $ok ? 'text-emerald-700' : 'text-amber-700' }}"><span class="size-2 rounded-full {{ $ok ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>{{ $state }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.settings') }}" class="mt-4 inline-block text-[13px] font-semibold text-brand-600">Integration settings →</a>
        </section>
    </div>
</x-layouts.dashboard>
