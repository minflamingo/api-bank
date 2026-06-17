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
      'logs' => 'admin.logs',
  ];
  $sectionMetaMap = [
      'overview' => ['label' => 'Super Admin', 'hint' => 'Tổng quan hệ thống, chỉ giữ số liệu và lối tắt chính.'],
      'users' => ['label' => 'User đã đăng ký', 'hint' => 'Danh sách user, tìm theo ID, tên, email hoặc số điện thoại.'],
      'sessions' => ['label' => 'Đang đăng nhập', 'hint' => 'Session đăng nhập, sắp xếp theo hoạt động mới nhất.'],
      'recharges' => ['label' => 'Lịch sử nạp tiền', 'hint' => 'Giao dịch nạp từ các ngân hàng nhận nạp, mới nhất ở trên cùng.'],
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
                  @if((int) $row->id === (int) auth()->id())
                    <span class="badge bg-label-secondary">Đang dùng</span>
                  @else
                    <form method="POST" action="{{ route('admin.users.impersonate', $row->id) }}" class="mb-0" onsubmit="return confirm('Đăng nhập dưới dạng user #{{ $row->id }}?');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-log-in-circle me-1"></i>Vào vai
                      </button>
                    </form>
                  @endif
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
    logs: @json(route('admin.logs')),
  };
  const target = redirects[window.location.hash.replace('#', '')];
  if (target) window.location.replace(target);
})();
</script>
@endsection
