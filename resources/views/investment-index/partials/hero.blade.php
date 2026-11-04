{{--
    Investment Index hero. $title, $intro, $breadcrumbs, $run (published run or null),
    optional $searchGroup (property search tab: null | commercial).
--}}
@php $photo = config('meezan.place_photos.home'); @endphp
<section class="relative isolate overflow-hidden bg-brand-900 text-white">
    @if ($photo)
        <img src="{{ asset($photo['file']) }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-30" style="object-position: {{ $photo['position'] ?? 'center' }}" fetchpriority="high">
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/92 to-brand-800/70"></div>
    <div class="container-page pt-6 pb-10">
        <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        <p class="mt-5 text-xs font-semibold tracking-[0.16em] text-gold-300 uppercase">Meezan Investment Index</p>
        <h1 class="mt-2 max-w-3xl font-sans text-3xl leading-tight font-bold text-white sm:text-4xl">{{ $title }}</h1>
        <p class="mt-3 max-w-2xl text-[15px] leading-7 text-white/85">{{ $intro }}</p>
        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1.5 text-[13px] font-semibold text-white/90">
            <li class="flex items-center gap-1.5"><x-icon name="shield" class="size-4 text-gold-300" /> Verified, traceable evidence only</li>
            <li class="flex items-center gap-1.5"><x-icon name="badge-check" class="size-4 text-gold-300" /> Official legal checks</li>
            <li class="flex items-center gap-1.5"><x-icon name="chart" class="size-4 text-gold-300" /> No paid or sponsored ranking</li>
        </ul>
        <p class="mt-4 text-[13px] text-white/70">
            @if ($run)
                Recalculated automatically; last published {{ $run->published_at->localTime()->format('j M Y, H:i') }} (methodology v{{ $run->methodology_version }}).
            @else
                No results have been published yet.
            @endif
            <a href="{{ route('rankings.methodology') }}" class="font-semibold text-white underline decoration-gold-300/70 underline-offset-2 hover:text-gold-200">How the Index works</a>
        </p>
        <x-search-bar class="mt-6 max-w-5xl" :group="$searchGroup ?? null" />
    </div>
</section>
