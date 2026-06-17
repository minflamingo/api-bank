@extends('layouts/contentNavbarLayout')

@section('title', 'Thông tin tài khoản')

@section('page-script')
{{-- Kịch bản xử lý upload ảnh qua AJAX và reset ảnh --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const fileInput   = document.getElementById('avatarInput');
  const previewImg  = document.getElementById('uploadedAvatar');
  const hiddenInput = document.getElementById('avatar_path');
  
  // Nếu input file tồn tại, gắn sự kiện "change" => uploadAvatar
  if (fileInput) {
    fileInput.addEventListener('change', function() {
      if (!fileInput.files || fileInput.files.length === 0) {
        alert('Vui lòng chọn ảnh!');
        return;
      }
      const file = fileInput.files[0];

      // Tạo FormData
      const formData = new FormData();
      formData.append('avatar', file);

      // Thêm CSRF token
      const token = document.querySelector('meta[name="csrf-token"]');
      let headers = {};
      if (token) {
        headers['X-CSRF-TOKEN'] = token.content;
      }

      // Gửi request fetch
      fetch('/upload-avatar', {
        method: 'POST',
        headers: headers,
        body: formData
      })
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (!data.success) {
          alert("Lỗi: " + data.message);
          return;
        }
        // Cập nhật ảnh preview
        if (previewImg) {
          previewImg.src = data.image_path; // => /storage/avatars/xxx.jpg
        }
        // Lưu path vào hidden để form submit
        if (hiddenInput) {
          hiddenInput.value = data.file_path; // => "avatars/xxx.jpg"
        }
      })
      .catch(err => {
        console.error("Lỗi upload:", err);
        alert("Đã xảy ra lỗi khi upload ảnh!");
      });
    });
  }

  // Nút "Xóa" avatar
  const resetBtn = document.querySelector('.account-image-reset');
  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      // (1) Xóa giá trị file input
      if (fileInput) {
        fileInput.value = "";
      }
      // (2) Đặt preview về ảnh mặc định
      if (previewImg) {
        previewImg.src = "{{ asset('assets/img/avatars/avatar.png') }}";
      }
      // (3) Gửi request xóa file cũ nếu có
      if (hiddenInput) {
        const oldFilePath = hiddenInput.value; // "avatars/xxx.jpg"
        // => DB sẽ xóa avatar nếu user nhấn "Lưu" vì ta đặt hiddenInput.value = ""
        hiddenInput.value = ""; // xóa path => update() sẽ set avatar=null

        if (oldFilePath) {
          fetch('/delete-avatar', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ file_path: oldFilePath })
          })
          .then(res => res.json())
          .then(data => {
            if (!data.success) {
              console.warn("Xóa ảnh thất bại:", data.message);
            } else {
              console.log("Đã xóa ảnh trên server:", oldFilePath);
            }
          })
          .catch(err => {
            console.error("Lỗi xóa ảnh:", err);
          });
        }
      }
    });
  }
});
</script>
@endsection

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="nav-align-top">
      <ul class="nav nav-pills flex-column flex-md-row mb-6">
        <li class="nav-item">
          <a class="nav-link active" href="{{ route('pages-account-settings-account') }}">
            <i class="bx bx-sm bx-user me-1_5"></i> Tài khoản
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-security') }}">
            <i class="bx bx-sm bx-lock me-1_5"></i> Bảo mật
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-settings-notifications') }}">
            <i class="bx bx-sm bx-bell me-1_5"></i> Thông báo
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('pages-account-settings-connections') }}">
            <i class="bx bx-sm bx-link-alt me-1_5"></i> Kết nối API
          </a>
        </li>
      </ul>
    </div>

    {{-- Nếu có session flash báo thành công --}}
    @if (session('success'))
      <div class="alert alert-success">
        {{ session('success') }}
      </div>
    @endif

    {{-- Nếu có lỗi validate --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card mb-6">
      <!-- Account -->
      <div class="card-body">
        @php
          $avatarPath = trim((string) ($user->avatar ?? ''));
          $avatarUrl  = $avatarPath === ''
                        ? asset('assets/img/avatars/avatar.png')
                        : (\Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
                          ? $avatarPath
                          : asset('storage/' . ltrim($avatarPath, '/')));
        @endphp

        <div class="d-flex align-items-start align-items-sm-center gap-6 pb-4 border-bottom">
          {{-- Ảnh preview --}}
          <img
            src="{{ $avatarUrl }}"
            alt="user-avatar"
            class="d-block w-px-100 h-px-100 rounded"
            id="uploadedAvatar"
            style="object-fit: cover;"
          />

          <div class="button-wrapper">
            {{-- Nhãn để mở hộp thoại chọn file --}}
            <label for="avatarInput" class="btn btn-primary me-3 mb-4" tabindex="0">
              <span class="d-none d-sm-block">Tải lên ảnh mới</span>
              <i class="bx bx-upload d-block d-sm-none"></i>
            </label>

            {{-- Input file ẩn (để AJAX upload) --}}
            <input
              type="file"
              id="avatarInput"
              name="avatar"
              hidden
              accept="image/png, image/jpeg"
            />

            {{-- Nút Xóa --}}
            <button
              type="button"
              class="btn btn-outline-secondary account-image-reset mb-4"
            >
              <i class="bx bx-reset d-block d-sm-none"></i>
              <span class="d-none d-sm-block">Xóa</span>
            </button>

            <div>Ảnh JPG hoặc PNG, kích thước tối đa 2MB.</div>
          </div>
        </div>
      </div>

      <div class="card-body pt-4">
        {{-- Form cập nhật thông tin user (displayName, phone,...) --}}
        <form
          id="formAccountSettings"
          method="POST"
          action="{{ route('pages-account-settings-account.update') }}"
          enctype="multipart/form-data"
        >
          @csrf
          @method('PUT')

          {{-- Hidden lưu path ảnh (nếu AJAX upload) --}}
          <input
            type="hidden"
            name="avatar_path"
            id="avatar_path"
            value="{{ old('avatar_path', $user->avatar) }}"
          />

          <div class="row g-6">
            <div class="col-md-6">
              <label for="displayName" class="form-label">Tên hiển thị</label>
              <input
                class="form-control"
                type="text"
                id="displayName"
                name="displayName"
                value="{{ old('displayName', $user->display_name) }}"
                autofocus
              />
            </div>

            <div class="col-md-6">
              <label for="name" class="form-label">Tên đăng nhập</label>
              <input
                class="form-control"
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $user->name) }}"
                disabled
              />
            </div>

            <div class="col-md-6">
              <label for="email" class="form-label">E-mail</label>
              <input
                class="form-control"
                type="text"
                id="email"
                name="email"
                value="{{ old('email', $user->email) }}"
                disabled
              />
            </div>

            <div class="col-md-6">
              <label for="type" class="form-label">Loại tài khoản</label>
              <input
                class="form-control"
                type="text"
                id="type"
                name="type"
                value="{{ old('type', $user->type ?? 'Thử nghiệm') }}"
                disabled
              />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="phone">Số điện thoại</label>
              <div class="input-group input-group-merge">
                <span class="input-group-text">VN</span>
                <input
                  type="text"
                  id="phone"
                  name="phone"
                  class="form-control"
                  placeholder="0987123456"
                  value="{{ old('phone', $user->phone) }}"
                />
              </div>
            </div>

            <div class="col-md-6">
              <label for="create_at" class="form-label">Ngày tạo tài khoản</label>
              <input
                class="form-control"
                type="text"
                id="create_at"
                name="create_at"
                value="{{ $user->created_at }}"
                disabled
              />
            </div>
          </div>

          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Lưu thay đổi</button>
            <!--<button type="reset" class="btn btn-outline-secondary">Hủy bỏ</button>-->
          </div>
        </form>
      </div>
      <!-- /Account -->
    </div>
  </div>
</div>
@endsection
