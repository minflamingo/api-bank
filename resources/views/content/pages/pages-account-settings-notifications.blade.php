@extends('layouts/contentNavbarLayout')

@section('title', 'Thông báo')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-4">
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-account') }}"><i class="bx bx-user bx-sm me-1"></i> Tài khoản</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-security') }}"><i class="bx bx-lock bx-sm me-1"></i> Bảo mật</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('pages-account-settings-notifications') }}"><i class="bx bx-bell bx-sm me-1"></i> Thông báo</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-connections') }}"><i class="bx bx-link-alt bx-sm me-1"></i> Kết nối</a></li>
      </ul>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Kênh thông báo</h5>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <span class="badge bg-label-primary rounded-pill">Email</span>
          <div>
            <div class="fw-semibold">Thông báo tài khoản</div>
            <div class="text-muted small">Xác minh email, đặt lại mật khẩu và gửi token API khi bạn yêu cầu.</div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3 mb-3">
          <span class="badge bg-label-success rounded-pill">Ví</span>
          <div>
            <div class="fw-semibold">Nạp tiền tự động</div>
            <div class="text-muted small">Giao dịch ACB đúng nội dung sẽ tạo hóa đơn và cộng số dư.</div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <span class="badge bg-label-secondary rounded-pill">API</span>
          <div>
            <div class="fw-semibold">Hoạt động API ngân hàng</div>
            <div class="text-muted small">Các thao tác lấy token, số dư, lịch sử và gia hạn được lưu lại trong nhật ký.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7 mt-4 mt-lg-0">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Nhật ký gần đây</h5>
        <span class="text-muted small">12 dòng mới nhất</span>
      </div>
      <div class="card-body">
        @forelse($logs as $log)
          <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between gap-2">
              <strong>{{ $log->log }}</strong>
              <span class="text-muted small text-nowrap">{{ optional($log->created_at ? \Carbon\Carbon::parse($log->created_at) : null)->format('H:i d-m-Y') }}</span>
            </div>
            <div class="text-muted small mt-1">{{ $log->notes }}</div>
          </div>
        @empty
          <div class="text-center text-muted py-5">Chưa có nhật ký hoạt động.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
