<x-layouts.dashboard title="AI Usage & Cost" section="admin" heading="Usage & Cost" subheading="Token use reported by Google for every model call (thinking tokens count as output). Estimated cost uses the prices you enter below, else Google's published prices; on the free tier Google does not charge, and the estimate shows the paid-tier equivalent.">
    @include('admin.ai.partials.nav')

    @if ($controls['emergency_stop'])
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status"><strong>Emergency AI stop is on.</strong> No AI requests are being made. The portal and data-only reports keep working.</div>
    @endif
    @foreach ($quota as $key => $block)
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status"><strong>{{ $labels[$key] ?? $key }} requests paused.</strong> {{ $block['reason'] }}
            {{ $block['until'] ? 'Resumes at '.\Carbon\CarbonImmutable::parse($block['until'])->setTimezone(config('meezan.timezone'))->format('j M Y, H:i').'.' : 'Test the connection in Gemini Settings once fixed.' }}</div>
    @endforeach
    @if ($failures->isNotEmpty())
        <p class="mb-4 text-[13px] text-muted">Failed requests this month by reason: {{ $failures->map(fn ($n, $f) => str_replace('_', ' ', $f).' '.$n)->implode(' · ') }}.</p>
    @endif

    <section class="card card-body min-w-0">
        <h2 class="text-base font-bold">By provider</h2>
        <div class="relative mt-2 overflow-x-auto">
            <table class="table table-compact min-w-[860px]">
                <thead><tr><th scope="col">Provider</th><th scope="col">Period</th><th scope="col">Requests</th><th scope="col">Succeeded</th><th scope="col">Failed</th><th scope="col">Input tokens</th><th scope="col">Output tokens</th><th scope="col">Total tokens</th><th scope="col">Cost (USD)</th><th scope="col">From fallback</th></tr></thead>
                <tbody>
                    @foreach ($labels as $key => $label)
                        @foreach (['Today' => $today[$key] ?? null, 'This month' => $month[$key] ?? null] as $period => $s)
                            <tr>
                                <td class="font-semibold">{{ $period === 'Today' ? $label : '' }}</td><td class="text-[13px]">{{ $period }}</td>
                                <td class="tabular-nums">{{ number_format($s->requests ?? 0) }}</td><td class="tabular-nums">{{ number_format($s->succeeded ?? 0) }}</td><td class="tabular-nums">{{ number_format(($s->requests ?? 0) - ($s->succeeded ?? 0)) }}</td>
                                <td class="tabular-nums">{{ number_format($s->tin ?? 0) }}</td><td class="tabular-nums">{{ number_format($s->tout ?? 0) }}</td><td class="tabular-nums">{{ number_format(($s->tin ?? 0) + ($s->tout ?? 0)) }}</td>
                                <td class="tabular-nums">{{ isset($s->cost) ? number_format($s->cost, 4) : '—' }}@if ($s->unpriced ?? 0) <span class="text-[12px] text-muted">({{ $s->unpriced }} unpriced)</span>@endif</td>
                                <td class="tabular-nums text-[13px]">{{ $s->fallbacks ?? 0 }}@if ($s->fallback_cost ?? 0) · {{ number_format($s->fallback_cost, 4) }}@endif</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.ai.controls') }}" class="card card-body min-w-0 space-y-3">
            @csrf @method('PATCH')
            <h2 class="text-base font-bold">Budgets and limits</h2>
            <label class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm"><input type="hidden" name="emergency_stop" value="0"><input type="checkbox" name="emergency_stop" value="1" class="mt-0.5" @checked($controls['emergency_stop'])>
                <span><span class="font-semibold text-red-800">Emergency stop</span><br><span class="text-[13px] text-red-800">Stops every paid AI request at once. The portal and data-only reports keep working.</span></span></label>
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field name="monthly_total_usd" type="number" step="0.01" min="0" label="Monthly total (USD)" :value="$controls['monthly_total_usd']" placeholder="No limit" />
                <x-field name="daily_requests_total" type="number" min="1" label="Requests per day" :value="$controls['daily_requests_total']" placeholder="No limit" />
                <x-field name="max_tokens_per_request" type="number" min="1" label="Max tokens per request" :value="$controls['max_tokens_per_request']" placeholder="No limit" />
            </div>
            <p class="text-[13px] text-muted">Spent this month: {{ $monthSpend !== null ? 'USD '.number_format($monthSpend, 4) : 'not calculated' }}. Gemini's monthly spending limit is set in <a href="{{ route('admin.ai.providers') }}" class="link">Gemini Settings</a>; each agent's limits on its own page. While a monthly budget is set, a model with no price below cannot be used, because its spending could not be controlled. When a budget runs out, paid requests stop and agents report from data only.</p>
            <button class="btn btn-primary btn-sm">Save limits</button>
        </form>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Other limits</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-[13px]">
                @foreach (['max_tool_calls' => 'Tool calls per task', 'max_depth' => 'Delegation depth', 'max_subtasks' => 'Subtasks per task', 'max_attempts' => 'Attempts per task', 'max_seconds' => 'Seconds per task', 'max_tokens_per_task' => 'Tokens per task', 'daily_tokens_per_agent' => 'Tokens per agent per day', 'daily_tokens_total' => 'Tokens per day (all agents)', 'daily_cost_total_usd' => 'Cost per day (USD)', 'approval_expiry_days' => 'Approval expiry (days)'] as $key => $label)
                    <dt class="text-muted">{{ $label }}</dt><dd class="tabular-nums">{{ $limits[$key] ?? 'not set' }}</dd>
                @endforeach
            </dl>
            <p class="mt-3 text-[13px] text-muted">These are set in the environment (MEEZAN_AGENT_* variables).</p>
            <h3 class="mt-5 text-sm font-bold">Tool calls (30 days)</h3>
            <p class="mt-1 text-[13px]">{{ $toolCalls->map(fn ($n, $s) => "{$s} {$n}")->implode(' · ') ?: 'None' }}</p>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Cost per agent (30 days)</h2>
            <div class="relative mt-2 overflow-x-auto">
                <table class="table table-compact min-w-[520px]">
                    <thead><tr><th scope="col">Agent</th><th scope="col">Calls</th><th scope="col">Tokens in / out</th><th scope="col">Cost (USD)</th><th scope="col">Errors</th></tr></thead>
                    <tbody>
                        @forelse ($byAgent as $r)
                            <tr><td>{{ $r->agent }}</td><td class="tabular-nums">{{ $r->calls }}</td><td class="tabular-nums">{{ number_format($r->tin) }} / {{ number_format($r->tout) }}</td>
                                <td class="tabular-nums">{{ $r->cost !== null ? number_format($r->cost, 4) : '—' }}@if ($r->unpriced) <span class="text-[12px] text-muted">({{ $r->unpriced }} unpriced)</span>@endif</td><td class="tabular-nums">{{ $r->errors }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No model calls in the last 30 days{{ $anyProvider ? '' : ' (no language model is configured; agents run in rules mode)' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Most expensive tasks (30 days)</h2>
            <ul class="mt-2 divide-y divide-line text-sm">
                @forelse ($topTasks as $t)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">@if ($t->task)<a href="{{ route('admin.ai.tasks.show', $t->task) }}" class="link min-w-0 max-w-full truncate">{{ $t->task->title }}</a>@else<span class="text-muted">Deleted task</span>@endif <span class="text-[13px] tabular-nums text-muted">{{ number_format($t->tokens) }} tokens · {{ $t->cost !== null ? 'USD '.number_format($t->cost, 4) : 'not priced' }}</span></li>
                @empty
                    <li class="py-2 text-muted">None.</li>
                @endforelse
            </ul>
        </section>

        <section class="card card-body min-w-0 xl:col-span-2">
            <h2 class="text-base font-bold">Daily use (14 days)</h2>
            <div class="relative mt-2 overflow-x-auto">
                <table class="table table-compact min-w-[520px]">
                    <thead><tr><th scope="col">Day</th><th scope="col">Provider</th><th scope="col">Requests</th><th scope="col">Tokens</th><th scope="col">Cost (USD)</th></tr></thead>
                    <tbody>
                        @forelse ($daily as $d)
                            <tr><td class="text-[13px] whitespace-nowrap">{{ $d->day }}</td><td class="text-[13px]">{{ $labels[$d->provider] ?? $d->provider }}</td><td class="tabular-nums">{{ number_format($d->requests) }}</td><td class="tabular-nums">{{ number_format($d->tokens) }}</td><td class="tabular-nums">{{ $d->cost !== null ? number_format($d->cost, 4) : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">None.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <form method="POST" action="{{ route('admin.ai.usage.pricing') }}" class="card card-body mt-6">
        @csrf @method('PUT')
        <h2 class="text-base font-bold">Model pricing (USD per 1M tokens)</h2>
        <p class="mt-1 text-[13px] text-muted">Prices you enter here take priority. Without one, Google's published price (checked {{ config('services.gemini.pricing_checked_on') }}) is used: {{ collect($published)->map(fn ($p, $m) => $m.' '.$p['input'].' / '.$p['output'])->implode('; ') ?: 'none' }}. Check Google's pricing page when prices change (3.8 and 3.6 Flash prices rise on 1 January 2027, which is already configured). Leave a row empty to remove it.</p>
        @php $rows = collect($pricing)->map(fn ($p, $m) => ['model' => $m] + $p)->values()->all(); $rows = array_pad($rows, count($rows) + 2, ['model' => '', 'input' => '', 'output' => '']); @endphp
        <div class="mt-3 space-y-2">
            @foreach ($rows as $i => $row)
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="sr-only" for="pm-{{ $i }}">Model</label><input id="pm-{{ $i }}" name="pricing[{{ $i }}][model]" class="input" value="{{ $row['model'] }}" placeholder="model name">
                    <label class="sr-only" for="pi-{{ $i }}">Input price</label><input id="pi-{{ $i }}" name="pricing[{{ $i }}][input]" class="input" type="number" step="0.0001" min="0" value="{{ $row['input'] }}" placeholder="input per 1M">
                    <label class="sr-only" for="po-{{ $i }}">Output price</label><input id="po-{{ $i }}" name="pricing[{{ $i }}][output]" class="input" type="number" step="0.0001" min="0" value="{{ $row['output'] }}" placeholder="output per 1M">
                </div>
            @endforeach
        </div>
        <button class="btn btn-primary mt-4">Save pricing</button>
    </form>

    <section class="card card-body mt-6 min-w-0">
        <h2 class="text-base font-bold">Recent model calls</h2>
        <div class="relative mt-2 overflow-x-auto">
            <table class="table table-compact min-w-[760px]">
                <thead><tr><th scope="col">Time</th><th scope="col">Agent</th><th scope="col">Purpose</th><th scope="col">Provider</th><th scope="col">Model</th><th scope="col">Tokens</th><th scope="col">Cost</th><th scope="col">Result</th></tr></thead>
                <tbody>
                    @forelse ($records as $u)
                        <tr><td class="text-[13px] whitespace-nowrap">{{ $u->created_at?->localTime()->format('j M, H:i') }}</td><td class="text-[13px]">{{ $u->agent?->name ?? 'General' }}</td><td class="text-[13px]">{{ $u->purpose }}</td>
                            <td class="text-[13px]">{{ $labels[$u->provider] ?? $u->provider }}@if ($u->fallback_from)<br><span class="text-amber-800">fallback from {{ $labels[$u->fallback_from] ?? $u->fallback_from }}</span>@endif</td><td class="text-[13px]">{{ $u->model }}</td>
                            <td class="tabular-nums">{{ number_format($u->tokens_in + $u->tokens_out) }}</td><td class="tabular-nums text-[13px]">{{ $u->cost_usd !== null ? number_format($u->cost_usd, 6) : '—' }}</td><td class="text-[13px]">{{ $u->ok ? 'ok' : $u->error }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="text-muted">None.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.dashboard>
