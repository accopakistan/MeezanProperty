{{-- Shown on every investment page whose published snapshot contains demonstration data. $calc: the InvestmentCalculation on show. --}}
@if ($calc?->includes_demo)
    <div {{ $attributes ?? '' }} class="{{ $class ?? 'mb-5' }} flex items-start gap-3 rounded-xl border-2 border-dashed border-gold-500 bg-gold-50 px-4 py-3 text-sm text-ink" role="note" aria-label="Demo data notice">
        <span class="shrink-0 rounded bg-gold-500 px-2 py-0.5 text-[12px] font-extrabold tracking-wide text-brand-900">Preview</span>
        <p><strong>{{ \Illuminate\Support\Str::before(config('meezan.investment.demo_notice'), ' — ') }}</strong> — {{ \Illuminate\Support\Str::after(config('meezan.investment.demo_notice'), ' — ') }}
            The projects shown are fictional examples.</p>
    </div>
@endif
