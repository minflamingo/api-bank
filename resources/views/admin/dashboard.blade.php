@php
  $container = 'container-fluid';
  $containerNav = 'container-fluid';
@endphp

@extends('layouts/adminLayout')

@section('title', 'Super Admin')

@section('page-style')
<style>
  .super-admin-page {
    --admin-border: rgba(67, 89, 113, .14);
  }

  .super-admin-page .soft-card {
    border: 1px solid var(--admin-border);
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }

  .super-admin-page .stat-card {
    min-height: 118px;
  }

  .super-admin-page .section-card {
    min-height: 128px;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }

  .super-admin-page .section-card:hover {
    border-color: rgba(105, 108, 255, .42);
    box-shadow: 0 .45rem 1rem rgba(67, 89, 113, .08);
    transform: translateY(-1px);
  }

  .super-admin-page .stat-icon {
    width: 2.25rem;
    height: 2.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;
    font-size: 1.2rem;
  }

  .super-admin-page .cell-wrap {
    max-width: 360px;
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .super-admin-page .table th {
    font-size: .78rem;
  }

  @media (max-width: 767.98px) {
    .super-admin-page .page-actions,
    .super-admin-page .page-actions .btn,
    .super-admin-page .search-form,
    .super-admin-page .search-form .form-control {
      width: 100%;
    }
  }
</style>
@endsection

@section('content')
@php
  $money = fn ($value) => number_format((int) $value) . ' đ';
  $timestamp = fn ($value) => $value ? date('H:i d-m-Y', (int) $value) : '-';
  $dateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('H:i d-m-Y') : '-';
  $roleMeta = function ($role) use ($roleLabels) {
      return $roleLabels[(int) $role] ?? ['label' => 'Role ' . $role, 'class' => 'bg-label-secondary'];
  };
  $section = $section ?? 'overview';
  $sectionRoutes = [
      'overview' => 'admin.dashboard',
      'users' => 'admin.users',
      'sessions' => 'admin.sessions',
      'recharges' => 'admin.recharges',
      'wallet' => 'admin.wallet',
      'logs' => 'admin.logs',
  ];
  $sectionMetaMap = [
      'overview' => ['label' => 'Super Admin', 'hint' => 'Tổng quan hệ thống, chỉ giữ số liệu và lối tắt chính.'],
      'users' => ['label' => 'User đã đăng ký', 'hint' => 'Danh sách user, tìm theo ID, tên, email hoặc số điện thoại.'],
      'sessions' => ['label' => 'Đang đăng nhập', 'hint' => 'Session đăng nhập, sắp xếp theo hoạt động mới nhất.'],
      'recharges' => ['label' => 'Lịch sử nạp tiền', 'hint' => 'Giao dịch nạp từ các ngân hàng nhận nạp, mới nhất ở trên cùng.'],
      'wallet' => ['label' => 'Ví & ledger', 'hint' => 'Tặng tiền có ghi sổ và kiểm tra số dư lệch ledger.'],
      'logs' => ['label' => 'Nhật ký hệ thống', 'hint' => 'Đăng nhập, gia hạn, cấu hình và thao tác vận hành.'],
  ];
  $sectionMeta = $sectionMetaMap[$section] ?? $sectionMetaMap['overview'];
  $currentSectionRoute = route($sectionRoutes[$section] ?? 'admin.dashboard');
  $tiles = [
      ['label' => 'Tổng user', 'value' => number_format($stats['users_total']), 'hint' => number_format($stats['users_verified']) . ' đã xác minh', 'icon' => 'bx-user', 'class' => 'bg-label-primary text-primary'],
      ['label' => 'User mới hôm nay', 'value' => number_format($stats['users_today']), 'hint' => number_format($stats['users_pending']) . ' chờ kích hoạt', 'icon' => 'bx-user-plus', 'class' => 'bg-label-info text-info'],
      ['label' => 'Đang đăng nhập', 'value' => number_format($stats['online_sessions']), 'hint' => 'Trong ' . $sessionLifetime . ' phút gần nhất', 'icon' => 'bx-log-in-circle', 'class' => 'bg-label-success text-success'],
      ['label' => 'Nạp hôm nay', 'value' => $money($stats['recharge_today']), 'hint' => number_format($stats['invoices_today']) . ' giao dịch', 'icon' => 'bx-wallet', 'class' => 'bg-label-warning text-warning'],
      ['label' => 'Tổng nạp', 'value' => $money($stats['recharge_total']), 'hint' => 'Toàn hệ thống', 'icon' => 'bx-line-chart', 'class' => 'bg-label-success text-success'],
      ['label' => 'Số dư ví user', 'value' => $money($stats['wallet_balance']), 'hint' => 'Tổng số dư hiện có', 'icon' => 'bx-money', 'class' => 'bg-label-primary text-primary'],
      ['label' => 'Tài khoản API', 'value' => number_format($stats['api_accounts']), 'hint' => number_format($stats['api_tokens']) . ' có token', 'icon' => 'bx-credit-card', 'class' => 'bg-label-info text-info'],
      ['label' => 'Gói còn hạn', 'value' => number_format($stats['packages_active']), 'hint' => 'User còn hạn API', 'icon' => 'bx-time-five', 'class' => 'bg-label-secondary text-secondary'],
  ];
  $sectionCards = [
      ['label' => 'User đã đăng ký', 'value' => number_format($stats['users_total']), 'hint' => number_format($stats['users_today']) . ' user mới hôm nay', 'icon' => 'bx-user', 'class' => 'bg-label-primary text-primary', 'href' => route('admin.users')],
      ['label' => 'Đang đăng nhập', 'value' => number_format($stats['online_sessions']), 'hint' => 'Trong ' . $sessionLifetime . ' phút gần nhất', 'icon' => 'bx-log-in-circle', 'class' => 'bg-label-success text-success', 'href' => route('admin.sessions')],
      ['label' => 'Lịch sử nạp tiền', 'value' => $money($stats['recharge_today']), 'hint' => number_format($stats['invoices_today']) . ' giao dịch hôm nay', 'icon' => 'bx-wallet', 'class' => 'bg-label-warning text-warning', 'href' => route('admin.recharges')],
      ['label' => 'Ví & ledger', 'value' => $money($stats['wallet_balance']), 'hint' => 'Tặng tiền và audit lệch ví', 'icon' => 'bx-money', 'class' => 'bg-label-primary text-primary', 'href' => route('admin.wallet')],
      ['label' => 'Nhật ký hệ thống', 'value' => 'Logs', 'hint' => 'Theo dõi thao tác vận hành', 'icon' => 'bx-list-ul', 'class' => 'bg-label-secondary text-secondary', 'href' => route('admin.logs')],
  ];
@endphp

<div class="super-admin-page">
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
      <div class="text-muted small mb-1">Quản trị hệ thống</div>
      <h4 class="mb-1">{{ $sectionMeta['label'] }}</h4>
      <div class="text-muted">{{ $sectionMeta['hint'] }}</div>
    </div>
    <div class="page-actions d-flex flex-column flex-sm-row gap-2">
      <a class="btn btn-outline-secondary" href="{{ $currentSectionRoute }}">
        <i class="bx bx-refresh me-1"></i>Tải lại
      </a>
      <a class="btn btn-outline-primary" href="{{ route('admin.recharge-settings.edit') }}">
        <i class="bx bx-cog me-1"></i>Cấu hình nạp tiền
      </a>
    </div>
  </div>

  @if($section === 'overview')
    <div class="row g-3 mb-4">
      @foreach($tiles as $tile)
        <div class="col-sm-6 col-xl-3">
          <div class="card soft-card stat-card h-100">
            <div class="card-body d-flex justify-content-between gap-3">
              <div>
                <div class="text-muted small mb-1">{{ $tile['label'] }}</div>
                <h4 class="mb-1">{{ $tile['value'] }}</h4>
                <div class="text-muted small">{{ $tile['hint'] }}</div>
              </div>
              <span class="stat-icon {{ $tile['class'] }}"><i class="bx {{ $tile['icon'] }}"></i></span>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="row g-3">
      @foreach($sectionCards as $card)
        <div class="col-md-6 col-xl-3">
          <a class="card soft-card section-card h-100 text-reset text-decoration-none" href="{{ $card['href'] }}">
            <div class="card-body d-flex justify-content-between gap-3">
              <div>
                <div class="text-muted small mb-1">{{ $card['label'] }}</div>
                <h5 class="mb-1">{{ $card['value'] }}</h5>
                <div class="text-muted small">{{ $card['hint'] }}</div>
              </div>
              <span class="stat-icon {{ $card['class'] }}"><i class="bx {{ $card['icon'] }}"></i></span>
            </div>
          </a>
        </div>
      @endforeach
    </div>
  @endif

  @if($section === 'users')
    <div class="card soft-card" id="users">
      <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
          <h5 class="mb-1">User đã đăng ký</h5>
          <div class="text-muted small">Danh sách mới nhất, tìm theo ID, tên, email hoặc số điện thoại.</div>
        </div>
        <form class="search-form d-flex flex-column flex-sm-row gap-2" method="GET" action="{{ route('admin.users') }}">
          <input class="form-control" name="q" value="{{ $search }}" placeholder="Tìm user">
          <button class="btn btn-primary" type="submit"><i class="bx bx-search me-1"></i>Tìm</button>
          @if($search !== '')
            <a class="btn btn-outline-secondary" href="{{ route('admin.users') }}">Xóa</a>
          @endif
        </form>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Role</th>
              <th>Số dư</th>
              <th>Tổng nạp</th>
              <th>Hạn API</th>
              <th>IP</th>
              <th>Ngày đăng ký</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $row)
              @php
                $meta = $roleMeta($row->role);
                $timeEnd = (int) ($row->time_end ?? 0);
              @endphp
              <tr>
                <td class="fw-semibold">#{{ $row->id }}</td>
                <td>
                  <div class="fw-semibold">{{ $row->display_name ?: $row->name ?: 'User #' . $row->id }}</div>
                  <div class="text-muted small">{{ $row->email }}</div>
                  @if($row->phone)
                    <div class="text-muted small">{{ $row->phone }}</div>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                  @if((int) $row->banned === 1)
                    <span class="badge bg-label-danger">Bị khóa</span>
                  @endif
                </td>
                <td class="fw-semibold">{{ $money($row->amount) }}</td>
                <td>{{ $money($row->total_paid) }}</td>
                <td>
                  @if($timeEnd > time())
                    <span class="text-success">{{ $timestamp($timeEnd) }}</span>
                  @else
                    <span class="text-muted">Hết hạn/chưa có</span>
                  @endif
                </td>
                <td>{{ $row->ip ?: '-' }}</td>
                <td>{{ $dateTime($row->created_at) }}</td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-success" href="{{ route('admin.wallet', ['user_id' => $row->id]) }}">
                      <i class="bx bx-money me-1"></i>Tặng tiền
                    </a>
                    @if((int) $row->id === (int) auth()->id())
                      <span class="badge bg-label-secondary align-self-center">Đang dùng</span>
                    @else
                      <form method="POST" action="{{ route('admin.users.impersonate', $row->id) }}" class="mb-0" onsubmit="return confirm('Đăng nhập dưới dạng user #{{ $row->id }}?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                          <i class="bx bx-log-in-circle me-1"></i>Vào vai
                        </button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-muted text-center py-4">Không có user phù hợp.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @include('admin._simple-pager', ['items' => $users])
    </div>
  @endif

  @if($section === 'sessions')
    <div class="card soft-card" id="sessions">
      <div class="card-header">
        <h5 class="mb-1">Đang đăng nhập</h5>
        <div class="text-muted small">Dữ liệu từ bảng session, sắp xếp theo hoạt động mới nhất.</div>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>User</th>
              <th>Trạng thái</th>
              <th>IP</th>
              <th>Hoạt động</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sessions as $session)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $session->name ?: 'User #' . $session->user_id }}</div>
                  <div class="text-muted small">{{ $session->email ?: '-' }}</div>
                  <div class="text-muted small cell-wrap">{{ \Illuminate\Support\Str::limit((string) $session->user_agent, 64) }}</div>
                </td>
                <td>
                  @if((int) $session->last_activity >= $activeSince)
                    <span class="badge bg-label-success">Online</span>
                  @else
                    <span class="badge bg-label-secondary">Gần đây</span>
                  @endif
                </td>
                <td>{{ $session->ip_address ?: '-' }}</td>
                <td>{{ $timestamp($session->last_activity) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted text-center py-4">Chưa có session đăng nhập.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @include('admin._simple-pager', ['items' => $sessions])
    </div>
  @endif

  @if($section === 'wallet')
    @if(!$ledgerStats['available'])
      <div class="alert alert-warning">
        Bảng <code>wallet_ledgers</code> chưa tồn tại. Hãy chạy migration trước khi tặng tiền hoặc audit ledger.
      </div>
    @else
      @if(($ledgerStats['baseline_created'] ?? 0) > 0)
        <div class="alert alert-info">
          Đã tạo số dư khởi tạo ledger cho {{ number_format($ledgerStats['baseline_created']) }} user chưa có baseline.
        </div>
      @endif

      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
          <div class="card soft-card stat-card h-100">
            <div class="card-body">
              <div class="text-muted small mb-1">Tổng ví user</div>
              <h4 class="mb-1">{{ $money($ledgerStats['wallet_total']) }}</h4>
              <div class="text-muted small">Theo bảng users</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card soft-card stat-card h-100">
            <div class="card-body">
              <div class="text-muted small mb-1">Tổng ledger</div>
              <h4 class="mb-1">{{ $money($ledgerStats['ledger_total']) }}</h4>
              <div class="text-muted small">Tổng bút toán đã ghi</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card soft-card stat-card h-100">
            <div class="card-body">
              <div class="text-muted small mb-1">Lệch toàn hệ thống</div>
              <h4 class="mb-1 {{ (int) $ledgerStats['delta_total'] === 0 ? 'text-success' : 'text-danger' }}">{{ $money($ledgerStats['delta_total']) }}</h4>
              <div class="text-muted small">users.amount - ledger</div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="card soft-card stat-card h-100">
            <div class="card-body">
              <div class="text-muted small mb-1">Cảnh báo</div>
              <h4 class="mb-1 {{ (int) $ledgerStats['alert_count'] === 0 ? 'text-success' : 'text-danger' }}">{{ number_format($ledgerStats['alert_count']) }}</h4>
              <div class="text-muted small">User lệch ví hoặc âm ví</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-4">
          <div class="card soft-card h-100">
            <div class="card-header">
              <h5 class="mb-1">Tặng tiền cho user</h5>
              <div class="text-muted small">Mỗi lần tặng đều khóa số dư, ghi ledger và log admin.</div>
            </div>
            <div class="card-body">
              <form method="POST" action="{{ route('admin.wallet.grant') }}" class="d-grid gap-3">
                @csrf
                <div>
                  <label class="form-label">ID user</label>
                  <input class="form-control @error('user_id') is-invalid @enderror" name="user_id" value="{{ old('user_id', request('user_id')) }}" inputmode="numeric" placeholder="Ví dụ: 1001" required>
                  @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">Số tiền tặng</label>
                  <input class="form-control @error('amount') is-invalid @enderror" name="amount" value="{{ old('amount') }}" inputmode="numeric" placeholder="50000" required>
                  @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                  <label class="form-label">Lý do</label>
                  <textarea class="form-control @error('note') is-invalid @enderror" name="note" rows="3" placeholder="Ví dụ: Tặng bù lỗi nạp tiền, chăm sóc khách hàng..." required>{{ old('note') }}</textarea>
                  @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-success" onclick="return confirm('Xác nhận tặng tiền và ghi ledger cho user này?');">
                  <i class="bx bx-plus-circle me-1"></i>Tặng tiền
                </button>
              </form>
            </div>
          </div>
        </div>
        <div class="col-xl-8">
          <div class="card soft-card h-100">
            <div class="card-header d-flex justify-content-between gap-3">
              <div>
                <h5 class="mb-1">Kiểm tra lệch ledger</h5>
                <div class="text-muted small">Chỉ những tài khoản có số dư lệch ledger hoặc ví âm mới hiện ở đây.</div>
              </div>
              <span class="badge {{ (int) $ledgerStats['alert_count'] === 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                {{ number_format($ledgerStats['alert_count']) }} cảnh báo
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Số dư ví</th>
                    <th>Ledger</th>
                    <th>Lệch</th>
                    <th>Bút toán</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($ledgerAlerts as $alert)
                    <tr>
                      <td>
                        <div class="fw-semibold">#{{ $alert->id }} · {{ $alert->display_name ?: $alert->name ?: 'User' }}</div>
                        <div class="text-muted small">{{ $alert->email ?: '-' }}</div>
                      </td>
                      <td class="fw-semibold">{{ $money($alert->wallet_amount) }}</td>
                      <td>{{ $money($alert->ledger_amount) }}</td>
                      <td class="fw-semibold text-danger">{{ $money($alert->ledger_delta) }}</td>
                      <td>
                        <div>{{ number_format($alert->ledger_count) }} dòng</div>
                        <div class="text-muted small">{{ $dateTime($alert->last_ledger_at) }}</div>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Ledger đang khớp, chưa thấy tài khoản bất thường.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="card soft-card">
        <div class="card-header">
          <h5 class="mb-1">Lịch sử ledger</h5>
          <div class="text-muted small">Bút toán mới nhất, gồm nạp tiền, tặng tiền và trừ tiền gói API.</div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th>Loại</th>
                <th>Số tiền</th>
                <th>Số dư</th>
                <th>Actor</th>
                <th>Thời gian</th>
                <th>Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              @forelse($walletLedgers as $entry)
                <tr>
                  <td>
                    <div class="fw-semibold">#{{ $entry->user_id }} · {{ $entry->display_name ?: $entry->name ?: 'User' }}</div>
                    <div class="text-muted small">{{ $entry->email ?: '-' }}</div>
                  </td>
                  <td>
                    <span class="badge bg-label-secondary">{{ $entry->type }}</span>
                    <div class="text-muted small cell-wrap">{{ $entry->reference }}</div>
                  </td>
                  <td class="fw-semibold {{ (int) $entry->amount >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ (int) $entry->amount >= 0 ? '+' : '' }}{{ $money($entry->amount) }}
                  </td>
                  <td>
                    <div>{{ $money($entry->balance_before) }}</div>
                    <div class="text-muted small">-> {{ $money($entry->balance_after) }}</div>
                  </td>
                  <td>
                    @if($entry->actor_id)
                      <div>#{{ $entry->actor_id }} · {{ $entry->actor_name ?: 'Admin' }}</div>
                      <div class="text-muted small">{{ $entry->actor_email ?: '-' }}</div>
                    @else
                      <span class="text-muted">Hệ thống</span>
                    @endif
                  </td>
                  <td>{{ $dateTime($entry->created_at) }}</td>
                  <td class="cell-wrap">{{ $entry->description ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có ledger.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @include('admin._simple-pager', ['items' => $walletLedgers])
      </div>
    @endif
  @endif

  @if($section === 'logs')
    <div class="card soft-card" id="logs">
      <div class="card-header">
        <h5 class="mb-1">Nhật ký hệ thống</h5>
        <div class="text-muted small">Bao gồm đăng nhập/đăng xuất mới, gia hạn, cấu hình và các thao tác hệ thống.</div>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>User</th>
              <th>Log</th>
              <th>IP</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            @forelse($logs as $log)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $log->name ?: 'User #' . $log->user }}</div>
                  <div class="text-muted small">{{ $log->email ?: '-' }}</div>
                </td>
                <td>
                  <div class="fw-semibold cell-wrap">{{ $log->log ?: '-' }}</div>
                  @if($log->notes)
                    <div class="text-muted small cell-wrap">{{ $log->notes }}</div>
                  @endif
                </td>
                <td>{{ $log->ip ?: '-' }}</td>
                <td>{{ $dateTime($log->created_at) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted text-center py-4">Chưa có nhật ký.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @include('admin._simple-pager', ['items' => $logs])
    </div>
  @endif

  @if($section === 'recharges')
    <div class="card soft-card" id="recharges">
      <div class="card-header">
        <h5 class="mb-1">Lịch sử nạp tiền</h5>
        <div class="text-muted small">Toàn bộ giao dịch nạp từ các ngân hàng nhận nạp, mới nhất ở trên cùng.</div>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>User</th>
              <th>Ngân hàng</th>
              <th>Mã giao dịch</th>
              <th>Số tiền</th>
              <th>Trạng thái</th>
              <th>Thời gian</th>
              <th>Nội dung</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recharges as $row)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $row->name ?: 'User #' . $row->user_id }}</div>
                  <div class="text-muted small">{{ $row->email ?: '-' }}</div>
                </td>
                <td>{{ $row->payment_method ?: '-' }}</td>
                <td>{{ $row->trans_id ?: '-' }}</td>
                <td class="text-success fw-semibold">{{ $money($row->amount) }}</td>
                <td>
                  @if((int) $row->status === 1)
                    <span class="badge bg-label-success">Hoàn tất</span>
                  @else
                    <span class="badge bg-label-secondary">Ghi nhận</span>
                  @endif
                </td>
                <td>{{ $timestamp($row->create_time) }}</td>
                <td class="cell-wrap">{{ $row->description ?: '-' }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-muted text-center py-4">Chưa có lịch sử nạp tiền.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @include('admin._simple-pager', ['items' => $recharges])
    </div>
  @endif
</div>
@endsection

@section('page-script')
<script>
(() => {
  const redirects = {
    users: @json(route('admin.users')),
    sessions: @json(route('admin.sessions')),
    recharges: @json(route('admin.recharges')),
    wallet: @json(route('admin.wallet')),
    logs: @json(route('admin.logs')),
  };
  const target = redirects[window.location.hash.replace('#', '')];
  if (target) window.location.replace(target);
})();
</script>
@endsection
