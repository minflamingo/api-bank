@extends('layouts/contentNavbarLayout')

@section('title', 'Thêm tài khoản ngân hàng')

@section('page-style')
<style>
  .bank-create-page {
    --bank-border: rgba(67, 89, 113, .14);
    --bank-muted: #697a8d;
    --bank-touch: 46px;
  }

  .bank-create-page .btn-touch,
  .bank-create-page .form-control,
  .bank-create-page .input-group-text {
    min-height: var(--bank-touch);
    border-radius: .5rem;
  }

  .bank-create-page .btn-touch {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    white-space: nowrap;
  }

  .bank-create-page .connect-panel {
    border: 1px solid var(--bank-border);
    border-radius: .5rem;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
    overflow: hidden;
    background: #fff;
  }

  .bank-create-page .connect-main,
  .bank-create-page .connect-aside {
    padding: 1.25rem;
  }

  .bank-create-page .connect-aside {
    height: 100%;
    border-left: 1px solid var(--bank-border);
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  }

  .bank-create-page .step-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .65rem;
  }

  .bank-create-page .step-item {
    border: 1px solid var(--bank-border);
    border-radius: .5rem;
    padding: .75rem;
    background: #fff;
  }

  .bank-create-page .step-item.is-active {
    border-color: rgba(105, 108, 255, .45);
    background: rgba(105, 108, 255, .07);
  }

  .bank-create-page .step-index {
    width: 1.7rem;
    height: 1.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #eef0f4;
    color: #566a7f;
    font-weight: 700;
    font-size: .78rem;
  }

  .bank-create-page .step-item.is-active .step-index {
    background: #696cff;
    color: #fff;
  }

  .bank-create-page .bank-option {
    display: block;
    cursor: pointer;
    height: 100%;
    position: relative;
  }

  .bank-create-page .bank-option-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  .bank-create-page .bank-option-card {
    height: 100%;
    border: 1px solid var(--bank-border);
    border-radius: .5rem;
    padding: .9rem;
    background: #fff;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .bank-create-page .bank-option-input:focus + .bank-option-card,
  .bank-create-page .bank-option-input:checked + .bank-option-card,
  .bank-create-page .bank-option:hover .bank-option-card {
    border-color: #696cff;
    box-shadow: 0 0 0 .18rem rgba(105, 108, 255, .12);
  }

  .bank-create-page .bank-icon {
    width: 2.45rem;
    height: 2.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;
    background: #eef0ff;
    color: #696cff;
    font-size: 1.35rem;
  }

  .bank-create-page .field-icon {
    width: 2.8rem;
    justify-content: center;
    color: var(--bank-muted);
  }

  .bank-create-page .info-strip,
  .bank-create-page .aside-block,
  .bank-create-page .otp-panel {
    border: 1px solid var(--bank-border);
    border-radius: .5rem;
    padding: .9rem;
  }

  .bank-create-page .info-strip {
    border-color: rgba(105, 108, 255, .24);
    background: rgba(105, 108, 255, .06);
  }

  .bank-create-page .otp-panel {
    display: none;
    border-color: rgba(255, 171, 0, .34);
    background: rgba(255, 171, 0, .08);
  }

  .bank-create-page .otp-panel.is-open {
    display: block;
  }

  .bank-create-page .otp-code-input {
    letter-spacing: .28rem;
    font-size: 1.35rem;
    font-weight: 700;
    text-align: center;
  }

  .bank-create-page .aside-list {
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .bank-create-page .aside-list li {
    display: flex;
    gap: .5rem;
    align-items: flex-start;
    color: var(--bank-muted);
    font-size: .88rem;
  }

  .bank-create-page .aside-list li + li {
    margin-top: .65rem;
  }

  .bank-create-page .action-row {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
  }

  .bank-create-page .action-row .btn-primary,
  .bank-create-page .action-row .btn-success {
    min-width: 12rem;
  }

  @media (max-width: 991.98px) {
    .bank-create-page .connect-aside {
      border-left: 0;
      border-top: 1px solid var(--bank-border);
    }
  }

  @media (max-width: 767.98px) {
    .bank-create-page .connect-main,
    .bank-create-page .connect-aside {
      padding: 1rem;
    }

    .bank-create-page .step-row {
      grid-template-columns: 1fr;
    }

    .bank-create-page .action-row,
    .bank-create-page .page-actions {
      display: grid;
      grid-template-columns: 1fr;
    }

    .bank-create-page .action-row .btn,
    .bank-create-page .page-actions .btn {
      width: 100%;
    }
  }
</style>
@endsection

@section('content')
@php
  $editAccount = $editAccount ?? null;
  $pendingVcb = $pendingVcb ?? [];
  $pendingVpbank = $pendingVpbank ?? [];
  $pendingTechcombank = $pendingTechcombank ?? [];
  $pendingBank = !empty($pendingTechcombank) ? 'techcombank' : (!empty($pendingVpbank) ? 'vpbank' : (!empty($pendingVcb) ? 'vcb' : null));
  $pending = $pendingBank === 'techcombank' ? $pendingTechcombank : ($pendingBank === 'vpbank' ? $pendingVpbank : ($pendingBank === 'vcb' ? $pendingVcb : []));
  $hasPendingOtp = request()->boolean('otp') && !empty($pending) && $pendingBank;
  $currentBank = old('bank_code', $hasPendingOtp ? $pendingBank : $defaultBank);
  $formSeed = $hasPendingOtp ? $pending : ($editAccount ?: []);
  $isEditing = !$hasPendingOtp && !empty($editAccount);
  $systemReceiver = (bool) ($systemReceiver ?? false);
  $indexUrl = $systemReceiver ? route('admin.recharge-settings.edit', ['tab' => 'accounts']) . '#accounts' : route('bank.accounts.index');
@endphp

<div id="bankCreatePage"
     class="bank-create-page"
     data-store-url="{{ route('bank.accounts.store') }}"
     data-index-url="{{ $indexUrl }}"
     data-has-pending-otp="{{ $hasPendingOtp ? '1' : '0' }}"
     data-pending-bank="{{ $pendingBank ?: '' }}"
     data-system-receiver="{{ $systemReceiver ? '1' : '0' }}"
     data-edit-mode="{{ $isEditing ? '1' : '0' }}">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">{{ $systemReceiver ? 'Super Admin' : 'API ngân hàng' }}</div>
      <h4 class="mb-1">
        @if($systemReceiver)
          {{ $isEditing ? 'Cập nhật account nhận nạp' : 'Kết nối account nhận nạp hệ thống' }}
        @else
          {{ $isEditing ? 'Cập nhật kết nối ngân hàng' : 'Kết nối tài khoản ngân hàng' }}
        @endif
      </h4>
      <div class="text-muted">
        @if($systemReceiver)
          Account này lưu ở nhóm hệ thống để làm tài khoản nhận nạp, không tính vào giới hạn gói của user.
        @else
          {{ $isEditing ? 'Nhập lại mật khẩu và xác thực để làm mới phiên API cho tài khoản hiện có.' : 'Thêm ACB, Vietcombank, VPBank, Techcombank hoặc MBBank để tạo API số dư và giao dịch.' }}
        @endif
      </div>
    </div>
    <div class="page-actions">
      <a class="btn btn-outline-secondary btn-touch" href="{{ $indexUrl }}">
        <i class="bx bx-arrow-back"></i> {{ $systemReceiver ? 'Cấu hình nhận nạp' : 'Danh sách' }}
      </a>
    </div>
  </div>

  <div class="connect-panel">
    <div class="row g-0">
      <div class="col-12 col-lg-8">
        <div class="connect-main">
          <div class="step-row mb-4">
            <div class="step-item is-active" data-step-card="info">
              <div class="d-flex align-items-center gap-2">
                <span class="step-index">1</span>
                <div>
                  <div class="fw-semibold">Thông tin</div>
                  <div class="text-muted small">Ngân hàng và tài khoản</div>
                </div>
              </div>
            </div>
            <div class="step-item {{ $hasPendingOtp ? 'is-active' : '' }}" data-step-card="verify">
              <div class="d-flex align-items-center gap-2">
                <span class="step-index">2</span>
                <div>
                  <div class="fw-semibold">Xác thực</div>
                  <div class="text-muted small">Đăng nhập hoặc OTP</div>
                </div>
              </div>
            </div>
            <div class="step-item" data-step-card="api">
              <div class="d-flex align-items-center gap-2">
                <span class="step-index">3</span>
                <div>
                  <div class="fw-semibold">API</div>
                  <div class="text-muted small">Token và endpoint</div>
                </div>
              </div>
            </div>
          </div>

          <div data-form-alerts>
            @if ($isEditing)
              <div class="alert alert-info">
                Đang cập nhật {{ $editAccount['account_no'] ?? '' }}{{ !empty($editAccount['account_name']) ? ' - ' . $editAccount['account_name'] : '' }}. Mật khẩu cũ không được hiển thị, vui lòng nhập lại để làm mới phiên.
              </div>
            @endif
            @if (session('warning'))
              <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
              <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Chưa kết nối được</div>
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
              </div>
            @endif
          </div>

          <form id="bankConnectForm" method="POST" action="{{ route('bank.accounts.store') }}">
            @csrf
            <input type="hidden" name="step" value="{{ $hasPendingOtp ? 'otp' : 'init' }}" data-step-input>
            @if($systemReceiver)
              <input type="hidden" name="system_receiver" value="1">
            @endif

            <div class="mb-4">
              <label class="form-label fw-semibold">Chọn ngân hàng</label>
              <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="bank-option">
                    <input class="bank-option-input" type="radio" name="bank_code" value="acb" @checked($currentBank === 'acb')>
                    <span class="bank-option-card d-flex gap-3">
                      <span class="bank-icon"><i class="bx bx-credit-card"></i></span>
                      <span>
                        <span class="d-block fw-semibold">ACB</span>
                        <span class="d-block text-muted small">Dùng số điện thoại đăng nhập và mật khẩu ACB.</span>
                      </span>
                    </span>
                  </label>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="bank-option">
                    <input class="bank-option-input" type="radio" name="bank_code" value="vcb" @checked($currentBank === 'vcb')>
                    <span class="bank-option-card d-flex gap-3">
                      <span class="bank-icon"><i class="bx bx-bank"></i></span>
                      <span>
                        <span class="d-block fw-semibold">Vietcombank</span>
                        <span class="d-block text-muted small">Tự giải captcha qua apibank.com.vn, hỗ trợ OTP.</span>
                      </span>
                    </span>
                  </label>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="bank-option">
                    <input class="bank-option-input" type="radio" name="bank_code" value="vpbank" @checked($currentBank === 'vpbank')>
                    <span class="bank-option-card d-flex gap-3">
                      <span class="bank-icon"><i class="bx bx-buildings"></i></span>
                      <span>
                        <span class="d-block fw-semibold">VPBank</span>
                        <span class="d-block text-muted small">Kết nối VPBank NEO, hỗ trợ OTP thiết bị tin cậy.</span>
                      </span>
                    </span>
                  </label>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="bank-option">
                    <input class="bank-option-input" type="radio" name="bank_code" value="techcombank" @checked($currentBank === 'techcombank')>
                    <span class="bank-option-card d-flex gap-3">
                      <span class="bank-icon"><i class="bx bx-building-house"></i></span>
                      <span>
                        <span class="d-block fw-semibold">Techcombank</span>
                        <span class="d-block text-muted small">Kết nối web và xác nhận đăng nhập trên app Mobile.</span>
                      </span>
                    </span>
                  </label>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                  <label class="bank-option">
                    <input class="bank-option-input" type="radio" name="bank_code" value="mbbank" @checked($currentBank === 'mbbank')>
                    <span class="bank-option-card d-flex gap-3">
                      <span class="bank-icon"><i class="bx bx-bank"></i></span>
                      <span>
                        <span class="d-block fw-semibold">MBBank</span>
                        <span class="d-block text-muted small">Tự giải captcha qua apibank.com.vn, đăng nhập một bước.</span>
                      </span>
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <div class="info-strip mb-4" data-bank-note>
              <div class="fw-semibold mb-1">ACB kết nối trực tiếp</div>
              <div class="text-muted small">Nhập số điện thoại ACB, mật khẩu và số tài khoản cần lấy giao dịch.</div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="bankUsername" data-username-label>Số điện thoại đăng nhập</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input id="bankUsername"
                         class="form-control"
                         name="username"
                         value="{{ old('username', $formSeed['username'] ?? '') }}"
                         autocomplete="username"
                         required>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-semibold" for="bankAccountNo" data-account-label>Số tài khoản</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input id="bankAccountNo"
                         class="form-control"
                         name="account_no"
                         value="{{ old('account_no', $formSeed['account_no'] ?? '') }}"
                         inputmode="numeric"
                         required>
                </div>
              </div>
              <div class="col-12" data-password-field>
                <label class="form-label fw-semibold" for="bankPassword">Mật khẩu ngân hàng</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-lock-alt"></i></span>
                  <input id="bankPassword"
                         class="form-control"
                         type="password"
                         name="password"
                         value="{{ old('password', $hasPendingOtp ? ($pending['password'] ?? '') : '') }}"
                         autocomplete="current-password"
                         required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                    <i class="bx bx-show"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="otp-panel mt-4 {{ $hasPendingOtp ? 'is-open' : '' }}" data-otp-panel>
              <div class="d-flex gap-2 mb-3">
                <i class="bx bx-message-rounded-dots fs-4 text-warning"></i>
                <div>
                  <div class="fw-semibold" data-otp-title>Ngân hàng yêu cầu OTP</div>
                  <div class="text-muted small" data-otp-message>Nhập mã OTP vừa gửi về điện thoại để hoàn tất lưu tài khoản.</div>
                </div>
              </div>
              <div data-otp-code-area>
                <label class="form-label fw-semibold" for="bankOtp">Mã OTP</label>
                <input id="bankOtp"
                       class="form-control otp-code-input"
                       name="otp_code"
                       maxlength="12"
                       inputmode="numeric"
                       autocomplete="one-time-code"
                       placeholder="------">
              </div>
              <div data-techcombank-manual-area style="display:none">
                <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                  <a class="btn btn-outline-primary btn-touch" href="#" target="_blank" rel="noopener" data-techcombank-auth-link>
                    <i class="bx bx-log-in-circle"></i> Mở Techcombank
                  </a>
                  <button class="btn btn-outline-secondary btn-touch" type="button" data-copy-techcombank-link>
                    <i class="bx bx-copy"></i> Copy link
                  </button>
                </div>
                <label class="form-label fw-semibold" for="bankRedirectUrl">URL sau khi xác nhận trên Techcombank</label>
                <textarea id="bankRedirectUrl"
                          class="form-control"
                          name="redirect_url"
                          rows="3"
                          placeholder="Dán toàn bộ URL có code=... sau khi đăng nhập và duyệt app Mobile"></textarea>
                <div class="text-muted small mt-2">Sau khi đăng nhập thành công, copy nguyên thanh địa chỉ của trình duyệt rồi dán vào đây.</div>
              </div>
            </div>

            <div class="action-row mt-4">
              <button class="btn btn-primary btn-touch" type="submit" data-submit-btn>
                <span class="submit-icon"><i class="bx bx-link-alt"></i></span>
                <span data-submit-text>Kết nối tài khoản</span>
              </button>
              <button class="btn btn-outline-secondary btn-touch" type="button" data-reset-form>
                <i class="bx bx-rotate-left"></i> Làm lại
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="connect-aside">
          <div class="aside-block mb-3">
            <div class="fw-semibold mb-2">Sau khi kết nối</div>
            <ul class="aside-list">
              <li><i class="bx bx-check-circle text-success mt-1"></i><span>Hệ thống lưu phiên đăng nhập để sinh API số dư và giao dịch.</span></li>
              <li><i class="bx bx-check-circle text-success mt-1"></i><span>Token API nằm ở màn danh sách, có nút copy nhanh.</span></li>
              <li><i class="bx bx-check-circle text-success mt-1"></i><span>Số dư được tải sau bằng AJAX để trang mở nhanh hơn.</span></li>
            </ul>
          </div>

          <div class="aside-block mb-3">
            <div class="fw-semibold mb-2">Vietcombank</div>
            <div class="text-muted small">
              Nếu báo Data Invalid, hãy mở app Vietcombank và bật đăng nhập web:
              Cài đặt - Quản lý đăng nhập kênh - Cài đặt đăng nhập VCB Digibank trên Web.
            </div>
          </div>

          <div class="aside-block">
            <div class="fw-semibold mb-2">Giới hạn</div>
            <div class="text-muted small">
              {{ $systemReceiver ? 'Account nhận nạp hệ thống không tính vào giới hạn gói khách hàng.' : 'Gói hiện tại cho phép tối đa ' . $accountLimit . ' tài khoản ngân hàng.' }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const page = document.getElementById('bankCreatePage');
  if (!page) return;

  const form = document.getElementById('bankConnectForm');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const stepInput = page.querySelector('[data-step-input]');
  const submitBtn = page.querySelector('[data-submit-btn]');
  let submitText = page.querySelector('[data-submit-text]');
  const otpPanel = page.querySelector('[data-otp-panel]');
  const otpInput = document.getElementById('bankOtp');
  const otpCodeArea = page.querySelector('[data-otp-code-area]');
  const manualArea = page.querySelector('[data-techcombank-manual-area]');
  const authLink = page.querySelector('[data-techcombank-auth-link]');
  const copyAuthLinkBtn = page.querySelector('[data-copy-techcombank-link]');
  const redirectInput = document.getElementById('bankRedirectUrl');
  const alerts = page.querySelector('[data-form-alerts]');
  const note = page.querySelector('[data-bank-note]');
  const usernameLabel = page.querySelector('[data-username-label]');
  const accountLabel = page.querySelector('[data-account-label]');
  const passwordField = page.querySelector('[data-password-field]');
  const passwordInput = document.getElementById('bankPassword');
  const isEditMode = page.dataset.editMode === '1';
  const isSystemReceiver = page.dataset.systemReceiver === '1';
  const pendingTechcombankAuthUrl = @json($pendingBank === 'techcombank' ? ($pending['auth_url'] ?? '') : '');
  const bankNames = {
    acb: 'ACB',
    vcb: 'Vietcombank',
    vpbank: 'VPBank',
    techcombank: 'Techcombank',
    mbbank: 'MBBank'
  };

  const bankCopy = {
    acb: {
      username: 'Số điện thoại đăng nhập',
      account: 'Số tài khoản ACB',
      noteTitle: 'ACB kết nối trực tiếp',
      noteText: 'Nhập số điện thoại ACB, mật khẩu và số tài khoản cần lấy giao dịch.',
      submit: 'Kết nối ACB'
    },
    vcb: {
      username: 'Tên đăng nhập Vietcombank',
      account: 'Số tài khoản Vietcombank',
      noteTitle: 'Vietcombank có thể cần OTP',
      noteText: 'Hệ thống tự giải captcha qua apibank.com.vn. Nếu ngân hàng yêu cầu OTP, form OTP sẽ mở ngay bên dưới.',
      submit: 'Lấy OTP / kết nối VCB'
    },
    vpbank: {
      username: 'Tên đăng nhập VPBank',
      account: 'Số tài khoản VPBank',
      noteTitle: 'VPBank có thể cần OTP',
      noteText: 'Nếu thiết bị VPBank NEO chưa được tin cậy, hệ thống sẽ yêu cầu OTP từ điện thoại để hoàn tất.',
      submit: 'Lấy OTP / kết nối VPBank'
    },
    techcombank: {
      username: 'Tên đăng nhập Techcombank',
      account: 'Số tài khoản Techcombank',
      noteTitle: 'Techcombank đăng nhập bằng trình duyệt thật',
      noteText: 'Hệ thống chỉ tạo link bảo mật. Bạn mở Techcombank, đăng nhập và duyệt Mobile trực tiếp trên trình duyệt rồi dán URL xác nhận lại.',
      submit: 'Tạo link Techcombank'
    },
    mbbank: {
      username: 'Tên đăng nhập MBBank',
      account: 'Số tài khoản MBBank',
      noteTitle: 'MBBank kết nối trực tiếp',
      noteText: 'Hệ thống tự lấy captcha MBBank qua apibank.com.vn và đăng nhập để tạo API số dư, giao dịch.',
      submit: 'Kết nối MBBank'
    }
  };

  function selectedBank() {
    return form.querySelector('input[name="bank_code"]:checked')?.value || 'acb';
  }

  function updateBankCopy() {
    const bank = selectedBank();
    const copy = bankCopy[bank];
    usernameLabel.textContent = copy.username;
    accountLabel.textContent = copy.account;
    note.innerHTML = '<div class="fw-semibold mb-1">' + copy.noteTitle + '</div><div class="text-muted small">' + copy.noteText + '</div>';
    const isTechcombank = bank === 'techcombank';
    if (passwordField) passwordField.style.display = isTechcombank ? 'none' : '';
    if (passwordInput) passwordInput.required = !isTechcombank;
    if (stepInput.value !== 'otp') submitText.textContent = isEditMode ? 'Cập nhật ' + (bankNames[bank] || 'tài khoản') : copy.submit;
  }

  function showAlert(type, message, list) {
    const items = Array.isArray(list) ? '<ul class="mb-0">' + list.map(item => '<li>' + item + '</li>').join('') + '</ul>' : message;
    alerts.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' + items + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function setBusy(isBusy) {
    submitBtn.disabled = isBusy;
    if (isBusy) {
      submitBtn.dataset.oldHtml = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xử lý';
    } else if (submitBtn.dataset.oldHtml) {
      submitBtn.innerHTML = submitBtn.dataset.oldHtml;
      delete submitBtn.dataset.oldHtml;
      submitText = page.querySelector('[data-submit-text]');
      if (stepInput.value === 'otp') {
        submitText.textContent = selectedBank() === 'techcombank' ? 'Hoàn tất bằng URL xác nhận' : 'Xác thực OTP & lưu';
      } else {
        updateBankCopy();
      }
    }
  }

  function formPayload() {
    const payload = {
      step: stepInput.value,
      bank_code: selectedBank(),
      username: document.getElementById('bankUsername').value.trim(),
      account_no: document.getElementById('bankAccountNo').value.trim(),
      password: document.getElementById('bankPassword').value
    };

    if (isSystemReceiver) {
      payload.system_receiver = 1;
    }

    if (stepInput.value === 'otp') {
      payload.bank_code = selectedBank();
      if (selectedBank() === 'techcombank') {
        payload.redirect_url = redirectInput ? redirectInput.value.trim() : '';
      } else {
        payload.otp_code = otpInput.value.trim();
      }
    }

    return payload;
  }

  function openOtp(message, bank, authUrl) {
    bank = bank || selectedBank();
    authUrl = authUrl || pendingTechcombankAuthUrl || '';
    stepInput.value = 'otp';
    const input = form.querySelector('input[name="bank_code"][value="' + bank + '"]');
    if (input) input.checked = true;
    otpPanel.classList.add('is-open');
    page.querySelector('[data-step-card="verify"]').classList.add('is-active');
    const isTechcombank = bank === 'techcombank';
    if (otpCodeArea) otpCodeArea.style.display = isTechcombank ? 'none' : '';
    if (manualArea) manualArea.style.display = isTechcombank ? '' : 'none';
    if (authLink) {
      authLink.href = authUrl || '#';
      authLink.classList.toggle('disabled', !authUrl);
    }
    otpInput.required = !isTechcombank;
    if (redirectInput) redirectInput.required = isTechcombank;
    submitText.textContent = isTechcombank ? 'Hoàn tất bằng URL xác nhận' : 'Xác thực OTP & lưu';
    page.querySelector('[data-otp-title]').textContent = isTechcombank ? 'Mở Techcombank và dán URL xác nhận' : (bank === 'vpbank' ? 'VPBank' : 'Vietcombank') + ' yêu cầu OTP';
    page.querySelector('[data-otp-message]').textContent = message || (isTechcombank ? 'Đăng nhập trên trang Techcombank thật, duyệt Mobile, copy URL có code=... rồi dán bên dưới.' : 'Nhập OTP để hoàn tất.');
    updateBankCopy();
    if (isTechcombank) {
      setTimeout(() => redirectInput?.focus(), 80);
    } else {
      setTimeout(() => otpInput.focus(), 80);
    }
  }

  function resetForm() {
    stepInput.value = 'init';
    otpInput.value = '';
    otpInput.required = false;
    if (redirectInput) {
      redirectInput.value = '';
      redirectInput.required = false;
    }
    if (otpCodeArea) otpCodeArea.style.display = '';
    if (manualArea) manualArea.style.display = 'none';
    otpPanel.classList.remove('is-open');
    page.querySelector('[data-step-card="verify"]').classList.remove('is-active');
    updateBankCopy();
  }

  form.addEventListener('change', function (event) {
    if (event.target.name === 'bank_code') {
      if (stepInput.value === 'otp') resetForm();
      updateBankCopy();
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    setBusy(true);

    fetch(page.dataset.storeUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify(formPayload())
    })
      .then(async res => {
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw data;
        return data;
      })
      .then(data => {
        if (data.needs_otp) {
          showAlert('warning', data.message || 'Vui lòng nhập OTP.');
          openOtp(data.message, data.bank_code || selectedBank(), data.auth_url || '');
          return;
        }

        showAlert('success', data.message || 'Kết nối thành công.');
        window.setTimeout(() => {
          window.location.href = data.redirect_url || page.dataset.indexUrl;
        }, 650);
      })
      .catch(data => {
        const errors = data.errors ? Object.values(data.errors).flat() : null;
        showAlert('danger', data.message || 'Không kết nối được tài khoản ngân hàng.', errors);
      })
      .finally(() => setBusy(false));
  });

  page.querySelector('[data-reset-form]')?.addEventListener('click', function () {
    form.reset();
    resetForm();
    alerts.innerHTML = '';
  });

  page.querySelector('[data-toggle-password]')?.addEventListener('click', function () {
    const input = document.getElementById('bankPassword');
    input.type = input.type === 'password' ? 'text' : 'password';
  });

  copyAuthLinkBtn?.addEventListener('click', function () {
    const url = authLink?.href || '';
    if (!url || url === '#') return;
    navigator.clipboard?.writeText(url);
    showAlert('info', 'Đã copy link đăng nhập Techcombank.');
  });

  if (page.dataset.hasPendingOtp === '1') {
    const pendingBank = page.dataset.pendingBank || 'vcb';
    openOtp(pendingBank === 'techcombank' ? 'Mở Techcombank, đăng nhập, duyệt Mobile rồi dán URL xác nhận.' : 'Tiếp tục nhập OTP để hoàn tất.', pendingBank, pendingTechcombankAuthUrl);
  }
  updateBankCopy();
});
</script>
@endsection
