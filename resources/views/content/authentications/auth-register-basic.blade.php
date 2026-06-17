@extends('layouts.blankLayout')

@section('title', 'Đăng Ký - Trang')

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
@endsection

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner">
      <!-- Register Card -->
      <div class="card px-sm-6 px-0">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center mb-6">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
              <span class="app-brand-logo demo">
                <img src="{{ asset('img/app-pro-logo.svg') }}" alt="API Bank" width="25">
              </span>
              <span class="app-brand-text demo text-heading fw-bold">
                {{ config('variables.templateName') }}
              </span>
            </a>
          </div>
          <!-- /Logo -->

          <h4 class="mb-1">Tạo tài khoản API Bank</h4>
          <p class="mb-6">Quản lý API ngân hàng và nạp tiền tài khoản gói dễ dàng.</p>

          <!-- Nếu có lỗi chung, hiển thị Alert -->
          @if ($errors->any())
            <div class="alert alert-danger mb-4">
              Đã xảy ra lỗi, vui lòng kiểm tra lại thông tin bên dưới!
            </div>
          @endif

          <form id="formAuthentication" class="mb-6"
                action="{{ route('auth-register-basic-post') }}"
                method="POST">
            @csrf

            <!-- Tên đăng nhập -->
            <div class="mb-6">
              <label for="username" class="form-label">Tên đăng nhập</label>
              <input type="text"
                     class="form-control @error('username') is-invalid @enderror"
                     id="username"
                     name="username"
                     value="{{ old('username') }}"
                     placeholder="Nhập tên đăng nhập của bạn"
                     autofocus>
              @error('username')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
              @enderror
            </div>

            <!-- Email -->
            <div class="mb-6">
              <label for="email" class="form-label">Email</label>
              <input type="text"
                     class="form-control @error('email') is-invalid @enderror"
                     id="email"
                     name="email"
                     value="{{ old('email') }}"
                     placeholder="Nhập email của bạn">
              @error('email')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
              @enderror
            </div>

            <!-- Mật khẩu -->
            <div class="mb-6 form-password-toggle">
              <label class="form-label" for="password">Mật khẩu</label>
              <div class="input-group input-group-merge">
                <input type="password"
                       id="password"
                       class="form-control @error('password') is-invalid @enderror"
                       name="password"
                       placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                       aria-describedby="password" />
                <span class="input-group-text cursor-pointer">
                  <i class="bx bx-hide"></i>
                </span>
                @error('password')
                  <div class="invalid-feedback mt-0">
                    {{ $message }}
                  </div>
                @enderror
              </div>
            </div>

            <!-- Xác nhận mật khẩu -->
            <div class="mb-6 form-password-toggle">
              <label class="form-label" for="password_confirmation">Xác nhận mật khẩu</label>
              <div class="input-group input-group-merge">
                <input type="password"
                       id="password_confirmation"
                       class="form-control @error('password_confirmation') is-invalid @enderror"
                       name="password_confirmation"
                       placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;">
                <span class="input-group-text cursor-pointer">
                  <i class="bx bx-hide"></i>
                </span>
                @error('password_confirmation')
                  <div class="invalid-feedback mt-0">
                    {{ $message }}
                  </div>
                @enderror
              </div>
            </div>

            <!-- Điều khoản -->
            <div class="my-8">
              <div class="form-check mb-0 ms-2">
                <input class="form-check-input"
                       type="checkbox"
                       id="terms-conditions"
                       name="terms">
                <label class="form-check-label" for="terms-conditions">
                  Tôi đồng ý với
                  <a href="javascript:void(0);">chính sách riêng tư & điều khoản</a>
                </label>
              </div>
            </div>

            <!-- Nút đăng ký -->
            <button class="btn btn-primary d-grid w-100">
              Đăng ký
            </button>
          </form>

          <!-- Chuyển sang đăng nhập -->
          <p class="text-center">
            <span>Bạn đã có tài khoản?</span>
            <a href="{{ route('auth-login-basic') }}">
              <span>Đăng nhập ngay</span>
            </a>
          </p>
        </div>
      </div>
      <!-- Register Card -->
    </div>
  </div>
</div>
@endsection
 
