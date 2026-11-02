{{-- Listing allowance for the signed-in dealer: shown when the limit is reached or exceeded. --}}
@php
    $entitlements = app(\App\Services\Billing\Entitlements::class);
    $dealer = auth()->user();
    $limit = $entitlements->plan($dealer)->limit('listings');
    $used = $entitlements->usage($dealer)['listings'];
@endphp
@if ($limit !== null && $used >= $limit)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-4 rounded-xl border p-4 text-sm '.($used > $limit ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900')]) }} role="status">
        <x-icon name="alert" class="size-5 shrink-0" />
        <div class="min-w-0 flex-1">
            <p class="font-bold">You have reached your listing limit. Upgrade your package to add more listings.</p>
            <p class="mt-0.5">{{ $used }} of {{ $limit }} active listings used on {{ $entitlements->plan($dealer)->name }}. Drafts can still be saved; they don’t count until submitted.</p>
        </div>
        <a href="{{ route('dealer.billing.packages') }}" class="btn btn-gold btn-sm">Upgrade package</a>
    </div>
@endif
