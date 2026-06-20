@extends('layouts/contentNavbarLayout')

@section('title', 'Tài khoản ngân hàng')

@section('page-style')
<style>
  .bank-accounts-page {
    --bank-border: rgba(67, 89, 113, .14);
    --bank-muted: #697a8d;
  }

  .bank-accounts-page .btn-touch {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    border-radius: .5rem;
    white-space: nowrap;
  }

  .bank-accounts-page .summary-tile,
  .bank-accounts-page .bank-mobile-card,
  .bank-accounts-page .api-panel {
    border: 1px solid var(--bank-border);
    border-radius: .5rem;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }

  .bank-accounts-page .summary-tile {
    height: 100%;
    padding: .95rem;
    background: #fff;
  }

  .bank-accounts-page .summary-label {
    color: var(--bank-muted);
    font-size: .78rem;
  }

  .bank-accounts-page .summary-value {
    font-size: 1.15rem;
    font-weight: 700;
  }

  .bank-accounts-page .bank-chip {
    min-width: 3rem;
    min-height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;
    font-weight: 700;
    font-size: .8rem;
  }

  .bank-accounts-page .bank-chip-vcb {
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
  }

  .bank-accounts-page .bank-chip-acb {
    color: #1548a8;
    background: rgba(3, 169, 244, .12);
  }

  .bank-accounts-page .bank-chip-vpbank {
    color: #6a2aa8;
    background: rgba(105, 108, 255, .12);
  }

  .bank-accounts-page .bank-chip-techcombank {
    color: #b01824;
    background: rgba(255, 62, 29, .12);
  }

  .bank-accounts-page .bank-chip-mbbank {
    color: #0b4f9c;
    background: rgba(3, 169, 244, .12);
  }

  .bank-accounts-page .balance-slot {
    min-width: 128px;
  }

  .bank-accounts-page .token-mask,
  .bank-accounts-page .account-no {
    overflow-wrap: anywhere;
  }

  .bank-accounts-page .mobile-list {
    display: none;
  }

  .bank-accounts-page .bank-mobile-card {
    background: #fff;
    padding: 1rem;
  }

  .bank-accounts-page .mobile-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .65rem;
  }

  .bank-accounts-page .mobile-meta-item {
    border-radius: .5rem;
    background: #f8f9fb;
    padding: .65rem;
    min-width: 0;
  }

  .bank-accounts-page .api-panel {
    display: none;
    padding: 1rem;
    background: #fff;
  }

  .bank-accounts-page .api-panel.is-open {
    display: block;
  }

  .bank-accounts-page textarea.api-output {
    min-height: 138px;
    resize: vertical;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: .86rem;
  }

  @media (max-width: 767.98px) {
    .bank-accounts-page .desktop-table {
      display: none;
    }

    .bank-accounts-page .mobile-list {
      display: grid;
      gap: .85rem;
    }

    .bank-accounts-page .page-actions,
    .bank-accounts-page .page-actions .btn {
      width: 100%;
    }

    .bank-accounts-page .mobile-meta {
      grid-template-columns: 1fr;
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
@endphp

<div id="bankAccountsPage" class="bank-accounts-page">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">API ngân hàng</div>
      <h4 class="mb-1">Tài khoản ngân hàng</h4>
      <div class="text-muted">Quản lý tài khoản ACB/Vietcombank/VPBank/Techcombank/MBBank, lấy token API, xem số dư và lịch sử giao dịch.</div>
    </div>
    <div class="page-actions d-flex flex-column flex-sm-row gap-2">
      <button class="btn btn-outline-secondary btn-touch" type="button" data-refresh-balances>
        <i class="bx bx-refresh"></i> Tải số dư
      </button>
      <a class="btn btn-primary btn-touch" href="{{ route('bank.accounts.create') }}">
        <i class="bx bx-plus-circle"></i> Thêm ngân hàng
      </a>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Tổng tài khoản</div>
        <div class="summary-value">{{ number_format($stats['total']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Đang chạy</div>
        <div class="summary-value text-success">{{ number_format($stats['active'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Tạm dừng</div>
        <div class="summary-value text-secondary">{{ number_format($stats['inactive'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Vietcombank</div>
        <div class="summary-value">{{ number_format($stats['vcb']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">ACB</div>
        <div class="summary-value">{{ number_format($stats['acb']) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">VPBank</div>
        <div class="summary-value">{{ number_format($stats['vpbank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Techcombank</div>
        <div class="summary-value">{{ number_format($stats['techcombank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">MBBank</div>
        <div class="summary-value">{{ number_format($stats['mbbank'] ?? 0) }}</div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
      <div class="summary-tile">
        <div class="summary-label">Có API token</div>
        <div class="summary-value">{{ number_format($stats['has_token']) }}</div>
      </div>
    </div>
  </div>

  <div class="api-panel mb-4" data-api-panel>
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
      <div>
        <h6 class="mb-1">Thông tin API</h6>
        <div class="text-muted small">Copy token hoặc endpoint để tích hợp vào hệ thống của bạn.</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm btn-touch" type="button" data-copy-api>
          <i class="bx bx-copy"></i> Copy
        </button>
        <button class="btn btn-outline-secondary btn-sm btn-touch" type="button" data-close-api>
          <i class="bx bx-x"></i> Đóng
        </button>
      </div>
    </div>
    <textarea class="form-control api-output" data-api-output readonly></textarea>
  </div>

  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-2">
      <div>
        <h5 class="mb-1">Danh sách kết nối</h5>
        <div class="text-muted small">Trang tải nhanh, số dư được lấy sau bằng AJAX để không chờ API ngân hàng.</div>
      </div>
      <div class="d-flex flex-column flex-sm-row gap-2">
        <a class="btn btn-outline-secondary btn-sm btn-touch" href="{{ route('bank.accounts.create', ['bank' => 'vcb']) }}">
          <i class="bx bx-shield-quarter"></i> Kết nối VCB
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-touch" href="{{ route('bank.accounts.create', ['bank' => 'vpbank']) }}">
          <i class="bx bx-buildings"></i> Kết nối VPBank
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-touch" href="{{ route('bank.accounts.create', ['bank' => 'techcombank']) }}">
          <i class="bx bx-building-house"></i> Kết nối Techcombank
        </a>
        <a class="btn btn-outline-secondary btn-sm btn-touch" href="{{ route('bank.accounts.create', ['bank' => 'mbbank']) }}">
          <i class="bx bx-bank"></i> Kết nối MBBank
        </a>
      </div>
    </div>

    <div class="card-body desktop-table table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Ngân hàng</th>
            <th>Số tài khoản</th>
            <th>Chủ tài khoản</th>
            <th>Đăng nhập</th>
            <th>Số dư</th>
            <th>Token</th>
            <th>Trạng thái</th>
            <th>Ngày thêm</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse($accounts as $account)
            <tr data-account-row>
              <td>
                <span class="bank-chip bank-chip-{{ $account->bank }}">{{ $account->bank_badge }}</span>
              </td>
              <td class="fw-semibold account-no">{{ $account->account_no }}</td>
              <td>{{ $account->account_name }}</td>
              <td>{{ $account->username }}</td>
              <td>
                <div class="balance-slot text-muted" data-balance-url="{{ $account->balance_url }}">
                  <span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang chờ
                </div>
              </td>
              <td><span class="token-mask">{{ $maskToken($account->token) }}</span></td>
              <td>
                <span class="badge bg-label-{{ $account->status_badge_class }}">{{ $account->status_text }}</span>
                @php
                  $statusReason = trim($account->status_note ?: $account->last_scan_error);
                @endphp
                @if(!$account->is_active && $statusReason !== '')
                  <div class="small text-danger mt-1" style="max-width: 220px;">{{ $statusReason }}</div>
                @elseif($account->is_active && $account->scan_failure_count > 0 && $account->last_scan_error !== '')
                  <div class="small text-warning mt-1" style="max-width: 220px;">Lỗi gần nhất: {{ $account->last_scan_error }}</div>
                @endif
              </td>
              <td>{{ $account->created_text }}</td>
              <td class="text-end">
                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                  <a class="btn btn-sm btn-outline-secondary btn-touch" href="{{ $account->edit_url }}">
                    <i class="bx bx-edit-alt"></i> Sửa
                  </a>
                  <a class="btn btn-sm btn-outline-secondary btn-touch" href="{{ $account->history_url }}">
                    <i class="bx bx-list-ul"></i> Lịch sử
                  </a>
                  <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-token-url="{{ $account->token_url }}">
                    <i class="bx bx-code-alt"></i> API
                  </button>
                  <button class="btn btn-sm btn-outline-{{ $account->is_active ? 'warning' : 'success' }} btn-touch"
                          type="button"
                          data-status-url="{{ $account->status_url }}"
                          data-status-active="{{ $account->is_active ? '0' : '1' }}">
                    <i class="bx bx-{{ $account->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                    {{ $account->is_active ? 'Dừng' : 'Bật' }}
                  </button>
                  <button class="btn btn-sm btn-outline-danger btn-touch" type="button" data-delete-url="{{ $account->delete_url }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center py-5">
                <div class="mb-2"><i class="bx bx-credit-card fs-1 text-muted"></i></div>
                <div class="fw-semibold">Chưa có tài khoản ngân hàng</div>
                <div class="text-muted mb-3">Kết nối ACB, Vietcombank, VPBank, Techcombank hoặc MBBank để lấy API số dư và giao dịch.</div>
                <a class="btn btn-primary btn-touch" href="{{ route('bank.accounts.create') }}">
                  <i class="bx bx-plus-circle"></i> Thêm ngân hàng
                </a>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body mobile-list">
      @forelse($accounts as $account)
        <div class="bank-mobile-card" data-account-row>
          <div class="d-flex justify-content-between gap-2 mb-3">
            <div class="min-w-0">
              <span class="bank-chip bank-chip-{{ $account->bank }} mb-2">{{ $account->bank_badge }}</span>
              <div class="fw-semibold account-no">{{ $account->account_no }}</div>
              <div class="text-muted small">{{ $account->account_name }}</div>
            </div>
            <div class="text-end">
              <div class="small text-muted">Số dư</div>
              <div class="balance-slot fw-semibold" data-balance-url="{{ $account->balance_url }}">Đang chờ</div>
            </div>
          </div>
          <div class="mobile-meta mb-3">
            <div class="mobile-meta-item">
              <div class="small text-muted">Đăng nhập</div>
              <div>{{ $account->username }}</div>
            </div>
            <div class="mobile-meta-item">
              <div class="small text-muted">Token</div>
              <div class="token-mask">{{ $maskToken($account->token) }}</div>
            </div>
            <div class="mobile-meta-item">
              <div class="small text-muted">Ngày thêm</div>
              <div>{{ $account->created_text }}</div>
            </div>
            <div class="mobile-meta-item">
              <div class="small text-muted">Trạng thái</div>
              <div><span class="badge bg-label-{{ $account->status_badge_class }}">{{ $account->status_text }}</span></div>
              @php
                $statusReason = trim($account->status_note ?: $account->last_scan_error);
              @endphp
              @if(!$account->is_active && $statusReason !== '')
                <div class="small text-danger mt-1">{{ $statusReason }}</div>
              @elseif($account->is_active && $account->scan_failure_count > 0 && $account->last_scan_error !== '')
                <div class="small text-warning mt-1">Lỗi gần nhất: {{ $account->last_scan_error }}</div>
              @endif
            </div>
            <div class="mobile-meta-item">
              <div class="small text-muted">Ngân hàng</div>
              <div>{{ $account->bank_label }}</div>
            </div>
          </div>
          <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary btn-touch" href="{{ $account->edit_url }}">
              <i class="bx bx-edit-alt"></i> Sửa kết nối
            </a>
            <a class="btn btn-outline-secondary btn-touch" href="{{ $account->history_url }}">
              <i class="bx bx-list-ul"></i> Lịch sử giao dịch
            </a>
            <button class="btn btn-outline-primary btn-touch" type="button" data-token-url="{{ $account->token_url }}">
              <i class="bx bx-code-alt"></i> Lấy API
            </button>
            <button class="btn btn-outline-{{ $account->is_active ? 'warning' : 'success' }} btn-touch"
                    type="button"
                    data-status-url="{{ $account->status_url }}"
                    data-status-active="{{ $account->is_active ? '0' : '1' }}">
              <i class="bx bx-{{ $account->is_active ? 'pause-circle' : 'play-circle' }}"></i>
              {{ $account->is_active ? 'Tạm dừng' : 'Kích hoạt lại' }}
            </button>
            <button class="btn btn-outline-danger btn-touch" type="button" data-delete-url="{{ $account->delete_url }}">
              <i class="bx bx-trash"></i> Xóa tài khoản
            </button>
          </div>
        </div>
      @empty
        <div class="bank-mobile-card text-center">
          <div class="mb-2"><i class="bx bx-credit-card fs-1 text-muted"></i></div>
          <div class="fw-semibold">Chưa có tài khoản ngân hàng</div>
          <div class="text-muted mb-3">Kết nối ACB, Vietcombank, VPBank, Techcombank hoặc MBBank để lấy API.</div>
          <a class="btn btn-primary btn-touch" href="{{ route('bank.accounts.create') }}">
            <i class="bx bx-plus-circle"></i> Thêm ngân hàng
          </a>
        </div>
      @endforelse
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const page = document.getElementById('bankAccountsPage');
  if (!page) return;

  const apiPanel = page.querySelector('[data-api-panel]');
  const apiOutput = page.querySelector('[data-api-output]');

  function setBalanceText(el, text, tone) {
    el.classList.remove('text-muted', 'text-danger', 'text-success');
    el.classList.add(tone || 'text-muted');
    el.textContent = text;
  }

  function loadBalance(el) {
    const url = el.dataset.balanceUrl;
    if (!url || el.dataset.loaded === '1') return Promise.resolve();
    el.dataset.loaded = '1';
    el.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang tải';

    return fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(res => res.json())
      .then(data => {
        const ok = data.status === 200 || data.status === '200' || data.ok === true;
        const value = data.SoDu ?? data.balance;
        if (ok && value !== undefined && value !== null) {
          setBalanceText(el, Number(value).toLocaleString('vi-VN') + 'đ', 'text-success');
        } else {
          setBalanceText(el, data.msg || data.message || 'Không lấy được', 'text-danger');
        }
      })
      .catch(() => setBalanceText(el, 'Lỗi kết nối', 'text-danger'));
  }

  function loadBalances(force) {
    const slots = Array.from(page.querySelectorAll('[data-balance-url]'));
    if (force) slots.forEach(el => delete el.dataset.loaded);
    let index = 0;
    const workers = Math.min(2, slots.length);

    function next() {
      if (index >= slots.length) return Promise.resolve();
      return loadBalance(slots[index++]).then(next);
    }

    for (let i = 0; i < workers; i++) next();
  }

  function postJson(url, options) {
    return fetch(url, {
      method: options.method || 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify(options.body || {})
    }).then(async res => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw data;
      return data;
    });
  }

  page.addEventListener('click', function (event) {
    const refreshBtn = event.target.closest('[data-refresh-balances]');
    if (refreshBtn) {
      loadBalances(true);
      return;
    }

    const tokenBtn = event.target.closest('[data-token-url]');
    if (tokenBtn) {
      tokenBtn.disabled = true;
      const oldHtml = tokenBtn.innerHTML;
      tokenBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lấy';

      postJson(tokenBtn.dataset.tokenUrl, {})
        .then(data => {
          apiOutput.value = data.msg || 'Không có dữ liệu API.';
          apiPanel.classList.add('is-open');
          apiPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(data => alert(data.msg || data.message || 'Không lấy được API.'))
        .finally(() => {
          tokenBtn.disabled = false;
          tokenBtn.innerHTML = oldHtml;
        });
      return;
    }

    const deleteBtn = event.target.closest('[data-delete-url]');
    if (deleteBtn) {
      if (!confirm('Xóa tài khoản này? Nếu tài khoản đã có lịch sử giao dịch, hệ thống sẽ chỉ tạm dừng để giữ dữ liệu bank.')) return;
      deleteBtn.disabled = true;

      postJson(deleteBtn.dataset.deleteUrl, { method: 'DELETE' })
        .then(data => {
          alert(data.msg || 'Đã xóa tài khoản.');
          window.location.reload();
        })
        .catch(data => {
          deleteBtn.disabled = false;
          alert(data.msg || data.message || 'Không xóa được tài khoản.');
        });
      return;
    }

    const statusBtn = event.target.closest('[data-status-url]');
    if (statusBtn) {
      const activate = statusBtn.dataset.statusActive === '1';
      if (!confirm(activate ? 'Kích hoạt lại tài khoản ngân hàng này?' : 'Tạm dừng tài khoản này? Scanner, webhook và API sẽ ngừng dùng phiên này.')) return;
      statusBtn.disabled = true;

      postJson(statusBtn.dataset.statusUrl, { method: 'PATCH', body: { is_active: activate ? 1 : 0 } })
        .then(data => {
          alert(data.msg || (activate ? 'Đã kích hoạt lại.' : 'Đã tạm dừng.'));
          window.location.reload();
        })
        .catch(data => {
          statusBtn.disabled = false;
          alert(data.msg || data.message || 'Không cập nhật được trạng thái tài khoản.');
        });
      return;
    }

    if (event.target.closest('[data-close-api]')) {
      apiPanel.classList.remove('is-open');
      return;
    }

    if (event.target.closest('[data-copy-api]')) {
      navigator.clipboard?.writeText(apiOutput.value);
    }
  });

  setTimeout(() => loadBalances(false), 180);
});
</script>
@endsection
