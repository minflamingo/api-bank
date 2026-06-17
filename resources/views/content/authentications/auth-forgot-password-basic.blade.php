@extends('layouts.blankLayout')

@section('title', 'Quên Mật Khẩu - Trang')

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
@endsection

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner">

      <!-- Quên mật khẩu -->
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

          <h4 class="mb-1">Quên mật khẩu? 🔒</h4>
          <p class="mb-6">Nhập email của bạn và chúng tôi sẽ gửi hướng dẫn đặt lại mật khẩu</p>

          <!-- Thông báo thành công -->
          @if (session('status'))
            <div class="alert alert-success">
              {{ session('status') }}
            </div>
          @endif

          <!-- Thông báo chung khi có bất kỳ lỗi -->
          @if ($errors->any())
            <div class="alert alert-danger">
              Đã xảy ra lỗi, vui lòng kiểm tra form bên dưới!
            </div>
          @endif

          <form id="formAuthentication"
                class="mb-6"
                action="{{ route('auth-forgot-password-basic-post') }}"
                method="POST">
            @csrf
            <div class="mb-6">
              <label for="email" class="form-label">Email</label>
              <input type="text"
                     class="form-control @error('email') is-invalid @enderror"
                     id="email"
                     name="email"
                     value="{{ old('email') }}"
                     placeholder="Nhập email của bạn"
                     autofocus>
              @error('email')
                <div class="invalid-feedback">
                  {{ $message }}
                </div>
              @enderror
            </div>
            <button class="btn btn-primary d-grid w-100">
              Gửi liên kết đặt lại
            </button>
          </form>

          <div class="text-center">
            <a href="{{ route('auth-login-basic') }}" class="d-flex justify-content-center">
              <i class="bx bx-chevron-left scaleX-n1-rtl me-1"></i>
              Quay lại trang đăng nhập
            </a>
          </div>
        </div>
      </div>
      <!-- /Quên mật khẩu -->

    </div>
  </div>
</div>
@endsection
