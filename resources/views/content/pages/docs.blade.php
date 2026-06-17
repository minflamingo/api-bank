@extends('layouts/contentNavbarLayout')

@section('title', 'Tài liệu API')

@section('content')
<div class="row g-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
          <h4 class="mb-1">Tài liệu API ngân hàng</h4>
          <div class="text-muted">Dùng token từ trang Tài khoản ngân hàng để gọi các endpoint bên dưới.</div>
        </div>
        <a href="{{ route('bank.accounts.index') }}" class="btn btn-primary align-self-start">Lấy token API</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">ACB</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>GET {{ url('/v2/acb/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>GET {{ url('/v2/acb/balance/{token}') }}</code>
        </div>
        <div class="text-muted small">Token thuộc tài khoản ngân hàng bạn đã thêm và chỉ hoạt động khi gói API còn hạn.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Vietcombank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>GET {{ url('/v2/vcb/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>GET {{ url('/v2/vcb/balance/{token}') }}</code>
        </div>
        <div class="text-muted small">Nếu phiên ngân hàng hết hạn, hãy đăng nhập lại tài khoản ngân hàng trong app.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">VPBank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>GET {{ url('/v2/vpbank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>GET {{ url('/v2/vpbank/balance/{token}') }}</code>
        </div>
        <div class="text-muted small">VPBank NEO có thể yêu cầu OTP khi thiết bị chưa được tin cậy.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Techcombank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>GET {{ url('/v2/techcombank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>GET {{ url('/v2/techcombank/balance/{token}') }}</code>
        </div>
        <div class="text-muted small">Techcombank cần xác nhận đăng nhập trên app Mobile khi kết nối lần đầu.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">MBBank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>GET {{ url('/v2/mbbank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>GET {{ url('/v2/mbbank/balance/{token}') }}</code>
        </div>
        <div class="text-muted small">MBBank dùng captcha apibank.com.vn khi kết nối và tự đăng nhập lại khi phiên hết hạn.</div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card border-secondary">
      <div class="card-header">
        <h5 class="mb-0">API v1 legacy cho khách cũ</h5>
      </div>
      <div class="card-body">
        <div class="text-muted mb-3">Các endpoint cũ vẫn được giữ nguyên để khách cũ tiếp tục dùng. Khách mới nên dùng API v2 ở các thẻ phía trên.</div>
        <div class="row g-3">
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historyacb/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historyacbbalance/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historyvietcombank/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historyvietcombankbalance/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historymbbank/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/api/historymbbankbalance/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/v1.0/api/historyacb/{token}') }}</code></div>
          <div class="col-md-6 col-xl-4"><code>GET {{ url('/v1/api/historyacb/{token}') }}</code></div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
