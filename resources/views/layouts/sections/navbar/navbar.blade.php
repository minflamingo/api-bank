@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');
$buildingsForDropdown = $buildingsForDropdown ?? null;
@endphp

<!-- Navbar -->
@if(isset($navbarDetached) && $navbarDetached == 'navbar-detached')
<nav class="layout-navbar {{$containerNav}} navbar navbar-expand-xl {{$navbarDetached}} align-items-center bg-navbar-theme" id="layout-navbar">
@endif
@if(isset($navbarDetached) && $navbarDetached == '')
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="{{$containerNav}}">
    @endif

      <!--  Brand demo (display only for navbar-full and hide on below xl) -->
      @if(isset($navbarFull))
      <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
        <a href="{{url('/')}}" class="app-brand-link gap-2">
          <span class="app-brand-logo demo"><img src="{{ asset('img/app-pro-logo.svg') }}" alt="API Bank" width="28" height="28"></span>
          <span class="app-brand-text demo menu-text fw-bold text-heading">{{config('variables.templateName')}}</span>
        </a>
      </div>
      @endif

      <!-- ! Not required for layout-without-menu -->
      @if(!isset($navbarHideToggle))
      <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ?' d-xl-none ' : '' }}">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
          <i class="bx bx-menu bx-md"></i>
        </a>
      </div>
      @endif

      <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        <!-- Search 
        <div class="navbar-nav align-items-center">
          <div class="nav-item d-flex align-items-center">
            <i class="bx bx-search bx-md"></i>
            <input type="text" class="form-control border-0 shadow-none ps-1 ps-sm-2" placeholder="Search..." aria-label="Search...">
          </div>
        </div>
         /Search -->
        <ul class="navbar-nav flex-row align-items-center ms-auto">

          <!-- Place this tag where you want the button to render. -->
@if ($buildingsForDropdown)		  
<!-- ============================= -->
<!-- PHƯƠNG ÁN #2: HIỂN THỊ CHO DESKTOP (>= lg) -->
<!-- ============================= -->
<li class="nav-item lh-1 me-3 d-none d-lg-inline-block">
  <div class="btn-group">
    @php
      // Lấy bkey hiện tại nếu có (trong Controller, bạn trả về view với biến $bkey)
      $selectedBkey = $bkey ?? null;

      // Tìm tòa nhà ứng với $selectedBkey
      $currentBuilding = $buildingsForDropdown->firstWhere('bkey', $selectedBkey);
      // Nếu tìm thấy, hiển thị code tòa nhà, ngược lại hiển thị "Tòa nhà"
      $buildingTitle = $currentBuilding ? $currentBuilding->bcode : 'Tòa nhà';
    @endphp

    <!-- Nút chính (hiển thị tên tòa nhà, nếu có) -->
    <button type="button"
            class="btn btn-primary btn-sm text-truncate"
            style="max-width: 120px;">
      <i class="bx bx-building"></i>
      <span class="ms-1">{{ $buildingTitle }}</span>
    </button>

    <!-- Nút toggle -->
    <button type="button"
            class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown"
            aria-expanded="false">
      <span class="visually-hidden">Toggle Dropdown</span>
    </button>

    <!-- Danh sách dropdown (desktop) -->
    <ul class="dropdown-menu dropdown-menu-end"
        style="max-width: 200px; white-space: nowrap;">

      <!-- Tất cả tòa nhà -->
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2') }}"
           style="max-width: 180px;">
          Tất cả tòa nhà
        </a>
      </li>
      <li><hr class="dropdown-divider my-1"></li>

      <!-- Lặp tòa nhà -->
      @foreach($buildingsForDropdown as $building)
        <li>
          <a class="dropdown-item text-truncate"
             href="{{ route('v2', ['bkey' => $building->bkey]) }}"
             style="max-width: 180px;">
            {{ $building->bcode }}
          </a>
        </li>
      @endforeach
    </ul>
  </div>
</li>

