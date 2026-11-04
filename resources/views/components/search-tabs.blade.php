@props(['active' => 'sale', 'name' => 'search', 'compact' => false, 'links' => null])
{{--
    The four search tabs, always in this order: Buy | Rent | Plots | Commercial.
    In a search form they are radio buttons named "tab"; with $links (tab => url)
    they are links (map search). One look everywhere: white tabs, purple active
    tab with a gold underline.
--}}
@php
    $tabs = ['sale' => 'Buy', 'rent' => 'Rent', 'plots' => 'Plots', 'commercial' => 'Commercial'];
    $base = 'block whitespace-nowrap border-r border-line text-center font-semibold text-ink transition last:border-0 hover:bg-brand-50 hover:text-brand-700';
    $size = $compact ? 'px-3.5 py-2 text-[13px] sm:px-6' : 'px-3.5 py-3 text-[14px] sm:px-9 sm:text-[15px]';
    $on = 'bg-brand-600 text-white shadow-[inset_0_-3px_0_var(--color-gold-500)] hover:bg-brand-600 hover:text-white';
@endphp
<div {{ $attributes->merge(['class' => 'flex w-max max-w-full overflow-x-auto rounded-t-lg bg-white scrollbar-none']) }} @if ($links) role="navigation" aria-label="Search for" @else role="radiogroup" aria-label="Search for" @endif data-search-tabs>
    @foreach ($tabs as $value => $label)
        @if ($links)
            <a href="{{ $links[$value] }}" class="{{ $base }} {{ $size }} {{ $active === $value ? $on : '' }}" @if ($active === $value) aria-current="page" @endif>{{ $label }}</a>
        @else
            <label class="cursor-pointer">
                <input type="radio" name="tab" value="{{ $value }}" class="peer sr-only" @checked($active === $value)>
                <span class="{{ $base }} {{ $size }} peer-checked:bg-brand-600 peer-checked:text-white peer-checked:shadow-[inset_0_-3px_0_var(--color-gold-500)] peer-checked:hover:bg-brand-600 peer-checked:hover:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 peer-focus-visible:ring-inset">{{ $label }}</span>
            </label>
        @endif
    @endforeach
</div>
