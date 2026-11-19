{{-- ‹ › buttons for a [data-carousel] card row; hidden until the row overflows (page script). $label names the cards. --}}
@foreach (['prev' => ['arrow-left', 'left-1', 'Previous'], 'next' => ['arrow-right', 'right-1', 'Next']] as $dir => [$icon, $side, $word])
    <button type="button" data-carousel-{{ $dir }} hidden aria-label="{{ $word }} {{ mb_strtolower($label ?? 'projects') }}"
            class="absolute top-[38%] {{ $side }} z-10 grid size-8 -translate-y-1/2 place-items-center rounded-full bg-white text-brand-700 shadow-lift ring-1 ring-line transition hover:bg-brand-50 disabled:opacity-40">
        <x-icon :name="$icon" class="size-4" />
    </button>
@endforeach
