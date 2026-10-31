<x-layouts.dashboard title="Marketplace trust" section="admin" heading="Marketplace trust" subheading="Verification and listing-review work, from live counts. Signals suggest what to check; none is a finding.">
    @foreach (['Dealers' => $dealers, 'Listings' => $listings, 'Listing quality' => $quality] as $heading => $rows)
        <section class="mb-8" aria-labelledby="trust-{{ \Illuminate\Support\Str::slug($heading) }}">
            <h2 id="trust-{{ \Illuminate\Support\Str::slug($heading) }}" class="mb-3 text-base font-bold">{{ $heading }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($rows as [$label, $count, $url])
                    @php $card = '<p class="text-[13px] text-muted">'.e($label).'</p><p class="mt-1 text-3xl font-bold '.($count ? 'text-ink' : 'text-muted').'">'.number_format($count).'</p>'; @endphp
                    @if ($url)
                        <a href="{{ $url }}" class="card card-body transition hover:shadow-lift">{!! $card !!}</a>
                    @else
                        <div class="card card-body">{!! $card !!}</div>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach
    <p class="text-xs text-muted">Completeness measures how much information a listing provides; it is not a rating of the property. “Requires review” and “duplicate suspected” are prompts for a reviewer, never conclusions about a dealer.</p>
</x-layouts.dashboard>
