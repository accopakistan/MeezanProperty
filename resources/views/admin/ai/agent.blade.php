<x-layouts.dashboard :title="$agent->name" section="admin" :heading="$agent->name" :subheading="$agent->role">
    @include('admin.ai.partials.nav')

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <section class="card card-body">
                <div class="flex flex-wrap items-center gap-2">
                    @include('admin.ai.partials.status', ['status' => $ready['state'], 'label' => $ready['label']])
                    @include('admin.ai.partials.status', ['status' => $agent->status, 'label' => 'Setting: '.$agent->statusLabel()])
                    <span class="badge bg-page text-muted ring-1 ring-line">Stage {{ $agent->stage }}</span>
                    <span class="badge bg-page text-muted ring-1 ring-line">v{{ $agent->version }}</span>
                    @if ($canDelegate)<span class="badge bg-brand-50 text-brand-700 ring-1 ring-brand-200">Can delegate</span>@endif
                    @unless ($implemented)<span class="badge bg-red-50 text-red-700 ring-1 ring-red-200">No workflow implemented</span>@endunless
                </div>
                <p class="mt-3 text-sm">{{ $agent->description }}</p>
                <p class="mt-2 text-[13px] text-muted">{{ $ready['model'] }} · model setting: {{ $agent->resolvedModel() }} ({{ $agent->model ? 'override' : 'tier default' }}) · memory: {{ collect($agent->memory_policy ?? [])->filter()->keys()->implode(', ') ?: 'task only' }}</p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-4">
                    <div><dt class="text-[13px] text-muted">Completed</dt><dd class="font-semibold tabular-nums">{{ $ready['completed'] }}</dd></div>
                    <div><dt class="text-[13px] text-muted">Failed</dt><dd class="font-semibold tabular-nums">{{ $ready['failed'] }}</dd></div>
                    <div><dt class="text-[13px] text-muted">Tokens (30 days)</dt><dd class="font-semibold tabular-nums">{{ number_format($ready['tokens_30d']) }}</dd></div>
                    <div><dt class="text-[13px] text-muted">Cost (30 days)</dt><dd class="font-semibold tabular-nums">{{ $ready['cost_30d'] !== null ? 'USD '.number_format($ready['cost_30d'], 4) : 'Not calculated' }}</dd></div>
                </dl>
                @if ($ready['limits'])
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[13px] text-amber-900">Stays off until configured: {{ implode('; ', $ready['limits']) }}.</p>
                @endif
                @if ($ready['last_error'])
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-[13px] text-red-800">Latest task failed: {{ \Illuminate\Support\Str::limit($ready['last_error'], 240) }}</p>
                @endif
            </section>

            <form method="POST" action="{{ route('admin.ai.agents.update', $agent) }}" class="card card-body space-y-4">
                @csrf @method('PATCH')
                <h2 class="text-base font-bold">Configuration</h2>
                <x-field name="instructions" type="textarea" rows="8" label="Instructions" required :value="$agent->instructions" hint="Base safety rules (no invented data, no secrets, approvals) always apply and cannot be removed here." />
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-field name="status" type="select" label="Status" required :options="\App\Models\AiAgent::STATUSES" :value="$agent->status" />
                    <x-field name="model_tier" type="select" label="Model tier" required :options="\App\Models\AiAgent::TIERS" :value="$agent->model_tier" />
                    <x-field name="model" label="Primary model override (optional)" :value="$agent->model" placeholder="tier default" />
                    <x-field name="temperature" type="number" label="Temperature (0–1)" required :value="$agent->temperature" step="0.05" min="0" max="1" />
                    <x-field name="daily_token_budget" type="number" label="Daily token budget" :value="$agent->daily_token_budget" :placeholder="config('meezan.agents.limits.daily_tokens_per_agent')" min="0" />
                </div>
                <fieldset class="rounded-lg border border-line p-3">
                    <legend class="px-1 text-sm font-semibold">Provider and limits</legend>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-field name="primary_provider" type="select" label="Primary provider" required :options="$providerLabels" :value="$agent->primary_provider" />
                        <x-field name="secondary_provider" type="select" label="Secondary provider" :options="['' => 'None'] + $providerLabels" :value="$agent->secondary_provider" />
                        <x-field name="secondary_model" label="Secondary model (optional)" :value="$agent->secondary_model" placeholder="tier or provider default" />
                        <x-field name="max_tokens" type="number" min="1" label="Max tokens per request" :value="$agent->max_tokens" placeholder="No extra limit" />
                        <x-field name="daily_request_limit" type="number" min="1" label="Requests per day" :value="$agent->daily_request_limit" placeholder="No limit" />
                        <x-field name="monthly_budget_usd" type="number" step="0.01" min="0" label="Monthly budget (USD)" :value="$agent->monthly_budget_usd" placeholder="No limit" />
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="hidden" name="fallback_enabled" value="0"><input type="checkbox" name="fallback_enabled" value="1" @checked($agent->fallback_enabled)> Allow fallback for this agent (also needs the switch in <a href="{{ route('admin.ai.providers') }}" class="link">AI Providers</a>)</label>
                    @if ($modelWarning)<p class="mt-2 text-[13px] text-amber-800">{{ $modelWarning }}</p>@endif
                    <p class="mt-2 text-[13px] text-muted">The model override above is for the primary provider. Temperature applies to OpenAI only: Anthropic's current API version uses its default sampling.</p>
                </fieldset>
                <fieldset>
                    <legend class="label">Permitted tools</legend>
                    <div class="grid gap-x-4 gap-y-1.5 sm:grid-cols-2">
                        @foreach ($tools as $name => $tool)
                            <label class="flex items-start gap-2 text-[13px]">
                                <input type="checkbox" name="tools[]" value="{{ $name }}" class="mt-0.5" @checked(in_array($name, old('tools', $agent->tools ?? []), true))>
                                <span><span class="font-semibold">{{ $name }}</span> <span @class(['badge px-1.5 py-0 ring-1', 'bg-page text-muted ring-line' => $tool->risk === 'read', 'bg-amber-50 text-amber-800 ring-amber-200' => $tool->risk === 'low', 'bg-orange-50 text-orange-800 ring-orange-200' => $tool->risk === 'approval'])>{{ $tool->risk === 'approval' ? 'needs approval' : $tool->risk }}</span><br><span class="text-muted">{{ $tool->description }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-[13px] text-muted">Prohibited actions (refunds, transfers, deleting records, shell commands, deployment) cannot be granted to any agent.</p>
                </fieldset>
                <x-field name="change_note" label="Change note" required placeholder="Why this change?" />
                <button class="btn btn-primary">Save as version {{ $agent->version + 1 }}</button>
            </form>
        </div>

        <div class="min-w-0 space-y-6">
            <section class="card card-body">
                <h2 class="text-base font-bold">Run a task</h2>
                <form method="POST" action="{{ route('admin.ai.tasks.store') }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="agent" value="{{ $agent->slug }}">
                    <x-field name="request" type="textarea" rows="3" label="Request" required />
                    <button class="btn btn-primary" @disabled(! $agent->isActive())>Run with {{ $agent->name }}</button>
                </form>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Recent tasks</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($tasks as $t)
                        <li class="flex items-center justify-between gap-2 py-2"><a href="{{ route('admin.ai.tasks.show', $t) }}" class="link min-w-0 truncate">{{ $t->title }}</a>@include('admin.ai.partials.status', ['status' => $t->status, 'label' => $t->statusLabel()])</li>
                    @empty
                        <li class="py-2 text-muted">None yet.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Refused or failed tool calls</h2>
                <ul class="mt-2 space-y-2 text-[13px]">
                    @forelse ($recentProblems as $c)
                        <li><span class="font-semibold">{{ $c->tool }}</span> @include('admin.ai.partials.status', ['status' => $c->status])<br><span class="text-muted">{{ \Illuminate\Support\Str::limit($c->error, 120) }}</span></li>
                    @empty
                        <li class="text-muted">None.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Version history</h2>
                <ul class="mt-2 space-y-2 text-[13px]">
                    @foreach ($versions as $v)
                        <li><span class="font-semibold">v{{ $v->version }}</span> · {{ $v->created_at?->localTime()->format('j M Y, H:i') }} · {{ $v->changer?->name ?? 'System' }}<br><span class="text-muted">{{ $v->change_note }}</span></li>
                    @endforeach
                </ul>
            </section>
            <section class="card card-body">
                <h2 class="text-base font-bold">Agent memory</h2>
                <ul class="mt-2 space-y-2 text-[13px]">
                    @forelse ($memories as $m)
                        <li><span class="font-semibold">{{ $m->key }}</span> @include('admin.ai.partials.status', ['status' => $m->status]) <br><span class="text-muted">{{ \Illuminate\Support\Str::limit($m->content, 140) }}</span></li>
                    @empty
                        <li class="text-muted">No long-term memory. Proposed memories appear in Memory &amp; Knowledge for approval.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
