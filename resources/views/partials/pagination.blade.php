<div class="collection-pagination"><p role="status">{{ $paginator->count() ? 'Showing '.$paginator->firstItem().'–'.$paginator->lastItem().' of '.$paginator->total().' '.$label : 'No '.$label.' on this page' }}</p>
@if($paginator->isEmpty() && $paginator->total())<a href="{{ $paginator->url(1) }}">Return to the first page</a>@endif
@if($paginator->hasPages())<nav aria-label="{{ ucfirst($label) }} pagination">
@if($paginator->onFirstPage())<span aria-disabled="true">Previous</span>@else<a rel="prev" href="{{ $paginator->previousPageUrl() }}">← Previous</a>@endif
<span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a rel="next" href="{{ $paginator->nextPageUrl() }}">Next →</a>@else<span aria-disabled="true">Next</span>@endif
</nav>@endif</div>
