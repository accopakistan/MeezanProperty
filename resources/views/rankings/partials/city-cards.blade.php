{{--
    The seven city cards (scroll sideways on phones). $cities from RankingBrowser::cities(),
    $active (city name or null), $link: fn (?string $citySlug) => url, $countLabel: fn (array $c) => string.
--}}
<nav class="relative z-10 -mt-14 lg:-mt-16" aria-label="Choose a city">
    <div class="container-page">
        <ul class="flex snap-x gap-3 overflow-x-auto pb-2 scrollbar-none lg:grid lg:grid-cols-7 lg:overflow-visible">
            @foreach ($cities as $c)
                @php $isActive = $active === $c['name']; @endphp
                <li class="w-44 shrink-0 snap-start lg:w-auto">
                    <a href="{{ $link($c['slug']) }}" @class(['group relative flex h-24 overflow-hidden rounded-xl text-white shadow-lift ring-2 transition', 'ring-gold-400' => $isActive, 'ring-transparent hover:ring-white/70' => ! $isActive]) @if ($isActive) aria-current="page" @endif>
                        @if ($c['photo'])
                            <img src="{{ asset($c['photo']['file']) }}" alt="" class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-105" style="object-position: {{ $c['photo']['position'] ?? 'center' }}" loading="lazy">
                        @endif
                        <span @class(['absolute inset-0', 'bg-brand-700/75' => $isActive, 'bg-gradient-to-t from-brand-900/90 via-brand-900/55 to-brand-900/20' => ! $isActive])></span>
                        <span class="relative mt-auto flex w-full items-end justify-between gap-2 p-3">
                            <span class="min-w-0">
                                <span class="block text-base font-bold">{{ $c['name'] }}</span>
                                <span class="block truncate text-[12px] text-white/85">{{ $countLabel($c) }}</span>
                            </span>
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-white text-brand-700"><x-icon name="arrow-right" class="size-3.5" /></span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>
