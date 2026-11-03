{{-- Renders an agent report (App\Services\Agents\Report::toArray()). Model wording is shown apart from the data. --}}
<div class="space-y-5">
    @if ($report['narrative'] ?? null)
        <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 text-sm">
            <p class="text-xs font-semibold text-brand-700 uppercase">Model summary (checked against the data below)</p>
            <p class="mt-1">{{ $report['narrative'] }}</p>
        </div>
    @endif

    @if ($report['summary'] ?? [])
        <div>
            <h3 class="text-sm font-bold">Findings</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($report['summary'] as $line)<li>{{ $line }}</li>@endforeach
            </ul>
        </div>
    @endif

    @foreach ($report['sections'] ?? [] as $section)
        <div class="min-w-0">
            <h3 class="text-sm font-bold">{{ $section['heading'] }}</h3>
            @if ($section['items'] ?? null)
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($section['items'] as $item)<li class="break-words">{{ is_scalar($item) ? $item : json_encode($item) }}</li>@endforeach
                </ul>
            @endif
            @if ($table = $section['table'] ?? null)
                <div class="relative mt-2 overflow-x-auto rounded-lg border border-line">
                    <table class="table table-compact">
                        <thead><tr>@foreach ($table['columns'] as $col)<th scope="col">{{ $col }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($table['rows'] as $row)
                                <tr>@foreach ($row as $cell)<td class="text-[13px]">{{ is_scalar($cell) || $cell === null ? ($cell ?? '—') : json_encode($cell) }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    @foreach (['recommendations' => 'Recommendations', 'missing' => 'Missing data', 'limitations' => 'Limitations'] as $key => $heading)
        @if ($report[$key] ?? [])
            <div>
                <h3 class="text-sm font-bold">{{ $heading }}</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm {{ $key === 'recommendations' ? '' : 'text-muted' }}">
                    @foreach ($report[$key] as $line)<li>{{ $line }}</li>@endforeach
                </ul>
            </div>
        @endif
    @endforeach

    @if ($report['data_sources'] ?? [])
        <p class="text-[13px] text-muted">Data sources (tools): {{ implode(', ', $report['data_sources']) }}@if ($report['generated_at'] ?? null) · generated {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->localTime()->format('j M Y, H:i') }}@endif</p>
    @endif
</div>
