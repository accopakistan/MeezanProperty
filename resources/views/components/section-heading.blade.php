@props(['eyebrow' => null, 'title', 'href' => null, 'linkLabel' => 'View all'])
<div {{ $attributes->except('id')->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h2 class="section-title mt-2" @if ($attributes->has('id')) id="{{ $attributes->get('id') }}" @endif>{{ $title }}</h2>
        @if ($slot->isNotEmpty())
            <p class="mt-2 text-[15px] text-muted">{{ $slot }}</p>
        @endif
    </div>
    @if ($href)
        <a href="{{ $href }}" class="group inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800">
            {{ $linkLabel }} <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
        </a>
    @endif
</div>
