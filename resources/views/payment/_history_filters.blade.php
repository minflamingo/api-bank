@php
  $partyName = trim((string)($partyName ?? request('party_name', '')));
  $partyName = preg_replace('/\s+/u', ' ', $partyName) ?: $partyName;
  $totalIn = (int)($totalIn ?? 0);
  $totalOut = (int)($totalOut ?? 0);
  $net = (int)($net ?? ($totalIn - $totalOut));
  $clearQuery = request()->except(['party_name']);
  $clearUrl = url()->current() . (count($clearQuery) ? ('?' . http_build_query($clearQuery)) : '');
@endphp

<div class="card mb-3">
  <div class="card-body">
    <form class="js-bank-party-filter" method="GET" action="{{ url()->current() }}">
      @foreach(request()->except(['party_name']) as $key => $value)
        @if(is_scalar($value))
          <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
      @endforeach
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-5 col-lg-4">
          <label class="form-label">Tên người gửi/nhận</label>
          <input type="search" class="form-control" name="party_name" value="{{ $partyName }}" placeholder="Nhập tên cần tìm" autocomplete="off">
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit"><i class="bx bx-search"></i> Lọc</button>
          @if($partyName !== '')
            <a class="btn btn-outline-secondary js-bank-party-clear" href="{{ $clearUrl }}"><i class="bx bx-x"></i> Xóa lọc</a>
          @endif
        </div>
      </div>
    </form>

    @if($partyName !== '')
      <div class="alert alert-light border mt-3 mb-0">
        <div class="fw-semibold">Tổng theo tên: {{ $partyName }}</div>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <span class="badge bg-label-success">Tiền vào: +{{ number_format($totalIn) }}đ</span>
          <span class="badge bg-label-danger">Tiền ra: -{{ number_format($totalOut) }}đ</span>
          @if($net > 0)
            <span class="badge bg-label-success">Chênh: +{{ number_format($net) }}đ</span>
          @elseif($net < 0)
            <span class="badge bg-label-danger">Chênh: {{ number_format($net) }}đ</span>
          @else
            <span class="badge bg-label-secondary">Chênh: 0đ</span>
          @endif
          <span class="badge bg-label-secondary">{{ number_format(count($transactions ?? [])) }} giao dịch</span>
        </div>
      </div>
    @endif
  </div>
</div>
