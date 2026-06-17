@extends('layouts.blankLayout')

@section('title', 'Đặt lại mật khẩu - Trang')

@section('content')
<div class="container-xxl">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner">
      <div class="card px-sm-6 px-0">
        <div class="card-body">
          <h4 class="mb-2">Đặt lại mật khẩu</h4>
          <p class="mb-4">Nhập mật khẩu mới cho tài khoản của bạn</p>

          {{-- Thông báo thành công, lỗi, v.v. --}}
          @if(session('status'))
            <div class="alert alert-success">
              {{ session('status') }}
            </div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('auth-reset-password-basic-post') }}">
            @csrf
            {{-- Token ẩn --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input id="email" type="email"
                     class="form-control"
                     name="email"
                     value="{{ old('email') }}"
                     required autofocus />
            </div>

            <div class="mb-3 form-password-toggle">
              <label class="form-label" for="password">Mật khẩu mới</label>
              <div class="input-group input-group-merge">
                <input type="password"
                       id="password"
                       class="form-control"
                       name="password"
                       placeholder="******" />
                <span class="input-group-text cursor-pointer">
                  <i class="bx bx-hide"></i>
                </span>
              </div>
            </div>

            <div class="mb-3 form-password-toggle">
              <label class="form-label" for="password_confirmation">Xác nhận mật khẩu mới</label>
              <div class="input-group input-group-merge">
                <input type="password"
                       id="password_confirmation"
                       class="form-control"
                       name="password_confirmation"
                       placeholder="******" />
                <span class="input-group-text cursor-pointer">
                  <i class="bx bx-hide"></i>
                </span>
              </div>
            </div>

            <button class="btn btn-primary d-grid w-100">Đặt lại mật khẩu</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
 