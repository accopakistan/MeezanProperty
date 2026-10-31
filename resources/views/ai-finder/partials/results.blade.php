{{-- AI matched cards as in the AI Search Dashboard / AI Platform Mockup (7C) references. --}}
@if (! $result)
    <div class="card grid place-items-center px-6 py-14 text-center">
        <span class="grid size-16 place-items-center rounded-full bg-brand-50 text-brand-600"><x-icon name="target" class="size-7" /></span>
        <h3 class="mt-4 text-lg font-bold">Your best matches will appear here</h3>
        <p class="mt-1.5 max-w-md text-sm text-muted">Describe your requirement or pick an example. We’ll return up to three published listings ranked by how well they fit.</p>
    </div>
@elseif ($result->isEmpty())
    <x-empty-state icon="search" title="No exact match available right now">
        @foreach ($result->hints as $hint)
            <span class="block">{{ $hint }}</span>
        @endforeach
        <span class="mt-2 block">Save this requirement to hear about new listings, or let up to {{ (int) config('meezan.leads.max_dealers_per_requirement', 3) }} verified dealers who cover the area help you.</span>
        <x-slot:actions>
            <a href="{{ route('properties.index', array_filter(['city' => $result->criteria->city ?: null, 'purpose' => $result->criteria->purpose?->value, 'type' => $result->criteria->type?->value, 'group' => $result->criteria->type ? null : $result->criteria->group])) }}" class="btn btn-secondary">Browse similar listings</a>
            <button type="button" class="btn btn-primary" data-ai-action="save" hidden>Save this requirement</button>
            <noscript><a href="{{ auth()->user()?->hasRole('buyer') ? route('account.requirements.create') : route('register') }}" class="btn btn-primary">Save this requirement</a></noscript>
        </x-slot:actions>
    </x-empty-state>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($result->matches as $match)
            @php $p = $match->property; @endphp
            <article class="card flex flex-col overflow-hidden">
                <div class="relative">
                    <img src="{{ $p->coverThumbUrl() ?? $p->placeholderUrl() }}" alt="{{ $p->title }}" loading="lazy" class="aspect-[16/10] w-full object-cover">
                    <span class="tag absolute top-2.5 left-2.5 {{ $match->budgetStatus === 'above' ? 'bg-amber-600' : ($match->score >= 85 && ! $match->caveats ? 'bg-emerald-600' : 'bg-brand-600') }}"><x-icon name="sparkles" class="size-3.5" /> {{ $match->score }}% Match</span>
                    @if ($p->is_demo)<span class="tag absolute bottom-2.5 left-2.5 bg-ink/75 text-[10px]">Demo listing</span>@endif
                    <x-save-button :property="$p" :saved="\App\Support\Lookup::isSaved($p->id)" class="absolute top-2 right-2" />
                </div>
                <div class="flex flex-1 flex-col p-4">
                    <h3 class="line-clamp-1 font-serif text-[17px] font-bold"><a href="{{ route('properties.show', $p) }}" class="hover:text-brand-700">{{ $p->title }}</a></h3>
                    <p class="mt-1 flex items-center gap-1 text-[13px] text-muted"><x-icon name="map-pin" class="size-3.5 text-brand-500" /> {{ $p->locationLabel() }}</p>
                    <p class="mt-1.5 text-lg font-bold text-brand-600">{{ $p->priceLabel() }}</p>
                    <p class="mt-1 text-[12.5px] text-muted">
                        @if ($p->type->hasRooms() && $p->bedrooms){{ $p->bedrooms }} Beds · @endif
                        @if ($p->type->hasRooms() && $p->bathrooms){{ $p->bathrooms }} Baths · @endif
                        {{ $p->areaLabel() }}
                    </p>
                    <p @class(['mt-3 text-xs font-semibold', 'text-amber-700' => in_array($match->budgetStatus, ['close', 'above'], true), 'text-brand-700' => ! in_array($match->budgetStatus, ['close', 'above'], true)])>{{ $match->label() }}</p>
                    <p class="mt-1 text-[12.5px] leading-snug text-ink/80">{{ \Illuminate\Support\Str::after($match->explanation(), ': ') }}</p>
                    <ul class="mt-1.5 space-y-1 text-xs">
                        @foreach (array_slice($match->reasons, 0, 3) as $reason)
                            <li class="flex gap-1.5"><x-icon name="check" class="size-3.5 shrink-0 text-emerald-600" /> {{ $reason }}</li>
                        @endforeach
                        @foreach ($match->caveats as $caveat)
                            <li class="flex gap-1.5 text-amber-800"><x-icon name="alert" class="size-3.5 shrink-0 text-amber-500" /> {{ $caveat }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                        <a href="{{ route('properties.show', $p) }}" class="btn btn-gold btn-sm">View Details</a>
                        <a href="{{ route('properties.show', $p) }}#inquiry" class="btn btn-outline btn-sm">Contact Dealer</a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif
