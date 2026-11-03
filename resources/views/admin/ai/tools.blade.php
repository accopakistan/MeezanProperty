<x-layouts.dashboard title="AI Tool Registry" section="admin" heading="Tool Registry" subheading="Everything an agent can do. Agents use only tools assigned to them; every call is validated and recorded. Approval tools only create a request, and prohibited tools never run.">
    @include('admin.ai.partials.nav')

    @foreach ($groups as $risk => $tools)
        <section class="mb-6">
            <h2 class="mb-2 text-base font-bold">{{ \App\Services\Agents\ToolDefinition::RISKS[$risk] ?? $risk }} <span class="text-sm font-medium text-muted">({{ $tools->count() }})</span></h2>
            <div class="card relative overflow-x-auto">
                <table class="table table-compact min-w-[860px]">
                    <thead><tr><th scope="col">Tool</th><th scope="col">What it does</th><th scope="col">Input</th><th scope="col">Assigned to</th><th scope="col">Calls (30 days)</th></tr></thead>
                    <tbody>
                        @foreach ($tools as $name => $tool)
                            <tr>
                                <td class="whitespace-nowrap font-semibold">{{ $name }}</td>
                                <td class="max-w-md text-[13px] text-muted">{{ $tool->description }}</td>
                                <td class="max-w-56 text-[13px]">{{ collect($tool->rules)->keys()->reject(fn ($k) => str_contains($k, '.'))->implode(', ') ?: 'None' }}</td>
                                <td class="max-w-72 text-[13px]">{{ $risk === 'prohibited' ? 'No agent can be given this' : (($assigned[$name] ?? collect())->implode(', ') ?: 'None') }}</td>
                                <td class="tabular-nums">{{ number_format($calls[$name] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</x-layouts.dashboard>
