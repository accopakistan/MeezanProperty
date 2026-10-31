@if ($parts = $criteria->describe())
    <div class="mb-5 rounded-2xl border border-line bg-white p-4">
        <p class="text-xs font-bold tracking-wide text-muted uppercase">What I understood</p>
        <div class="mt-2.5 flex flex-wrap gap-2">
            @foreach ($parts as $part)
                <span class="badge bg-brand-50 px-3 py-1 text-[13px] whitespace-normal text-brand-700">{{ $part }}</span>
            @endforeach
        </div>
    </div>
@endif
