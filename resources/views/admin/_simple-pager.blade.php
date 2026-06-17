@if($items->hasPages())
  <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-top">
    <div class="text-muted small">Trang {{ $items->currentPage() }}</div>
    <div class="btn-group btn-group-sm">
      @if($items->onFirstPage())
        <button class="btn btn-outline-secondary" type="button" disabled>Trước</button>
      @else
        <a class="btn btn-outline-secondary" href="{{ $items->previousPageUrl() }}">Trước</a>
      @endif

      @if($items->hasMorePages())
        <a class="btn btn-outline-secondary" href="{{ $items->nextPageUrl() }}">Sau</a>
      @else
        <button class="btn btn-outline-secondary" type="button" disabled>Sau</button>
      @endif
    </div>
  </div>
@endif
