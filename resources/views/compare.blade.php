@php
    use App\Support\Format;
    $rows = [
        'Price' => fn ($p) => $p->priceLabel(),
        'Price per Marla' => fn ($p) => $p->pricePerMarla() ? Format::price($p->pricePerMarla()) : '—',
        'Purpose' => fn ($p) => $p->purpose->label(),
        'Type' => fn ($p) => $p->type->label(),
        'Location' => fn ($p) => $p->locationLabel(),
        'Plot / land area' => fn ($p) => $p->areaLabel().' ('.number_format($p->area_sqft).' sq ft)',
        'Covered area' => fn ($p) => $p->coveredAreaLabel() ?? '—',
        'Bedrooms' => fn ($p) => $p->type->hasRooms() ? ($p->bedrooms ?? '—') : 'n/a',
        'Bathrooms' => fn ($p) => $p->bathrooms ?? '—',
        'Status' => fn ($p) => $p->completionLabel() ?? '—',
        'Furnishing' => fn ($p) => $p->furnishing ? ucfirst($p->furnishing) : '—',
        'Features' => fn ($p) => $p->amenityLabels() ? implode(', ', $p->amenityLabels()) : '—',
        'Listed by' => fn ($p) => ($p->dealer?->dealerProfile?->agency_name ?? '—').($p->hasVerifiedDealer() ? ' · Verified dealer' : ''),
        'Listed on' => fn ($p) => $p->published_at?->format('j M Y') ?? '—',
        'Reference' => fn ($p) => $p->reference,
    ];
@endphp
<x-layouts.app title="Compare properties" description="Compare up to four Meezan Property listings side by side." noindex>
    <div class="container-page py-8">
        <x-breadcrumbs :items="[['Compare', null]]" />
        <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl">Compare Properties</h1>
                <p class="mt-1 text-sm text-muted">Up to {{ \App\Http\Controllers\CompareController::MAX }} listings side by side. All prices are asking prices set by the lister.</p>
            </div>
            @if ($properties->isNotEmpty())
                <form method="POST" action="{{ route('compare.clear') }}">@csrf <button class="btn btn-secondary btn-sm">Clear all</button></form>
            @endif
        </div>

        @if ($properties->isEmpty())
            <x-empty-state class="mt-6" icon="layers" title="Nothing to compare yet">
                Open any listing and choose “Add to compare”.
                <x-slot:actions><a href="{{ route('properties.index') }}" class="btn btn-primary">Browse properties</a></x-slot:actions>
            </x-empty-state>
        @else
            <div class="card mt-6 overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr>
                            <th class="w-40 border-b border-line p-4"></th>
                            @foreach ($properties as $p)
                                <th class="border-b border-line p-4 text-left align-top font-normal">
                                    <img src="{{ $p->coverThumbUrl() ?? $p->placeholderUrl() }}" alt="" class="aspect-[16/10] w-full rounded-md object-cover">
                                    <a href="{{ route('properties.show', $p) }}" class="mt-2 block font-serif text-base font-bold hover:text-brand-700">{{ $p->title }}</a>
                                    <form method="POST" action="{{ route('compare.toggle', $p) }}" class="mt-1">@csrf <button class="text-xs font-semibold text-red-600 hover:underline">Remove</button></form>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $label => $value)
                            <tr class="odd:bg-page/60">
                                <th scope="row" class="p-3 pl-4 text-left text-[13px] font-semibold text-muted">{{ $label }}</th>
                                @foreach ($properties as $p)
                                    <td class="p-3 align-top {{ $label === 'Price' ? 'font-bold text-brand-600' : '' }}">{{ $value($p) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
