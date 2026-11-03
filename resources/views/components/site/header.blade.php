@php
    $user = auth()->user();
    // Navigation follows the reference header; only sections that exist are linked.
    $links = [
        ['label' => 'Buy', 'route' => 'properties.buy'],
        ['label' => 'Rent', 'route' => 'properties.rent'],
        ['label' => 'Plots', 'route' => 'properties.plots'],
        ['label' => 'Commercial', 'route' => 'properties.commercial'],
        ['label' => 'Projects', 'route' => 'projects.index'],
        ['label' => 'Agents', 'route' => 'dealers.index'],
        ['label' => 'AI Search', 'route' => 'ai-finder'],
    ];
    $more = [
        ['label' => 'Sell / List a Property', 'route' => 'sell'],
        ['label' => 'Map Search', 'route' => 'properties.map'],
        ['label' => 'Investment Insights', 'route' => 'investment.index'],
        ['label' => 'Houses', 'route' => 'properties.index', 'params' => ['type' => 'house']],
        ['label' => 'Apartments & Flats', 'route' => 'properties.index', 'params' => ['type' => 'apartment']],
        ['label' => 'Area Guides', 'route' => 'areas.index'],
        ['label' => 'Compare', 'route' => 'compare'],
        ['label' => 'All properties', 'route' => 'properties.index'],
        ['label' => 'How it works', 'route' => 'pages.how-it-works'],
        ['label' => 'About us', 'route' => 'pages.about'],
        ['label' => 'Contact', 'route' => 'pages.contact'],
    ];
    $listUrl = $user?->isDealer() ? route('dealer.properties.create') : route('sell');
    $savedUrl = $user?->hasRole('buyer') ? route('account.saved') : route('login');
@endphp
<header {{ $attributes->merge(['class' => 'sticky top-0 z-40 border-b border-line bg-white']) }}>
    <div class="mx-auto flex h-[76px] max-w-[1440px] items-center gap-6 px-4 sm:px-6 lg:h-[88px] lg:px-10">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="Meezan Property home">
            <x-logo class="h-[60px] w-auto lg:h-[72px]" />
        </a>

        <nav class="hidden flex-1 items-center justify-center gap-1 xl:flex" aria-label="Main">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['route']); @endphp
                <a href="{{ route($link['route']) }}" @class(['relative px-3 py-2 text-[15px] font-medium transition', 'text-brand-600 after:absolute after:inset-x-3 after:-bottom-[22px] after:h-0.5 after:bg-brand-600' => $active, 'text-ink hover:text-brand-600' => ! $active])
                   @if ($active) aria-current="page" @endif>{{ $link['label'] }}</a>
            @endforeach
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-1 px-3 py-2 text-[15px] font-medium text-ink hover:text-brand-600">More <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" /></summary>
                <div class="absolute top-full left-0 z-50 mt-3 w-52 rounded-lg border border-line bg-white p-1.5 shadow-lift">
                    @foreach ($more as $link)
                        <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="block rounded-md px-3 py-2 text-sm hover:bg-brand-50 hover:text-brand-700">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </details>
        </nav>

        <div class="ml-auto hidden flex-col items-end gap-2 xl:flex">
            <div class="flex items-center gap-5 text-[13px] text-ink">
                <span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4 text-brand-600" /> {{ config('meezan.default_city') }}</span>
                <a href="{{ $savedUrl }}" class="flex items-center gap-1.5 hover:text-brand-600"><x-icon name="heart" class="size-4" /> Saved</a>
                @auth
                    <a href="{{ route($user->role->homeRoute()) }}" class="flex items-center gap-1.5 hover:text-brand-600">
                        <span class="grid size-6 place-items-center rounded-full bg-brand-600 text-[10px] font-bold text-white">{{ $user->initials() }}</span>
                        {{ \Illuminate\Support\Str::before($user->name, ' ') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="flex items-center gap-1.5 hover:text-brand-600"><x-icon name="user" class="size-4" /> Login</a>
                @endauth
            </div>
            @unless ($user?->isAdmin())
                <a href="{{ $listUrl }}" class="btn btn-gold px-5 py-2"><span class="grid size-5 place-items-center rounded-full bg-white text-gold-600"><x-icon name="plus" class="size-3.5" /></span> List Your Property</a>
            @endunless
        </div>

        <button type="button" class="btn btn-secondary ml-auto px-3 xl:hidden" data-toggle="mobile-nav" aria-controls="mobile-nav" aria-expanded="false" aria-label="Open menu">
            <x-icon name="menu" />
        </button>
    </div>

    <div id="mobile-nav" class="hidden border-t border-line bg-white xl:hidden">
        <nav class="container-page grid gap-1 py-4" aria-label="Mobile">
            @foreach (array_merge($links, $more) as $link)
                <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="rounded-md px-3 py-3 text-[15px] font-medium text-ink hover:bg-brand-50">{{ $link['label'] }}</a>
            @endforeach
            <div class="divider my-2"></div>
            @auth
                <a href="{{ route($user->role->homeRoute()) }}" class="rounded-md px-3 py-3 text-[15px] font-semibold text-ink hover:bg-brand-50">{{ $user->isAdmin() ? 'Admin dashboard' : 'My account' }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="w-full rounded-md px-3 py-3 text-left text-[15px] font-medium text-muted hover:bg-brand-50">Sign out</button>
                </form>
            @else
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="btn btn-secondary">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-outline">Create account</a>
                </div>
                <a href="{{ route('dealer.register') }}" class="rounded-md px-3 py-3 text-[15px] font-medium text-ink hover:bg-brand-50">Dealer sign up</a>
            @endauth
            @unless ($user?->isAdmin())
                <a href="{{ $listUrl }}" class="btn btn-gold mt-2">List Your Property</a>
            @endunless
        </nav>
    </div>
</header>
