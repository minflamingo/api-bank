@php
  $container = 'container-fluid';
  $containerNav = 'container-fluid';
@endphp

@extends('layouts/adminLayout')

@section('title', 'Super Admin - Giám sát ngân hàng')

@section('page-style')
<style>
  .bank-monitor-page .soft-card {
    border: 1px solid rgba(67, 89, 113, .12);
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }
  .bank-monitor-page .metric-card {
    min-height: 112px;
  }
  .bank-monitor-page .bank-dot {
    width: .65rem;
    height: .65rem;
    border-radius: 999px;
    display: inline-block;
  }
  .bank-monitor-page .code-text {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  }
  .bank-monitor-page .table td,
  .bank-monitor-page .table th {
    vertical-align: middle;
  }
  .bank-monitor-page .issue-cell {
    max-width: 520px;
    white-space: normal;
    overflow-wrap: anywhere;
  }
  .bank-monitor-page .action-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .5rem;
  }
  @media (max-width: 767.98px) {
    .bank-monitor-page .page-title { font-size: 1.25rem; }
    .bank-monitor-page .page-actions,
    .bank-monitor-page .page-actions .btn {
      width: 100%;
    }
    .bank-monitor-page .action-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')
@php
  $banks = collect($apibank['banks'] ?? []);
  $healthyAccounts = collect($apibank['healthy_accounts'] ?? []);
  $problemAccounts = collect($apibank['problem_accounts'] ?? []);
  $scanner = (array) ($apibank['scanner'] ?? []);
  $scannerRunning = !empty($scanner['running']);
  $activeTotal = $banks->sum('active');
  $inactiveTotal = $banks->sum('inactive');
  $errorTotal = $banks->sum('scan_errors');
  $warningTotal = $banks->sum('warning');
  $metrics = [
      ['label' => 'Account khỏe', 'value' => number_format($activeTotal), 'hint' => 'Active, không lỗi và không cảnh báo', 'class' => 'text-success', 'icon' => 'bx-play-circle'],
      ['label' => 'Deactive', 'value' => number_format($inactiveTotal), 'hint' => 'Tạm dừng tránh spam bank', 'class' => 'text-warning', 'icon' => 'bx-pause-circle'],
      ['label' => 'Lỗi scan', 'value' => number_format($errorTotal), 'hint' => 'last_scan_status = error', 'class' => 'text-danger', 'icon' => 'bx-error-circle'],
      ['label' => 'Cảnh báo', 'value' => number_format($warningTotal), 'hint' => 'scan_failed_count > 0', 'class' => 'text-info', 'icon' => 'bx-bell'],
  ];
@endphp

<div class="bank-monitor-page">
  @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flashKey => $flashClass)
    @if(session($flashKey))
      <div class="alert alert-{{ $flashClass }} alert-dismissible fade show" role="alert">
        {{ session($flashKey) }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif
  @endforeach

  <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">Nguồn chính APIBank</div>
      <h4 class="page-title mb-1">Giám sát ngân hàng APIBank</h4>
      <div class="text-muted">Trang chính quản trị account ngân hàng, session scan và lỗi worker.</div>
    </div>
    <div class="page-actions d-flex flex-column flex-sm-row gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('admin.bank-monitor') }}">
        <i class="bx bx-refresh me-1"></i>Tải lại
      </a>
      <a class="btn btn-outline-primary" href="https://quanly.3w.com.vn/admin/bank-monitor" target="_blank" rel="noopener">
        <i class="bx bx-link-external me-1"></i>Bản xem lại Quanly
      </a>
      <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">
        <i class="bx bx-shield-quarter me-1"></i>Super Admin
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @foreach($metrics as $metric)
      <div class="col-6 col-xl-3">
        <div class="card soft-card metric-card h-100">
          <div class="card-body d-flex justify-content-between gap-3">
            <div>
              <div class="text-muted small mb-1">{{ $metric['label'] }}</div>
              <h4 class="mb-1 {{ $metric['class'] }}">{{ $metric['value'] }}</h4>
              <div class="text-muted small">{{ $metric['hint'] }}</div>
            </div>
            <span class="avatar-initial rounded bg-label-secondary"><i class="bx {{ $metric['icon'] }}"></i></span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card soft-card mb-4">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h5 class="mb-1">Tổng quan từng ngân hàng</h5>
        <div class="text-muted small">Dữ liệu đọc từ SQL APIBank, không gọi bank thật khi mở trang này.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge {{ $scannerRunning ? 'bg-label-success' : 'bg-label-danger' }}">
          Scanner {{ $scanner['active'] ?? 'unknown' }}
        </span>
        <span class="badge bg-label-secondary">
          Heartbeat {{ isset($scanner['heartbeat_age']) && $scanner['heartbeat_age'] !== null ? ((int) $scanner['heartbeat_age'] . 's') : '-' }}
        </span>
        <span class="badge bg-label-secondary">{{ $scanner['name'] ?? 'apibank-bank-scan.service' }}</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Ngân hàng</th>
            <th>Scanner</th>
            <th>Active</th>
            <th>Deactive</th>
            <th>Lỗi</th>
            <th>Interval</th>
            <th>Sync mới nhất</th>
          </tr>
        </thead>
        <tbody>
          @forelse($banks as $bank)
            @php
              $bad = (int) ($bank['inactive'] ?? 0) > 0 || (int) ($bank['scan_errors'] ?? 0) > 0;
              $scanStatus = (string) ($bank['scan_status'] ?? '');
              $scanRunning = !empty($bank['scan_running']);
              $scanBadge = 'bg-label-secondary';
              $scanLabel = 'Không có account';
              if (!$scannerRunning || $scanStatus === 'scanner_stopped') {
                  $scanBadge = 'bg-label-danger';
                  $scanLabel = 'Scanner tắt';
              } elseif ($scanRunning) {
                  $scanBadge = 'bg-label-success';
                  $scanLabel = 'Đang chạy';
              } elseif ((int) ($bank['scan_active'] ?? 0) > 0) {
                  $scanBadge = 'bg-label-info';
                  $scanLabel = 'Đang chờ lượt';
              } elseif ((int) ($bank['inactive'] ?? 0) > 0) {
                  $scanBadge = 'bg-label-warning';
                  $scanLabel = 'Tạm dừng';
              }
            @endphp
            <tr>
              <td>
                <span class="bank-dot {{ $bad ? 'bg-danger' : 'bg-success' }} me-2"></span>
                <span class="fw-semibold">{{ $bank['label'] }}</span>
                <div class="small text-muted code-text">{{ $bank['table'] }}</div>
              </td>
              <td>
                <span class="badge {{ $scanBadge }}">{{ $scanLabel }}</span>
                <div class="small text-muted mt-1">
                  {{ number_format((int) ($bank['scan_active'] ?? 0)) }} account quét · due {{ number_format((int) ($bank['scan_due'] ?? 0)) }}
                </div>
                <div class="small text-muted">Next: {{ $bank['next_scan_at'] ?: '-' }}</div>
              </td>
              <td><span class="badge bg-label-success">{{ number_format((int) ($bank['active'] ?? 0)) }}</span></td>
              <td><span class="badge {{ (int) ($bank['inactive'] ?? 0) > 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ number_format((int) ($bank['inactive'] ?? 0)) }}</span></td>
              <td><span class="badge {{ (int) ($bank['scan_errors'] ?? 0) > 0 ? 'bg-label-danger' : 'bg-label-secondary' }}">{{ number_format((int) ($bank['scan_errors'] ?? 0)) }}</span></td>
              <td>{{ $bank['min_interval'] ?: '-' }}@if($bank['max_interval'] && $bank['max_interval'] !== $bank['min_interval']) - {{ $bank['max_interval'] }}@endif s</td>
              <td>{{ $bank['latest_synced_at'] ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu ngân hàng.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card soft-card mb-4">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h5 class="mb-1">Account khỏe</h5>
        <div class="text-muted small">Tối đa 200 account active, không lỗi scan và không cảnh báo, sắp xếp theo lần sync mới nhất.</div>
      </div>
      <span class="badge bg-label-success">{{ number_format($healthyAccounts->count()) }} account hiển thị</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Bank</th>
            <th>Account</th>
            <th>User</th>
            <th>Sync</th>
            <th>Next scan</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse($healthyAccounts as $row)
            @php
              $bankCode = (string) ($row['bank'] ?? '');
              $rowId = (int) ($row['id'] ?? 0);
            @endphp
            <tr>
              <td><span class="badge bg-label-success">{{ $row['bank_label'] ?? strtoupper($bankCode) }}</span></td>
              <td>
                <div class="fw-semibold">{{ $row['account_no'] ?: '-' }}</div>
                <div class="small text-muted">{{ $row['name'] ?: $row['login_name'] ?: '-' }}</div>
              </td>
              <td>#{{ (int) ($row['user_id'] ?? 0) }}</td>
              <td>{{ $row['last_synced_at'] ?: '-' }}</td>
              <td>{{ $row['next_scan_at'] ?: '-' }}</td>
              <td class="text-end">
                @if($rowId > 0 && $bankCode !== '')
                  <div class="action-grid">
                    <form method="POST" action="{{ route('admin.bank-monitor.accounts.status', [$bankCode, $rowId]) }}" onsubmit="return confirm('Tạm dừng account {{ $row['account_no'] ?: ('#' . $rowId) }}?');">
                      @csrf
                      <input type="hidden" name="active" value="0">
                      <input type="hidden" name="note" value="Super Admin tạm dừng account khỏe từ APIBank Monitor">
                      <button class="btn btn-sm btn-outline-warning w-100" type="submit">
                        <i class="bx bx-pause me-1"></i>Dừng
                      </button>
                    </form>
                    <a class="btn btn-sm btn-outline-primary w-100" href="{{ route('admin.bank-accounts.index', ['bank' => $bankCode, 'q' => $row['account_no'] ?: $row['login_name']]) }}">
                      <i class="bx bx-search me-1"></i>Mở account
                    </a>
                  </div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có account khỏe.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card soft-card">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h5 class="mb-1">Account cần xử lý</h5>
        <div class="text-muted small">Account deactive, lỗi scan hoặc có lỗi liên tiếp. Account lỗi quá 3 lần nên giữ deactive để tránh bị bank chặn.</div>
      </div>
      <span class="badge bg-label-secondary">{{ number_format($problemAccounts->count()) }} account</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Bank</th>
            <th>Account</th>
            <th>User</th>
            <th>Trạng thái</th>
            <th>Lỗi</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse($problemAccounts as $row)
            @php
              $active = (int) ($row['is_active'] ?? 1) === 1;
              $bankCode = (string) ($row['bank'] ?? '');
              $rowId = (int) ($row['id'] ?? 0);
              $lastScanStatus = (string) ($row['last_scan_status'] ?? '');
              $lastScanError = (string) ($row['last_scan_error'] ?? '');
              $statusNote = (string) ($row['status_note'] ?? '');
              $failedCount = (int) ($row['scan_failed_count'] ?? 0);
              $packageExpired = str_contains(strtolower($lastScanError), 'package expired')
                  || str_contains(mb_strtolower($statusNote), 'gói api hết hạn')
                  || $lastScanStatus === 'package_expired';
              $statusLabel = 'Active';
              $statusClass = 'bg-label-success';
              if ($packageExpired) {
                  $statusLabel = 'Gói hết hạn';
                  $statusClass = 'bg-label-danger';
              } elseif (!$active) {
                  $statusLabel = 'Deactive';
                  $statusClass = 'bg-label-warning';
              } elseif ($lastScanStatus === 'error') {
                  $statusLabel = 'Lỗi scan';
                  $statusClass = 'bg-label-danger';
              } elseif ($failedCount > 0) {
                  $statusLabel = 'Cảnh báo';
                  $statusClass = 'bg-label-info';
              }
            @endphp
            <tr>
              <td><span class="badge bg-label-primary">{{ $row['bank_label'] ?? strtoupper($bankCode) }}</span></td>
              <td>
                <div class="fw-semibold">{{ $row['account_no'] ?: '-' }}</div>
                <div class="small text-muted">{{ $row['name'] ?: $row['login_name'] ?: '-' }}</div>
              </td>
              <td>#{{ (int) ($row['user_id'] ?? 0) }}</td>
              <td>
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                <div class="small text-muted mt-1">Lỗi liên tiếp: {{ $failedCount }}</div>
              </td>
              <td class="issue-cell">
                <div class="{{ $lastScanStatus === 'error' ? 'text-danger' : 'text-muted' }}">
                  {{ $row['last_scan_error'] ?: $row['status_note'] ?: '-' }}
                </div>
                <div class="small text-muted">Sync: {{ $row['last_synced_at'] ?: '-' }} · Next: {{ $row['next_scan_at'] ?: '-' }}</div>
              </td>
              <td class="text-end">
                @if($rowId > 0 && $bankCode !== '')
                  <div class="action-grid">
                    <form method="POST" action="{{ route('admin.bank-monitor.accounts.status', [$bankCode, $rowId]) }}" onsubmit="return confirm('{{ $active ? 'Tạm dừng' : 'Bật lại' }} account {{ $row['account_no'] ?: ('#' . $rowId) }}?');">
                      @csrf
                      <input type="hidden" name="active" value="{{ $active ? 0 : 1 }}">
                      <input type="hidden" name="note" value="{{ $active ? 'Super Admin tạm dừng từ APIBank Monitor' : 'Super Admin bật lại từ APIBank Monitor' }}">
                      <button class="btn btn-sm {{ $active ? 'btn-outline-warning' : 'btn-outline-success' }} w-100" type="submit">
                        <i class="bx {{ $active ? 'bx-pause' : 'bx-play' }} me-1"></i>{{ $active ? 'Dừng' : 'Bật lại' }}
                      </button>
                    </form>
                    <a class="btn btn-sm btn-outline-primary w-100" href="{{ route('admin.bank-accounts.index', ['bank' => $bankCode, 'q' => $row['account_no'] ?: $row['login_name']]) }}">
                      <i class="bx bx-search me-1"></i>Mở account
                    </a>
                  </div>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Không có account lỗi hoặc bị dừng.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
