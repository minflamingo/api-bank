@extends('layouts/contentNavbarLayout')

@section('title', 'Lịch sử giao dịch MBBank')

@section('content')
<div data-bank-history-page>
  <div data-bank-history-content>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
      <div>
        <div class="text-muted small mb-1">MBBank</div>
        <h4 class="mb-1">Lịch sử giao dịch</h4>
        <div class="text-muted">Số tài khoản: <span class="fw-semibold">{{ $acc->account }}</span></div>
      </div>
      <a class="btn btn-outline-secondary" href="{{ route('bank.accounts.index') }}">
        <i class="bx bx-arrow-back"></i> Tài khoản ngân hàng
      </a>
    </div>

    @include('payment._history_filters')

    <div class="card">
      <div class="card-body table-responsive">
        <table class="table align-middle mb-0" id="datatable">
          <thead>
            <tr>
              <th>Thời gian</th>
              <th>Loại GD</th>
              <th>Mã GD</th>
              <th>Số tiền</th>
              <th>Người gửi/nhận</th>
              <th>Nội dung</th>
            </tr>
          </thead>
          <tbody>
            @forelse($transactions as $item)
              @php
                $dateText = (string) ($item['_date_text'] ?? '');
                $amount = (int) ($item['_amount'] ?? 0);
                $isCredit = (bool) ($item['_is_credit'] ?? ($amount > 0));
                $reference = (string) ($item['_reference'] ?? ($item['refNo'] ?? ''));
                $description = (string) ($item['_description'] ?? ($item['description'] ?? ''));
              @endphp
              <tr>
                <td>{{ $dateText ?: '-' }}</td>
                <td>
                  @if($isCredit)
                    <span class="text-success">Nhận tiền</span>
                  @else
                    <span class="text-danger">Trừ tiền</span>
                  @endif
                </td>
                <td class="text-primary">{{ $reference }}</td>
                <td class="{{ $isCredit ? 'text-success' : 'text-danger' }}">{{ number_format(abs($amount)) }}đ</td>
                <td>@include('payment._history_party_cell', ['party' => $item['_party_info'] ?? []])</td>
                <td>{{ $description }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-4">Không có dữ liệu giao dịch</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@include('payment._history_scripts')
@endsection
