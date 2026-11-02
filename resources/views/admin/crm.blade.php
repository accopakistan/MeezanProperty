@php use App\Enums\PipelineStage; @endphp
<x-layouts.dashboard title="Lead oversight" section="admin" heading="Lead oversight" subheading="Marketplace leads and dealer pipelines. A lead shared with several dealers has one pipeline per dealer, so stage figures count dealer assignments.">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.leads.index') }}" class="btn btn-secondary btn-sm">All leads</a>
        <div class="ml-auto flex gap-1 rounded-xl border border-line bg-white p-1 text-sm">
            @foreach ([7, 30, 90] as $days)
                <a href="{{ route('admin.crm', ['days' => $days]) }}" @class(['rounded-lg px-3 py-1.5 font-semibold', 'bg-brand-600 text-white' => $period === $days, 'text-muted hover:text-ink' => $period !== $days])>{{ $days }} days</a>
            @endforeach
        </div>
    </div>

    <section class="mb-8" aria-labelledby="totals-title">
        <h2 id="totals-title" class="mb-3 text-base font-bold">Marketplace leads</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Total leads', $totals['leads'], route('admin.leads.index')],
                ['With at least one dealer', $totals['assigned'], route('admin.leads.index', ['status' => 'assigned'])],
                ['No active dealer', $totals['unassigned'], route('admin.leads.index', ['status' => 'unrouted'])],
                ['Held: dealers at lead limit', $totals['held'], route('admin.leads.index', ['status' => 'unrouted'])],
                ['At least one dealer marked won', $totals['anyWon'], null],
                ['Marked spam/duplicate by staff', $totals['invalidated'], null],
                ['Dealer reported invalid/duplicate', $totals['dealerReportedInvalid'], null],
                ['Overdue dealer follow-ups', $totals['overdue'], null],
            ] as [$label, $count, $url])
                @php $inner = '<p class="text-[13px] text-muted">'.e($label).'</p><p class="mt-1 text-3xl font-bold '.($count ? 'text-ink' : 'text-muted').'">'.number_format($count).'</p>'; @endphp
                @if ($url)<a href="{{ $url }}" class="card card-body transition hover:shadow-lift">{!! $inner !!}</a>@else<div class="card card-body">{!! $inner !!}</div>@endif
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">“At least one dealer marked won” is what dealers reported in their own CRM; it is not a verified sale and the lead is not closed for other dealers.</p>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card card-body" aria-labelledby="stage-title">
            <h2 id="stage-title" class="font-bold">Dealer pipeline stages (active assignments)</h2>
            @php $stageTotal = max(1, $stages->sum()); @endphp
            <div class="mt-4 space-y-2.5">
                @foreach ($stageCases as $stage)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $stage->label() }}</span><span class="font-semibold tabular-nums">{{ $stages[$stage->value] ?? 0 }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-page"><div class="h-full rounded-full bg-brand-600" style="width: {{ round(($stages[$stage->value] ?? 0) / $stageTotal * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-muted">{{ $totals['stale'] }} active {{ \Illuminate\Support\Str::plural('assignment', $totals['stale']) }} flagged for no dealer activity in {{ config('meezan.crm.stale_after_days') }}+ days.</p>
        </section>

        <section class="card card-body" aria-labelledby="city-title">
            <h2 id="city-title" class="font-bold">Leads by city</h2>
            @if ($byCity->isEmpty())
                <p class="mt-3 text-sm text-muted">No leads yet.</p>
            @else
                <dl class="mt-4 divide-y divide-line text-sm">
                    @foreach ($byCity as $city => $count)
                        <div class="flex justify-between py-2"><dt>{{ $city ?: 'Not specified' }}</dt><dd class="font-semibold tabular-nums">{{ $count }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>

    <section class="mt-8" aria-labelledby="dealer-title">
        <h2 id="dealer-title" class="mb-1 text-base font-bold">Dealer activity, last {{ $period }} days</h2>
        <p class="mb-3 text-xs text-muted">Operational insight from recorded CRM activity, for leads assigned in the period. Not a ranking, and not shown to dealers or the public. Rates appear once a dealer has at least {{ config('meezan.crm.min_leads_for_rates') }} leads or follow-ups.</p>
        @if ($dealers->isEmpty())
            <x-empty-state icon="users" title="No dealer has received a lead yet" />
        @else
            <div class="card overflow-hidden"><div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Dealer</th><th class="text-right">Received</th><th class="text-right">Contacted</th><th class="text-right">Qualified</th><th class="text-right">Viewings</th><th class="text-right">Won</th><th class="text-right">Lost</th><th class="text-right">Follow-ups done / overdue</th><th class="text-right">Needs attention</th><th>Last activity</th></tr></thead>
                    <tbody>
                        @foreach ($dealers as $dealer)
                            @php $p = $performance[$dealer->id]; @endphp
                            <tr>
                                <td><a href="{{ $dealer->dealerProfile ? route('admin.dealers.show', $dealer->dealerProfile) : '#' }}" class="font-semibold hover:text-brand-700">{{ $dealer->dealerProfile?->agency_name ?? $dealer->name }}</a><p class="text-xs text-muted">{{ $dealer->dealerProfile?->city }}</p></td>
                                <td class="text-right tabular-nums">{{ $p['received'] }}</td>
                                <td class="text-right tabular-nums">{{ $p['contacted'] }}@if ($p['contact_rate'] !== null)<span class="block text-xs text-muted">{{ $p['contact_rate'] }}%</span>@endif</td>
                                <td class="text-right tabular-nums">{{ $p['qualified'] }}</td>
                                <td class="text-right tabular-nums">{{ $p['viewings'] }}</td>
                                <td class="text-right tabular-nums">{{ $p['won'] }}</td>
                                <td class="text-right tabular-nums">{{ $p['lost'] }}</td>
                                <td class="text-right tabular-nums">{{ $p['followups_completed'] }} / {{ $p['followups_overdue'] }}@if ($p['followup_rate'] !== null)<span class="block text-xs text-muted">{{ $p['followup_rate'] }}% on time</span>@endif</td>
                                <td class="text-right tabular-nums">{{ $p['stale'] }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $p['last_activity'] ? \Illuminate\Support\Carbon::parse($p['last_activity'])->diffForHumans() : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div></div>
        @endif
    </section>
</x-layouts.dashboard>
