@php $cityFilter = $filters['city'] ?? null; @endphp
<x-layouts.app :title="'Find a property dealer'.($cityFilter ? ' in '.$cityFilter : ' in Pakistan')" :description="'Find property dealers and agencies'.($cityFilter ? ' in '.$cityFilter : ' in Lahore, Karachi, Islamabad and more').' by area. See verified dealers and their current listings on Meezan Property.'">
    <section class="bg-gradient-to-r from-brand-800 to-brand-600">
        <div class="container-page py-10">
            <x-breadcrumbs :items="[['Agents', null]]" class="[&_a]:text-white/75 [&_span]:text-white [&_svg]:text-white/50" />
            <h1 class="mt-5 text-4xl text-white">Find a Property Dealer</h1>
            <p class="mt-2 text-white/85">Agencies with live listings on Meezan Property. The verified badge appears only after our identity and agency checks.</p>
            <form method="GET" class="mt-6 grid max-w-5xl gap-2.5 rounded-lg bg-white p-3 shadow-lift sm:grid-cols-[1.4fr_1fr_1fr_auto_auto]">
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="input h-12" placeholder="Agency name" aria-label="Agency name">
                <select name="city" class="input h-12" aria-label="City served">
                    <option value="">All cities</option>
                    @foreach (config('meezan.cities') as $c)<option value="{{ $c }}" @selected($cityFilter === $c)>{{ $c }}</option>@endforeach
                </select>
                <select name="area" class="input h-12" aria-label="Area served">
                    <option value="">All areas</option>
                    @foreach (\App\Support\Lookup::areas($cityFilter)->concat($area ? [$area] : [])->unique('id') as $a)<option value="{{ $a->slug }}" @selected($area?->id === $a->id)>{{ $a->name }}</option>@endforeach
                </select>
                <label class="flex h-12 items-center gap-2 px-2 text-sm"><input type="checkbox" name="verified" value="1" class="checkbox" @checked($filters['verified'] ?? false)> Verified only</label>
                <button class="btn btn-primary h-12 px-8">Search</button>
            </form>
        </div>
    </section>

    <div class="container-page mt-8">
        <p class="text-sm text-muted">{{ number_format($dealers->total()) }} {{ \Illuminate\Support\Str::plural('dealer', $dealers->total()) }}{{ $area ? ' serving '.$area->name.', '.$area->city : ($cityFilter ? ' serving '.$cityFilter : '') }}</p>
        @if ($dealers->isEmpty())
            <x-empty-state class="mt-4" icon="briefcase" title="No dealers match this search">
                Try another area. Dealers appear here once they are verified or have a published listing.
                <x-slot:actions><a href="{{ route('dealer.register') }}" class="btn btn-primary">Register your agency</a></x-slot:actions>
            </x-empty-state>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($dealers as $dealer)
                    <a href="{{ route('dealers.show', $dealer) }}" class="card flex flex-col p-5 transition hover:shadow-lift">
                        <div class="flex items-center gap-4">
                            <span class="grid size-14 shrink-0 place-items-center rounded-full bg-ink font-serif text-xl text-white">{{ collect(explode(' ', $dealer->agency_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-serif text-lg font-bold">{{ $dealer->agency_name }}</p>
                                @if ($dealer->isVerified())
                                    <x-verified-badge />
                                @else
                                    <p class="text-xs text-muted">Verification pending</p>
                                @endif
                            </div>
                        </div>
                        <p class="mt-4 text-[13px] text-muted">Serves {{ $dealer->areas->pluck('name')->take(4)->implode(', ') ?: $dealer->city }}@if ($dealer->areas->count() > 4) +{{ $dealer->areas->count() - 4 }} more @endif</p>
                        <div class="mt-auto flex items-center justify-between border-t border-line pt-4 text-sm">
                            <span><strong>{{ $dealer->listings_count }}</strong> active {{ \Illuminate\Support\Str::plural('listing', $dealer->listings_count) }}</span>
                            <span class="font-semibold text-brand-600">View profile →</span>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-8">{{ $dealers->links() }}</div>
        @endif
    </div>
</x-layouts.app>