<!-- ============================= -->
<!-- PHƯƠNG ÁN #1: HIỂN THỊ CHO MOBILE (< lg) -->
<!-- ============================= -->
<li class="nav-item dropdown d-lg-none me-3">
  @php
    // Lấy lại $bkey và xác định tòa nhà đang chọn
    $selectedBkey = $bkey ?? null;
    $currentBuilding = $buildingsForDropdown->firstWhere('bkey', $selectedBkey);
    $buildingTitle = $currentBuilding ? $currentBuilding->bcode : 'Tòa nhà';
  @endphp

  <!-- Một nút (dropdown) gọn gàng cho mobile -->
  <a class="nav-link dropdown-toggle text-truncate" 
     href="#" 
     id="dropdownBuildingsMobile" 
     role="button" 
     data-bs-toggle="dropdown" 
     aria-expanded="false"
     style="max-width: 120px;">
    <i class="bx bx-building"></i>
    <span class="ms-1">{{ $buildingTitle }}</span>
  </a>

  <!-- Danh sách dropdown (mobile) -->
  <ul class="dropdown-menu dropdown-menu-end"
      aria-labelledby="dropdownBuildingsMobile"
      style="max-width: 200px;">

    <!-- Tất cả tòa nhà -->
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2') }}"
         style="max-width: 180px;">
        Tất cả tòa nhà
      </a>
    </li>
    <li><hr class="dropdown-divider my-1"></li>

    <!-- Lặp tòa nhà -->
    @foreach($buildingsForDropdown as $building)
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $building->bkey]) }}"
           style="max-width: 180px;">
          {{ $building->bcode }}
        </a>
      </li>
    @endforeach
  </ul>
</li>


<!-- =========== MOBILE (< lg) =========== -->
<li class="nav-item dropdown d-lg-none me-3">
  @php
    $selectedStatus = $status ?? null;
    switch ($selectedStatus) {
      case 'vacant':
        $statusLabel = 'Phòng trống';
        break;
      case 'deposit':
        $statusLabel = 'Phòng đã cọc';
        break;
      case 'owing':
        $statusLabel = 'Phòng nợ tiền';
        break;
      case 'partiallyPaid':
        $statusLabel = 'Phòng đóng thiếu';
        break;
      case 'missingInvoice':
        $statusLabel = 'Chưa lập hóa đơn';
        break;
      case 'missingFirstInvoice':
        $statusLabel = 'Chưa lập HĐ lần đầu';
        break;
      case 'expiredContract':
        $statusLabel = 'Hết hạn HĐ';
        break;
      case 'nearlyExpired':
        $statusLabel = 'Sắp hết hạn HĐ';
        break;
      default:
        $statusLabel = 'Trạng thái';
        break;
    }
  @endphp

  <a class="nav-link dropdown-toggle text-truncate"
     href="#"
     id="dropdownStatusMobile"
     role="button"
     data-bs-toggle="dropdown"
     aria-expanded="false"
     style="max-width: 140px;">
    <i class="bx bx-filter"></i>
    <span class="ms-1">{{ $statusLabel }}</span>
  </a>

  <ul class="dropdown-menu dropdown-menu-end"
      aria-labelledby="dropdownStatusMobile"
      style="max-width: 240px;">

    <!-- Tất cả trạng thái -->
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey]) }}">
        Tất cả trạng thái
      </a>
    </li>
    <li><hr class="dropdown-divider my-1"></li>

    <!-- Các trạng thái -->
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'vacant']) }}">
        Phòng trống
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'deposit']) }}">
        Phòng đã cọc
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'owing']) }}">
        Phòng nợ tiền
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'partiallyPaid']) }}">
        Phòng đóng thiếu
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'missingInvoice']) }}">
        Phòng chưa lập hóa đơn
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'missingFirstInvoice']) }}">
        Phòng chưa lập HĐ lần đầu
      </a>
    </li>
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'expiredContract']) }}">
        Hết hạn HĐ
      </a>
    </li>
    <!-- Thêm mới -->
    <li>
      <a class="dropdown-item text-truncate"
         href="{{ route('v2', ['bkey' => $bkey, 'status' => 'nearlyExpired']) }}">
        Sắp hết hạn HĐ
      </a>
    </li>
  </ul>
</li>

