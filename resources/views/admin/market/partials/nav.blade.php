<nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Data Centre">
    @foreach ([
        ['Overview', 'admin.market.index', []],
        ['Observations', 'admin.market.records.index', ['observations']],
        ['Indicators', 'admin.market.records.index', ['indicators']],
        ['Sources', 'admin.market.sources.index', []],
        ['Categories', 'admin.market.categories', []],
        ['Demo Data', 'admin.market.demo', []],
        ['Projects', 'admin.projects.index', []],
        ['Methodology', 'admin.market.methodologies.index', []],
        ['Calculations', 'admin.market.calculations.index', []],
    ] as [$label, $route, $params])
        @php $active = request()->routeIs($route) && (! $params || request()->route('type') === $params[0]); @endphp
        <a href="{{ route($route, $params) }}" @class(['rounded-full px-3.5 py-1.5 font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $active, 'bg-white ring-line hover:ring-brand-300' => ! $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
