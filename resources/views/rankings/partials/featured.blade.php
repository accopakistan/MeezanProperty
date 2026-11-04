{{-- Featured investment cards (scroll sideways). $featured (RankingBrowser items), $title, $url: fn (item) => string. --}}
<section class="card overflow-hidden" aria-labelledby="featured-title">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="featured-title" class="flex items-center gap-2 text-base font-bold"><x-icon name="star" class="size-5 text-gold-500" /> {{ $title }}</h2>
    </div>
    @if ($featured->isEmpty())
        <p class="p-4 text-[13px] text-muted">Nothing to feature yet: entries are featured only when they have a verified score.</p>
    @else
        <ul class="flex snap-x gap-3 overflow-x-auto p-3 scrollbar-none">
            @foreach ($featured as $i)
                <li class="w-56 shrink-0 snap-start overflow-hidden rounded-lg ring-1 ring-line">
                    <a href="{{ $url($i) }}" class="block" data-select-item="{{ $i['key'] }}">
                        <span class="relative block aspect-[16/10] bg-gradient-to-br from-brand-600 to-brand-900">
                            @if ($i['image'])<img src="{{ $i['image'] }}" alt="" class="size-full object-cover" loading="lazy">@endif
                            <span class="absolute top-2 left-2">@include('rankings.partials.badges', ['badge' => 'status', 'item' => $i])</span>
                        </span>
                        <span class="block p-3 text-[13px]">
                            <span class="block font-semibold text-ink">{{ $i['name'] }}@if ($i['group_label'])<span class="font-normal text-muted"> · {{ $i['group_label'] }}</span>@endif</span>
                            <span class="flex items-center gap-1 text-[12px] text-muted"><x-icon name="map-pin" class="size-3.5" />{{ $i['location'] }}</span>
                            <span class="mt-1.5 block">{{ $i['price'] ? $i['price']['label'].' ('.strtolower($i['price']['basis_label']).')' : 'No verified price' }}</span>
                            <span class="block">MPIS: <strong>{{ $i['score'] ?? 'not scored' }}</strong>@if ($i['yield'] !== null) · Yield {{ $i['yield'] }}%@endif</span>
                            @if ($i['stage_label'])<span class="mt-1.5 inline-block rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ $i['stage_label'] }}</span>@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
