@extends('layouts/contentNavbarLayout')

@section('title', 'Không tìm thấy')

@section('content')
<div class="card">
  <div class="card-body text-center py-5">
    <h1 class="display-5 mb-2">404</h1>
    <h4 class="mb-2">Không tìm thấy trang</h4>
    <p class="text-muted mb-4">Liên kết có thể đã thay đổi hoặc bạn không có quyền truy cập nội dung này.</p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
      <a href="{{ route('v2') }}" class="btn btn-primary">Về trang chủ</a>
      <a href="{{ route('pages-support') }}" class="btn btn-outline-secondary">Liên hệ hỗ trợ</a>
    </div>
  </div>
</div>
@endsection
