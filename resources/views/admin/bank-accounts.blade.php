@extends('layouts/adminLayout')

@section('title', 'Tài khoản ngân hàng/API')

@section('page-style')
<style>
  .admin-bank-page {
    --admin-bank-border: rgba(67, 89, 113, .14);
    --admin-bank-muted: #697a8d;
  }

  .admin-bank-page .btn-touch,
  .admin-bank-page .form-control,
  .admin-bank-page .form-select {
    min-height: 42px;
    border-radius: .5rem;
  }

  .admin-bank-page .btn-touch {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    white-space: nowrap;
  }

  .admin-bank-page .metric-tile,
  .admin-bank-page .admin-bank-card {
    border: 1px solid var(--admin-bank-border);
    border-radius: .5rem;
    background: #fff;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }

  .admin-bank-page .metric-tile {
    height: 100%;
    padding: .95rem;
  }

  .admin-bank-page .metric-label {
    color: var(--admin-bank-muted);
    font-size: .78rem;
  }

  .admin-bank-page .metric-value {
    font-size: 1.15rem;
    font-weight: 700;
  }

  .admin-bank-page .bank-chip,
  .admin-bank-page .status-chip {
    min-height: 1.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .45rem;
    padding: .15rem .55rem;
    font-size: .76rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .admin-bank-page .bank-chip-acb {
    color: #1548a8;
    background: rgba(3, 169, 244, .12);
  }

  .admin-bank-page .bank-chip-vcb {
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
  }

  .admin-bank-page .bank-chip-vpbank {
    color: #6a2aa8;
    background: rgba(105, 108, 255, .12);
  }

  .admin-bank-page .bank-chip-techcombank {
    color: #b01824;
    background: rgba(255, 62, 29, .12);
  }

  .admin-bank-page .bank-chip-mbbank {
    color: #0b4f9c;
    background: rgba(3, 169, 244, .12);
  }

  .admin-bank-page .status-system {
    color: #8a5a00;
    background: rgba(255, 171, 0, .14);
  }

  .admin-bank-page .status-customer {
    color: #566a7f;
    background: #eef0f4;
  }

  .admin-bank-page .status-ok {
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
  }

  .admin-bank-page .status-missing {
    color: #b42318;
    background: rgba(255, 62, 29, .1);
  }

  .admin-bank-page .account-text,
  .admin-bank-page .token-text,
  .admin-bank-page .owner-text {
    overflow-wrap: anywhere;
  }

  .admin-bank-page .filter-bar {
    border: 1px solid var(--admin-bank-border);
    border-radius: .5rem;
    padding: 1rem;
    background: #fff;
  }

  .admin-bank-page .mobile-list {
    display: none;
  }

  @media (max-width: 767.98px) {
    .admin-bank-page .desktop-table {
      display: none;
    }

    .admin-bank-page .mobile-list {
      display: grid;
      gap: .85rem;
    }

    .admin-bank-page .page-actions,
    .admin-bank-page .page-actions .btn,
    .admin-bank-page .filter-bar .btn {
      width: 100%;
    }
  }
</style>
@endsection

@section('content')
@php
  $maskToken = function ($token) {
      $token = (string) $token;
      if ($token === '') return 'Chưa có token';
      if (strlen($token) <= 10) return $token;
      return substr($token, 0, 6) . '...' . substr($token, -4);
  };
  $ownerLabel = function ($account) {
      if ($account->user_id === null) return 'Hệ thống';
      return trim(($account->owner_email ?: $account->owner_name) . ' #' . $account->user_id);
  };
@endphp

<div class="admin-bank-page">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">Super Admin</div>
      <h4 class="mb-1">Tài khoản ngân hàng/API</h4>
      <div class="text-muted">Theo dõi ACB/VCB/VPBank/Techcombank/MBBank toàn hệ thống, phân biệt account khách và account hệ thống nhận nạp.</div>
    </div>
    <div class="page-actions d-flex flex-column flex-sm-row gap-2">
      <a class="btn btn-outline-secondary btn-touch" href="{{ route('bank.accounts.index') }}">
        <i class="bx bx-window-open"></i> Khu khách
      </a>
      <a class="btn btn-primary btn-touch" href="{{ route('admin.recharge-settings.edit') }}">
        <i class="bx bx-cog"></i> Cấu hình nhận nạp
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">Tổng account</div>
        <div class="metric-value">{{ number_format($stats['total']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">ACB</div>
        <div class="metric-value">{{ number_format($stats['acb']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">Vietcombank</div>
        <div class="metric-value">{{ number_format($stats['vcb']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">VPBank</div>
        <div class="metric-value">{{ number_format($stats['vpbank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">Techcombank</div>
        <div class="metric-value">{{ number_format($stats['techcombank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">MBBank</div>
        <div class="metric-value">{{ number_format($stats['mbbank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="metric-tile">
        <div class="metric-label">Có token / hệ thống</div>
        <div class="metric-value">{{ number_format($stats['has_token']) }} / {{ number_format($stats['system']) }}</div>
      </div>
    </div>
  </div>

  <form class="filter-bar mb-4" method="GET" action="{{ route('admin.bank-accounts.index') }}">
    <div class="row g-3 align-items-end">
      <div class="col-12 col-lg-6">
        <label class="form-label" for="q">Tìm kiếm</label>
        <input class="form-control" id="q" name="q" value="{{ $keyword }}" placeholder="Email, tên user, STK, tên chủ tài khoản">
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <label class="form-label" for="bank">Ngân hàng</label>
        <select class="form-select" id="bank" name="bank">
          <option value="all" @selected($bankFilter === 'all')>Tất cả</option>
          <option value="acb" @selected($bankFilter === 'acb')>ACB</option>
          <option value="vcb" @selected($bankFilter === 'vcb')>Vietcombank</option>
          <option value="vpbank" @selected($bankFilter === 'vpbank')>VPBank</option>
          <option value="techcombank" @selected($bankFilter === 'techcombank')>Techcombank</option>
          <option value="mbbank" @selected($bankFilter === 'mbbank')>MBBank</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2">
        <button class="btn btn-primary btn-touch flex-fill" type="submit">
          <i class="bx bx-search"></i> Lọc
        </button>
        <a class="btn btn-outline-secondary btn-touch" href="{{ route('admin.bank-accounts.index') }}">
          <i class="bx bx-reset"></i>
        </a>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-2">
      <div>
        <h5 class="mb-1">Danh sách kết nối</h5>
        <div class="text-muted small">
          Hiển thị {{ number_format($accounts->firstItem() ?: 0) }}-{{ number_format($accounts->lastItem() ?: 0) }} trên {{ number_format($accounts->total()) }} kết quả.
        </div>
      </div>
      @if($receiverBank)
        <div class="text-muted small">
          Account nhận nạp: {{ $receiverBank->receiver_bank_type ?: 'ACB' }} #{{ (int) $receiverBank->receiver_account_id }} · {{ $receiverBank->accountNumber }}
        </div>
      @endif
    </div>

    <div class="card-body desktop-table table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Ngân hàng</th>
            <th>Tài khoản</th>
            <th>Owner</th>
            <th>Đăng nhập</th>
            <th>Token</th>
            <th>Session</th>
            <th>Ngày thêm</th>
            <th class="text-end">Vai trò</th>
          </tr>
        </thead>
        <tbody>
          @forelse($accounts as $account)
            <tr>
              <td><span class="bank-chip bank-chip-{{ $account->bank }}">{{ $account->bank_badge }}</span></td>
              <td>
                <div class="fw-semibold account-text">{{ $account->account_no }}</div>
                <div class="text-muted small account-text">{{ $account->account_name }}</div>
              </td>
              <td>
                <div class="owner-text">{{ $ownerLabel($account) }}</div>
                @if($account->owner_role !== null)
                  <div class="text-muted small">Role {{ $account->owner_role }}</div>
                @endif
              </td>
              <td class="account-text">{{ $account->login_name }}</td>
              <td><span class="token-text">{{ $maskToken($account->token) }}</span></td>
              <td>
                <span class="status-chip {{ $account->has_session ? 'status-ok' : 'status-missing' }}">
                  {{ $account->has_session ? 'Có session' : 'Thiếu session' }}
                </span>
              </td>
              <td>{{ $account->created_text }}</td>
              <td class="text-end">
                @if($account->is_receiver)
                  <span class="status-chip status-system">Nhận nạp</span>
                @elseif($account->user_id === null)
                  <span class="status-chip status-system">Hệ thống</span>
                @else
                  <span class="status-chip status-customer">Khách hàng</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="mb-2"><i class="bx bx-credit-card fs-1 text-muted"></i></div>
                <div class="fw-semibold">Chưa có tài khoản ngân hàng</div>
                <div class="text-muted">Chưa tìm thấy kết nối nào theo bộ lọc hiện tại.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body mobile-list">
      @forelse($accounts as $account)
        <div class="admin-bank-card p-3">
          <div class="d-flex justify-content-between gap-2 mb-3">
            <div class="min-w-0">
              <span class="bank-chip bank-chip-{{ $account->bank }} mb-2">{{ $account->bank_badge }}</span>
              <div class="fw-semibold account-text">{{ $account->account_no }}</div>
              <div class="text-muted small account-text">{{ $account->account_name }}</div>
            </div>
            <div class="text-end">
              @if($account->is_receiver)
                <span class="status-chip status-system">Nhận nạp</span>
              @elseif($account->user_id === null)
                <span class="status-chip status-system">Hệ thống</span>
              @else
                <span class="status-chip status-customer">Khách</span>
              @endif
            </div>
          </div>
          <div class="row g-2 small">
            <div class="col-12">
              <div class="text-muted">Owner</div>
              <div class="owner-text">{{ $ownerLabel($account) }}</div>
            </div>
            <div class="col-6">
              <div class="text-muted">Đăng nhập</div>
              <div class="account-text">{{ $account->login_name }}</div>
            </div>
            <div class="col-6">
              <div class="text-muted">Token</div>
              <div class="token-text">{{ $maskToken($account->token) }}</div>
            </div>
            <div class="col-6">
              <div class="text-muted">Session</div>
              <span class="status-chip {{ $account->has_session ? 'status-ok' : 'status-missing' }}">
                {{ $account->has_session ? 'Có session' : 'Thiếu session' }}
              </span>
            </div>
            <div class="col-6">
              <div class="text-muted">Ngày thêm</div>
              <div>{{ $account->created_text }}</div>
            </div>
          </div>
        </div>
      @empty
        <div class="admin-bank-card text-center p-4">
          <div class="mb-2"><i class="bx bx-credit-card fs-1 text-muted"></i></div>
          <div class="fw-semibold">Chưa có tài khoản ngân hàng</div>
          <div class="text-muted">Chưa tìm thấy kết nối nào theo bộ lọc hiện tại.</div>
        </div>
      @endforelse
    </div>

    @if($accounts->hasPages())
      <div class="card-footer">
        {{ $accounts->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
