<x-layouts.dashboard title="Official sources" section="admin" heading="Official sources" subheading="Official lists read automatically every day. A source is read only after an admin records, once, on what basis automated reading is permitted. The collector identifies itself, obeys robots.txt and waits between pages.">
    @include('admin.investment-index.partials.nav')

    @if ($missingPresets)
        <form method="POST" action="{{ route('admin.investment-index.sources.setup') }}" class="card card-body mb-6 flex flex-wrap items-center justify-between gap-3">@csrf
            <p class="text-[14px]">{{ $missingPresets }} built-in official {{ $missingPresets === 1 ? 'source is' : 'sources are' }} not added yet: {{ $presets->pluck('label')->implode('; ') }}.</p>
            <button class="btn btn-primary btn-sm">Add built-in sources</button>
        </form>
    @endif

    <div class="space-y-4">
        @forelse ($sources as $source)
            @php $preset = $source->collectorPreset(); @endphp
            <section class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold">{{ $source->name }}</h2>
                        <p class="text-[13px] text-muted"><a class="link" href="{{ $preset['url'] ?? $source->url }}" rel="noopener noreferrer" target="_blank">{{ $preset['url'] ?? $source->url }}</a> · {{ $preset['authority'] ?? $source->publisher }} · {{ $preset['city'] ?? '' }} · {{ \App\Models\InvestmentRegistryEntry::LIST_TYPES[$preset['list_type'] ?? ''] ?? '' }}</p>
                        <p class="mt-1 text-[13px]">
                            @if ($source->isCollectable())
                                <span class="font-semibold text-emerald-700">Authorized</span> — {{ config('investment_index.collection.access_bases.'.$source->access_basis) }}, {{ $source->authorized_at->localTime()->format('j M Y') }}.
                            @elseif ($source->authorized_at && ! $source->is_active)
                                <span class="font-semibold text-amber-700">Switched off</span>
                            @else
                                <span class="font-semibold text-amber-700">Not authorized</span> — not collected until access is reviewed.
                            @endif
                            @if ($source->last_collected_at) Last read {{ $source->last_collected_at->localTime()->format('j M Y, H:i') }} ({{ $source->last_collection_status }}).@endif
                        </p>
                        @if ($source->access_note)<p class="mt-1 text-[13px] text-muted">Access note: {{ $source->access_note }}</p>@endif
                    </div>
                    <div class="flex gap-2">
                        @if ($source->isCollectable())
                            <form method="POST" action="{{ route('admin.investment-index.sources.collect', $source) }}">@csrf<button class="btn btn-primary btn-sm">Collect now</button></form>
                            <form method="POST" action="{{ route('admin.investment-index.sources.authorize', $source) }}">@csrf @method('PATCH')<button name="action" value="deactivate" class="btn btn-secondary btn-sm">Switch off</button></form>
                        @endif
                    </div>
                </div>

                @unless ($source->isCollectable())
                    <form method="POST" action="{{ route('admin.investment-index.sources.authorize', $source) }}" class="mt-4 grid gap-4 border-t border-line pt-4 md:grid-cols-2">@csrf @method('PATCH')
                        <x-field name="access_basis" type="select" label="Access basis" :options="config('investment_index.collection.access_bases')" :value="$source->access_basis" placeholder="Choose…" required />
                        <x-field name="terms_url" type="url" label="Terms of use page (if any)" :value="$source->terms_url" />
                        <x-field class="md:col-span-2" name="access_note" type="textarea" rows="2" label="What you checked" :value="$source->access_note" placeholder="e.g. Public list published by the authority; robots.txt has no restrictions; no terms prohibit automated reading (checked 4 Nov 2026)." required />
                        <label class="flex items-start gap-2 text-[14px] md:col-span-2"><input type="checkbox" name="confirm" value="1" class="mt-1"> <span>I confirm that automated, polite reading of this source is permitted on this basis.</span></label>
                        <div class="md:col-span-2"><button class="btn btn-primary btn-sm">Authorize and activate</button></div>
                    </form>
                @endunless
            </section>
        @empty
            <x-empty-state title="No official sources yet" compact>Add the built-in sources above to begin.</x-empty-state>
        @endforelse
    </div>

    <section class="card card-body mt-6">
        <h2 class="text-base font-bold">Collection history</h2>
        @include('admin.investment-index.partials.runs', ['runs' => $runs])
    </section>
</x-layouts.dashboard>
