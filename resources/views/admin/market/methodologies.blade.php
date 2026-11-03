<x-layouts.dashboard title="Scoring methodology" section="admin" heading="Scoring methodology" subheading="Versioned weights per ranking category. Active and retired versions are locked; changes go into a new draft version. Published rankings keep the version they used.">
    @include('admin.market.partials.nav')
    <form method="POST" action="{{ route('admin.market.methodologies.store') }}" class="mb-4">@csrf<button class="btn btn-primary btn-sm">New draft version from the active one</button></form>
    <div class="space-y-4">
        @foreach ($versions as $v)
            <section class="card card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-bold">v{{ $v->version }} · {{ $v->name }} <span @class(['ml-2 rounded-full px-2 py-0.5 text-xs font-semibold ring-1', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $v->status === 'active', 'bg-page text-muted ring-line' => $v->status !== 'active'])>{{ ucfirst($v->status) }}</span></h2>
                    @if ($v->isEditable())<a href="{{ route('admin.market.methodologies.edit', $v) }}" class="btn btn-secondary btn-sm">Edit draft</a>@endif
                </div>
                @if ($v->notes)<p class="mt-1 text-[13px] text-muted">{{ $v->notes }}</p>@endif
                <div class="mt-3 relative overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-[13px]">
                        <thead><tr><th class="py-1.5 font-medium text-muted">Factor</th>@foreach ($categories as $c)<th class="py-1.5 font-medium">{{ str_replace('Best ', '', $c['label']) }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($factors as $key => $label)
                                <tr><th scope="row" class="py-1.5 pr-2 font-medium">{{ $label }}</th>@foreach ($categories as $ck => $c)<td class="py-1.5 tabular-nums">{{ $v->weightsFor($ck)[$key] ?? 0 }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($v->activated_at)<p class="mt-2 text-xs text-muted">Activated {{ $v->activated_at->localTime()->format('j M Y') }}</p>@endif
            </section>
        @endforeach
    </div>
</x-layouts.dashboard>
