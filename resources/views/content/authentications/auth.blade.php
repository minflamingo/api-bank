@extends('layouts.blankLayout')

@php
  $mode = $mode ?? 'login';
  $mathA = (int) session('reg_math_a', 0);
  $mathB = (int) session('reg_math_b', 0);
@endphp

@section('title', 'Tài khoản - ' . config('variables.templateName'))

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-auth.scss'
])
<style>
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, #f7faf9 0%, #eef6f4 50%, #fff8e5 100%);
  }

  .auth-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 2rem 0;
  }

  .auth-wrap {
    max-width: 1080px;
    margin: 0 auto;
    overflow: hidden;
    border: 1px solid rgba(15, 118, 110, .12);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .12);
  }

  .auth-brand-panel {
    min-height: 640px;
    padding: 2.5rem;
    color: #fff;
    background:
      linear-gradient(145deg, rgba(15, 118, 110, .96), rgba(14, 116, 144, .92)),
      url("{{ asset('img/logo.png') }}") center/cover no-repeat;
  }

  .auth-logo-box {
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .28);
  }

  .auth-logo-box img {
    width: 36px;
    height: 36px;
  }

  .auth-panel-title {
    font-size: 2.4rem;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: 0;
  }

  .auth-panel-text {
    color: rgba(255, 255, 255, .82);
    line-height: 1.75;
  }

  .auth-badge-list {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
  }

  .auth-badge-list span {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .48rem .7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .18);
    color: rgba(255, 255, 255, .9);
    font-size: .82rem;
    font-weight: 700;
  }

  .auth-form-panel {
    padding: 2.4rem;
  }

  .auth-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .4rem;
    padding: .35rem;
    border-radius: 14px;
    background: #f4f7f7;
  }

  .auth-tabs a {
    border-radius: 11px;
    padding: .8rem .75rem;
    color: #52616b;
    font-weight: 800;
    text-align: center;
  }

  .auth-tabs a.active {
    color: #0f766e;
    background: #fff;
    box-shadow: 0 8px 22px rgba(15, 118, 110, .12);
  }

  .btn-google {
    border: 1px solid #d8dee4;
    background: #fff;
    color: #1f2937;
    font-weight: 800;
  }

  .btn-google:hover {
    border-color: #b9c2cc;
    background: #f8fafc;
  }

  .auth-divider {
    display: flex;
    align-items: center;
    gap: .85rem;
    color: #7a8791;
    font-size: .86rem;
    font-weight: 700;
  }

  .auth-divider::before,
  .auth-divider::after {
    content: "";
    height: 1px;
    flex: 1;
    background: #e4e9ed;
  }

  .auth-alert {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
  }

  @media (max-width: 991.98px) {
    .auth-brand-panel {
      min-height: auto;
      padding: 1.6rem;
    }

    .auth-panel-title {
      font-size: 1.8rem;
    }

    .auth-form-panel {
      padding: 1.5rem;
    }
  }
</style>
@endsection

