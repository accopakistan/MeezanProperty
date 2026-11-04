<nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="AI Command Center">
    @foreach ([
        ['Overview', 'admin.ai.overview', 'admin.ai.overview'],
        ['Agents', 'admin.ai.agents.index', 'admin.ai.agents.*'],
        ['Tasks', 'admin.ai.tasks.index', 'admin.ai.tasks.*'],
        ['Approvals', 'admin.ai.approvals.index', 'admin.ai.approvals.*'],
        ['Staff Work', 'admin.ai.work', 'admin.ai.work'],
        ['Usage & Cost', 'admin.ai.usage', 'admin.ai.usage*'],
        ['Memory & Knowledge', 'admin.ai.knowledge', 'admin.ai.knowledge*'],
        ['Gemini Settings', 'admin.ai.providers', 'admin.ai.providers*'],
        ['Tools', 'admin.ai.tools', 'admin.ai.tools'],
        ['Connectors', 'admin.ai.connectors', 'admin.ai.connectors*'],
        ['Activity & Errors', 'admin.ai.logs', 'admin.ai.logs'],
    ] as [$label, $route, $pattern])
        @php $active = request()->routeIs($pattern); @endphp
        <a href="{{ route($route) }}" @class(['rounded-full px-3.5 py-1.5 font-semibold ring-1', 'bg-brand-600 text-white ring-brand-600' => $active, 'bg-white ring-line hover:ring-brand-300' => ! $active]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
