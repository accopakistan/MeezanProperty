@php
    $isObs = $type === 'observations';
    $editable = ! $record->exists || $record->isEditable();
    $indicators = config('meezan.investment.indicators');
    $levelOptions = collect($indicators)->flatMap(fn ($d, $k) => collect($d['levels'] ?? [])->keys()->mapWithKeys(fn ($l) => [$l => ucwords(str_replace('_', ' ', $l))]))->all();
    $title = ($record->exists ? ($isObs ? 'Observation' : 'Indicator').' #'.$record->id : 'New '.($isObs ? 'observation' : 'indicator'));
    $actionLabels = ['submit' => 'Submit for review', 'review' => 'Mark reviewed', 'verify' => 'Verify', 'reject' => 'Reject', 'reopen' => 'Reopen for correction'];
@endphp
<x-layouts.dashboard :title="$title" section="admin" :heading="$title" :subheading="$record->exists ? 'Status: '.$record->statusLabel().($record->rejection_reason ? ' — '.$record->rejection_reason : '') : 'Saved as a draft. Only verified records are used in calculations.'">
    @include('admin.market.partials.nav')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form method="POST" action="{{ $record->exists ? route('admin.market.records.update', [$type, $record->id]) : route('admin.market.records.store', $type) }}" class="card card-body space-y-4">
            @csrf @if ($record->exists) @method('PATCH') @endif
            <fieldset @disabled(! $editable) class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="city" type="select" label="City" required :value="$record->city" placeholder="Choose" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
                    <div>
                        <label for="area_slug" class="label">Area / society <span class="font-normal text-muted">(optional)</span></label>
                        <input id="area_slug" name="area_slug" class="input" list="area-options" value="{{ old('area_slug', $record->area?->slug) }}" placeholder="Type to search; blank = whole city">
                        <datalist id="area-options">@foreach (\App\Models\Area::active()->whereIn('city', config('meezan.cities'))->orderBy('city')->orderBy('name')->get(['slug', 'name', 'city']) as $a)<option value="{{ $a->slug }}">{{ $a->name }}, {{ $a->city }}</option>@endforeach</datalist>
                        @error('area_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <x-field name="project_slug" type="select" label="Project (optional)" placeholder="No project" :value="old('project_slug', $record->project?->slug)" :options="\App\Models\Project::orderBy('name')->pluck('name', 'slug')->all()" />
                </div>
                @if ($isObs)
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field name="kind" type="select" label="Kind" required :value="$record->kind" placeholder="Choose" :options="\App\Models\MarketObservation::KINDS" />
                        <x-field name="price_basis" type="select" label="Price basis" required :value="$record->price_basis" placeholder="Choose" :options="config('meezan.investment.price_bases')" hint="Asking prices and transactions are never mixed." />
                        <x-field name="asset_class" type="select" label="Asset class" required :value="$record->asset_class" :options="['residential' => 'Residential', 'commercial' => 'Commercial']" />
                        <x-field name="property_type" type="select" label="Property type" required :value="$record->property_type" placeholder="Choose" :options="collect(\App\Enums\PropertyType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                        <x-field name="size_value" type="number" label="Size" :value="$record->size_value" step="0.01" />
                        <x-field name="size_unit" type="select" label="Size unit" placeholder="—" :value="$record->size_unit" :options="collect(\App\Enums\AreaUnit::cases())->mapWithKeys(fn ($u) => [$u->value => $u->label()])->all()" />
                        <x-field name="bedrooms" type="number" label="Bedrooms (apartments; 0 = studio)" :value="$record->bedrooms" min="0" max="10" />
                        <x-field name="average_amount" type="number" label="Average (PKR; rent per month)" required :value="$record->average_amount" min="1" />
                        <x-field name="min_amount" type="number" label="Lowest (optional)" :value="$record->min_amount" min="1" />
                        <x-field name="max_amount" type="number" label="Highest (optional)" :value="$record->max_amount" min="1" />
                        <x-field name="sample_size" type="number" label="Sample size" required :value="$record->sample_size" min="1" />
                        <input type="hidden" name="currency" value="PKR">
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field name="indicator" type="select" label="Indicator" required :value="$record->indicator" placeholder="Choose" :options="collect($indicators)->map(fn ($d) => $d['label'])->all()" />
                        <x-field name="level" type="select" label="Level" :value="$record->level" placeholder="— (use % for development progress)" :options="$levelOptions" />
                        <x-field name="numeric_value" type="number" label="Development progress %" :value="$record->numeric_value" step="0.1" min="0" max="100" />
                        <x-field name="asset_class" type="select" label="Asset class" placeholder="Both" :value="$record->asset_class" :options="['residential' => 'Residential', 'commercial' => 'Commercial']" />
                    </div>
                    <p class="text-xs text-muted">Levels: @foreach ($indicators as $k => $d)@if (! empty($d['levels']))<strong>{{ $d['label'] }}</strong> {{ implode(', ', array_map(fn ($l) => str_replace('_', ' ', $l), array_keys($d['levels']))) }}; @endif @endforeach</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="observed_on" type="date" label="Observation date" required :value="$record->observed_on?->toDateString()" />
                    <x-field name="source_id" type="select" label="Source" required :value="$record->source_id" placeholder="Choose a registered source" :options="$sources->mapWithKeys(fn ($s) => [$s->id => $s->name.' ('.$s->typeLabel().')'])->all()" />
                </div>
                <x-field name="methodology" type="textarea" label="Methodology" required rows="3" :value="$record->methodology" hint="How the figure or level was collected or decided, including the evidence." />
                <x-field name="notes" type="textarea" label="Notes" rows="2" :value="$record->notes" />
            </fieldset>
            @if ($editable)<button class="btn btn-primary">{{ $record->exists ? 'Save correction' : 'Save draft' }}</button>@endif
            @if (! $sources->count())<p class="text-sm text-amber-800">Add a source first: every record must cite one.</p>@endif
        </form>

        <div class="space-y-6">
            @if ($record->exists)
                <section class="card card-body">
                    <h2 class="text-base font-bold">Review</h2>
                    <p class="mt-1 text-[13px] text-muted">Draft → Submitted → Reviewed → Verified. Rejected records go back for correction.</p>
                    <dl class="mt-3 space-y-1 text-[13px]">
                        <div class="flex justify-between"><dt class="text-muted">Entered</dt><dd>{{ $record->created_at?->localTime()->format('j M Y') }} · {{ $record->creator?->name }}</dd></div>
                        @if ($record->verified_at)<div class="flex justify-between"><dt class="text-muted">Verified</dt><dd>{{ $record->verified_at->localTime()->format('j M Y') }} · {{ $record->verifier?->name }}</dd></div>@endif
                    </dl>
                    <div class="mt-4 space-y-2">
                        @foreach ($record->allowedActions() as $action)
                            <form method="POST" action="{{ route('admin.market.records.transition', [$type, $record->id]) }}" class="space-y-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="{{ $action }}">
                                @if ($action === 'reject')<x-field name="reason" label="Reason for rejection" />@endif
                                <button @class(['btn btn-sm w-full', 'btn-primary' => in_array($action, ['verify', 'review', 'submit'], true), 'btn-secondary' => ! in_array($action, ['verify', 'review', 'submit'], true)])>{{ $actionLabels[$action] }}</button>
                            </form>
                        @endforeach
                    </div>
                    @error('action')<p class="field-error mt-2">{{ $message }}</p>@enderror
                </section>
                @if ($history->isNotEmpty())
                    <section class="card card-body">
                        <h2 class="text-base font-bold">History</h2>
                        <ul class="mt-3 space-y-1.5 text-[13px]">@foreach ($history as $log)<li><span class="font-medium">{{ $log->actionLabel() }}</span> <span class="text-muted">· {{ $log->user?->name ?? 'System' }} · {{ $log->created_at->localTime()->format('j M Y, g:i a') }}</span></li>@endforeach</ul>
                    </section>
                @endif
            @endif
        </div>
    </div>
</x-layouts.dashboard>
