{{-- Shown when no verified ranking snapshot is published. $place (city name or null). --}}
<p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
    <strong>Insufficient Verified Data{{ $place ? ' for '.$place : '' }}: no verified rankings yet.</strong> Projects are undergoing data verification, and you can open each one to see its legal status, sources and what is still missing. Scores, yields and price trends appear only after verified records are reviewed and published; nothing is estimated to fill these pages.
    <a href="{{ route('investment.methodology') }}" class="link">How rankings work</a>
</p>
