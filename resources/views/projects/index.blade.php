<x-layouts.app title="Project Directory — Real Estate Developments in Pakistan" description="Official factual directory of housing societies, commercial plazas, apartment buildings, shopping malls, and business towers across Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar with verified authority approval status.">
    <section class="relative isolate overflow-hidden bg-brand-900">
        <img src="{{ asset('images/ref/type-projects.jpg') }}" alt="" class="absolute inset-y-0 right-0 -z-20 h-full w-full object-cover opacity-80 md:w-[55%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-brand-900 via-brand-900/85 to-brand-900/20"></div>
        <div class="container-page py-12">
            <x-breadcrumbs :items="[['Project Directory', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-6 text-4xl text-white sm:text-5xl">Pakistan Project Directory</h1>
            <p class="mt-2 max-w-3xl text-white/85">Factual directory profiles of developments across 7 major cities, verified against official authority records (LDA, CDA, FDA, NAB). Factual directory records are distinct from individual property advertisements and investment rankings.</p>
            <x-search-bar class="mt-6 max-w-5xl" />
            
            {{-- Directory Search & Filter Bar --}}
            <form action="{{ route('projects.index') }}" method="GET" class="mt-8 rounded-xl bg-white/95 p-4 shadow-lift backdrop-blur-sm sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Search Input --}}
                    <div>
                        <label for="dir-q" class="text-xs font-semibold uppercase tracking-wider text-muted">Search Development</label>
                        <input type="text" id="dir-q" name="q" value="{{ $searchQuery }}" placeholder="Project or developer name…" class="input mt-1 w-full text-sm">
                    </div>

                    {{-- City Filter --}}
                    <div>
                        <label for="dir-city" class="text-xs font-semibold uppercase tracking-wider text-muted">City</label>
                        <select id="dir-city" name="city" class="input mt-1 w-full text-sm">
                            <option value="">All 7 Cities</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected($selectedCity === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Category Filter --}}
                    <div>
                        <label for="dir-category" class="text-xs font-semibold uppercase tracking-wider text-muted">Category</label>
                        <select id="dir-category" name="category" class="input mt-1 w-full text-sm">
                            <option value="">All 5 Categories</option>
                            @foreach ($categories as $catKey => $catConfig)
                                <option value="{{ $catKey }}" @selected($selectedCategory === $catKey || (isset($catConfig['project_categories']) && in_array($selectedCategory, $catConfig['project_categories'], true)))>{{ $catConfig['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Authority Status Filter --}}
                    <div>
                        <label for="dir-legal" class="text-xs font-semibold uppercase tracking-wider text-muted">Authority Status</label>
                        <select id="dir-legal" name="legal" class="input mt-1 w-full text-sm">
                            <option value="">All Authority Statuses</option>
                            <option value="approved" @selected($selectedLegal === 'approved')>Verified Approved</option>
                            <option value="partially_approved" @selected($selectedLegal === 'partially_approved')>Partially / Technically Approved</option>
                            <option value="pending" @selected($selectedLegal === 'pending')>Approval Pending</option>
                            <option value="cancelled" @selected($selectedLegal === 'cancelled')>Cancelled / Regulatory Warning</option>
                            <option value="unverified" @selected($selectedLegal === 'unverified')>Status Not Verified</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line/60 pt-3">
                    <div class="flex items-center gap-2">
                        @if ($searchQuery || $selectedCity || $selectedCategory || $selectedLegal || $status)
                            <a href="{{ route('projects.index') }}" class="text-xs font-medium text-brand-700 hover:underline">Clear all filters</a>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-6">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <div class="container-page mt-8">
        {{-- Architecture Scope Banner --}}
        <div class="rounded-xl border border-line bg-surface p-4 text-xs text-muted leading-relaxed sm:text-sm">
            <span class="font-bold text-ink">Project Directory Architecture:</span>
            Every real development in Pakistan can be published as a factual directory profile once identified from official authority records or developer disclosures, even if it lacks the operational data required for an investment ranking. A directory profile is <strong>not</strong> an investment endorsement and <strong>not</strong> an individual property advertisement.
        </div>

        {{-- Development Status Secondary Filter --}}
        <div class="mt-6 flex gap-1 overflow-x-auto rounded-md border border-line bg-white p-1 scrollbar-none sm:w-max">
            <a href="{{ route('projects.index', array_filter(['q' => $searchQuery, 'city' => $selectedCity, 'category' => $selectedCategory, 'legal' => $selectedLegal])) }}" @class(['shrink-0 rounded px-4 py-2 text-sm font-medium', 'bg-brand-600 text-white' => empty($status), 'text-muted hover:text-ink' => !empty($status)])>All Developments ({{ $projects->total() }})</a>
            @foreach (\App\Models\Project::STATUSES as $value => $label)
                <a href="{{ route('projects.index', array_filter(['status' => $value, 'q' => $searchQuery, 'city' => $selectedCity, 'category' => $selectedCategory, 'legal' => $selectedLegal])) }}" @class(['shrink-0 rounded px-4 py-2 text-sm font-medium', 'bg-brand-600 text-white' => (string) $status === $value, 'text-muted hover:text-ink' => (string) $status !== $value])>{{ $label }}</a>
            @endforeach
        </div>

        @if ($projects->isEmpty())
            <x-empty-state class="mt-6" icon="building" title="No projects match your criteria">
                Try widening your search terms, changing the city filter, or selecting "All Categories".
                <x-slot:actions><a href="{{ route('projects.index') }}" class="btn btn-primary">Reset Filters</a></x-slot:actions>
            </x-empty-state>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    @php
                        $specs = $project->specifications ?? [];
                        $legalStatus = $project->approval_status;
                        $legalTag = match ($legalStatus) {
                            'approved' => ['bg' => 'bg-emerald-600 text-white', 'text' => 'Verified Approved'],
                            'partially_approved' => ['bg' => 'bg-amber-600 text-white', 'text' => 'Partially Approved'],
                            'cancelled' => ['bg' => 'bg-red-600 text-white', 'text' => 'Cancelled / Excluded'],
                            'pending' => ['bg' => 'bg-amber-500 text-white', 'text' => 'Approval Pending'],
                            default => ['bg' => 'bg-gray-600 text-white', 'text' => 'Status Not Verified'],
                        };
                        $catName = $project->categoryLabel() ?: 'Development';
                        $areaKanal = $specs['approved_area_kanals'] ?? null;
                        $totalPlots = $specs['total_plots'] ?? null;
                    @endphp
                    <a href="{{ route('projects.show', $project) }}" class="card group flex flex-col overflow-hidden transition hover:shadow-lift">
                        <div class="relative">
                            <img src="{{ $project->coverUrl() }}" alt="{{ $project->name }}" loading="lazy" class="aspect-[16/9] w-full object-cover">
                            <span class="tag absolute top-3 left-3 {{ $legalTag['bg'] }}">{{ $legalTag['text'] }}</span>
                            <span class="tag absolute top-3 right-3 bg-brand-900/80 text-white text-[11px] backdrop-blur-xs">{{ $catName }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-4">
                            <div class="min-w-0">
                                <h2 class="font-serif text-lg font-bold group-hover:text-brand-700 line-clamp-1">{{ $project->name }}</h2>
                                <p class="mt-1 text-[13px] text-muted">{{ collect([$project->developer, $project->area?->name, $project->city])->filter()->implode(' · ') }}</p>
                            </div>

                            {{-- Factual Highlights if available --}}
                            <div class="mt-3 rounded bg-surface p-2 text-xs text-ink/80 space-y-1">
                                @if ($areaKanal)
                                    <div class="flex justify-between"><span class="text-muted">Approved Area:</span> <span class="font-semibold">{{ $areaKanal }} Kanals</span></div>
                                @endif
                                @if ($totalPlots)
                                    <div class="flex justify-between"><span class="text-muted">Sanctioned Plots:</span> <span class="font-semibold">{{ $totalPlots }} units</span></div>
                                @elseif (isset($specs['zone']))
                                    <div class="flex justify-between"><span class="text-muted">CDA Zone:</span> <span class="font-semibold">{{ $specs['zone'] }}</span></div>
                                @endif
                                <div class="flex justify-between"><span class="text-muted">Directory Status:</span> <span class="font-medium text-brand-800">{{ $project->statusLabel() }}</span></div>
                            </div>

                            <div class="mt-auto pt-3 flex items-center justify-between border-t border-line/60">
                                <span class="text-[11px] font-semibold tracking-wider uppercase text-muted">Directory Profile</span>
                                <span class="text-xs font-semibold text-brand-700 group-hover:underline">View Verified Details &rarr;</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $projects->links() }}</div>
        @endif
    </div>
</x-layouts.app>
