@extends('layouts/contentNavbarLayout')

@section('title', 'Danh sách Tòa nhà')

@section('vendor-style')
  {{-- Đặt link CSS vendor cần thiết ở đây (nếu có) --}}
@endsection

@section('vendor-script')
  {{-- Đặt link JS vendor cần thiết ở đây (nếu có) --}}
@endsection

@section('content')
<!-- [ Main Content ] start -->
<section class="pcoded-main-container">
  <div class="pcoded-content">

    <!-- [ breadcrumb ] start -->
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Danh sách tài khoản Vietcombank</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item">
                <a href="{{ url('/') }}"><i class="feather icon-home"></i></a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ url('/') }}">Trang chủ</a>
              </li>
              <li class="breadcrumb-item">
                Danh sách tài khoản Vietcombank
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <!-- [ breadcrumb ] end -->

    <!-- [ Main Content ] start -->
    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <!-- HEADER -->
          <div class="card-header">
            <div class="row w-100">
              <div class="col-6">
                <h5>Tài khoản Vietcombank</h5>
              </div>
              <div class="col-6 d-flex justify-content-end">
                <button
                  class="btn btn-success btn-sm"
                  data-bs-toggle="modal"
                  data-bs-target="#addVcbModal"
                >
                  Thêm Vietcombank
                </button>
              </div>
            </div>
          </div>
          <!-- BODY -->
          <div class="card-body table-border-style">
            <div class="table-responsive">
              <table class="table w-100" id="datatable">
                <thead>
                  <tr>
                    <th>TÀI KHOẢN</th>
                    <th>CHỦ TÀI KHOẢN</th>
                    <th>SỐ TÀI KHOẢN</th>
                    <th>SỐ DƯ</th>
                    <th>THỜI GIAN THÊM</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($accounts as $row)
                    <tr>
                      <td>{{ $row->username }}</td>
                      <td>{{ $row->name }}</td>
                      <td>{{ $row->account }}</td>
                      {{-- Hiển thị số dư (AJAX load) --}}
                      <td class="money-vietcombank" data-token="{{ $row->token }}">Đang tải...</td>
                      <td>{{ $row->create_date }}</td>
                      <td>
                        <input
                          type="hidden"
                          id="token_{{ $row->id }}"
                          value="{{ $user->token }}"
                        />

                        {{-- Xem lịch sử giao dịch --}}
                        <a href="{{ url('client/viewhisvcb/'.$row->account) }}">
                          <button class="btn btn-success btn-xs" type="button">
                            <i class="fa fa-list"></i> Lịch sử giao dịch
                          </button>
                        </a>

                        {{-- Lấy token qua Email --}}
                        <button
                          class="btn btn-warning btn-xs"
                          onclick="GetToken({{ $row->id }})"
                          type="button"
                        >
                          <i class="fa fa-power-off"></i> Lấy Token
                        </button>

                        {{-- Xóa tài khoản --}}
                        <button
                          class="btn btn-danger btn-xs"
                          onclick="DeleteVcb({{ $row->id }})"
                          type="button"
                        >
                          <i class="fa fa-trash"></i> Xóa
                        </button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
          <!-- END BODY -->
        </div>
      </div>
    </div>
    <!-- [ Main Content ] end -->
  </div>
</section>
<!-- [ Main Content ] end -->

<!-- MODAL THÊM TÀI KHOẢN VCB -->
<div class="modal fade" id="addVcbModal" tabindex="-1" aria-labelledby="addVcbModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- HEADER -->
      <div class="modal-header">
        <h5 class="modal-title" id="addVcbModalLabel">Thông tin tài khoản Vietcombank</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <!-- BODY -->
      <div class="modal-body">
        <label class="floating-label">
          Khi bạn thêm Vietcombank mà thấy thông báo <b>"Data Invalid"</b>
          vui lòng vào app mở khóa đăng nhập web:
          <br>
          Cài đặt &gt; Quản lý đăng nhập kênh &gt; Cài đặt đăng nhập VCB Digibank trên Web
        </label>
        <div class="row">
          {{-- Tài khoản --}}
          <div class="col-sm-12 mb-3">
            <label class="floating-label" for="account">Tài khoản Vietcombank</label>
            <input
              type="text"
              class="form-control"
              id="account"
              placeholder="Nhập tài khoản Vietcombank"
            />
            <input
              type="hidden"
              class="form-control"
              id="token"
              value="{{ $user->token }}"
            />
          </div>

          {{-- Mật khẩu --}}
          <div class="col-sm-12 mb-3">
            <label class="floating-label" for="password">Mật khẩu Vietcombank</label>
            <input
              type="text"
              class="form-control"
              id="password"
              placeholder="Mật khẩu Vietcombank"
            />
          </div>

          {{-- Số tài khoản --}}
          <div class="col-sm-12 mb-3">
            <label class="floating-label" for="stk">Số tài khoản Vietcombank</label>
            <input
              type="text"
              class="form-control"
              id="stk"
              placeholder="Nhập số tài khoản Vietcombank"
            />
          </div>

          {{-- OTP --}}
          <div class="col-sm-12 mb-3">
            <label class="floating-label" for="btnGetOtp">OTP XÁC THỰC</label>
            <div class="input-group">
              <input
                type="number"
                max="6"
                id="otp"
                class="form-control"
                placeholder="Nhập OTP khi nhận từ SMS"
              />
              <button class="btn btn-dark" type="button" id="btnGetOtp">
                GET OTP
              </button>
            </div>
          </div>

          {{-- Đăng nhập --}}
          <div class="col-sm-12">
            <button type="button" id="btnLogin" class="btn btn-success w-100">
              Đăng Nhập
            </button>
          </div>
        </div>
      </div>
      <!-- END BODY -->
    </div>
  </div>
