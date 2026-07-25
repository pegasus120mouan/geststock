@if ($paginator->hasPages())
<style>
.custom-pagination .page-link {
    border: none;
    padding: 8px 14px;
    margin: 0 3px;
    border-radius: 8px;
    color: #697a8d;
    font-weight: 500;
    transition: all 0.2s ease;
}
.custom-pagination .page-link:hover {
    background: linear-gradient(135deg, #696cff 0%, #8592ff 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(105, 108, 255, 0.4);
}
.custom-pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #696cff 0%, #8592ff 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(105, 108, 255, 0.4);
}
.custom-pagination .page-item.disabled .page-link {
    background: #f5f5f9;
    color: #c4c4c4;
}
.pagination-info {
    background: #f5f5f9;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 14px;
}
.pagination-info strong {
    color: #696cff;
}
</style>

<nav class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
    <div class="pagination-info">
        <span class="text-muted">
            Affichage de <strong>{{ $paginator->firstItem() ?? 0 }}</strong> à <strong>{{ $paginator->lastItem() ?? 0 }}</strong> sur <strong>{{ $paginator->total() }}</strong> résultats
        </span>
    </div>

    <ul class="pagination custom-pagination mb-0">
        {{-- First Page Link --}}
        @if ($paginator->onFirstPage())
            <li class="page-item disabled">
                <span class="page-link"><i class="bx bx-chevrons-left"></i></span>
            </li>
        @else
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->url(1) }}" title="Première page"><i class="bx bx-chevrons-left"></i></a>
            </li>
        @endif

        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <li class="page-item disabled">
                <span class="page-link"><i class="bx bx-chevron-left"></i></span>
            </li>
        @else
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bx bx-chevron-left"></i></a>
            </li>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next"><i class="bx bx-chevron-right"></i></a>
            </li>
        @else
            <li class="page-item disabled">
                <span class="page-link"><i class="bx bx-chevron-right"></i></span>
            </li>
        @endif

        {{-- Last Page Link --}}
        @if ($paginator->hasMorePages())
            <li class="page-item">
                <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}" title="Dernière page"><i class="bx bx-chevrons-right"></i></a>
            </li>
        @else
            <li class="page-item disabled">
                <span class="page-link"><i class="bx bx-chevrons-right"></i></span>
            </li>
        @endif
    </ul>
</nav>
@endif
