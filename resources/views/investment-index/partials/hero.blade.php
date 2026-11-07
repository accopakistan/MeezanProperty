{{--
    Investment Index hero. $title, $intro, $breadcrumbs, $run (published run or null),
    optional $searchGroup (property search tab: null | commercial).
--}}
@php
    $hero = \App\Services\InvestmentIndex\IndexHeroImage::resolve($city ?? null, $category ?? null);
    $cityName = isset($city) && ! in_array(mb_strtolower($city), ['pakistan', 'all'], true) ? \App\Support\Cities::canonical($city) ?? $city : null;
    $province = $cityName ? \App\Support\Cities::province($cityName) : null;
    $eyebrow = $province ? "{$province}, Pakistan" : ($cityName ? "{$cityName}, Pakistan" : 'Meezan Investment Index · Pakistan');
@endphp
<section class="relative isolate overflow-hidden bg-brand-900 text-white">
    <img src="{{ $hero['url'] }}"
         alt="{{ $hero['alt'] }}"
         width="1920" height="1200"
         class="absolute inset-0 -z-20 size-full object-cover object-center"
         fetchpriority="high"
         loading="eager"
         onerror="this.onerror=null;this.src='{{ asset('images/investment-index/fallback.jpg') }}';">
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900/95 via-brand-900/75 to-brand-900/25"></div>
    <div class="container-page py-10 pb-12 lg:py-16">
        <x-breadcrumbs :items="$breadcrumbs" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
        <p class="mt-6 text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ $eyebrow }}</p>
        <h1 class="mt-1 text-4xl text-white sm:text-5xl font-bold">{{ $title }}</h1>
        <p class="mt-3 max-w-2xl text-white/85 text-[15px] sm:text-base leading-relaxed">{{ $intro }}</p>

        <x-search-bar class="mt-6 max-w-5xl" :city="$cityName" :group="$searchGroup ?? null" />

        <dl class="mt-6 flex flex-wrap gap-x-8 gap-y-2 text-white">
            <div><dt class="text-xs text-white/70">Evidence Standard</dt><dd class="text-2xl font-bold">&ge; 60% Verified</dd></div>
            <div><dt class="text-xs text-white/70">Coverage</dt><dd class="text-2xl font-bold">7 Launch Cities</dd></div>
            <div><dt class="text-xs text-white/70">Methodology</dt><dd class="text-2xl font-bold">{{ $run ? 'v'.$run->methodology_version : 'Active' }}</dd></div>
        </dl>
    </div>
</section>
