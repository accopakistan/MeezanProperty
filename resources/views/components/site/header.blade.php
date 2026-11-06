@php
    use App\Support\Contact;
    use App\Support\Navigation;

    $user = auth()->user();
    // One menu definition for desktop and mobile; every link is a page that exists.
    $sections = Navigation::sections();
    $listUrl = $user?->isDealer() ? route('dealer.properties.create') : route('sell');
    $savedUrl = $user?->hasRole('buyer') ? route('account.saved') : route('login');
    $aiActive = request()->routeIs('ai-finder*');
@endphp
<header {{ $attributes->merge(['class' => 'sticky top-0 z-40 border-b border-line bg-white']) }} data-site-header>
    <div class="mx-auto flex h-[76px] max-w-[1440px] items-center gap-4 px-4 sm:px-6 lg:h-[88px] lg:gap-6 lg:px-8 2xl:px-10">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="Meezan Property home">
            <x-logo class="h-[60px] w-auto lg:h-[64px] xl:h-[72px]" />
        </a>

        {{-- Desktop mega menu: a disclosure per section (buttons with aria-expanded), opened by click, hover or keyboard. --}}
        <nav class="hidden flex-1 items-center justify-center gap-0.5 lg:flex xl:gap-1" aria-label="Main" data-mega>
            @foreach ($sections as $section)
                @php $active = Navigation::isActive($section); @endphp
                <div data-mega-item>
                    <button type="button" id="mega-trigger-{{ $section['key'] }}" aria-expanded="false" aria-controls="mega-{{ $section['key'] }}" data-mega-trigger
                            @class(['group relative flex items-center gap-1 rounded-lg px-2.5 py-2 text-[14px] font-semibold whitespace-nowrap transition hover:bg-brand-50 hover:text-brand-700 aria-expanded:bg-brand-50 aria-expanded:text-brand-700 xl:px-3 xl:text-[15px]',
                                'text-brand-700 after:absolute after:inset-x-3 after:-bottom-[19px] after:h-0.5 after:rounded-full after:bg-brand-600' => $active, 'text-ink' => ! $active])>
                        {{ $section['label'] }}
                        <x-icon name="chevron-down" class="size-4 text-muted transition group-aria-expanded:rotate-180 group-aria-expanded:text-brand-600" />
                    </button>
                    <div id="mega-{{ $section['key'] }}" class="absolute inset-x-0 top-full hidden border-t border-line bg-white shadow-[0_24px_48px_-24px_rgb(46_26_82/0.35)]" data-mega-panel>
                        <div class="mx-auto grid max-h-[calc(100vh-88px)] max-w-[1440px] gap-x-10 gap-y-6 overflow-y-auto px-8 py-8 2xl:px-10"
                             style="grid-template-columns: repeat({{ count($section['columns']) }}, minmax(0, 1fr)) {{ isset($section['feature']) ? 'minmax(0, 300px)' : '' }};">
                            @foreach ($section['columns'] as $column)
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold tracking-[0.14em] text-muted uppercase">{{ $column['title'] }}</p>
                                    <ul class="mt-3 space-y-0.5">
                                        @foreach ($column['links'] as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}" class="group/link flex items-start gap-3 rounded-lg px-2 py-2 -mx-2 text-[14px] text-ink transition hover:bg-brand-50 hover:text-brand-700 focus-visible:bg-brand-50">
                                                    @isset($link['icon'])
                                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 transition group-hover/link:bg-brand-600 group-hover/link:text-white"><x-icon :name="$link['icon']" class="size-4" /></span>
                                                    @endisset
                                                    <span class="min-w-0 {{ isset($link['icon']) ? 'pt-1' : '' }}">
                                                        <span class="block font-semibold">{{ $link['label'] }}</span>
                                                        @isset($link['hint'])<span class="block text-[12.5px] leading-5 font-normal text-muted">{{ $link['hint'] }}</span>@endisset
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                            @isset($section['feature'])
                                @php $f = $section['feature']; @endphp
                                <aside class="relative flex flex-col overflow-hidden rounded-xl bg-brand-800 p-6 text-white">
                                    <span class="pointer-events-none absolute -top-10 -right-10 size-40 rounded-full bg-gold-500/15" aria-hidden="true"></span>
                                    <span class="grid size-11 place-items-center rounded-xl bg-white/10 text-gold-500"><x-icon :name="$f['icon']" class="size-6" /></span>
                                    <p class="mt-4 font-serif text-xl font-bold">{{ $f['title'] }}</p>
                                    <p class="mt-2 text-[13.5px] leading-6 text-white/80">{{ $f['body'] }}</p>
                                    <a href="{{ $f['url'] }}" @if ($f['external'] ?? false) target="_blank" rel="noopener" @endif class="btn btn-gold mt-auto w-max pt-2.5">{{ $f['cta'] }} <x-icon name="arrow-right" class="size-4" /></a>
                                </aside>
                            @endisset
                        </div>
                        <div class="border-t border-line bg-page">
                            <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-3 px-8 py-3 text-[13px] text-muted 2xl:px-10">
                                <span>Lahore · Karachi · Islamabad · Multan · Faisalabad · Quetta · Peshawar</span>
                                <span class="flex items-center gap-4">
                                    @if (Contact::phone())<a href="{{ Contact::phoneHref() }}" class="flex items-center gap-1.5 font-semibold text-ink hover:text-brand-700"><x-icon name="phone" class="size-4 text-brand-600" /> {{ Contact::phoneDisplay() }}</a>@endif
                                    @if (Contact::whatsapp())<a href="{{ Contact::whatsappUrl() }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 font-semibold text-ink hover:text-brand-700"><x-icon name="whatsapp" class="size-4 text-[#16a34a]" /> WhatsApp</a>@endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <a href="{{ route('ai-finder') }}" @class(['ml-1 flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[14px] font-semibold whitespace-nowrap ring-1 transition xl:text-[15px]',
                    'bg-brand-600 text-white ring-brand-600' => $aiActive, 'text-brand-700 ring-brand-200 hover:bg-brand-50' => ! $aiActive])
               @if ($aiActive) aria-current="page" @endif>
                <x-icon name="sparkles" class="size-4 {{ $aiActive ? 'text-gold-300' : 'text-gold-500' }}" /> AI Search
            </a>
        </nav>

        <div class="ml-auto hidden flex-col items-end gap-2 lg:flex">
            <div class="flex items-center gap-4 text-[13px] text-ink xl:gap-5">
                @if (Contact::phone())
                    <a href="{{ Contact::phoneHref() }}" class="hidden items-center gap-1.5 hover:text-brand-600 xl:flex" aria-label="Call Meezan Property, {{ Contact::phoneDisplay() }}"><x-icon name="phone" class="size-4 text-brand-600" /> {{ Contact::phoneDisplay() }}</a>
                @endif
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
                <a href="{{ $listUrl }}" class="btn btn-gold px-4 py-2 xl:px-5"><span class="grid size-5 place-items-center rounded-full bg-white text-gold-600"><x-icon name="plus" class="size-3.5" /></span> List Your Property</a>
            @endunless
        </div>

        <button type="button" class="btn btn-secondary ml-auto px-3 lg:hidden" data-toggle="mobile-nav" data-lock-scroll aria-controls="mobile-nav" aria-expanded="false" aria-label="Open menu">
            <x-icon name="menu" />
        </button>
    </div>

    {{-- Mobile: the same sections as expandable groups. --}}
    <div id="mobile-nav" class="hidden max-h-[calc(100dvh-76px)] overflow-y-auto border-t border-line bg-white lg:hidden">
        <nav class="container-page py-3" aria-label="Mobile">
            <a href="{{ route('ai-finder') }}" class="mb-2 flex items-center gap-3 rounded-xl bg-brand-800 px-4 py-3.5 text-white">
                <x-icon name="sparkles" class="size-5 text-gold-500" />
                <span><span class="block text-[15px] font-semibold">AI Property Finder</span><span class="block text-[12.5px] text-white/75">Describe what you need in plain words</span></span>
            </a>
            @foreach ($sections as $section)
                <details class="group border-b border-line" @if (Navigation::isActive($section)) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-1 py-3.5 text-[15px] font-semibold text-ink [&::-webkit-details-marker]:hidden">
                        {{ $section['label'] }}
                        <x-icon name="chevron-down" class="size-5 text-muted transition group-open:rotate-180" />
                    </summary>
                    <div class="grid gap-4 pb-4 sm:grid-cols-2">
                        @foreach ($section['columns'] as $column)
                            <div>
                                <p class="px-1 text-[11px] font-bold tracking-[0.14em] text-muted uppercase">{{ $column['title'] }}</p>
                                <ul class="mt-1.5">
                                    @foreach ($column['links'] as $link)
                                        <li><a href="{{ $link['url'] }}" class="block rounded-md px-1 py-2 text-[14.5px] text-ink hover:bg-brand-50 hover:text-brand-700">{{ $link['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach

            <div class="mt-4 grid gap-2">
                @auth
                    <a href="{{ route($user->role->homeRoute()) }}" class="btn btn-secondary">{{ $user->isAdmin() ? 'Admin dashboard' : 'My account' }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full rounded-md px-3 py-2.5 text-[14px] font-medium text-muted hover:bg-brand-50">Sign out</button>
                    </form>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('login') }}" class="btn btn-secondary">Login</a>
                        <a href="{{ route('register') }}" class="btn btn-outline">Create account</a>
                    </div>
                @endauth
                @unless ($user?->isAdmin())
                    <a href="{{ $listUrl }}" class="btn btn-gold">List Your Property</a>
                @endunless
                <div class="grid grid-cols-2 gap-2">
                    @if (Contact::phone())
                        <a href="{{ Contact::phoneHref() }}" class="btn btn-secondary"><x-icon name="phone" class="size-4" /> Call us</a>
                    @endif
                    @if (Contact::whatsapp())
                        <a href="{{ Contact::whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                    @endif
                </div>
                <a href="{{ $savedUrl }}" class="flex items-center justify-center gap-1.5 py-2 text-[14px] text-muted hover:text-brand-700"><x-icon name="heart" class="size-4" /> Saved properties</a>
            </div>
        </nav>
    </div>
    <div class="fixed inset-x-0 top-[88px] bottom-0 -z-10 hidden bg-brand-900/20 backdrop-blur-[1px]" data-mega-backdrop aria-hidden="true"></div>
</header>
