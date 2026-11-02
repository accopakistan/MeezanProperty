@php
    use App\Enums\PipelineStage;
    $columnCounts = collect(PipelineStage::columns())->map(fn ($c, $key) => collect(PipelineStage::cases())->filter(fn ($s) => $s->column() === $key)->sum(fn ($s) => $stageCounts[$s->value] ?? 0));
    $total = max(1, $columnCounts->sum());
@endphp
<x-layouts.dashboard title="Lead CRM" section="dealer" heading="Lead CRM" subheading="Your leads, follow-ups and viewings in one place. Everything here is private to your agency.">
    @include('dealer.crm._nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="New leads to contact" :value="$newLeads" icon="inbox" :href="route('dealer.crm.leads', ['stage' => 'new'])" />
        <x-stat-card label="Follow-ups due today" :value="$dueToday" icon="clock" tone="gold" :href="route('dealer.crm.leads', ['follow_up' => 'due_today'])" />
        <x-stat-card label="Overdue follow-ups" :value="$overdue" icon="alert" :tone="$overdue ? 'danger' : 'success'" :href="route('dealer.crm.agenda')" />
        <x-stat-card label="Viewings today" :value="$viewingsToday" icon="calendar" tone="info" :href="route('dealer.crm.agenda')" />
        <x-stat-card label="Need attention" :value="$stale" icon="target" :tone="$stale ? 'warning' : 'success'" :href="route('dealer.crm.leads', ['stale' => 1])" :hint="'No activity for '.config('meezan.crm.stale_after_days').'+ days'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <section class="card card-body" aria-labelledby="stages-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="stages-title" class="font-bold">Your pipeline</h2>
                <a href="{{ route('dealer.crm.pipeline') }}" class="text-sm font-semibold text-brand-700 hover:underline">Open board</a>
            </div>
            <div class="mt-4 space-y-3">
                @foreach (PipelineStage::columns() as $key => $column)
                    <a href="{{ route('dealer.crm.pipeline') }}#col-{{ $key }}" class="block">
                        <div class="flex justify-between text-sm"><span>{{ $column['label'] }}</span><span class="font-semibold tabular-nums">{{ $columnCounts[$key] }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-page"><div @class(['h-full rounded-full', 'bg-gold-500' => $key === 'won', 'bg-zinc-300' => $key === 'lost', 'bg-brand-600' => ! in_array($key, ['won', 'lost'], true)]) style="width: {{ $columnCounts[$key] ? max(3, round($columnCounts[$key] / $total * 100)) : 0 }}%"></div></div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="card card-body" aria-labelledby="perf-title">
            <h2 id="perf-title" class="font-bold">Last 30 days</h2>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                @foreach (['Received' => $performance['received'], 'Contacted' => $performance['contacted'], 'Qualified' => $performance['qualified'], 'Viewings' => $performance['viewings'], 'Won' => $performance['won'], 'Lost' => $performance['lost']] as $label => $value)
                    <div class="rounded-xl bg-page p-3"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="text-2xl font-bold tabular-nums">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-muted">
                Contact rate: {{ $performance['contact_rate'] !== null ? $performance['contact_rate'].'%' : 'not enough leads yet' }} ·
                Follow-ups done on time: {{ $performance['followup_rate'] !== null ? $performance['followup_rate'].'%' : 'not enough follow-ups yet' }}.
                Counted from leads assigned to you in the last 30 days; “contacted” and “qualified” mean the lead reached that stage at least once.
            </p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden" aria-labelledby="today-title">
            <h2 id="today-title" class="card-body pb-0 font-bold">Today’s appointments</h2>
            @forelse ($todayAppointments as $appointment)
                <a href="{{ route('dealer.crm.leads.show', $appointment->lead_assignment_id) }}" class="flex items-start gap-3 border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-page/60">
                    <span class="w-14 shrink-0 font-bold tabular-nums text-brand-700">{{ $appointment->starts_at->localTime()->format('g:i a') }}</span>
                    <span class="min-w-0"><span class="block font-semibold">{{ $appointment->typeLabel() }}</span><span class="block truncate text-xs text-muted">{{ $appointment->property?->title ?? $appointment->location ?? $appointment->assignment->lead?->area?->name }}</span></span>
                </a>
            @empty
                <p class="card-body pt-3 text-sm text-muted">Nothing scheduled today.</p>
            @endforelse
        </section>

        <section class="card overflow-hidden" aria-labelledby="fu-title">
            <div class="card-body flex items-center justify-between pb-0"><h2 id="fu-title" class="font-bold">Next follow-ups</h2><a href="{{ route('dealer.crm.agenda') }}" class="text-sm font-semibold text-brand-700 hover:underline">Agenda</a></div>
            @forelse ($upcomingFollowUps as $followUp)
                <a href="{{ route('dealer.crm.leads.show', $followUp->lead_assignment_id) }}" class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-page/60">
                    <span class="min-w-0"><span class="block font-semibold">{{ $followUp->typeLabel() }}</span><span class="block truncate text-xs text-muted">{{ $followUp->notes ?: ($followUp->assignment->lead?->area?->name ?? $followUp->assignment->lead?->requirement?->city) }}</span></span>
                    <span @class(['shrink-0 text-xs font-semibold', 'text-red-600' => $followUp->isOverdue(), 'text-muted' => ! $followUp->isOverdue()])>{{ $followUp->isOverdue() ? 'Overdue · ' : '' }}{{ $followUp->due_at->localTime()->format('j M, g:i a') }}</span>
                </a>
            @empty
                <p class="card-body pt-3 text-sm text-muted">No follow-ups scheduled.</p>
            @endforelse
        </section>

        <section class="card overflow-hidden" aria-labelledby="act-title">
            <h2 id="act-title" class="card-body pb-0 font-bold">Recent activity</h2>
            @forelse ($activity as $entry)
                <a href="{{ route('dealer.crm.leads.show', $entry->lead_assignment_id) }}" class="block border-b border-line px-5 py-2.5 text-sm last:border-0 hover:bg-page/60">
                    <span class="font-semibold">{{ $entry->label() }}</span>
                    @if ($entry->type === 'stage_changed')<span class="text-muted"> → {{ \App\Enums\PipelineStage::tryFrom($entry->properties['to'] ?? '')?->label() }}</span>@endif
                    <span class="block text-xs text-muted">{{ $entry->created_at->diffForHumans() }}{{ $entry->actor ? ' · '.$entry->actor->name : ' · automatic' }}</span>
                </a>
            @empty
                <p class="card-body pt-3 text-sm text-muted">No activity yet.</p>
            @endforelse
        </section>
    </div>
</x-layouts.dashboard>
