@extends('layouts.blankLayout')

@section('title', 'Đăng Nhập - Trang')

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
@endsection

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner">
      <!-- Login Card -->
      <div class="card px-sm-6 px-0">
        <div class="card-body">
          <!-- Logo -->
          <div class="app-brand justify-content-center mb-6">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
              <span class="app-brand-logo demo">
                @include('_partials.macros', ["width"=>25, "withbg"=>'var(--bs-primary)'])
              </span>
              <span class="app-brand-text demo text-heading fw-bold">
                {{ config('variables.templateName') }}
              </span>
            </a>
          </div>
          <!-- /Logo -->

          <h4 class="mb-1">
            Chào mừng đến với {{ config('variables.templateName') }}! 👋
          </h4>
          <p class="mb-6">
            Vui lòng đăng nhập tài khoản để bắt đầu trải nghiệm
          </p>

          <!-- Hiển thị tổng quát thông báo lỗi (nếu có) -->
          @if ($errors->any())
            <div class="alert alert-danger mb-4">
              Vui lòng kiểm tra lại thông tin bạn vừa nhập!
            </div>
          @endif

          <form id="formAuthentication" class="mb-6"
                action="{{ route('auth-login-basic-post') }}"
                method="POST">
            @csrf

            <!-- Email hoặc Tên đăng nhập -->
            <div class="mb-6">
              <label for="email_username" class="form-label">Email hoặc Tên đăng nhập</label>
              <input type="text"
                     class="form-control @error('email_username') is-invalid @enderror"
                     id="email_username"
                     name="email_username"
                     placeholder="Nhập email hoặc tên đăng nhập"
                     value="{{ old('email_username') }}"
                     autofocus>
              @error('email_username')
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
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
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

            <!-- Ghi nhớ đăng nhập / Quên mật khẩu -->
            <div class="mb-8">
              <div class="d-flex justify-content-between mt-8">
                <div class="form-check mb-0 ms-2">
                  <input class="form-check-input"
                         type="checkbox"
                         id="remember-me"
                         name="remember_me">
                  <label class="form-check-label" for="remember-me">
                    Ghi nhớ tôi
                  </label>
                </div>
                <a href="#">
                  <span>Quên mật khẩu?</span>
                </a>
              </div>
            </div>

            <!-- Nút Đăng nhập -->
            <div class="mb-6">
              <button class="btn btn-primary d-grid w-100" type="submit">
                Đăng nhập
              </button>
            </div>
          </form>

          <!-- Chuyển sang Đăng ký -->
          <p class="text-center">
            <span>Bạn mới biết đến nền tảng này?</span>
            <a href="#">
              <span>Tạo tài khoản mới</span>
            </a>
          </p>
        </div>
      </div>
      <!-- /Login Card -->
    </div>
  </div>
</div>
@endsection
