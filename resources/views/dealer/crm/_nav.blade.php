<nav class="mb-6 flex gap-1 overflow-x-auto rounded-xl border border-line bg-white p-1 scrollbar-none" aria-label="CRM sections">
    @foreach ([['Overview', 'dealer.crm', 'dashboard'], ['Pipeline', 'dealer.crm.pipeline', 'layers'], ['All leads', 'dealer.crm.leads', 'list'], ['Agenda', 'dealer.crm.agenda', 'calendar']] as [$label, $route, $icon])
        <a href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => request()->routeIs($route), 'text-muted hover:bg-page hover:text-ink' => ! request()->routeIs($route)]) @if (request()->routeIs($route)) aria-current="page" @endif>
            <x-icon :name="$icon" class="size-4" /> {{ $label }}
        </a>
    @endforeach
</nav>
