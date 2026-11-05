{{-- Shown wherever no project has enough verified evidence to be ranked. $counts: assessed/ranked/... --}}
<div class="rounded-xl border border-dashed border-brand-200 bg-white px-5 py-6 text-center" role="status">
    <p class="text-base font-bold text-ink">Insufficient Verified Data — Research in Progress</p>
    <p class="mx-auto mt-1 max-w-xl text-[14px] text-muted">
        No project here has enough verified, traceable evidence to be ranked yet.
        @if (($counts['assessed'] ?? 0) > 0)
            {{ $counts['assessed'] }} {{ $counts['assessed'] === 1 ? 'project was' : 'projects were' }} assessed: {{ $counts['insufficient'] }} with insufficient verified data, {{ $counts['not_eligible'] }} not eligible, {{ $counts['excluded'] }} excluded.
        @endif
        Scores appear only when official legal records and verified evidence cover enough of the methodology.
    </p>
</div>
