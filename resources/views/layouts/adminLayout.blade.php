@extends('layouts/commonMaster')

@php
use Illuminate\Support\Facades\Route;

$container = $container ?? 'container-fluid';
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = 'navbar-detached';
$currentRouteName = Route::currentRouteName();

$adminLinks = [
    ['label' => 'Tổng quan', 'href' => route('admin.dashboard'), 'icon' => 'bx-grid-alt', 'active' => in_array($currentRouteName, ['admin.dashboard', 'admin.dashboard.index'], true)],
    ['label' => 'User đã đăng ký', 'href' => route('admin.users'), 'icon' => 'bx-user', 'active' => $currentRouteName === 'admin.users'],
    ['label' => 'Đang đăng nhập', 'href' => route('admin.sessions'), 'icon' => 'bx-log-in-circle', 'active' => $currentRouteName === 'admin.sessions'],
    ['label' => 'Lịch sử nạp tiền', 'href' => route('admin.recharges'), 'icon' => 'bx-wallet', 'active' => $currentRouteName === 'admin.recharges'],
    ['label' => 'Ví & ledger', 'href' => route('admin.wallet'), 'icon' => 'bx-money', 'active' => $currentRouteName === 'admin.wallet'],
    ['label' => 'Nhật ký hệ thống', 'href' => route('admin.logs'), 'icon' => 'bx-list-ul', 'active' => $currentRouteName === 'admin.logs'],
    ['label' => 'Tài khoản ngân hàng', 'href' => route('admin.bank-accounts.index'), 'icon' => 'bx-credit-card', 'active' => $currentRouteName === 'admin.bank-accounts.index'],
    ['label' => 'Giám sát ngân hàng', 'href' => route('admin.bank-monitor'), 'icon' => 'bx-pulse', 'active' => $currentRouteName === 'admin.bank-monitor'],
    ['label' => 'Cấu hình nạp tiền', 'href' => route('admin.recharge-settings.edit'), 'icon' => 'bx-cog', 'active' => $currentRouteName === 'admin.recharge-settings.edit'],
];
@endphp

@section('layoutContent')
<div class="layout-wrapper layout-content-navbar admin-layout">
  <div class="layout-container">
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
      <div class="app-brand demo">
        <a href="{{ route('admin.dashboard') }}" class="app-brand-link">
          <span class="app-brand-logo demo"><img src="{{ asset('img/app-pro-logo.svg') }}" alt="API Bank" width="28" height="28"></span>
          <span class="app-brand-text demo menu-text fw-bold ms-2">Super Admin</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
          <i class="bx bx-chevron-left bx-sm d-flex align-items-center justify-content-center"></i>
        </a>
      </div>

      <div class="menu-inner-shadow"></div>

      <ul class="menu-inner py-1">
        <li class="menu-header small text-uppercase">
          <span class="menu-header-text">Quản trị</span>
        </li>
        @foreach($adminLinks as $link)
          <li class="menu-item {{ $link['active'] ? 'active' : '' }}">
            <a href="{{ $link['href'] }}" class="menu-link">
              <i class="menu-icon tf-icons bx {{ $link['icon'] }}"></i>
              <div>{{ $link['label'] }}</div>
            </a>
          </li>
        @endforeach
      </ul>
    </aside>

    <div class="layout-page">
      <nav class="layout-navbar {{ $containerNav }} navbar navbar-expand-xl {{ $navbarDetached }} align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="bx bx-menu bx-md"></i>
          </a>
        </div>

        <div class="navbar-nav-right d-flex align-items-center w-100">
          <div class="d-flex flex-column">
            <span class="fw-semibold">Super Admin</span>
            <small class="text-muted">Chỉ hiển thị chức năng quản trị hệ thống</small>
          </div>

          <div class="navbar-nav flex-row align-items-center ms-auto gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('v2') }}">
              <i class="bx bx-arrow-back me-1"></i>Khu dịch vụ
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mb-0">
              @csrf
              <button class="btn btn-sm btn-outline-danger" type="submit">
                <i class="bx bx-power-off me-1"></i>Đăng xuất
              </button>
            </form>
          </div>
        </div>
      </nav>

      <div class="content-wrapper">
        <div class="{{ $container }} flex-grow-1 container-p-y">
          @yield('content')
        </div>
        <div class="content-backdrop fade"></div>
      </div>
    </div>
  </div>

  <div class="layout-overlay layout-menu-toggle"></div>
  <div class="drag-target"></div>
</div>
@endsection
