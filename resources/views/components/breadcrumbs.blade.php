@props(['items' => []])
{{-- $items: list of [label, url|null]. Home is prepended automatically. --}}
@php
    $trail = array_merge([['Home', route('home')]], $items);
@endphp
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-[13px] text-muted">
        @foreach ($trail as $i => [$label, $url])
            <li class="flex items-center gap-1.5">
                @if ($i > 0)
                    <x-icon name="chevron-right" class="size-3.5 text-muted/60" />
                @endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-brand-700">{{ $label }}</a>
                @else
                    <span class="font-medium text-ink" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($trail)->values()->map(fn ($item, $i) => array_filter([
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $item[0],
        'item' => $item[1] ?? null,
    ]))->all(),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
</script>
