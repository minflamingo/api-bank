@extends('layouts/contentNavbarLayout')

@section('title', 'Bảo mật tài khoản')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-6">
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-settings-account') }}">
            <i class="bx bx-sm bx-user me-1_5"></i> Tài khoản
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="{{ route('pages-account-security') }}">
            <i class="bx bx-sm bx-lock me-1_5"></i> Bảo mật
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-settings-notifications') }}">
            <i class="bx bx-sm bx-bell me-1_5"></i> Thông báo
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-settings-connections') }}">
            <i class="bx bx-sm bx-link-alt me-1_5"></i> Kết nối API
          </a>
        </li>
      </ul>
    </div>

    {{-- Nếu có session flash báo thành công --}}
    @if (session('success'))
      <div class="alert alert-success">
        {{ session('success') }}
      </div>
    @endif

    {{-- Nếu có lỗi validate --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card mb-6">
      <div class="card-body pt-4">
        <form
          id="formChangePassword"
          method="POST"
          action="{{ route('security.update') }}"
        >
          @csrf
          @method('PUT')

          {{-- Mật khẩu cũ --}}
          <div class="col-12 col-md-6 mb-3">
            <label for="current_password" class="form-label">Mật khẩu cũ</label>
            <input
              class="form-control"
              type="password"
              id="current_password"
              name="current_password"
              placeholder="Nhập mật khẩu cũ"
              required
            />
          </div>

          {{-- Mật khẩu mới --}}
          <div class="col-12 col-md-6 mb-3">
            <label for="password" class="form-label">Mật khẩu mới</label>
            <input
              class="form-control"
              type="password"
              id="password"
              name="password"
              placeholder="Nhập mật khẩu mới"
              required
            />
          </div>

          {{-- Xác nhận mật khẩu mới --}}
          <div class="col-12 col-md-6 mb-3">
            <label for="password_confirmation" class="form-label">Xác nhận mật khẩu mới</label>
            <input
              class="form-control"
              type="password"
              id="password_confirmation"
              name="password_confirmation"
              placeholder="Nhập lại mật khẩu mới"
              required
            />
          </div>

          <button type="submit" class="btn btn-primary me-3">Lưu thay đổi</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
