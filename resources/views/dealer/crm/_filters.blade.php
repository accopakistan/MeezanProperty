@php use App\Enums\PipelineStage; use App\Enums\PropertyType; use App\Enums\Purpose; @endphp
{{-- Filters run in the database; the page only receives the leads it shows. --}}
<details class="card mb-6" @if (array_filter($filters)) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3.5 text-sm font-semibold">
        <span class="flex items-center gap-2"><x-icon name="sliders" class="size-4" /> Filters @if ($n = count(array_filter($filters)))<span class="badge bg-brand-50 text-brand-700">{{ $n }} active</span>@endif</span>
        <x-icon name="chevron-down" class="size-4 text-muted" />
    </summary>
    <form method="GET" class="grid gap-3 border-t border-line p-5 sm:grid-cols-2 lg:grid-cols-4">
        @if ($showStage ?? true)
            <x-field name="stage" type="select" label="Stage" :value="$filters['stage'] ?? null" placeholder="Any stage" :options="collect(PipelineStage::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        @endif
        <x-field name="outcome" type="select" label="Outcome" :value="$filters['outcome'] ?? null" placeholder="All" :options="['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost']" />
        <x-field name="priority" type="select" label="Priority" :value="$filters['priority'] ?? null" placeholder="Any priority" :options="config('meezan.crm.priorities')" />
        <x-field name="follow_up" type="select" label="Follow-ups" :value="$filters['follow_up'] ?? null" placeholder="Any" :options="['due_today' => 'Due today', 'overdue' => 'Overdue', 'none' => 'None scheduled']" />
        <x-field name="city" type="select" label="City" :value="$filters['city'] ?? null" placeholder="Any city" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
        <x-field name="area" type="select" label="Area" :value="$filters['area'] ?? null" placeholder="Any area" :options="$areaOptions->all()" />
        <x-field name="type" type="select" label="Property type" :value="$filters['type'] ?? null" placeholder="Any type" :options="collect(PropertyType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
        <x-field name="purpose" type="select" label="Buy / rent" :value="$filters['purpose'] ?? null" placeholder="Either" :options="collect(Purpose::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()" />
        <x-field name="from" type="date" label="Assigned from" :value="$filters['from'] ?? null" />
        <x-field name="to" type="date" label="Assigned to" :value="$filters['to'] ?? null" />
        <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="stale" value="1" class="checkbox" @checked($filters['stale'] ?? false)> Needs attention only</label>
        <div class="flex items-end gap-2 self-end pb-0.5">
            <button class="btn btn-primary btn-sm">Apply</button>
            <a href="{{ url()->current() }}" class="btn btn-ghost btn-sm">Clear</a>
        </div>
    </form>
</details>
