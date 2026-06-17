@extends('layouts/contentNavbarLayout')

@section('title', 'Kết nối API')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-4">
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-account') }}"><i class="bx bx-user bx-sm me-1"></i> Tài khoản</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-security') }}"><i class="bx bx-lock bx-sm me-1"></i> Bảo mật</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('pages-account-settings-notifications') }}"><i class="bx bx-bell bx-sm me-1"></i> Thông báo</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('pages-account-settings-connections') }}"><i class="bx bx-link-alt bx-sm me-1"></i> Kết nối API</a></li>
      </ul>
    </div>
  </div>

  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Tài khoản ACB</div>
        <h4 class="mb-0">{{ number_format($stats['acb']) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Token ACB</div>
        <h4 class="mb-0">{{ number_format($stats['acb_token']) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Tài khoản VCB</div>
        <h4 class="mb-0">{{ number_format($stats['vcb']) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Token VCB</div>
        <h4 class="mb-0">{{ number_format($stats['vcb_token']) }}</h4>
      </div>
    </div>
  </div>

  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Tài khoản VPBank</div>
        <h4 class="mb-0">{{ number_format($stats['vpbank'] ?? 0) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Token VPBank</div>
        <h4 class="mb-0">{{ number_format($stats['vpbank_token'] ?? 0) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Tài khoản TCB</div>
        <h4 class="mb-0">{{ number_format($stats['techcombank'] ?? 0) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Token TCB</div>
        <h4 class="mb-0">{{ number_format($stats['techcombank_token'] ?? 0) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Tài khoản MBB</div>
        <h4 class="mb-0">{{ number_format($stats['mbbank'] ?? 0) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-xl-2 mb-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="text-muted small">Token MBB</div>
        <h4 class="mb-0">{{ number_format($stats['mbbank_token'] ?? 0) }}</h4>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Endpoint ACB</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>{{ url('/v1/acb/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>{{ url('/v1/acb/balance/{token}') }}</code>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('payment.acb.index') }}">Quản lý ACB</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4 mt-lg-0">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Endpoint Vietcombank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>{{ url('/v1/vcb/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>{{ url('/v1/vcb/balance/{token}') }}</code>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('payment.vcb.index') }}">Quản lý Vietcombank</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4 mt-lg-0">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Endpoint VPBank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>{{ url('/v1/vpbank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>{{ url('/v1/vpbank/balance/{token}') }}</code>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('bank.accounts.create', ['bank' => 'vpbank']) }}">Quản lý VPBank</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Endpoint Techcombank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>{{ url('/v1/techcombank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>{{ url('/v1/techcombank/balance/{token}') }}</code>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('bank.accounts.create', ['bank' => 'techcombank']) }}">Quản lý Techcombank</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mt-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Endpoint MBBank</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="text-muted small">Lịch sử giao dịch</div>
          <code>{{ url('/v1/mbbank/transhistory/{token}') }}</code>
        </div>
        <div class="mb-3">
          <div class="text-muted small">Số dư</div>
          <code>{{ url('/v1/mbbank/balance/{token}') }}</code>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('bank.accounts.create', ['bank' => 'mbbank']) }}">Quản lý MBBank</a>
      </div>
    </div>
  </div>
</div>
@endsection
