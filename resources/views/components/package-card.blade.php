@props(['package', 'current' => false, 'action' => null, 'actionLabel' => 'Choose package'])
{{-- A package from the database: price, limits and features. Highlighted only when an admin marked it recommended. --}}
@php
    $features = config('meezan.billing.features');
    $limitRows = [
        ['listing_limit', 'active listings'],
        ['lead_limit', 'buyer leads a month'],
        ['featured_limit', 'featured listings'],
    ];
@endphp
<article {{ $attributes->merge(['class' => 'card relative flex flex-col p-6 '.($package->is_recommended ? 'border-2 border-gold-500 shadow-lift' : '')]) }} aria-labelledby="package-{{ $package->id }}">
    @if ($package->is_recommended)
        <span class="tag absolute -top-3 left-6 bg-gold-500">Recommended</span>
    @endif
    <div class="flex items-start justify-between gap-3">
        <h3 id="package-{{ $package->id }}" class="text-lg font-extrabold">{{ $package->name }}</h3>
        @if ($package->is_demo)
            <span class="badge bg-amber-50 text-amber-700 ring-1 ring-amber-200 ring-inset" title="Draft package for testing; prices and limits are not final">Draft</span>
        @endif
    </div>
    @if ($package->description)
        <p class="mt-1 text-sm text-muted">{{ $package->description }}</p>
    @endif

    <p class="mt-5">
        @if ($package->isFree())
            <span class="text-3xl font-extrabold">Free</span>
        @else
            <span class="text-sm font-semibold text-muted">{{ $package->currency }}</span>
            <span class="text-3xl font-extrabold tabular-nums">{{ number_format((float) $package->price) }}</span>
            <span class="text-sm text-muted">/ {{ $package->intervalLabel() }}</span>
        @endif
    </p>
    @if ($package->trial_days && ! $package->isFree())
        <p class="mt-1 text-xs font-semibold text-brand-700">{{ $package->trial_days }}-day free trial for first-time subscribers</p>
    @endif

    <ul class="mt-5 flex-1 space-y-2.5 text-sm">
        @foreach ($limitRows as [$column, $label])
            <li class="flex items-start gap-2">
                <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                <span><strong>{{ $package->{$column} === null ? 'Unlimited' : number_format($package->{$column}) }}</strong> {{ $label }}</span>
            </li>
        @endforeach
        @foreach ($features as $key => $label)
            <li @class(['flex items-start gap-2', 'text-muted/70 line-through decoration-muted/40' => ! $package->hasFeature($key)])>
                <x-icon :name="$package->hasFeature($key) ? 'check' : 'x'" @class(['mt-0.5 size-4 shrink-0', 'text-brand-600' => $package->hasFeature($key), 'text-muted/60' => ! $package->hasFeature($key)]) />
                <span>{{ $label }}</span>
            </li>
        @endforeach
    </ul>

    <div class="mt-6">
        @if ($current)
            <span class="btn btn-secondary w-full cursor-default">Your current package</span>
        @elseif ($action)
            <a href="{{ $action }}" @class(['btn w-full', 'btn-gold' => $package->is_recommended, 'btn-primary' => ! $package->is_recommended])>{{ $actionLabel }}</a>
        @endif
    </div>
</article>
