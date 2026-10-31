@php
    $verified = $dealer->isVerified();
    $wa = $verified && $dealer->whatsapp_number ? preg_replace('/\D+/', '', $dealer->whatsapp_number) : null;
@endphp
<x-layouts.app :title="$dealer->agency_name.' — property dealer in '.$dealer->city" :description="$dealer->agency_name.' on Meezan Property: '.($counts['sale'] + $counts['rent']).' live listings in '.$dealer->areas->pluck('name')->take(3)->implode(', ').'.'">
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['Agents', route('dealers.index')], [$dealer->agency_name, null]]" />
        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                <span class="grid size-24 shrink-0 place-items-center rounded-full bg-ink font-serif text-3xl text-white">{{ collect(explode(' ', $dealer->agency_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                <div class="min-w-0">
                    <h1 class="text-3xl">{{ $dealer->agency_name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                        @if ($verified)
                            <span class="inline-flex items-center gap-1 rounded bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700"><x-icon name="badge-check" class="size-3.5" /> Verified Dealer</span>
                        @else
                            <span class="rounded bg-page px-2 py-0.5 text-xs text-muted">Verification pending</span>
                        @endif
                        <span class="text-muted">{{ $dealer->city }}</span>
                        @if ($dealer->years_in_business)<span class="text-muted">{{ $dealer->years_in_business }} years in business</span>@endif
                        <span class="text-muted">On Meezan since {{ $dealer->user->created_at->format('Y') }}</span>
                    </div>
                    @if ($dealer->bio)<p class="mt-3 text-[15px] text-ink/80">{{ $dealer->bio }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($dealer->areas as $area)<a href="{{ route('areas.show', $area) }}" class="badge bg-brand-50 text-brand-700 hover:bg-brand-100">{{ $area->name }}</a>@endforeach
                    </div>
                </div>
            </section>
            <aside class="card p-6">
                <dl class="grid grid-cols-2 gap-4 text-center">
                    <div class="rounded-lg bg-page p-3"><dd class="text-2xl font-bold">{{ $counts['sale'] }}</dd><dt class="text-xs text-muted">For sale</dt></div>
                    <div class="rounded-lg bg-page p-3"><dd class="text-2xl font-bold">{{ $counts['rent'] }}</dd><dt class="text-xs text-muted">For rent</dt></div>
                </dl>
                @if ($verified && ($dealer->public_phone || $wa))
                    <div class="mt-4 grid gap-2">
                        @if ($dealer->public_phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $dealer->public_phone) }}" class="btn btn-primary"><x-icon name="phone" class="size-4" /> Call {{ $dealer->public_phone }}</a>@endif
                        @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-whatsapp"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>@endif
                    </div>
                @else
                    <p class="mt-4 text-[13px] text-muted">Contact this dealer through the inquiry form on any of their listings.</p>
                @endif
            </aside>
        </div>

        <section class="mt-10" aria-labelledby="listings-title">
            <h2 id="listings-title" class="section-title">Listings by {{ $dealer->agency_name }}</h2>
            @if ($listings->isEmpty())
                <x-empty-state class="mt-4" icon="home" title="No live listings right now" />
            @else
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($listings as $property)<x-property-card :property="$property" />@endforeach
                </div>
                <div class="mt-8">{{ $listings->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
