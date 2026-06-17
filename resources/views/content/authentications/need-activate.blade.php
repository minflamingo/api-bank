@extends('layouts.blankLayout')

@section('title','Cần kích hoạt tài khoản')

@section('content')
  <div class="container py-4">
    <h2>Tài khoản của bạn chưa được kích hoạt!</h2>

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('info'))
      <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <p>
      Vui lòng kiểm tra email để kích hoạt tài khoản, hoặc bấm
      <a href="{{ route('resend.verification') }}">Gửi lại mail</a>, hoặc tạo lại một tài khoản có email khác bằng việc <a href="{{ route('logout') }}">Đăng xuất</a>.
    </p>
  </div>
@endsection
 