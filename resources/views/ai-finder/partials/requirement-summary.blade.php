{{-- What the assistant has understood so far (remembered across messages). --}}
@if (! empty($summary))
    <div class="mb-5 rounded-2xl border border-line bg-white p-4">
        <p class="text-xs font-bold tracking-wide text-muted uppercase">Your requirement so far</p>
        <dl class="mt-2.5 flex flex-wrap gap-2">
            @foreach ($summary as $label => $value)
                <div class="badge bg-brand-50 px-3 py-1 text-[13px] whitespace-normal text-brand-700">
                    <dt class="inline font-normal text-brand-700/70">{{ $label }}:</dt>
                    <dd class="inline">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