</div>
<!-- END MODAL -->
@endsection

@section('page-script')
<script>
  // Cho phép gọi từ inline onclick
  window.DeleteVcb = DeleteVcb;
  window.GetToken = GetToken;

  // Khi DOM đã sẵn sàng
  document.addEventListener("DOMContentLoaded", function() {
    
    // (1) Cập nhật số dư
    function updateBalances() {
      let elements = document.querySelectorAll('.money-vietcombank');
      elements.forEach(el => {
        let token = el.dataset.token;
        let apiUrl = "/v1/vcb/balance/" + token; // route GET API

        fetch(apiUrl)
          .then(response => response.json())
          .then(data => {
            if (data.status === 200) {
              el.textContent = parseInt(data.SoDu).toLocaleString('en-US') + "đ";
            } else {
              el.textContent = 'Lỗi khi tải dữ liệu';
            }
          })
          .catch(() => {
            el.textContent = 'Lỗi kết nối';
          });
      });
    }
    // Gọi lần đầu + setInterval 10s
    updateBalances();
    setInterval(updateBalances, 10000);

    // (2) GET OTP
    let btnGetOtp = document.getElementById('btnGetOtp');
    if (btnGetOtp) {
      btnGetOtp.addEventListener('click', function() {
        btnGetOtp.disabled = true;
        btnGetOtp.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang xử lý...';

        let bodyData = {
          action: 'GETOTP',
          account:  document.getElementById("account").value,
          password: document.getElementById("password").value,
          stk:      document.getElementById("stk").value,
          token:    document.getElementById("token").value
        };

        fetch("{{ route('payment.vcb.getOtp') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(bodyData)
        })
        .then(res => res.json())
        .then(data => {
          alert(data.msg);
          if (data.status === '3') {
            // Reload sau 1 giây
            setTimeout(() => location.reload(), 1000);
          }
        })
        .catch(() => {
          alert("Lỗi kết nối!");
        })
        .finally(() => {
          btnGetOtp.disabled = false;
          btnGetOtp.innerHTML = 'GET OTP';
        });
      });
    }

    // (3) Đăng nhập
    let btnLogin = document.getElementById('btnLogin');
    if (btnLogin) {
      btnLogin.addEventListener('click', function() {
        btnLogin.disabled = true;
        btnLogin.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang xử lý...';

        let bodyData = {
          action:   'LOGIN',
          account:  document.getElementById("account").value,
          password: document.getElementById("password").value,
          stk:      document.getElementById("stk").value,
          otp:      document.getElementById("otp").value,
          token:    document.getElementById("token").value
        };

        fetch("{{ route('payment.vcb.loginOtp') }}", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(bodyData)
        })
        .then(res => res.json())
        .then(data => {
          alert(data.msg);
          if (data.status === '2') {
            setTimeout(() => location.reload(), 1000);
          }
        })
        .catch(() => {
          alert("Lỗi kết nối!");
        })
        .finally(() => {
          btnLogin.disabled = false;
          btnLogin.innerHTML = 'Đăng Nhập';
        });
      });
    }
  });

  // (4) Xóa tài khoản
  function DeleteVcb(id) {
    if (!confirm("Bạn có chắc chắn muốn xóa không?")) return;
    let tokenVal = document.getElementById("token_"+id).value;

    let bodyData = {
      action: "REMOVE",
      id: id,
      token: tokenVal
    };

    fetch("{{ route('payment.vcb.remove') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(bodyData)
    })
    .then(res => res.json())
    .then(data => {
      alert(data.msg);
      if (data.status === '2') {
        setTimeout(() => location.reload(), 1000);
      }
    })
    .catch(() => alert("Lỗi kết nối!"));
  }

  // (5) Gửi token qua Email
  function GetToken(id) {
    if (!confirm("Bạn có chắc muốn lấy token qua Email?")) return;
    let tokenVal = document.getElementById("token_"+id).value;

    let bodyData = {
      action: "SENDTOKEN",
      id: id,
      token: tokenVal
    };

    fetch("{{ route('payment.vcb.sendToken') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(bodyData)
    })
    .then(res => res.json())
    .then(data => alert(data.msg))
    .catch(() => alert("Lỗi kết nối!"));
  }
</script>
@endsection
