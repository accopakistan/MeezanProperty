@php
    use App\Enums\AreaUnit;
    use App\Support\Lookup;
    $editing = $requirement->exists;
    $marla = (int) config('meezan.marla_sqft', 225);
    $selectedAreas = \App\Models\Area::whereIn('id', array_map('intval', (array) old('area_ids', $requirement->area_ids ?? [])))->get(['id', 'name', 'city']);
@endphp
<x-layouts.dashboard :title="$editing ? 'Edit requirement' : 'New requirement'" section="account" :heading="$editing ? 'Edit requirement' : 'What are you looking for?'" subheading="Budget and property type are treated as must-haves when we match listings.">
    <form method="POST" action="{{ $editing ? route('account.requirements.update', $requirement) : route('account.requirements.store') }}" class="grid max-w-4xl gap-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card card-body space-y-5">
            <x-field name="title" label="Name this search (optional)" :value="$requirement->title" placeholder="e.g. Family home near school" />
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="purpose" type="select" label="Looking to" required :value="$requirement->purpose?->value" :options="['sale' => 'Buy', 'rent' => 'Rent']" />
                <x-field name="type" type="select" label="Property type" :value="$requirement->type?->value">
                    <option value="">Any type</option>
                    @foreach (Lookup::typesByGroup() as $label => $types)
                        <optgroup label="{{ $label }}">
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}" @selected(old('type', $requirement->type?->value) === $t->value)>{{ $t->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-field>
                <x-field name="city" type="select" label="City" required :value="$requirement->city" :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
            </div>

            <x-area-multi-picker name="area_ids" :max="5" :city="old('city', $requirement->city)" :selected="$selectedAreas" :suggestions="$suggestions" />
        </section>

        <section class="card card-body space-y-5">
            <h2 class="text-base font-bold">Budget & size</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="min_price" type="number" min="0" step="100000" label="Minimum budget (PKR)" :value="$requirement->min_price" />
                <x-field name="max_price" type="number" min="0" step="100000" label="Maximum budget (PKR)" :value="$requirement->max_price" hint="Listings above this are excluded." />
            </div>
            <div class="grid gap-5 sm:grid-cols-[1fr_1fr_auto_1fr]">
                <x-field name="min_size" type="number" min="0" step="0.5" label="Min size" :value="$requirement->min_area_sqft ? round($requirement->min_area_sqft / $marla, 2) : null" />
                <x-field name="max_size" type="number" min="0" step="0.5" label="Max size" :value="$requirement->max_area_sqft ? round($requirement->max_area_sqft / $marla, 2) : null" />
                <x-field name="size_unit" type="select" label="Unit" :value="old('size_unit', 'marla')" :options="collect(AreaUnit::cases())->mapWithKeys(fn ($u) => [$u->value => $u->label()])->all()" />
                <x-field name="bedrooms" type="select" label="Bedrooms" :value="$requirement->bedrooms" placeholder="Any" :options="[1 => '1+', 2 => '2+', 3 => '3+', 4 => '4+', 5 => '5+', 6 => '6+']" />
            </div>
            <x-field name="notes" type="textarea" rows="3" label="Anything else?" :value="$requirement->notes" placeholder="e.g. Corner plot preferred, near a park, ready to move" />
        </section>

        <section class="card card-body">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="share_with_dealers" value="1" class="checkbox mt-1" @checked(old('share_with_dealers'))>
                <span>
                    <span class="block text-sm font-semibold">Share this requirement with up to 3 verified dealers in my areas</span>
                    <span class="mt-0.5 block text-[13px] text-muted">They’ll see your name, email and phone so they can contact you. Leave unticked to keep it private and only see recommendations.</span>
                </span>
            </label>
            @if ($editing)
                <label class="mt-4 flex items-center gap-3 text-sm"><input type="checkbox" name="closed" value="1" class="checkbox" @checked($requirement->status === 'closed')> Mark this requirement as closed</label>
            @endif
        </section>

        <div class="flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Save & see matches' }}</button>
            <a href="{{ route('account.requirements.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</x-layouts.dashboard>
