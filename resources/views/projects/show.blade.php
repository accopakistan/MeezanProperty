<x-layouts.app :title="$project->name.' — '.$project->city" :description="\Illuminate\Support\Str::limit($project->description, 155)">
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['New Projects', route('projects.index')], [$project->name, null]]" />
        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <img src="{{ $project->coverUrl() }}" alt="{{ $project->name }}" class="aspect-[16/9] w-full rounded-[var(--radius-card)] object-cover">
            <div>
                <span class="tag {{ $project->status === 'completed' ? 'bg-emerald-600' : 'bg-brand-600' }}">{{ $project->statusLabel() }}</span>
                <h1 class="mt-3 text-3xl">{{ $project->name }}</h1>
                <p class="mt-1 text-muted">{{ collect([$project->area?->name, $project->city])->filter()->implode(', ') }}</p>
                @if ($project->priceRange())<p class="mt-4 font-serif text-2xl font-bold text-brand-600">{{ $project->priceRange() }}</p><p class="text-xs text-muted">Indicative prices provided by the developer.</p>@endif
                <dl class="mt-5 space-y-2 text-sm">
                    @if ($project->developer)<div class="flex gap-3"><dt class="w-32 text-muted">Developer</dt><dd class="font-medium">{{ $project->developer }}</dd></div>@endif
                    @if ($project->approval_note)<div class="flex gap-3"><dt class="w-32 text-muted">Approval status</dt><dd class="font-medium">{{ $project->approval_note }} <span class="block text-xs font-normal text-muted">As stated by the developer; verify with the approving authority.</span></dd></div>@endif
                    @if ($project->website)<div class="flex gap-3"><dt class="w-32 text-muted">Website</dt><dd><a href="{{ $project->website }}" target="_blank" rel="noopener nofollow" class="link">{{ parse_url($project->website, PHP_URL_HOST) }}</a></dd></div>@endif
                </dl>
            </div>
        </div>
        <section class="card card-body mt-6">
            <h2 class="font-serif text-xl font-bold">About this project</h2>
            <p class="mt-3 text-[15px] leading-7 whitespace-pre-line text-ink/85">{{ $project->description }}</p>
        </section>
        @if ($listings->isNotEmpty())
            <section class="mt-10">
                <h2 class="section-title text-2xl">Listings in {{ $project->area->name }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach ($listings as $property)<x-property-card :property="$property" />@endforeach</div>
            </section>
        @endif
    </div>
</x-layouts.app>
