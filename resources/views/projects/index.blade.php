<x-layouts.app title="New housing projects in Pakistan" description="New housing societies and developments in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, with status, developer and starting prices as provided by the developer.">
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/type-projects.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover opacity-80 md:w-[55%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/20"></div>
        <div class="container-page py-12">
            <x-breadcrumbs :items="[['New Projects', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-6 text-4xl text-white sm:text-5xl">New Projects</h1>
            <p class="mt-2 text-white/85">Housing schemes and developments. Details are provided by developers — always confirm approvals with the relevant authority.</p>
            <x-search-bar class="mt-6 max-w-5xl" />
        </div>
    </section>

    <div class="container-page mt-8">
        <div class="flex gap-1 overflow-x-auto rounded-md border border-line bg-white p-1 scrollbar-none sm:w-max">
            @foreach (['' => 'All'] + \App\Models\Project::STATUSES as $value => $label)
                <a href="{{ route('projects.index', array_filter(['status' => $value])) }}" @class(['shrink-0 rounded px-4 py-2 text-sm font-medium', 'bg-brand-600 text-white' => (string) $status === $value, 'text-muted hover:text-ink' => (string) $status !== $value])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($projects->isEmpty())
            <x-empty-state class="mt-6" icon="building" title="No projects listed yet">
                Our team adds developments after checking the details with the developer. Browse current listings in the meantime.
                <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Browse properties</a></x-slot:actions>
            </x-empty-state>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="card group overflow-hidden transition hover:shadow-lift">
                        <div class="relative">
                            <img src="{{ $project->coverUrl() }}" alt="{{ $project->name }}" loading="lazy" class="aspect-[16/9] w-full object-cover">
                            <span class="tag absolute top-3 left-3 {{ $project->status === 'completed' ? 'bg-emerald-600' : 'bg-brand-600' }}">{{ $project->statusLabel() }}</span>
                        </div>
                        <div class="p-4">
                            <h2 class="font-serif text-lg font-bold group-hover:text-brand-700">{{ $project->name }}</h2>
                            <p class="text-[13px] text-muted">{{ collect([$project->developer, $project->area?->name, $project->city])->filter()->implode(' · ') }}</p>
                            @if ($project->priceRange())<p class="mt-2 font-bold text-brand-600">{{ $project->priceRange() }}</p>@endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $projects->links() }}</div>
        @endif
    </div>
</x-layouts.app>
