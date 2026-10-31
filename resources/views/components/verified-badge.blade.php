@props(['label' => 'Verified dealer'])
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-emerald-700']) }} title="Identity and agency checked by Meezan Property">
    <x-icon name="badge-check" class="size-4" /> {{ $label }}
</span>
