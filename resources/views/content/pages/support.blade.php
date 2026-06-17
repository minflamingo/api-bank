@extends('layouts/contentNavbarLayout')

@section('title', 'Hỗ trợ')

@section('content')
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Hỗ trợ nhanh</h5>
      </div>
      <div class="card-body">
        <p class="text-muted">Khi cần hỗ trợ, hãy gửi kèm email tài khoản, ngân hàng đang dùng, thời điểm phát sinh lỗi và ảnh chụp màn hình nếu có.</p>
        <div class="d-grid gap-2">
          <a class="btn btn-primary" href="{{ route('bank.accounts.index') }}">Kiểm tra tài khoản ngân hàng</a>
          <a class="btn btn-outline-primary" href="{{ route('client.payin') }}">Kiểm tra nạp tiền</a>
          <a class="btn btn-outline-secondary" href="{{ config('variables.support') }}" target="_blank" rel="noopener">Liên hệ 3W Group</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Các lỗi thường gặp</h5>
      </div>
      <div class="card-body">
        <div class="mb-4">
          <h6 class="mb-1">Chưa cộng tiền sau khi chuyển khoản</h6>
          <div class="text-muted">Kiểm tra đúng nội dung nạp, sau đó bấm “Tôi đã chuyển khoản” tại trang nạp tiền.</div>
        </div>
        <div class="mb-4">
          <h6 class="mb-1">Token ngân hàng không dùng được</h6>
          <div class="text-muted">Vào trang tài khoản ngân hàng, tạo/gửi lại token hoặc đăng nhập lại ngân hàng nếu phiên hết hạn.</div>
        </div>
        <div>
          <h6 class="mb-1">API hết hạn</h6>
          <div class="text-muted">Nạp tiền vào ví rồi gia hạn gói API tại trang Gia hạn.</div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
