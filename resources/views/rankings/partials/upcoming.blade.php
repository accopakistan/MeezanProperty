{{-- New, upcoming and under-construction projects ($upcoming collection of Project), $title. --}}
<section class="card overflow-hidden" aria-labelledby="upcoming-title">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 id="upcoming-title" class="text-base font-bold">{{ $title ?? 'Upcoming & New Projects' }}</h2>
        <a href="{{ route('projects.index') }}" class="link text-[13px]">View all →</a>
    </div>
    @if ($upcoming->isEmpty())
        <p class="p-4 text-[13px] text-muted">No new or upcoming projects have been published here yet.</p>
    @else
        <ul class="grid gap-3 p-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($upcoming as $p)
                <li class="overflow-hidden rounded-lg ring-1 ring-line">
                    <a href="{{ route('projects.show', $p) }}" class="block">
                        <span class="relative block aspect-[16/9] bg-brand-50">
                            <img src="{{ $p->coverUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                            <span @class(['absolute top-2 right-2 rounded px-2 py-0.5 text-[11px] font-bold text-white', 'bg-red-600' => $p->launched_on?->gte(now()->subYear()), 'bg-brand-600' => ! $p->launched_on?->gte(now()->subYear())])>{{ $p->launched_on?->gte(now()->subYear()) ? 'New' : $p->statusLabel() }}</span>
                        </span>
                        <span class="block p-2.5">
                            <span class="block text-[13px] font-semibold text-ink">{{ $p->name }}</span>
                            <span class="block text-[12px] text-muted">{{ $p->area?->name ?? $p->city }}{{ $p->categoryLabel() ? ' · '.$p->categoryLabel() : '' }}</span>
                            <span class="mt-1 block text-[12px] font-semibold text-brand-700">{{ $p->min_price ? 'From '.\App\Support\Format::price($p->min_price).' (developer price)' : 'Price not published' }}</span>
                            <span class="block text-[11px] text-muted">{{ $p->statusLabel() }}{{ $p->launched_on ? ' · launched '.$p->launched_on->format('M Y') : '' }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
