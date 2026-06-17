@php
$container = 'container-fluid';
$containerNav = 'container-fluid';
$balance = (int) ($user->amount ?? 0);
$timeEnd = (int) ($user->time_end ?? 0);
$daysLeft = $timeEnd > time() ? max(0, (int) ceil(($timeEnd - time()) / 86400)) : 0;
@endphp

@extends('layouts/contentNavbarLayout')

@section('title', 'Tổng quan')

@section('content')
<div class="row g-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
          <div class="text-muted small mb-1">Tổng quan tài khoản</div>
          <h4 class="mb-1">Xin chào, {{ $user->display_name ?: $user->name ?: $user->email }}</h4>
          <div class="text-muted">Quản lý API ngân hàng, nạp tiền và gia hạn gói trong một màn hình.</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
          <a class="btn btn-primary" href="{{ route('client.payin') }}"><i class="bx bx-wallet me-1"></i>Nạp tiền</a>
          <a class="btn btn-outline-primary" href="{{ route('bank.accounts.create') }}"><i class="bx bx-plus-circle me-1"></i>Thêm ngân hàng</a>
          <a class="btn btn-outline-secondary" href="{{ route('client.upgrade') }}"><i class="bx bx-time-five me-1"></i>Gia hạn</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Số dư ví</div>
        <h4 class="mb-0 text-primary">{{ number_format($balance) }} đ</h4>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Tài khoản ngân hàng</div>
        <h4 class="mb-0">{{ number_format($stats['bank_total']) }}</h4>
        <div class="text-muted small">ACB {{ $stats['bank_acb'] }} · VCB {{ $stats['bank_vcb'] }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Token API</div>
        <h4 class="mb-0">{{ number_format($stats['has_token']) }}</h4>
        <div class="text-muted small">Giới hạn {{ $accountLimit }} tài khoản ngân hàng</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Hạn API</div>
        <h4 class="mb-0 {{ $timeEnd > time() ? 'text-success' : 'text-danger' }}">
          {{ $timeEnd > time() ? $daysLeft . ' ngày' : 'Hết hạn' }}
        </h4>
        <div class="text-muted small">{{ $timeEnd > 0 ? date('H:i d-m-Y', $timeEnd) : 'Chưa có hạn' }}</div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Giao dịch nạp gần đây</h5>
        <a href="{{ route('client.payin') }}" class="btn btn-sm btn-outline-primary">Xem nạp tiền</a>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Ngân hàng</th>
                <th>Số tiền</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              @forelse($latestInvoices as $invoice)
                <tr>
                  <td>{{ $invoice->payment_method }}</td>
                  <td class="text-success fw-semibold">{{ number_format($invoice->amount) }} đ</td>
                  <td>{{ date('H:i d-m-Y', $invoice->create_time) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="3" class="text-muted text-center py-4">Chưa có giao dịch nạp tiền.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Bắt đầu nhanh</h5>
      </div>
      <div class="card-body d-grid gap-2">
        <a class="btn btn-outline-primary text-start" href="{{ route('bank.accounts.index') }}">
          <i class="bx bx-credit-card me-1"></i> Quản lý tài khoản ngân hàng
        </a>
        <a class="btn btn-outline-primary text-start" href="{{ route('payment.acb.index') }}">
          <i class="bx bx-transfer me-1"></i> Kết nối ACB
        </a>
        <a class="btn btn-outline-primary text-start" href="{{ route('payment.vcb.index') }}">
          <i class="bx bx-transfer-alt me-1"></i> Kết nối Vietcombank
        </a>
        <a class="btn btn-outline-secondary text-start" href="{{ route('pages-account-settings-account') }}">
          <i class="bx bx-user me-1"></i> Cập nhật tài khoản
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
