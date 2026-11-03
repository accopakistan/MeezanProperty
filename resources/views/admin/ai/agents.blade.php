<x-layouts.dashboard title="AI Agents" section="admin" heading="Agent Directory" subheading="Each agent has its own role, instructions, permitted tools, memory rules and version history. Only active agents accept tasks.">
    @include('admin.ai.partials.nav')

    <div class="card relative overflow-x-auto">
        <table class="table table-compact min-w-[1040px]">
            <thead><tr><th scope="col">Agent</th><th scope="col">Operational status</th><th scope="col">Stays off until configured</th><th scope="col">Provider</th><th scope="col">Tools</th><th scope="col">Done / failed</th><th scope="col">Last run</th><th scope="col">Version</th></tr></thead>
            <tbody>
                @foreach ($agents as $a)
                    @php $r = $readiness[$a->slug]; @endphp
                    <tr>
                        <td><a href="{{ route('admin.ai.agents.show', $a) }}" class="link">{{ $a->name }}</a><br><span class="text-[13px] text-muted">{{ $a->role }}</span></td>
                        <td>@include('admin.ai.partials.status', ['status' => $r['state'], 'label' => $r['label']])</td>
                        <td class="max-w-72 text-[13px] text-muted">{{ implode('; ', $r['limits']) ?: '—' }}</td>
                        <td class="max-w-56 text-[13px]">{{ $r['model'] }}<br><span class="text-muted">{{ \App\Models\AiAgent::TIERS[$a->model_tier] ?? $a->model_tier }}</span></td>
                        <td class="tabular-nums">{{ count($a->tools ?? []) }}</td>
                        <td class="tabular-nums">{{ $r['completed'] }} / <span @class(['text-red-600' => $r['failed']])>{{ $r['failed'] }}</span></td>
                        <td class="whitespace-nowrap text-[13px]">{{ $r['last_run'] ? \Illuminate\Support\Carbon::parse($r['last_run'])->localTime()->format('j M, H:i') : 'Never' }}</td>
                        <td>v{{ $a->version }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-[13px] text-muted">"Active and verified" means a task finished without a failed tool, on local data, since the agent's configuration last changed. It does not mean a language model was used. Change providers and limits on each agent's page, or see all of them in <a href="{{ route('admin.ai.providers') }}" class="link">AI Providers</a>.</p>
</x-layouts.dashboard>
