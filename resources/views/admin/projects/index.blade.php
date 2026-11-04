<x-layouts.dashboard title="New projects" section="admin" heading="New Projects" subheading="Housing schemes and developments shown on the public Projects page.">
    <x-slot:actions><a href="{{ route('admin.projects.import') }}" class="btn btn-secondary"><x-icon name="upload" class="size-4" /> Import CSV</a> <a href="{{ route('admin.projects.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add project</a></x-slot:actions>
    @if ($projects->isEmpty())
        <x-empty-state icon="building" title="No projects yet">Add a development once its details have been checked with the developer.</x-empty-state>
    @else
        <div class="card overflow-hidden">
            <table class="table">
                <thead><tr><th>Project</th><th>Location</th><th>Category</th><th>Status</th><th>Published</th><th class="sr-only">Edit</th></tr></thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr>
                            <td><span class="font-semibold">{{ $project->name }}</span>@if ($project->is_demo) <span class="badge bg-gold-500 px-1.5 py-0 text-[10px] font-extrabold text-brand-900">DEMO</span>@endif<span class="block text-xs text-muted">{{ $project->developer }}</span></td>
                            <td class="text-muted">{{ collect([$project->area?->name, $project->city])->filter()->implode(', ') }}</td>
                            <td class="text-[13px]">{{ $project->categoryLabel() ?? '—' }}</td>
                            <td>{{ $project->statusLabel() }}</td>
                            <td><x-status-badge :tone="$project->is_published ? 'success' : 'neutral'" :label="$project->is_published ? 'Published' : 'Hidden'" /></td>
                            <td class="text-right"><a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $projects->links() }}</div>
    @endif
</x-layouts.dashboard>
