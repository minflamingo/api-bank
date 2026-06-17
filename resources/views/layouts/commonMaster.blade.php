<!DOCTYPE html>

<html class="light-style layout-menu-fixed" data-theme="theme-default" data-assets-path="{{ asset('/assets') . '/' }}" data-base-url="{{url('/')}}" data-framework="laravel" data-template="vertical-menu-laravel-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>@yield('title') | {{ config('variables.templateSuffix', 'API Bank - 3W Group') }}</title>
  <meta name="description" content="{{ config('variables.templateDescription') ? config('variables.templateDescription') : '' }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ? config('variables.templateKeyword') : '' }}">
  <!-- laravel CRUD token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ? config('variables.productPage') : '' }}">
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />


  <!-- Include Styles -->
  @include('layouts/sections/styles')

  <!-- Include Scripts for customizer, helper, analytics, config -->
  @include('layouts/sections/scriptsIncludes')

</head>

<body>

  @if(session('impersonator_id') && auth()->check() && \Illuminate\Support\Facades\Route::has('admin.impersonate.stop'))
    <style>
      .impersonation-banner {
        position: sticky;
        top: 0;
        z-index: 1095;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .7rem 1rem;
        color: #7a4b00;
        background: #fff3cd;
        border-bottom: 1px solid #ffdf7e;
        box-shadow: 0 .25rem .75rem rgba(67, 89, 113, .08);
      }
      .impersonation-banner strong { color: #5c3900; }
      .impersonation-banner form { margin: 0; }
      @media (max-width: 575.98px) {
        .impersonation-banner {
          align-items: flex-start;
          flex-direction: column;
        }
      }
    </style>
    <div class="impersonation-banner">
      <div>
        <strong>Đang vào vai user</strong>
        <span>{{ session('impersonated_user_name') ?: ('User #' . session('impersonated_user_id')) }}</span>
        <span class="text-muted">từ admin {{ session('impersonator_name') ?: ('#' . session('impersonator_id')) }}</span>
      </div>
      <form method="POST" action="{{ route('admin.impersonate.stop') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-warning fw-semibold">
          Quay lại super admin
        </button>
      </form>
    </div>
  @endif

  <!-- Layout Content -->
  @yield('layoutContent')
  <!--/ Layout Content -->

  

  <!-- Include Scripts -->
  @include('layouts/sections/scripts')

 
</body>

</html>