<!-- =========== DESKTOP (>= lg) =========== -->
<li class="nav-item lh-1 me-3 d-none d-lg-inline-block">
  <div class="btn-group">
    @php
      // Tương tự
      $selectedStatus = $status ?? null;
      switch ($selectedStatus) {
        case 'vacant':
          $statusLabel = 'Phòng trống';
          break;
        case 'deposit':
          $statusLabel = 'Phòng đã cọc';
          break;
        case 'owing':
          $statusLabel = 'Phòng nợ tiền';
          break;
        case 'partiallyPaid':
          $statusLabel = 'Phòng đóng thiếu';
          break;
        case 'missingInvoice':
          $statusLabel = 'Chưa lập hóa đơn';
          break;
        case 'missingFirstInvoice':
          $statusLabel = 'Chưa lập HĐ lần đầu';
          break;
        case 'expiredContract':
          $statusLabel = 'Hết hạn HĐ';
          break;
        case 'nearlyExpired':
          $statusLabel = 'Sắp hết hạn HĐ';
          break;
        default:
          $statusLabel = 'Trạng thái';
          break;
      }
    @endphp

    <button type="button"
            class="btn btn-secondary btn-sm text-truncate"
            style="max-width: 140px;">
      <i class="bx bx-filter"></i>
      <span class="ms-1">{{ $statusLabel }}</span>
    </button>

    <button type="button"
            class="btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown" aria-expanded="false">
      <span class="visually-hidden">Toggle Dropdown</span>
    </button>

    <ul class="dropdown-menu dropdown-menu-end" style="max-width: 240px; white-space: nowrap;">
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey]) }}">
          Tất cả trạng thái
        </a>
      </li>
      <li><hr class="dropdown-divider my-1"></li>

      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'vacant']) }}">
          Phòng trống
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'deposit']) }}">
          Phòng đã cọc
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'owing']) }}">
          Phòng nợ tiền
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'partiallyPaid']) }}">
          Phòng đóng thiếu
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'missingInvoice']) }}">
          Chưa lập hóa đơn mới
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'missingFirstInvoice']) }}">
          Chưa lập hóa đơn đầu
        </a>
      </li>
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'expiredContract']) }}">
          Hợp đồng hết hạn
        </a>
      </li>
      <!-- Thêm mới -->
      <li>
        <a class="dropdown-item text-truncate"
           href="{{ route('v2', ['bkey' => $bkey, 'status' => 'nearlyExpired']) }}">
          Hợp đồng sắp hết hạn
        </a>
      </li>
    </ul>
  </div>
</li>
@endif




			


          <!-- User -->
<li class="nav-item navbar-dropdown dropdown-user dropdown">
  @php
    $navbarUser = Auth::user();
    $navbarAvatarPath = trim((string) ($navbarUser->avatar ?? ''));
    $navbarAvatarUrl = $navbarAvatarPath === ''
      ? asset('assets/img/avatars/avatar.png')
      : (\Illuminate\Support\Str::startsWith($navbarAvatarPath, ['http://', 'https://'])
        ? $navbarAvatarPath
        : asset('storage/' . ltrim($navbarAvatarPath, '/')));
  @endphp
  <a class="nav-link dropdown-toggle hide-arrow p-0" href="#" data-bs-toggle="dropdown">
    <div class="avatar avatar-online">
      <!-- Avatar chính trên navbar -->
      <img
        src="{{ $navbarAvatarUrl }}"
        alt="User Avatar"
        class="w-px-40 h-px-40 rounded-circle"
      >
    </div>
  </a>

  <ul class="dropdown-menu dropdown-menu-end">
    <!-- Thông tin user -->
    <li>
      <a class="dropdown-item" href="#">
        <div class="d-flex align-items-center">
          <div class="flex-shrink-0 me-3">
            <div class="avatar avatar-online">
              <img
                src="{{ $navbarAvatarUrl }}"
                alt="User Avatar"
                class="w-px-40 h-px-40 rounded-circle"
              >
            </div>
          </div>
          <div class="flex-grow-1">
            <!-- Nếu bạn dùng Laravel Auth: -->
              <h6 class="mb-0">{{ Auth::user()->display_name ??  Auth::user()->name }}</h6>
              <small class="text-muted">Mã thành viên: {{ Auth::user()->id ?? 0 }}</small>

          </div>
        </div>
      </a>
    </li>
    <!-- ngăn cách -->
    <li><hr class="dropdown-divider my-1"></li>

    <!-- Link đến trang cá nhân -->
    <li>
      <a class="dropdown-item" href="{{ route('pages-account-settings-account') }}">
        <i class="bx bx-user me-2"></i>
        <span>Thông tin tài khoản</span>
      </a>
    </li>
    <!-- 
    <li>
      <a class="dropdown-item" href="#">
        <i class="bx bx-cog me-2"></i>
        <span>Settings</span>
      </a>
    </li>

    <!-- Link gói thanh toán (Billing Plan) 
    <li>
      <a class="dropdown-item d-flex justify-content-between align-items-center" href="#">
        <span><i class="bx bx-credit-card me-2"></i>Billing Plan</span>
        <span class="badge bg-danger">4</span>
      </a>
    </li>

    <li><hr class="dropdown-divider my-1"></li>

    <!-- Đăng xuất -->
    <li>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="dropdown-item" type="submit">
            <i class="bx bx-power-off me-2"></i>Log Out
          </button>
        </form>


    </li>
  </ul>
</li>
<!-- /User -->

        </ul>
      </div>

      @if(!isset($navbarDetached))
    </div>
    @endif
  </nav>
  <!-- / Navbar -->
