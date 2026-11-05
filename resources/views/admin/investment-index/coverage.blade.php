<x-layouts.dashboard title="Data coverage" section="admin" heading="Real-world data coverage" subheading="Counts of records actually collected. They show what Meezan Property knows today, not every development in Pakistan.">
    @include('admin.investment-index.partials.nav')

    @php
        $t = $summary['totals'];
        $columns = ['projects' => 'Projects', 'legal_verified' => 'Legal status verified', 'operational_verified' => 'Operations verified', 'history_verified' => 'Price history', 'rental_verified' => 'Rental data', 'ranked' => 'Ranked', 'under_review' => 'Not eligible yet', 'insufficient' => 'Insufficient data', 'excluded' => 'Excluded'];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach (['projects' => 'Genuine projects on file', 'legal_verified' => 'Verified legal status', 'ranked' => 'Eligible and ranked', 'insufficient' => 'Insufficient evidence', 'excluded' => 'Excluded on legal grounds'] as $key => $label)
            <section class="card card-body"><h2 class="text-sm font-semibold text-muted">{{ $label }}</h2><p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($t[$key]) }}</p></section>
        @endforeach
    </div>

    @foreach (['Coverage by city' => $summary['cities'], 'Coverage by category' => $summary['categories']] as $title => $rows)
        <section class="card mt-6 min-w-0 p-5">
            <h2 class="text-base font-bold">{{ $title }}</h2>
            <div class="relative mt-3 overflow-x-auto">
                <table class="table table-compact min-w-[860px]">
                    <thead><tr><th scope="col">{{ str_contains($title, 'city') ? 'City' : 'Category' }}</th>@foreach ($columns as $label)<th scope="col" class="text-right">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($rows as $key => $row)
                            <tr><td class="font-semibold">{{ $row['label'] ?? $key }}</td>@foreach ($columns as $column => $label)<td class="text-right tabular-nums">{{ number_format($row[$column]) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section class="card mt-6 min-w-0 p-5">
        <h2 class="text-base font-bold">Source registry</h2>
        <p class="mt-1 text-[13px] text-muted"><strong>Connected</strong> means collected automatically, and the last run succeeded. <strong>Read once</strong> means a person copied the source on the date shown: it will not refresh by itself.</p>
        <div class="relative mt-3 overflow-x-auto">
            <table class="table table-compact min-w-[940px]">
                <thead><tr><th scope="col">Source</th><th scope="col">Type</th><th scope="col">Access</th><th scope="col">Last success</th><th scope="col" class="text-right">Registry</th><th scope="col" class="text-right">Matched</th><th scope="col" class="text-right">Projects found</th></tr></thead>
                <tbody>
                    @foreach ($sources as $x)
                        @php $s = $x['source']; @endphp
                        <tr>
                            <td class="min-w-0"><span class="font-semibold">{{ $s->name }}</span>@if ($s->url)<a href="{{ $s->url }}" class="link block break-all text-[12px]" target="_blank" rel="noopener nofollow">{{ $s->url }}</a>@endif</td>
                            <td class="text-[13px]">{{ $s->typeLabel() }}</td>
                            <td class="text-[13px]">
                                @if ($x['connected'])<span class="font-semibold text-emerald-700">Connected</span>
                                @elseif ($s->collector)<span class="font-semibold text-amber-800">{{ $x['last_run']?->status ? ucfirst($x['last_run']->status) : 'Not collected' }}</span>
                                @else Read once @endif
                                @if ($s->access_note)<span class="mt-0.5 block max-w-sm text-[12px] text-muted">{{ \Illuminate\Support\Str::limit($s->access_note, 170) }}</span>@endif
                            </td>
                            <td class="text-[13px] whitespace-nowrap">{{ $s->last_success_at?->format('j M Y') ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format($x['registry']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($x['matched']) }}</td>
                            <td class="text-right tabular-nums">{{ number_format($x['projects']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card card-body">
            <h2 class="text-base font-bold">Waiting for a person</h2>
            <dl class="mt-3 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-[14px]">
                <dt>Legal records to verify</dt><dd class="font-semibold tabular-nums">{{ $pending['legal'] }}</dd>
                <dt>Evidence to verify</dt><dd class="font-semibold tabular-nums">{{ $pending['evidence'] }}</dd>
                <dt>Open exceptions (uncertain matches, conflicts)</dt><dd class="font-semibold tabular-nums">{{ $pending['exceptions'] }}</dd>
                <dt>Verified evidence past its age limit</dt><dd class="font-semibold tabular-nums">{{ $pending['stale'] }}</dd>
                <dt>Rejected records</dt><dd class="font-semibold tabular-nums">{{ $pending['rejected'] }}</dd>
            </dl>
            <p class="mt-3 text-[13px]"><a class="link" href="{{ route('admin.investment-index.verification') }}">Verification Centre</a> · <a class="link" href="{{ route('admin.investment-index.exceptions') }}">Exceptions</a></p>
        </section>
        <section class="card card-body min-w-0">
            <h2 class="text-base font-bold">Possible duplicate projects</h2>
            <p class="mt-1 text-[13px] text-muted">Similar names in one city and category. They are never merged automatically.</p>
            <ul class="mt-2 divide-y divide-line text-[13px]">
                @forelse ($duplicates as $d)
                    <li class="py-1.5"><a class="link" href="{{ route('admin.projects.edit', $d['a']) }}">{{ $d['a']->name }}</a> / <a class="link" href="{{ route('admin.projects.edit', $d['b']) }}">{{ $d['b']->name }}</a> <span class="text-muted">· {{ $d['a']->city }} · {{ round($d['score'] * 100) }}%</span></li>
                @empty
                    <li class="py-1.5 text-muted">None found.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.dashboard>
