@extends('layouts/contentNavbarLayout')

@section('title', 'Bảo trì')

@section('content')
<div class="card">
  <div class="card-body text-center py-5">
    <h4 class="mb-2">Tính năng đang được bảo trì</h4>
    <p class="text-muted mb-4">Một số chức năng có thể tạm thời bị giới hạn trong lúc hệ thống cập nhật.</p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
      <a href="{{ route('v2') }}" class="btn btn-primary">Về trang chủ</a>
      <a href="{{ route('pages-support') }}" class="btn btn-outline-secondary">Xem hỗ trợ</a>
    </div>
  </div>
</div>
@endsection
