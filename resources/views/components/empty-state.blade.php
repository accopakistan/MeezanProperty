@props(['icon' => 'inbox', 'title', 'compact' => false])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-[var(--radius-card)] border border-dashed border-brand-200 bg-white text-center '.($compact ? 'px-6 py-10' : 'px-6 py-16')]) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 text-base font-bold">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="mt-1.5 max-w-md text-sm text-muted">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="mt-5 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
