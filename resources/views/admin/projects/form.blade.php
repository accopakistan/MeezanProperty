@php $editing = $project->exists; @endphp
<x-layouts.dashboard :title="$editing ? 'Edit project' : 'Add project'" section="admin" :heading="$editing ? 'Edit '.$project->name : 'Add a project'">
    <form method="POST" action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl space-y-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="Project name" :value="$project->name" required />
            <x-field name="developer" label="Developer" :value="$project->developer" />
        </div>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="city" type="select" label="City" :value="$project->city" required :options="array_combine(config('meezan.cities'), config('meezan.cities'))" />
            <x-field name="area_id" type="select" label="Area" :value="$project->area_id" placeholder="Not in a listed area" :options="$areas->pluck('name', 'id')->all()" />
            <x-field name="status" type="select" label="Status" :value="$project->status" required :options="\App\Models\Project::STATUSES" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="min_price" type="number" min="0" label="Starting price (PKR)" :value="$project->min_price" />
            <x-field name="max_price" type="number" min="0" label="Highest price (PKR)" :value="$project->max_price" />
        </div>
        <x-field name="description" type="textarea" rows="6" label="Description" :value="$project->description" required />
        <x-field name="approval_note" label="Approval status (as stated by developer)" :value="$project->approval_note" hint="Shown with a note that visitors should verify it with the approving authority." />
        <x-field name="website" type="url" label="Developer website" :value="$project->website" />
        <div>
            <label class="label" for="cover">Cover image</label>
            <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="text-sm">
            @error('cover')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <input type="hidden" name="is_published" value="0">
        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_published" value="1" class="checkbox" @checked(old('is_published', $project->is_published))> Show on the public Projects page</label>
        <div class="flex gap-2"><button class="btn btn-primary">Save project</button><a href="{{ route('admin.projects.index') }}" class="btn btn-ghost">Cancel</a></div>
    </form>
</x-layouts.dashboard>
