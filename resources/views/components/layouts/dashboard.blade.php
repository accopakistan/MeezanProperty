@props(['title', 'section', 'heading' => null, 'subheading' => null])
{{--
    Dealer / buyer: site header on top, dark purple sidebar with the user card
      (public/Refrence/Meezan Property Dealer Platform UI Mockup.png, Buyer Dashboard Showcase.png).
    Admin: full-height dark sidebar with the white logo and a gold active item, plus a
      search top bar (public/Refrence/Meezan Property Admin Dashboard.png).
--}}
@php
    use App\Enums\DealerStatus;
    use App\Enums\PropertyStatus;

    $user = auth()->user();
    $isAdmin = $section === 'admin';

    $pendingDealers = $isAdmin ? \App\Models\DealerProfile::whereIn('verification_status', DealerStatus::reviewQueue())->count() : 0;
    $pendingListings = $isAdmin ? \App\Models\Property::where('status', PropertyStatus::Pending)->count() : 0;
    $unreadNotifications = $user->unreadNotifications()->count();

    $nav = match ($section) {
        'dealer' => [
            ['Dashboard', 'dealer.dashboard', 'dashboard', null],
            ['Notifications', 'notifications.index', 'bell', $unreadNotifications ?: null],
            ['My Listings', 'dealer.properties.index', 'layers', null],
            ['Add New Property', 'dealer.properties.create', 'plus', null],
            ['Performance', 'dealer.performance', 'chart', null],
            ['Lead CRM', 'dealer.crm', 'target', \App\Models\LeadFollowUp::where('dealer_id', $user->id)->overdue()->whereHas('assignment', fn ($q) => $q->active())->count() ?: null],
            ['Leads & Inquiries', 'dealer.leads', 'inbox', $user->receivedInquiries()->where('status', 'new')->count() ?: null],
            ['Agency Profile', 'dealer.profile', 'user', null],
            ['Verification', 'dealer.verification', 'badge-check', null],
            ['Packages & Payments', 'dealer.subscription', 'credit-card', null],
            ['Help & Support', 'pages.contact', 'info', null],
        ],
        'account' => [
            ['Dashboard', 'account.dashboard', 'dashboard', null],
            ['Notifications', 'notifications.index', 'bell', $unreadNotifications ?: null],
            ['Saved Properties', 'account.saved', 'heart', null],
            ['Saved Searches', 'account.saved-searches.index', 'search', null],
            ['Alerts', 'account.alerts', 'bell', $user->unseenAlertCount() ?: null],
            ['Compare', 'compare', 'layers', count(session('compare', [])) ?: null],
            ['My Requirements', 'account.requirements.index', 'target', null],
            ['Inquiries', 'account.inquiries', 'message', null],
            ['AI Recommendations', 'ai-finder', 'sparkles', null],
            ['My Profile', 'account.profile', 'user', null],
            ['Settings', 'account.settings', 'settings', null],
            ['Help & Support', 'pages.contact', 'info', null],
        ],
        'admin' => [
            ['Dashboard', 'admin.dashboard', 'dashboard', null],
            ['Notifications', 'notifications.index', 'bell', $unreadNotifications ?: null],
            ['Properties', 'admin.properties.index', 'home', $pendingListings ?: null],
            ['Users', 'admin.users.index', 'users', null],
            ['Dealers', 'admin.dealers.index', 'briefcase', $pendingDealers ?: null],
            ['Listing Reports', 'admin.reports.index', 'alert', \App\Models\ListingReport::where('status', 'open')->count() ?: null],
            ['Marketplace Trust', 'admin.trust', 'shield', null],
            ['Duplicate Review', 'admin.properties.duplicates', 'layers', null],
            ['New Projects', 'admin.projects.index', 'building', null],
            ['Locations', 'admin.areas.index', 'map-pin', null],
            ['Map Boundaries', 'admin.boundaries.index', 'map', null],
            ['Market Data Centre', 'admin.market.index', 'chart', null],
            ['Investment Index', 'admin.investment-index.overview', 'badge-check', (\App\Models\InvestmentException::where('status', 'open')->count() + \App\Models\InvestmentEvidenceSubmission::where('status', 'pending')->count()) ?: null],
            ['Inquiries & Leads', 'admin.leads.index', 'inbox', null],
            ['Lead Oversight', 'admin.crm', 'chart', null],
            ['AI Command Center', 'admin.ai.overview', 'sparkles', \App\Models\AiApproval::where('status', 'pending')->count() ?: null],
            ['AI & WhatsApp', 'admin.conversations.index', 'bot', null],
            ['WhatsApp Control', 'admin.whatsapp', 'whatsapp', \App\Models\OutboundMessage::where('status', 'failed')->count() ?: null],
            ['Billing', 'admin.billing', 'chart', null],
            ['Packages', 'admin.packages.index', 'tag', null],
            ['Subscriptions', 'admin.subscriptions', 'credit-card', null],
            ['Payments', 'admin.payments', 'file-text', \App\Models\Payment::where('status', 'awaiting_review')->count() ?: null],
            ['Reports & Analytics', 'admin.analytics', 'chart', null],
            ['Settings', 'admin.settings', 'settings', null],
            ['System Logs', 'admin.audit-logs', 'refresh', null],
        ],
    };

    $isActive = fn (string $route) => request()->routeIs($route)
        || (str_ends_with($route, '.index') && request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.index').'.*') && ! request()->routeIs('dealer.properties.create'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <x-layouts.head :title="$title" noindex />
</head>
<body class="min-h-screen bg-page">
    @unless ($isAdmin)
        <x-site.header />
    @endunless

    <div @class(['lg:grid', 'lg:grid-cols-[250px_minmax(0,1fr)]' => $isAdmin, 'lg:grid-cols-[232px_minmax(0,1fr)]' => ! $isAdmin])>
        {{-- Sidebar --}}
        <aside id="dash-nav" class="fixed inset-0 z-50 hidden lg:static lg:block">
            <div class="absolute inset-0 bg-ink/50 lg:hidden" data-toggle="dash-nav" data-lock-scroll></div>
            <div @class(['relative flex h-full w-[264px] flex-col bg-gradient-to-b from-brand-800 to-brand-900 text-white lg:sticky lg:w-auto',
                         'lg:top-0 lg:h-screen' => $isAdmin, 'lg:top-[88px] lg:h-[calc(100vh-88px)]' => ! $isAdmin])>
                <button type="button" class="absolute top-3 right-3 grid size-9 place-items-center rounded-md text-white/80 hover:bg-white/10 lg:hidden" data-toggle="dash-nav" data-lock-scroll aria-label="Close menu"><x-icon name="x" /></button>

                @if ($isAdmin)
                    <a href="{{ route('admin.dashboard') }}" class="px-6 pt-5 pb-4" aria-label="Admin dashboard"><x-logo variant="light" priority class="h-16 w-auto" /></a>
                @else
                    <div class="flex items-center gap-3 px-5 pt-6 pb-5">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-white font-serif text-lg font-bold text-brand-700 ring-2 ring-gold-500">{{ $user->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $user->name }}</p>
                            <p class="text-xs text-white/70">{{ $section === 'dealer' ? ($user->dealerProfile?->business_type === 'owner' ? 'Property owner' : ($user->isVerifiedDealer() ? 'Verified Dealer' : 'Dealer')) : 'Buyer' }}</p>
                        </div>
                    </div>
                @endif

                <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4" aria-label="Dashboard">
                    @foreach ($nav as [$label, $route, $icon, $count])
                        @php $active = $isActive($route); @endphp
                        <a href="{{ route($route) }}" @class(['side-link', $isAdmin ? 'side-link-gold' : 'side-link-active' => $active, 'py-3' => $isAdmin]) @if ($active) aria-current="page" @endif>
                            <x-icon :name="$icon" class="size-5" />
                            <span class="flex-1">{{ $label }}</span>
                            @if ($count)
                                <span class="rounded-full bg-gold-500 px-2 py-0.5 text-[11px] font-bold text-brand-900">{{ $count }}</span>
                            @elseif ($isAdmin)
                                <x-icon name="chevron-right" class="size-4 opacity-60" />
                            @endif
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="side-link w-full"><x-icon name="log-out" class="size-5" /> Logout</button>
                    </form>
                </nav>

                @if ($isAdmin)
                    <div class="space-y-3 border-t border-white/10 p-4">
                        <a href="{{ route('ai-finder') }}" class="flex items-center gap-3 rounded-lg border border-white/15 bg-white/5 p-3 hover:bg-white/10">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-500"><x-icon name="bot" class="size-5" /></span>
                            <span class="min-w-0 flex-1 text-xs leading-5"><span class="block text-sm font-semibold">Meezan AI Assistant</span><span class="text-white/70">Test matching on the live site</span></span>
                            <x-icon name="chevron-right" class="size-4" />
                        </a>
                        <a href="{{ route('home') }}" class="flex items-center justify-between rounded-lg border border-white/15 px-3 py-2.5 text-sm hover:bg-white/10">
                            <span class="flex items-center gap-2"><x-icon name="map" class="size-4" /> View Website</span><x-icon name="external" class="size-4" />
                        </a>
                    </div>
                @endif
            </div>
        </aside>

        {{-- Main --}}
        <div class="min-w-0">
            @if ($isAdmin)
                <header class="sticky top-0 z-30 border-b border-line bg-white">
                    <div class="flex h-[72px] items-center gap-3 px-4 sm:px-6">
                        <button type="button" class="btn btn-secondary px-2.5 lg:hidden" data-toggle="dash-nav" data-lock-scroll aria-controls="dash-nav" aria-label="Open menu"><x-icon name="menu" /></button>
                        <form method="GET" action="{{ route('admin.properties.index') }}" class="hidden max-w-md flex-1 sm:flex" role="search">
                            <input type="hidden" name="status" value="">
                            <label for="admin-search" class="sr-only">Search listings</label>
                            <div class="relative flex-1">
                                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                                <input id="admin-search" name="q" value="{{ request('q') }}" class="input h-11 rounded-r-none pl-9" placeholder="Search listings by title or reference…">
                            </div>
                            <button class="btn btn-primary h-11 rounded-l-none px-4" aria-label="Search"><x-icon name="search" class="size-4" /></button>
                        </form>
                        <div class="ml-auto flex items-center gap-4">
                            <span class="hidden items-center gap-2 rounded-md border border-line px-3 py-2 text-[13px] md:flex"><x-icon name="calendar" class="size-4 text-muted" /> {{ now()->format('j M Y') }}</span>
                            <a href="{{ route('notifications.index') }}" class="relative grid size-10 place-items-center rounded-full hover:bg-page" aria-label="Notifications{{ $unreadNotifications ? ', '.$unreadNotifications.' unread' : '' }}">
                                <x-icon name="bell" class="size-6 text-ink/80" />
                                @if ($unreadNotifications)
                                    <span class="absolute top-0.5 right-0.5 grid size-5 place-items-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ min($unreadNotifications, 99) }}</span>
                                @endif
                            </a>
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ $user->initials() }}</span>
                                <div class="hidden leading-tight sm:block"><p class="text-sm font-semibold">{{ $user->name }}</p><p class="text-xs text-muted">{{ $user->role->label() }}</p></div>
                            </div>
                        </div>
                    </div>
                </header>
            @else
                <div class="flex items-center gap-3 border-b border-line bg-white px-4 py-3 lg:hidden">
                    <button type="button" class="btn btn-secondary px-2.5" data-toggle="dash-nav" data-lock-scroll aria-controls="dash-nav" aria-label="Open dashboard menu"><x-icon name="menu" /></button>
                    <span class="text-sm font-semibold">{{ $section === 'dealer' ? 'Dealer portal' : 'My account' }}</span>
                    <a href="{{ route('notifications.index') }}" class="relative ml-auto grid size-10 place-items-center rounded-full hover:bg-page" aria-label="Notifications{{ $unreadNotifications ? ', '.$unreadNotifications.' unread' : '' }}">
                        <x-icon name="bell" class="size-5 text-ink/80" />
                        @if ($unreadNotifications)
                            <span class="absolute top-0.5 right-0.5 grid size-4.5 place-items-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ min($unreadNotifications, 99) }}</span>
                        @endif
                    </a>
                </div>
            @endif

            <main id="main" class="px-4 py-6 sm:px-6 lg:px-7">
                @if ($heading)
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 class="text-[26px] sm:text-[30px]">{{ $heading }}</h1>
                            @if ($subheading)
                                <p class="mt-1 text-sm text-muted">{{ $subheading }}</p>
                            @endif
                        </div>
                        {{ $actions ?? '' }}
                    </div>
                @endif
                <x-flash class="mb-5" />
                @if (auth()->user() && ! auth()->user()->isAdmin() && ! auth()->user()->hasVerifiedEmail())
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-ink" role="status">
                        <p class="flex items-start gap-2">
                            <x-icon name="mail" class="mt-0.5 size-4 shrink-0 text-gold-700" />
                            <span>Please confirm your email address, <strong>{{ auth()->user()->email }}</strong>.{{ config('meezan.require_verified_email') ? ' It is needed before you can share requirements or publish listings.' : '' }}</span>
                        </p>
                        <form method="POST" action="{{ route('verification.send') }}">@csrf
                            <button class="btn btn-secondary btn-sm">Send confirmation link</button>
                        </form>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
