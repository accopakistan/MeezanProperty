<x-layouts.dashboard title="Ranking categories" section="admin" heading="Investment ranking categories" subheading="Each public category (city → group → property type) ranks only its own entities and property types, scored with the weights of one methodology category. Categories are defined in config/meezan.php (investment.segments); weights are changed in Methodology.">
    @include('admin.market.partials.nav')

    @if ($uncategorised)
        <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ $uncategorised }} {{ \Illuminate\Support\Str::plural('project', $uncategorised) }} have no investment category, so they appear in no category ranking. <a href="{{ route('admin.projects.index') }}" class="link">Set categories</a></p>
    @endif

    <div class="card relative overflow-x-auto">
        <table class="table table-compact min-w-[1000px]">
            <thead><tr><th scope="col">Category</th><th scope="col">Entities</th><th scope="col">Property types used</th><th scope="col">Compared by</th><th scope="col">Weights</th><th scope="col">Projects</th><th scope="col">Snapshot rows</th></tr></thead>
            <tbody>
                @foreach ($segments as $slug => $s)
                    <tr>
                        <td><a href="{{ route('rankings.segment', ['pakistan', $s['group'], $slug]) }}" class="link">{{ $s['label'] }}</a><span class="block text-[12px] text-muted">{{ config('meezan.investment.groups.'.$s['group'].'.label') }}</span></td>
                        <td class="text-[13px]">{{ implode(', ', array_map(fn ($e) => $e === 'area' ? 'areas / societies' : 'projects', $s['entities'])) }}
                            @if ($s['project_types'])<span class="block text-[12px] text-muted">Projects: {{ collect($s['project_types'])->map(fn ($c) => config('meezan.investment.project_categories.'.$c.'.label'))->implode(', ') }}</span>@endif
                            @if ($s['area_types'])<span class="block text-[12px] text-muted">Area types: {{ count($s['area_types']) }} society types</span>@endif</td>
                        <td class="text-[13px]">{{ $s['types'] ? collect($s['types'])->map(fn ($t) => \App\Enums\PropertyType::from($t)->label())->implode(', ') : 'All types' }}</td>
                        <td class="text-[13px]">{{ match ($s['group_by']) { 'size' => 'Size', 'bedrooms' => 'Bedrooms', default => 'Whole project / society' } }}</td>
                        <td class="text-[13px]">{{ config('meezan.investment.categories.'.$s['weights'].'.label') }}</td>
                        <td class="text-[13px] tabular-nums">{{ $s['published_projects'] }} published @if ($s['hidden_projects'])<span class="text-muted">· {{ $s['hidden_projects'] }} hidden</span>@endif</td>
                        <td class="text-[13px] tabular-nums">{{ $s['rows'] }} ({{ $s['ranked'] }} ranked, {{ $s['cities'] }} {{ \Illuminate\Support\Str::plural('city', $s['cities']) }})</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-[13px] text-muted">{{ $calc ? 'Snapshot: published calculation #'.$calc->id.' (methodology v'.$calc->methodology_version.').' : 'No calculation is published yet, so every category is empty. Enter verified observations and indicators, run a calculation and publish it.' }}
        Occupancy is recorded as a market indicator (Occupancy %); apartment bedrooms are recorded on each observation.</p>
</x-layouts.dashboard>
