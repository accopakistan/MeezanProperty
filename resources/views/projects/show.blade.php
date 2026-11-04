<x-layouts.app :title="$project->name.' — '.$project->city" :description="\Illuminate\Support\Str::limit($project->description, 155)" :robots="$project->is_demo ? 'noindex, nofollow' : null">
    <div class="container-page py-8">
        @if ($project->is_demo)
            <div class="mb-5 flex items-start gap-3 rounded-xl border-2 border-dashed border-gold-500 bg-gold-50 px-4 py-3 text-sm" role="note" aria-label="Illustrative project notice">
                <span class="shrink-0 rounded bg-gold-500 px-2 py-0.5 text-[12px] font-extrabold text-brand-900">Illustrative</span>
                <p><strong>This is a fictional example project.</strong> It does not exist and cannot be bought, rented or contacted. {{ config('meezan.investment.demo_notice') }} Images are illustrations.</p>
            </div>
        @endif
        <x-breadcrumbs :items="[['New Projects', route('projects.index')], [$project->name, null]]" />
        <x-search-bar variant="bar" :city="$project->city" :area="$project->area?->slug" :group="in_array(config('meezan.investment.project_categories.'.$project->category.'.asset_class'), ['commercial', 'mixed'], true) ? 'commercial' : null" class="mt-4" />
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
