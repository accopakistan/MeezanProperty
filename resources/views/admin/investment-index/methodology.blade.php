<x-layouts.dashboard title="Index methodology" section="admin" heading="Methodology and weights" subheading="Weights per category, each summing to 100. They change only through a new version: draft it, edit it, then activate it. The Index then recalculates automatically, and the public methodology page shows the version in force.">
    @include('admin.investment-index.partials.nav')

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section class="card min-w-0 p-5">
            @if ($draft)
                <form method="POST" action="{{ route('admin.investment-index.methodology.update', $draft) }}" class="space-y-5">@csrf @method('PATCH')
                    <h2 class="text-base font-bold">Draft version {{ $draft->version }}</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($categories as $key => $c)
                            <fieldset class="rounded-lg border border-line p-3">
                                <legend class="px-1 text-sm font-semibold">{{ $c['label'] }}</legend>
                                @foreach (array_keys($c['factors']) as $factor)
                                    <label class="mt-1.5 flex items-center justify-between gap-3 text-[13px]"><span>{{ $c['factors'][$factor]['label'] }}</span>
                                        <input type="number" min="0" max="100" step="1" name="weights[{{ $key }}][{{ $factor }}]" value="{{ old("weights.$key.$factor", $draft->weights[$key][$factor] ?? 0) }}" class="input w-20 py-1 text-right" aria-label="{{ $c['label'] }}: {{ $c['factors'][$factor]['label'] }}"></label>
                                @endforeach
                                @error("weights.$key")<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror
                                @foreach (array_keys($c['factors']) as $factor)@error("weights.$key.$factor")<p class="mt-1 text-[13px] text-red-700">{{ $message }}</p>@enderror @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                    <x-field name="notes" type="textarea" rows="2" label="Why the weights change" :value="$draft->notes" required />
                    <div class="flex flex-wrap gap-2"><button class="btn btn-secondary">Save draft</button></div>
                </form>
                <form method="POST" action="{{ route('admin.investment-index.methodology.activate', $draft) }}" class="mt-3 border-t border-line pt-3">@csrf
                    <button class="btn btn-primary">Activate version {{ $draft->version }}</button>
                    <span class="ml-1 text-[12px] text-muted">Replaces version {{ $active?->version }} and recalculates the Index.</span>
                </form>
            @else
                <h2 class="text-base font-bold">Active weights (version {{ $active?->version }})</h2>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach ($categories as $key => $c)
                        <div class="rounded-lg border border-line p-3">
                            <h3 class="text-sm font-semibold">{{ $c['label'] }}</h3>
                            <ul class="mt-1.5 space-y-0.5 text-[13px]">@foreach ($active?->weightsFor($key) ?? [] as $factor => $w)<li class="flex justify-between"><span>{{ $c['factors'][$factor]['label'] ?? $factor }}</span><span class="tabular-nums">{{ $w }}%</span></li>@endforeach</ul>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.investment-index.methodology.draft') }}" class="mt-4">@csrf<button class="btn btn-primary btn-sm">Draft a new version</button></form>
            @endif
        </section>

        <section class="card p-5">
            <h2 class="text-base font-bold">Versions</h2>
            <ul class="mt-2 divide-y divide-line text-[14px]">
                @foreach ($versions as $v)
                    <li class="py-2"><span class="font-semibold">Version {{ $v->version }}</span> · {{ ucfirst($v->status) }}
                        <span class="block text-[12px] text-muted">{{ $v->activated_at ? 'Activated '.$v->activated_at->localTime()->format('j M Y, H:i') : 'Created '.$v->created_at?->localTime()->format('j M Y, H:i') }}@if ($v->notes) · {{ \Illuminate\Support\Str::limit($v->notes, 120) }}@endif</span></li>
                @endforeach
            </ul>
            <p class="mt-3 text-[12px] text-muted">Legal approval can never be set to 0: unverified legal status is never treated as approved.</p>
        </section>
    </div>
</x-layouts.dashboard>
