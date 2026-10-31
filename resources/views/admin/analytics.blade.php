@php
    $bars = function ($data) {
        $max = max(1, $data->max() ?? 1);
        return $data->map(fn ($v) => ['value' => $v, 'pct' => $v / $max * 100]);
    };
    $signupMax = max(1, $signups->max() ?? 1, $inquiries->max() ?? 1);
@endphp
<x-layouts.dashboard title="Analytics" section="admin" heading="Analytics" subheading="Live figures from the database.">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Published listings" :value="number_format($publishedCount)" icon="layers" tone="success" />
        <x-stat-card label="Average house price (sale)" :value="$avgPrice ? \App\Support\Format::price((int) $avgPrice) : '—'" icon="tag" tone="gold" />
        <x-stat-card label="Inquiries, last 30 days" :value="$inquiries->sum()" icon="message" tone="info" />
    </div>

    <section class="card card-body mt-6" aria-labelledby="daily-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="daily-title" class="font-bold">Sign-ups and inquiries, last 30 days</h2>
            <div class="flex gap-4 text-xs text-muted"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-600"></span> Sign-ups</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-gold-500"></span> Inquiries</span></div>
        </div>
        <div class="mt-6 flex h-40 items-end gap-1" role="img" aria-label="Daily sign-ups and inquiries for the last 30 days">
            @foreach ($days as $day)
                @php $k = $day->toDateString(); @endphp
                <div class="flex h-full flex-1 items-end gap-px" title="{{ $day->format('j M') }}: {{ $signups[$k] ?? 0 }} sign-ups, {{ $inquiries[$k] ?? 0 }} inquiries">
                    <div class="flex-1 rounded-t-sm bg-brand-600" style="height: {{ max(1, ($signups[$k] ?? 0) / $signupMax * 100) }}%"></div>
                    <div class="flex-1 rounded-t-sm bg-gold-500" style="height: {{ max(1, ($inquiries[$k] ?? 0) / $signupMax * 100) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[10px] text-muted"><span>{{ $days->first()->format('j M') }}</span><span>{{ $days->last()->format('j M') }}</span></div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        @foreach ([['Published listings by type', $byType], ['Published listings by area', $byArea], ['Inquiries by area', $inquiriesByArea]] as [$title, $data])
            <section class="card card-body">
                <h2 class="font-bold">{{ $title }}</h2>
                @if ($data->isEmpty())
                    <p class="mt-6 text-center text-sm text-muted">No data yet.</p>
                @else
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($bars($data) as $label => $row)
                            <li>
                                <div class="flex justify-between"><span>{{ $label }}</span><span class="font-semibold tabular-nums">{{ $row['value'] }}</span></div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-page"><div class="h-full rounded-full bg-brand-500" style="width: {{ $row['pct'] }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>

    <section class="card mt-6" aria-labelledby="viewed-title">
        <h2 id="viewed-title" class="border-b border-line px-5 py-4 font-bold">Most viewed listings</h2>
        @forelse ($topViewed as $p)
            <a href="{{ route('admin.properties.show', $p) }}" class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-page">
                <span class="truncate"><span class="font-semibold">{{ $p->title }}</span> <span class="text-muted">· {{ $p->area?->name }}</span></span>
                <span class="shrink-0 font-semibold tabular-nums">{{ number_format($p->views) }} views</span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-muted">No views recorded yet.</p>
        @endforelse
    </section>
</x-layouts.dashboard>
