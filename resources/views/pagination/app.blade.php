@if ($paginator->hasPages())
    <nav class="flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="Pagination">
        <p class="text-foreground/60 text-xs">
            Showing <span class="font-semibold">{{ $paginator->firstItem() }}</span>–<span class="font-semibold">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold">{{ $paginator->total() }}</span>
        </p>

        <ul class="flex items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="border-border text-foreground/30 inline-flex h-8 w-8 items-center justify-center rounded-lg border" aria-disabled="true"><i class="ri-arrow-left-s-line"></i></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="border-border hover:bg-muted inline-flex h-8 w-8 items-center justify-center rounded-lg border" aria-label="Previous page"><i class="ri-arrow-left-s-line"></i></a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="text-foreground/40 px-1 text-xs">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="bg-primary text-primary-foreground inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-bold">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="border-border hover:bg-muted inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="border-border hover:bg-muted inline-flex h-8 w-8 items-center justify-center rounded-lg border" aria-label="Next page"><i class="ri-arrow-right-s-line"></i></a>
                @else
                    <span class="border-border text-foreground/30 inline-flex h-8 w-8 items-center justify-center rounded-lg border" aria-disabled="true"><i class="ri-arrow-right-s-line"></i></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
