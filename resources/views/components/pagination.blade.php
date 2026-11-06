@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <p class="text-sm text-muted">
            Showing <span class="font-semibold text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-ink">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold text-ink">{{ $paginator->total() }}</span>
        </p>
        <ul class="flex max-w-full flex-wrap items-center justify-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="grid size-10 place-items-center rounded-xl text-muted/50" aria-disabled="true"><x-icon name="chevron-left" class="size-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid size-10 place-items-center rounded-xl border border-line bg-white hover:border-brand-300" aria-label="Previous page"><x-icon name="chevron-left" class="size-4" /></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="grid size-10 place-items-center text-sm text-muted">…</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="hidden sm:block">
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="grid size-10 place-items-center rounded-xl bg-brand-600 text-sm font-bold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-sm font-semibold hover:border-brand-300" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li class="px-2 text-sm font-semibold sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</li>
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid size-10 place-items-center rounded-xl border border-line bg-white hover:border-brand-300" aria-label="Next page"><x-icon name="chevron-right" class="size-4" /></a>
                @else
                    <span class="grid size-10 place-items-center rounded-xl text-muted/50" aria-disabled="true"><x-icon name="chevron-right" class="size-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
