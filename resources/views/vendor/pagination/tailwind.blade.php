@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col items-center gap-3">

        {{-- Results count --}}
        <p class="text-sm text-stone-500">
            Showing
            @if ($paginator->firstItem())
                <span class="font-semibold text-stone-700">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-semibold text-stone-700">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            of
            <span class="font-semibold text-stone-700">{{ $paginator->total() }}</span>
            results
        </p>

        {{-- Buttons row --}}
        <div class="flex items-center gap-1">

                {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="flex items-center justify-center shrink-0 w-9 h-9 text-stone-300 bg-white border border-stone-200 cursor-not-allowed rounded-lg" style="min-width:2.25rem;min-height:2.25rem;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                    class="flex items-center justify-center shrink-0 w-9 h-9 text-stone-500 bg-white border border-stone-200 rounded-lg hover:bg-stone-50 hover:text-stone-800 transition focus:outline-none focus:ring-2 focus:ring-brand-500/30" style="min-width:2.25rem;min-height:2.25rem;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex items-center justify-center shrink-0 w-9 h-9 text-sm text-stone-400 select-none" style="min-width:2.25rem;min-height:2.25rem;">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                class="flex items-center justify-center shrink-0 w-9 h-9 text-sm font-semibold text-white bg-brand-600 border border-brand-600 rounded-lg cursor-default shadow-sm" style="min-width:2.25rem;min-height:2.25rem;">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                class="flex items-center justify-center shrink-0 w-9 h-9 text-sm font-medium text-stone-600 bg-white border border-stone-200 rounded-lg hover:bg-stone-50 hover:text-stone-900 transition focus:outline-none focus:ring-2 focus:ring-brand-500/30" style="min-width:2.25rem;min-height:2.25rem;">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                    class="flex items-center justify-center shrink-0 w-9 h-9 text-stone-500 bg-white border border-stone-200 rounded-lg hover:bg-stone-50 hover:text-stone-800 transition focus:outline-none focus:ring-2 focus:ring-brand-500/30" style="min-width:2.25rem;min-height:2.25rem;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @else
                <span class="flex items-center justify-center shrink-0 w-9 h-9 text-stone-300 bg-white border border-stone-200 cursor-not-allowed rounded-lg" style="min-width:2.25rem;min-height:2.25rem;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </span>
            @endif

        </div>

    </nav>
@endif
