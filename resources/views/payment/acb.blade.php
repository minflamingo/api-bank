@extends('layouts/contentNavbarLayout')

@section('title', 'Danh sách tài khoản ACB')

@section('content')
<section class="pcoded-main-container">
  <div class="pcoded-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-12">
            <div class="page-header-title">
              <h5 class="m-b-10">Danh sách tài khoản ACB</h5>
            </div>
            <ul class="breadcrumb">
              <li class="breadcrumb-item">
                <a href="{{ url('/') }}"><i class="feather icon-home"></i></a>
              </li>
              <li class="breadcrumb-item">ACB</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <div class="row">
      <div class="col-xl-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between">
            <h5>Tài khoản ACB</h5>
            <button class="btn btn-success btn-sm"
                    data-bs-toggle="modal" data-bs-target="#addAcbModal">
              Thêm ACB
            </button>
          </div>
          <div class="card-body table-border-style">
            <div class="table-responsive">
              <table class="table" id="datatable">
                <thead>
                  <tr>
                    <th>Tài khoản (SĐT)</th>
                    <th>Số TK</th>
                    <th>Chủ TK</th>
                    <th>Loại tài khoản</th>
                    <th>Số dư</th>
                    <th>Thời gian thêm</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($accounts as $row)
                    <tr>
                      <td>{{ $row->phone }}</td>
                      <td>{{ $row->stk }}</td>
                      <td>{{ $row->name }}</td>
                      <!-- Cột loại tài khoản mới -->
                      <td class="type-acb" data-token="{{ $row->token }}">Đang tải...</td>
                      <!-- Cột hiển thị số dư -->
                      <td class="money-acb" data-token="{{ $row->token }}">Đang tải...</td>
                      <td>{{ date('H:i:s d/m/Y', $row->time) }}</td>
                      <td>
                        <input type="hidden" id="token_{{ $row->id }}" value="{{ $row->token }}">
                        <a href="{{ url('client/viewhisacb/'.$row->stk) }}" class="btn btn-success btn-xs">
                          <i class="fa fa-list"></i> Lịch sử
                        </a>
                        <button class="btn btn-warning btn-xs"
                                onclick="GetToken({{ $row->id }})">
                          <i class="fa fa-power-off"></i> Lấy Token
                        </button>
                        <button class="btn btn-danger btn-xs"
                                onclick="DeleteAcb({{ $row->id }})">
                          <i class="fa fa-trash"></i> Xoá
                        </button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL Thêm ACB -->
    <div class="modal fade" id="addAcbModal" tabindex="-1" aria-labelledby="addAcbModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Thông tin tài khoản ACB</h5>
            <button type="button" class="btn-close"
                    data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="account">Tài khoản ACB (SĐT)</label>
              <input type="text" class="form-control" id="account" placeholder="SĐT ACB">
            </div>
            <div class="mb-3">
              <label for="password">Mật khẩu</label>
              <input type="password" class="form-control" id="password" placeholder="Mật khẩu ACB">
            </div>
            <div class="mb-3">
              <label for="stk">Số tài khoản</label>
              <input type="text" class="form-control" id="stk" placeholder="Số TK ACB">
            </div>
            <div class="mt-3">
              <button type="button" id="btnLoginAcb" class="btn btn-success w-100">
                Đăng Nhập
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- end modal -->
  </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function(){
  // Hàm cập nhật cột Số dư và Loại tài khoản
  function updateBalances(){
    document.querySelectorAll('.money-acb').forEach(function(el){
      let token = el.dataset.token;
      // Lấy tr (row) chứa nó, để cập nhật luôn cột type-acb trong cùng hàng
      let row = el.closest('tr');
      let typeTd = row.querySelector('.type-acb[data-token="'+ token +'"]');

      let apiUrl = "/v1/acb/balance/" + token;
      fetch(apiUrl)
        .then(res => res.json())
        .then(data => {
          if(data.status === "200"){
            // Cập nhật số dư
            el.textContent = parseInt(data.SoDu).toLocaleString('en-US') + 'đ';
            // Cập nhật loại tài khoản
            if (typeTd) {
              typeTd.textContent = data.accountDescription ?? 'N/A';
            }
          } else {
            el.textContent = "Lỗi";
            if (typeTd) {
              typeTd.textContent = "Lỗi";
            }
          }
        })
        .catch(err => {
          el.textContent = "Err";
          if (typeTd) {
            typeTd.textContent = "Err";
          }
        });
    });
  }

  // Gọi lần đầu
  updateBalances();
  // 15s refresh 1 lần
  setInterval(updateBalances, 15000);

  // Đăng nhập
  let btnLoginAcb = document.getElementById('btnLoginAcb');
  btnLoginAcb.addEventListener('click', function(){
    btnLoginAcb.disabled = true;
    btnLoginAcb.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang xử lý...';

    let body = {
      account : document.getElementById('account').value,
      password: document.getElementById('password').value,
      stk     : document.getElementById('stk').value
    };

    fetch("{{ route('payment.acb.login') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
      alert(data.msg);
      if(data.status === '2'){
        setTimeout(() => window.location.reload(), 1000);
      }
    })
    .finally(() => {
      btnLoginAcb.disabled = false;
      btnLoginAcb.innerHTML = 'Đăng Nhập';
    });
  });
});

// Hàm xoá tài khoản ACB
function DeleteAcb(id){
  if(!confirm("Bạn có chắc muốn xoá?")) return;
  let tokenVal = document.getElementById("token_"+id).value;

  let body = {
    action: "REMOVE",
    id: id,
    token: tokenVal
  };

  fetch("{{ route('payment.acb.remove') }}", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(body)
  })
  .then(res => res.json())
  .then(data => {
    alert(data.msg);
    if(data.status === '2'){
      setTimeout(() => window.location.reload(), 1000);
    }
  })
  .catch(err => alert("Lỗi kết nối!"));
}

// Lấy token
function GetToken(id){
  if(!confirm("Bạn có chắc muốn lấy token qua Email?")) return;
  let tokenVal = document.getElementById("token_"+id).value;

  let body = {
    action: "SENDTOKEN",
    id: id,
    token: tokenVal
  };

  fetch("{{ route('payment.acb.sendToken') }}", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(body)
  })
  .then(res => res.json())
  .then(data => {
    alert(data.msg);
  })
  .catch(err => alert("Lỗi kết nối!"));
}
</script>
@endsection