@section('content')
<div class="container-xxl auth-shell">
  <div class="auth-wrap w-100">
    <div class="row g-0">
      <div class="col-lg-5">
        <div class="auth-brand-panel h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-3 mb-5">
              <span class="auth-logo-box">
                <img src="{{ asset('img/app-pro-logo.svg') }}" alt="API Bank">
              </span>
              <div>
                <div class="fw-bold fs-4">{{ config('variables.templateName') }}</div>
                <div class="small auth-panel-text">3W Group</div>
              </div>
            </div>
            <h1 class="auth-panel-title mb-3">Quản lý API ngân hàng nhanh và gọn.</h1>
            <p class="auth-panel-text mb-4">Thêm tài khoản ngân hàng, lấy token API và nạp tiền tài khoản trong cùng một màn hình quản lý.</p>
            <div class="auth-badge-list">
              <span><i class="bx bx-shield-quarter"></i> Google OAuth</span>
              <span><i class="bx bx-credit-card"></i> VCB / ACB</span>
              <span><i class="bx bx-wallet"></i> Nạp tiền</span>
            </div>
          </div>
          <div class="small auth-panel-text mt-5">apibank.com.vn</div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="auth-form-panel">
          <div class="auth-tabs mb-4">
            <a href="{{ route('auth-login-basic') }}" class="{{ $mode === 'login' ? 'active' : '' }}">Đăng nhập</a>
            <a href="{{ route('auth-register-basic') }}" class="{{ $mode === 'register' ? 'active' : '' }}">Đăng ký</a>
          </div>

          @if (session('status'))
            <div class="alert alert-success auth-alert mb-4"><i class="bx bx-check-circle"></i><div>{{ session('status') }}</div></div>
          @endif
          @if (session('success'))
            <div class="alert alert-success auth-alert mb-4"><i class="bx bx-check-circle"></i><div>{{ session('success') }}</div></div>
          @endif
          @if (session('error'))
            <div class="alert alert-danger auth-alert mb-4"><i class="bx bx-error-circle"></i><div>{{ session('error') }}</div></div>
          @endif

          @if($mode === 'login')
            @if ($errors->any())
              <div class="alert alert-danger auth-alert mb-4"><i class="bx bx-error-circle"></i><div>Vui lòng kiểm tra lại thông tin đăng nhập.</div></div>
            @endif

            <a class="btn btn-google d-flex align-items-center justify-content-center gap-2 w-100 mb-4"
               href="{{ route('auth.social.redirect', ['provider' => 'google']) }}">
              <i class="bx bxl-google fs-4"></i>
              Đăng nhập bằng Google
            </a>

            <div class="auth-divider mb-4">Hoặc dùng mật khẩu</div>

            <form id="formLogin" class="mb-4" action="{{ route('auth-login-basic-post') }}" method="POST" novalidate>
              @csrf
              <div class="mb-4">
                <label for="email_username" class="form-label fw-semibold">Email, tên đăng nhập hoặc số điện thoại</label>
                <input type="text"
                       class="form-control @error('email_username') is-invalid @enderror"
                       id="email_username"
                       name="email_username"
                       placeholder="Email, tên đăng nhập hoặc số điện thoại"
                       value="{{ old('email_username') }}"
                       autocomplete="username"
                       autofocus>
                @error('email_username')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-4 form-password-toggle">
                <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                <div class="input-group input-group-merge">
                  <input type="password"
                         id="password"
                         name="password"
                         class="form-control @error('password') is-invalid @enderror"
                         placeholder="Nhập mật khẩu"
                         autocomplete="current-password" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
                @error('password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">Ghi nhớ lần đăng nhập này</label>
              </div>

              <button class="btn btn-primary d-grid w-100" type="submit">Vào trang quản lý</button>
            </form>

            <p class="text-center mb-0">
              <span>Chưa có tài khoản?</span>
              <a href="{{ route('auth-register-basic') }}"><span>Tạo tài khoản mới</span></a>
            </p>
          @endif

          @if($mode === 'register')
            @if ($errors->any())
              <div class="alert alert-danger auth-alert mb-4"><i class="bx bx-error-circle"></i><div>Vui lòng kiểm tra lại thông tin đăng ký.</div></div>
            @endif

            <a class="btn btn-google d-flex align-items-center justify-content-center gap-2 w-100 mb-4"
               href="{{ route('auth.social.redirect', ['provider' => 'google']) }}">
              <i class="bx bxl-google fs-4"></i>
              Đăng ký bằng Google
            </a>

            <div class="auth-divider mb-4">Hoặc đăng ký thủ công</div>

            <form id="formRegister" class="mb-4" action="{{ route('auth-register-basic-post') }}" method="POST" novalidate>
              @csrf
              <div class="mb-4">
                <label for="username" class="form-label fw-semibold">Tên đăng nhập</label>
                <input type="text"
                       class="form-control @error('username') is-invalid @enderror"
                       id="username"
                       name="username"
                       value="{{ old('username') }}"
                       placeholder="Viết liền không dấu"
                       autocomplete="username"
                       autofocus>
                @error('username')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-4">
                <label for="email" class="form-label fw-semibold">Email</label>
                <input type="email"
                       class="form-control @error('email') is-invalid @enderror"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="email@domain.com"
                       autocomplete="email">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-4 form-password-toggle">
                <label class="form-label fw-semibold" for="password">Mật khẩu</label>
                <div class="input-group input-group-merge">
                  <input type="password"
                         id="password"
                         name="password"
                         class="form-control @error('password') is-invalid @enderror"
                         placeholder="Tối thiểu 6 ký tự"
                         autocomplete="new-password" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
                @error('password')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <div class="mb-4 form-password-toggle">
                <label class="form-label fw-semibold" for="password_confirmation">Xác nhận mật khẩu</label>
                <div class="input-group input-group-merge">
                  <input type="password"
                         id="password_confirmation"
                         name="password_confirmation"
                         class="form-control"
                         placeholder="Nhập lại mật khẩu"
                         autocomplete="new-password" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
              </div>

              <div class="mb-4">
                <label for="human_answer" class="form-label fw-semibold">Xác thực</label>
                <input type="number"
                       class="form-control @error('human_answer') is-invalid @enderror"
                       id="human_answer"
                       name="human_answer"
                       value="{{ old('human_answer') }}"
                       placeholder="{{ $mathA }} + {{ $mathB }} = ?"
                       inputmode="numeric">
                @error('human_answer')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="form-check mb-4">
                <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" {{ old('terms') ? 'checked' : '' }}>
                <label class="form-check-label" for="terms">Tôi đồng ý với điều khoản sử dụng</label>
                @error('terms')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
              </div>

              <button class="btn btn-primary d-grid w-100" type="submit">Tạo tài khoản</button>
            </form>

            <p class="text-center mb-0">
              <span>Đã có tài khoản?</span>
              <a href="{{ route('auth-login-basic') }}"><span>Đăng nhập ngay</span></a>
            </p>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
