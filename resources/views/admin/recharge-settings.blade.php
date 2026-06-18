@extends('layouts/adminLayout')

@section('title', 'Cấu hình nạp tiền')

@section('page-style')
<style>
  .recharge-settings-page {
    --recharge-border: rgba(67, 89, 113, .14);
    --recharge-muted: #697a8d;
    --recharge-soft: #f8f9fb;
  }

  .recharge-settings-page .btn-touch,
  .recharge-settings-page .form-control,
  .recharge-settings-page .form-select,
  .recharge-settings-page .input-group-text {
    min-height: 42px;
    border-radius: .5rem;
  }

  .recharge-settings-page .btn-touch {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    white-space: nowrap;
  }

  .recharge-settings-page .panel,
  .recharge-settings-page .metric-tile,
  .recharge-settings-page .receiver-plate,
  .recharge-settings-page .account-row {
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    background: #fff;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }

  .recharge-settings-page .panel-header {
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--recharge-border);
  }

  .recharge-settings-page .panel-body {
    padding: 1.15rem;
  }

  .recharge-settings-page .metric-tile {
    height: 100%;
    padding: .95rem;
  }

  .recharge-settings-page .metric-label,
  .recharge-settings-page .field-label {
    color: var(--recharge-muted);
    font-size: .78rem;
  }

  .recharge-settings-page .metric-value {
    font-size: 1.08rem;
    font-weight: 700;
    overflow-wrap: anywhere;
  }

  .recharge-settings-page .receiver-plate {
    border-color: rgba(40, 199, 111, .28);
    background: rgba(40, 199, 111, .06);
    padding: 1rem;
  }

  .recharge-settings-page .receiver-icon {
    width: 2.7rem;
    height: 2.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
    font-size: 1.35rem;
  }

  .recharge-settings-page .status-chip,
  .recharge-settings-page .bank-chip {
    min-height: 1.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .45rem;
    padding: .15rem .55rem;
    font-size: .76rem;
    font-weight: 700;
    white-space: nowrap;
  }

  .recharge-settings-page .bank-chip-acb {
    color: #1548a8;
    background: rgba(3, 169, 244, .12);
  }

  .recharge-settings-page .bank-chip-vcb {
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
  }

  .recharge-settings-page .bank-chip-vpbank {
    color: #6a2aa8;
    background: rgba(105, 108, 255, .12);
  }

  .recharge-settings-page .bank-chip-techcombank {
    color: #b01824;
    background: rgba(255, 62, 29, .12);
  }

  .recharge-settings-page .bank-chip-mbbank {
    color: #0b4f9c;
    background: rgba(3, 169, 244, .12);
  }

  .recharge-settings-page .status-ok {
    color: #0b6b3a;
    background: rgba(40, 199, 111, .12);
  }

  .recharge-settings-page .status-warn {
    color: #8a5a00;
    background: rgba(255, 171, 0, .14);
  }

  .recharge-settings-page .readonly-box {
    min-height: 42px;
    border: 1px solid #d9dee3;
    border-radius: .5rem;
    padding: .55rem .75rem;
    background: var(--recharge-soft);
    font-weight: 600;
    overflow-wrap: anywhere;
  }

  .recharge-settings-page .preview-code {
    color: #d92323;
    font-weight: 700;
    overflow-wrap: anywhere;
  }

  .recharge-settings-page .account-list {
    display: grid;
    gap: .6rem;
  }

  .recharge-settings-page .account-row {
    padding: .75rem;
    box-shadow: none;
  }

  .recharge-settings-page .account-row.is-active {
    border-color: rgba(40, 199, 111, .36);
    background: rgba(40, 199, 111, .05);
  }

  .recharge-settings-page .account-actions {
    display: inline-flex;
    flex-wrap: wrap;
    gap: .45rem;
    justify-content: flex-end;
  }

  .recharge-settings-page .account-edit-panel {
    display: none;
    margin-top: .85rem;
    border-top: 1px solid var(--recharge-border);
    padding-top: .85rem;
  }

  .recharge-settings-page .account-edit-panel.is-open {
    display: block;
  }

  .recharge-settings-page .settings-tabs {
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    padding: .35rem;
    background: #fff;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .04);
    gap: .35rem;
  }

  .recharge-settings-page .settings-tabs .nav-link {
    min-height: 44px;
    border-radius: .45rem;
    color: #566a7f;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    width: 100%;
  }

  .recharge-settings-page .settings-tabs .nav-link.active {
    color: #fff;
    background: #696cff;
    box-shadow: 0 .22rem .65rem rgba(105, 108, 255, .22);
  }

  .recharge-settings-page .recharge-tab-pane {
    display: none;
  }

  .recharge-settings-page .recharge-tab-pane.is-active {
    display: block;
  }

  .recharge-settings-page .connect-panel {
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    background: #fff;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
    overflow: hidden;
  }

  .recharge-settings-page .connect-main,
  .recharge-settings-page .connect-aside {
    padding: 1.15rem;
  }

  .recharge-settings-page .connect-aside {
    border-top: 1px solid var(--recharge-border);
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  }

  .recharge-settings-page .step-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
  }

  .recharge-settings-page .step-item {
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    padding: .75rem;
    background: #fff;
  }

  .recharge-settings-page .step-item.is-active {
    border-color: rgba(105, 108, 255, .45);
    background: rgba(105, 108, 255, .07);
  }

  .recharge-settings-page .step-index {
    width: 1.7rem;
    height: 1.7rem;
    flex: 0 0 1.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #eef0f4;
    color: #566a7f;
    font-size: .78rem;
    font-weight: 700;
  }

  .recharge-settings-page .step-item.is-active .step-index {
    color: #fff;
    background: #696cff;
  }

  .recharge-settings-page .bank-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
    gap: .75rem;
  }

  .recharge-settings-page .bank-option {
    display: block;
    height: 100%;
    position: relative;
    cursor: pointer;
  }

  .recharge-settings-page .bank-option-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  .recharge-settings-page .bank-option-card {
    height: 100%;
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    padding: .85rem;
    background: #fff;
    transition: border-color .18s ease, box-shadow .18s ease;
  }

  .recharge-settings-page .bank-option-input:focus + .bank-option-card,
  .recharge-settings-page .bank-option-input:checked + .bank-option-card,
  .recharge-settings-page .bank-option:hover .bank-option-card {
    border-color: #696cff;
    box-shadow: 0 0 0 .18rem rgba(105, 108, 255, .12);
  }

  .recharge-settings-page .bank-icon {
    width: 2.45rem;
    height: 2.45rem;
    flex: 0 0 2.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .5rem;
    color: #696cff;
    background: #eef0ff;
    font-size: 1.35rem;
  }

  .recharge-settings-page .field-icon {
    width: 2.8rem;
    justify-content: center;
    color: var(--recharge-muted);
  }

  .recharge-settings-page .info-strip,
  .recharge-settings-page .aside-block,
  .recharge-settings-page .otp-panel {
    border: 1px solid var(--recharge-border);
    border-radius: .5rem;
    padding: .9rem;
  }

  .recharge-settings-page .info-strip {
    border-color: rgba(105, 108, 255, .24);
    background: rgba(105, 108, 255, .06);
  }

  .recharge-settings-page .aside-block + .aside-block {
    margin-top: .75rem;
  }

  .recharge-settings-page .add-bank-form {
    display: none;
  }

  .recharge-settings-page .add-bank-form.is-active {
    display: block;
  }

  .recharge-settings-page .otp-panel {
    border-color: rgba(255, 171, 0, .34);
    background: rgba(255, 171, 0, .08);
  }

  .recharge-settings-page .otp-code-input {
    font-size: 1.35rem;
    font-weight: 700;
    text-align: center;
    letter-spacing: .22rem;
  }

  @media (max-width: 767.98px) {
    .recharge-settings-page .settings-tabs {
      flex-direction: column;
    }

    .recharge-settings-page .page-actions,
    .recharge-settings-page .page-actions .btn,
    .recharge-settings-page .save-row .btn,
    .recharge-settings-page .connect-actions .btn {
      width: 100%;
    }
  }

  @media (min-width: 992px) {
    .recharge-settings-page .connect-aside {
      height: 100%;
      border-top: 0;
      border-left: 1px solid var(--recharge-border);
    }
  }
</style>
@endsection

@section('content')
@php
  $acbReceiverAccounts = $acbReceiverAccounts ?? collect();
  $vcbReceiverAccounts = $vcbReceiverAccounts ?? collect();
  $vpbankReceiverAccounts = $vpbankReceiverAccounts ?? collect();
  $techcombankReceiverAccounts = $techcombankReceiverAccounts ?? collect();
  $mbbankReceiverAccounts = $mbbankReceiverAccounts ?? collect();
  $prefix = old('noidungnap', $bank->noidungnap);
  $exampleUserId = auth()->id() ?: 1;
  $selectedReceiverBankType = (string) old('receiver_bank_type', $selectedReceiverBankType ?? ($bank->receiver_bank_type ?: 'ACB'));
  $selectedReceiverAccountId = (int) old('receiver_account_id', $selectedReceiverAccountId ?? ($bank->receiver_account_id ?? 0));
  $selectedReceiver = match ($selectedReceiverBankType) {
      'VCB' => $vcbReceiverAccounts->firstWhere('id', $selectedReceiverAccountId),
      'VPBANK' => $vpbankReceiverAccounts->firstWhere('id', $selectedReceiverAccountId),
      'TECHCOMBANK' => $techcombankReceiverAccounts->firstWhere('id', $selectedReceiverAccountId),
      'MBBANK' => $mbbankReceiverAccounts->firstWhere('id', $selectedReceiverAccountId),
      default => $acbReceiverAccounts->firstWhere('id', $selectedReceiverAccountId),
  };
  if (!$selectedReceiver) {
      $selectedReceiver = match ($selectedReceiverBankType) {
          'VCB' => $vcbReceiverAccounts->first(),
          'VPBANK' => $vpbankReceiverAccounts->first(),
          'TECHCOMBANK' => $techcombankReceiverAccounts->first(),
          'MBBANK' => $mbbankReceiverAccounts->first(),
          default => $acbReceiverAccounts->first(),
      };
  }
  $selectedBankClass = strtolower($selectedReceiverBankType);
  $selectedAccountNo = in_array($selectedReceiverBankType, ['VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK'], true)
      ? ($selectedReceiver->account ?? $bank->accountNumber)
      : ($selectedReceiver->stk ?? $bank->accountNumber);
  $selectedName = $selectedReceiver->name ?? $bank->accountName;
  $selectedLogin = in_array($selectedReceiverBankType, ['VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK'], true)
      ? ($selectedReceiver->username ?? '-')
      : ($selectedReceiver->phone ?? '-');
  $selectedSession = match ($selectedReceiverBankType) {
      'VCB' => !empty($selectedReceiver->session_id),
      'VPBANK' => !empty($selectedReceiver->token_key),
      'TECHCOMBANK' => !empty($selectedReceiver->refresh_token),
      'MBBANK' => !empty($selectedReceiver->session_id),
      default => !empty($selectedReceiver->sessionId),
  };
  $quickCount = count(array_filter(array_map('trim', explode(',', (string) $quickAmounts))));
  $pendingVcb = $pendingVcb ?? [];
  $pendingVpbank = $pendingVpbank ?? [];
  $pendingTechcombank = $pendingTechcombank ?? [];
  $requestBank = in_array((string) request('bank'), ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'], true) ? (string) request('bank') : null;
  $oldBankCode = in_array((string) old('bank_code'), ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'], true) ? (string) old('bank_code') : null;
  $addBankType = $requestBank
      ?: ($oldBankCode
      ?: (!empty($pendingTechcombank)
      ? 'techcombank'
      : (!empty($pendingVpbank)
      ? 'vpbank'
      : (!empty($pendingVcb) ? 'vcb' : 'acb'))));
  $activeRechargeTab = request('tab');
  if (!in_array($activeRechargeTab, ['settings', 'accounts', 'add'], true)) {
      $activeRechargeTab = (!empty($pendingVcb) || !empty($pendingVpbank) || !empty($pendingTechcombank) || old('bank_code')) ? 'add' : 'settings';
  }
@endphp

<div class="recharge-settings-page">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">Super Admin</div>
      <h4 class="mb-1">Cấu hình nạp tiền</h4>
      <div class="text-muted">Chọn ACB, Vietcombank, VPBank, Techcombank hoặc MBBank làm tài khoản nhận nạp tự động.</div>
    </div>
    <div class="page-actions d-flex flex-column flex-sm-row gap-2">
      <a class="btn btn-outline-secondary btn-touch" href="{{ route('admin.bank-accounts.index') }}">
        <i class="bx bx-credit-card"></i> Account ngân hàng
      </a>
      <a class="btn btn-outline-secondary btn-touch" href="{{ route('admin.dashboard') }}">
        <i class="bx bx-arrow-back"></i> Tổng quan
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="metric-tile">
        <div class="metric-label">Ngân hàng active</div>
        <div class="metric-value" id="topBank">{{ $selectedReceiverBankType }}</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="metric-tile">
        <div class="metric-label">Tài khoản nhận</div>
        <div class="metric-value" id="topAccount">{{ $bank->accountNumber ?: '-' }}</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="metric-tile">
        <div class="metric-label">Nội dung mẫu</div>
        <div class="metric-value preview-code" id="topPreview">{{ $prefix }}{{ $exampleUserId }}</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="metric-tile">
        <div class="metric-label">Tối thiểu / mốc nhanh</div>
        <div class="metric-value">{{ number_format((int) ($bank->min_amount ?: 10000)) }}đ / {{ $quickCount }}</div>
      </div>
    </div>
  </div>

  <ul class="nav nav-pills settings-tabs mb-4" role="tablist" aria-label="Cấu hình nạp tiền">
    <li class="nav-item flex-fill" role="presentation">
      <button class="nav-link {{ $activeRechargeTab === 'settings' ? 'active' : '' }}" type="button" role="tab" aria-selected="{{ $activeRechargeTab === 'settings' ? 'true' : 'false' }}" data-recharge-tab="settings">
        <i class="bx bx-slider-alt"></i> Thiết lập nhận nạp
      </button>
    </li>
    <li class="nav-item flex-fill" role="presentation">
      <button class="nav-link {{ $activeRechargeTab === 'accounts' ? 'active' : '' }}" type="button" role="tab" aria-selected="{{ $activeRechargeTab === 'accounts' ? 'true' : 'false' }}" data-recharge-tab="accounts">
        <i class="bx bx-list-ul"></i> Account hệ thống
      </button>
    </li>
    <li class="nav-item flex-fill" role="presentation">
      <button class="nav-link {{ $activeRechargeTab === 'add' ? 'active' : '' }}" type="button" role="tab" aria-selected="{{ $activeRechargeTab === 'add' ? 'true' : 'false' }}" data-recharge-tab="add">
        <i class="bx bx-plus-circle"></i> Thêm/sửa token
      </button>
    </li>
  </ul>

  <div class="recharge-tab-content">
    <div class="recharge-tab-pane {{ $activeRechargeTab === 'settings' ? 'is-active' : '' }}" data-recharge-tab-pane="settings" role="tabpanel">
      <div class="panel">
        <div class="panel-header d-flex flex-column flex-md-row justify-content-between gap-2">
          <div>
            <h5 class="mb-1">Account nhận nạp đang chạy</h5>
            <div class="text-muted small">Nguồn quét giao dịch tự động.</div>
          </div>
          <span id="receiverStatus" class="status-chip {{ $selectedSession ? 'status-ok' : 'status-warn' }}">
            {{ $selectedSession ? 'Sẵn sàng quét' : 'Cần đăng nhập lại' }}
          </span>
        </div>
        <div class="panel-body">
          <div class="receiver-plate">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
              <div class="d-flex gap-3 min-w-0">
                <div class="receiver-icon"><i class="bx bx-buildings"></i></div>
                <div class="min-w-0">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span id="receiverBankChip" class="bank-chip bank-chip-{{ $selectedBankClass }}">{{ $selectedReceiverBankType }}</span>
                    <span class="text-muted small">#<span id="receiverId">{{ $selectedReceiver->id ?? '-' }}</span></span>
                  </div>
                  <h5 class="mb-1" id="receiverAccountNo">{{ $selectedAccountNo }}</h5>
                  <div class="text-muted" id="receiverName">{{ $selectedName }}</div>
                </div>
              </div>
              <div class="text-md-end">
                <div class="field-label">Login</div>
                <div class="fw-semibold" id="receiverLogin">{{ $selectedLogin }}</div>
              </div>
            </div>
          </div>

          <form class="mt-4" method="POST" action="{{ route('admin.recharge-settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" id="receiver_bank_type" name="receiver_bank_type" value="{{ $selectedReceiverBankType }}">

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="receiver_account_id">Account nhận nạp</label>
                <select class="form-select" id="receiver_account_id" name="receiver_account_id" required>
                  <option value="">Chọn account hệ thống</option>
                  @if($acbReceiverAccounts->isNotEmpty())
                    <optgroup label="ACB">
                      @foreach($acbReceiverAccounts as $account)
                        <option
                          value="{{ $account->id }}"
                          data-bank="ACB"
                          data-stk="{{ $account->stk }}"
                          data-name="{{ $account->name }}"
                          data-login="{{ $account->phone }}"
                          data-session="{{ $account->sessionId ? '1' : '0' }}"
                          @selected($selectedReceiverBankType === 'ACB' && (int) $account->id === $selectedReceiverAccountId)
                        >
                          ACB #{{ $account->id }} · {{ $account->stk }} · {{ $account->name ?: 'Chưa có tên' }} · {{ $account->sessionId ? 'Session OK' : 'Thiếu session' }}
                        </option>
                      @endforeach
                    </optgroup>
                  @endif
                  @if($vcbReceiverAccounts->isNotEmpty())
                    <optgroup label="Vietcombank">
                      @foreach($vcbReceiverAccounts as $account)
                        <option
                          value="{{ $account->id }}"
                          data-bank="VCB"
                          data-stk="{{ $account->account }}"
                          data-name="{{ $account->name }}"
                          data-login="{{ $account->username }}"
                          data-session="{{ $account->session_id ? '1' : '0' }}"
                          @selected($selectedReceiverBankType === 'VCB' && (int) $account->id === $selectedReceiverAccountId)
                        >
                          VCB #{{ $account->id }} · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }} · {{ $account->session_id ? 'Session OK' : 'Thiếu session' }}
                        </option>
                      @endforeach
                    </optgroup>
                  @endif
                  @if($vpbankReceiverAccounts->isNotEmpty())
                    <optgroup label="VPBank">
                      @foreach($vpbankReceiverAccounts as $account)
                        <option
                          value="{{ $account->id }}"
                          data-bank="VPBANK"
                          data-stk="{{ $account->account }}"
                          data-name="{{ $account->name }}"
                          data-login="{{ $account->username }}"
                          data-session="{{ $account->token_key ? '1' : '0' }}"
                          @selected($selectedReceiverBankType === 'VPBANK' && (int) $account->id === $selectedReceiverAccountId)
                        >
                          VPBank #{{ $account->id }} · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }} · {{ $account->token_key ? 'Session OK' : 'Thiếu session' }}
                        </option>
                      @endforeach
                    </optgroup>
                  @endif
                  @if($techcombankReceiverAccounts->isNotEmpty())
                    <optgroup label="Techcombank">
                      @foreach($techcombankReceiverAccounts as $account)
                        <option
                          value="{{ $account->id }}"
                          data-bank="TECHCOMBANK"
                          data-stk="{{ $account->account }}"
                          data-name="{{ $account->name }}"
                          data-login="{{ $account->username }}"
                          data-session="{{ $account->refresh_token ? '1' : '0' }}"
                          @selected($selectedReceiverBankType === 'TECHCOMBANK' && (int) $account->id === $selectedReceiverAccountId)
                        >
                          Techcombank #{{ $account->id }} · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }} · {{ $account->refresh_token ? 'Session OK' : 'Thiếu session' }}
                        </option>
                      @endforeach
                    </optgroup>
                  @endif
                  @if($mbbankReceiverAccounts->isNotEmpty())
                    <optgroup label="MBBank">
                      @foreach($mbbankReceiverAccounts as $account)
                        <option
                          value="{{ $account->id }}"
                          data-bank="MBBANK"
                          data-stk="{{ $account->account }}"
                          data-name="{{ $account->name }}"
                          data-login="{{ $account->username }}"
                          data-session="{{ $account->session_id ? '1' : '0' }}"
                          @selected($selectedReceiverBankType === 'MBBANK' && (int) $account->id === $selectedReceiverAccountId)
                        >
                          MBBank #{{ $account->id }} · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }} · {{ $account->session_id ? 'Session OK' : 'Thiếu session' }}
                        </option>
                      @endforeach
                    </optgroup>
                  @endif
                </select>
                @if($acbReceiverAccounts->isEmpty() && $vcbReceiverAccounts->isEmpty() && $vpbankReceiverAccounts->isEmpty() && $techcombankReceiverAccounts->isEmpty() && $mbbankReceiverAccounts->isEmpty())
                  <div class="alert alert-warning mt-3 mb-0" role="alert">
                    Chưa có account ngân hàng nhận nạp hệ thống.
                    <button class="btn btn-sm btn-warning ms-sm-2 mt-2 mt-sm-0" type="button" data-recharge-tab-jump="add">Thêm account</button>
                  </div>
                @endif
              </div>

              <div class="col-md-6">
                <div class="field-label mb-1">Số tài khoản hiển thị</div>
                <div class="readonly-box" id="accountNumber">{{ old('accountNumber', $bank->accountNumber) }}</div>
              </div>
              <div class="col-md-6">
                <div class="field-label mb-1">Chủ tài khoản hiển thị</div>
                <div class="readonly-box" id="accountName">{{ old('accountName', $bank->accountName) }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="noidungnap">Tiền tố nội dung</label>
                <input class="form-control" id="noidungnap" name="noidungnap" value="{{ $prefix }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="vietqr_template">Template VietQR</label>
                <input class="form-control" id="vietqr_template" name="vietqr_template" value="{{ old('vietqr_template', $bank->vietqr_template ?: 'IRuAFR6') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="min_amount">Tối thiểu</label>
                <input class="form-control" id="min_amount" name="min_amount" type="number" min="0" value="{{ old('min_amount', $bank->min_amount ?: 10000) }}">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="quick_amounts">Mốc tiền nhanh</label>
                <input class="form-control" id="quick_amounts" name="quick_amounts" value="{{ old('quick_amounts', $quickAmounts) }}">
              </div>
              <div class="col-12">
                <label class="form-label" for="instructions">Ghi chú</label>
                <textarea class="form-control" id="instructions" name="instructions" rows="3">{{ old('instructions', $bank->instructions) }}</textarea>
              </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mt-4 save-row">
              <div class="text-muted small">
                Nội dung chuyển khoản: <span class="preview-code" id="preview">{{ $prefix }}{{ $exampleUserId }}</span>
              </div>
              <button class="btn btn-primary btn-touch" type="submit">
                <i class="bx bx-save"></i> Lưu thiết lập
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="recharge-tab-pane {{ $activeRechargeTab === 'accounts' ? 'is-active' : '' }}" data-recharge-tab-pane="accounts" role="tabpanel">
      <div class="panel">
        <div class="panel-header d-flex flex-column flex-sm-row justify-content-between gap-2">
          <div>
            <h5 class="mb-1">Account hệ thống</h5>
            <div class="text-muted small">ACB: {{ number_format($acbReceiverAccounts->count()) }} · VCB: {{ number_format($vcbReceiverAccounts->count()) }} · VPBank: {{ number_format($vpbankReceiverAccounts->count()) }} · Techcombank: {{ number_format($techcombankReceiverAccounts->count()) }} · MBBank: {{ number_format($mbbankReceiverAccounts->count()) }}</div>
          </div>
          <button class="btn btn-outline-primary btn-touch align-self-sm-start" type="button" data-recharge-tab-jump="add">
            <i class="bx bx-plus"></i> Thêm account
          </button>
        </div>
        <div class="panel-body">
          <div class="account-list">
            @foreach($acbReceiverAccounts as $account)
              @php($isActiveAccount = $selectedReceiverBankType === 'ACB' && (int) $account->id === $selectedReceiverAccountId)
              <div class="account-row {{ $isActiveAccount ? 'is-active' : '' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">ACB · {{ $account->stk }} · {{ $account->name ?: 'Chưa có tên' }}</div>
                    <div class="text-muted small">#{{ $account->id }} · {{ $account->phone ?: '-' }}</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                    @if($isActiveAccount)
                      <span class="status-chip status-ok">Đang nhận nạp</span>
                    @endif
                    <span class="status-chip {{ $account->sessionId ? 'status-ok' : 'status-warn' }}">{{ $account->sessionId ? 'Session OK' : 'Thiếu session' }}</span>
                    <div class="account-actions">
                      <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-inline-token-edit data-bank="acb" data-username="{{ $account->phone }}" data-account-no="{{ $account->stk }}">
                        <i class="bx bx-edit"></i> Sửa
                      </button>
                      <form method="POST" action="{{ route('admin.recharge-settings.account.destroy', ['bank' => 'acb', 'id' => $account->id]) }}#accounts" data-delete-account>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger btn-touch" type="submit" @disabled($isActiveAccount) title="{{ $isActiveAccount ? 'Chọn account khác trước khi xóa' : 'Xóa account' }}">
                          <i class="bx bx-trash"></i> Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-edit-panel" data-edit-panel="acb-{{ $account->id }}">
                  <form method="POST" action="{{ route('admin.recharge-settings.account.update', ['bank' => 'acb', 'id' => $account->id]) }}#accounts">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Tài khoản ACB</label>
                        <input class="form-control" name="phone" value="{{ $account->phone }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Số tài khoản</label>
                        <input class="form-control" name="stk" value="{{ $account->stk }}" inputmode="numeric" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Chủ tài khoản</label>
                        <input class="form-control" name="name" value="{{ $account->name }}">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Mật khẩu mới</label>
                        <input class="form-control" name="password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                      </div>
                      <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary btn-touch" type="button" data-edit-close="acb-{{ $account->id }}">Hủy</button>
                        <button class="btn btn-primary btn-touch" type="submit">
                          <i class="bx bx-save"></i> Lưu ACB
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            @endforeach
            @foreach($vcbReceiverAccounts as $account)
              @php($isActiveAccount = $selectedReceiverBankType === 'VCB' && (int) $account->id === $selectedReceiverAccountId)
              <div class="account-row {{ $isActiveAccount ? 'is-active' : '' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">VCB · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }}</div>
                    <div class="text-muted small">#{{ $account->id }} · {{ $account->username ?: '-' }}</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                    @if($isActiveAccount)
                      <span class="status-chip status-ok">Đang nhận nạp</span>
                    @endif
                    <span class="status-chip {{ $account->session_id ? 'status-ok' : 'status-warn' }}">{{ $account->session_id ? 'Session OK' : 'Thiếu session' }}</span>
                    <div class="account-actions">
                      <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-inline-token-edit data-bank="vcb" data-username="{{ $account->username }}" data-account-no="{{ $account->account }}">
                        <i class="bx bx-edit"></i> Sửa
                      </button>
                      <form method="POST" action="{{ route('admin.recharge-settings.account.destroy', ['bank' => 'vcb', 'id' => $account->id]) }}#accounts" data-delete-account>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger btn-touch" type="submit" @disabled($isActiveAccount) title="{{ $isActiveAccount ? 'Chọn account khác trước khi xóa' : 'Xóa account' }}">
                          <i class="bx bx-trash"></i> Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-edit-panel" data-edit-panel="vcb-{{ $account->id }}">
                  <form method="POST" action="{{ route('admin.recharge-settings.account.update', ['bank' => 'vcb', 'id' => $account->id]) }}#accounts">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Tài khoản VCB</label>
                        <input class="form-control" name="vcb_username" value="{{ $account->username }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Số tài khoản</label>
                        <input class="form-control" name="vcb_account_no" value="{{ $account->account }}" inputmode="numeric" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Chủ tài khoản</label>
                        <input class="form-control" name="vcb_name" value="{{ $account->name }}">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Mật khẩu mới</label>
                        <input class="form-control" name="vcb_password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                      </div>
                      <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary btn-touch" type="button" data-edit-close="vcb-{{ $account->id }}">Hủy</button>
                        <button class="btn btn-primary btn-touch" type="submit">
                          <i class="bx bx-save"></i> Lưu VCB
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            @endforeach
            @foreach($vpbankReceiverAccounts as $account)
              @php($isActiveAccount = $selectedReceiverBankType === 'VPBANK' && (int) $account->id === $selectedReceiverAccountId)
              <div class="account-row {{ $isActiveAccount ? 'is-active' : '' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">VPBank · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }}</div>
                    <div class="text-muted small">#{{ $account->id }} · {{ $account->username ?: '-' }}</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                    @if($isActiveAccount)
                      <span class="status-chip status-ok">Đang nhận nạp</span>
                    @endif
                    <span class="status-chip {{ $account->token_key ? 'status-ok' : 'status-warn' }}">{{ $account->token_key ? 'Session OK' : 'Thiếu session' }}</span>
                    <div class="account-actions">
                      <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-inline-token-edit data-bank="vpbank" data-username="{{ $account->username }}" data-account-no="{{ $account->account }}">
                        <i class="bx bx-edit"></i> Sửa
                      </button>
                      <form method="POST" action="{{ route('admin.recharge-settings.account.destroy', ['bank' => 'vpbank', 'id' => $account->id]) }}#accounts" data-delete-account>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger btn-touch" type="submit" @disabled($isActiveAccount) title="{{ $isActiveAccount ? 'Chọn account khác trước khi xóa' : 'Xóa account' }}">
                          <i class="bx bx-trash"></i> Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-edit-panel" data-edit-panel="vpbank-{{ $account->id }}">
                  <form method="POST" action="{{ route('admin.recharge-settings.account.update', ['bank' => 'vpbank', 'id' => $account->id]) }}#accounts">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Tài khoản VPBank</label>
                        <input class="form-control" name="vpbank_username" value="{{ $account->username }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Số tài khoản</label>
                        <input class="form-control" name="vpbank_account_no" value="{{ $account->account }}" inputmode="numeric" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Chủ tài khoản</label>
                        <input class="form-control" name="vpbank_name" value="{{ $account->name }}">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Mật khẩu mới</label>
                        <input class="form-control" name="vpbank_password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                      </div>
                      <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary btn-touch" type="button" data-edit-close="vpbank-{{ $account->id }}">Hủy</button>
                        <button class="btn btn-primary btn-touch" type="submit">
                          <i class="bx bx-save"></i> Lưu VPBank
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            @endforeach
            @foreach($techcombankReceiverAccounts as $account)
              @php($isActiveAccount = $selectedReceiverBankType === 'TECHCOMBANK' && (int) $account->id === $selectedReceiverAccountId)
              <div class="account-row {{ $isActiveAccount ? 'is-active' : '' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">Techcombank · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }}</div>
                    <div class="text-muted small">#{{ $account->id }} · {{ $account->username ?: '-' }}</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                    @if($isActiveAccount)
                      <span class="status-chip status-ok">Đang nhận nạp</span>
                    @endif
                    <span class="status-chip {{ $account->refresh_token ? 'status-ok' : 'status-warn' }}">{{ $account->refresh_token ? 'Session OK' : 'Thiếu session' }}</span>
                    <div class="account-actions">
                      <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-inline-token-edit data-bank="techcombank" data-username="{{ $account->username }}" data-account-no="{{ $account->account }}">
                        <i class="bx bx-edit"></i> Sửa
                      </button>
                      <form method="POST" action="{{ route('admin.recharge-settings.account.destroy', ['bank' => 'techcombank', 'id' => $account->id]) }}#accounts" data-delete-account>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger btn-touch" type="submit" @disabled($isActiveAccount) title="{{ $isActiveAccount ? 'Chọn account khác trước khi xóa' : 'Xóa account' }}">
                          <i class="bx bx-trash"></i> Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-edit-panel" data-edit-panel="techcombank-{{ $account->id }}">
                  <form method="POST" action="{{ route('admin.recharge-settings.account.update', ['bank' => 'techcombank', 'id' => $account->id]) }}#accounts">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Tài khoản Techcombank</label>
                        <input class="form-control" name="techcombank_username" value="{{ $account->username }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Số tài khoản</label>
                        <input class="form-control" name="techcombank_account_no" value="{{ $account->account }}" inputmode="numeric" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Chủ tài khoản</label>
                        <input class="form-control" name="techcombank_name" value="{{ $account->name }}">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Mật khẩu mới</label>
                        <input class="form-control" name="techcombank_password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                      </div>
                      <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary btn-touch" type="button" data-edit-close="techcombank-{{ $account->id }}">Hủy</button>
                        <button class="btn btn-primary btn-touch" type="submit">
                          <i class="bx bx-save"></i> Lưu Techcombank
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            @endforeach
            @foreach($mbbankReceiverAccounts as $account)
              @php($isActiveAccount = $selectedReceiverBankType === 'MBBANK' && (int) $account->id === $selectedReceiverAccountId)
              <div class="account-row {{ $isActiveAccount ? 'is-active' : '' }}">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">MBBank · {{ $account->account }} · {{ $account->name ?: 'Chưa có tên' }}</div>
                    <div class="text-muted small">#{{ $account->id }} · {{ $account->username ?: '-' }}</div>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                    @if($isActiveAccount)
                      <span class="status-chip status-ok">Đang nhận nạp</span>
                    @endif
                    <span class="status-chip {{ $account->session_id ? 'status-ok' : 'status-warn' }}">{{ $account->session_id ? 'Session OK' : 'Thiếu session' }}</span>
                    <div class="account-actions">
                      <button class="btn btn-sm btn-outline-primary btn-touch" type="button" data-inline-token-edit data-bank="mbbank" data-username="{{ $account->username }}" data-account-no="{{ $account->account }}">
                        <i class="bx bx-edit"></i> Sửa
                      </button>
                      <form method="POST" action="{{ route('admin.recharge-settings.account.destroy', ['bank' => 'mbbank', 'id' => $account->id]) }}#accounts" data-delete-account>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger btn-touch" type="submit" @disabled($isActiveAccount) title="{{ $isActiveAccount ? 'Chọn account khác trước khi xóa' : 'Xóa account' }}">
                          <i class="bx bx-trash"></i> Xóa
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="account-edit-panel" data-edit-panel="mbbank-{{ $account->id }}">
                  <form method="POST" action="{{ route('admin.recharge-settings.account.update', ['bank' => 'mbbank', 'id' => $account->id]) }}#accounts">
                    @csrf
                    @method('PUT')
                    <div class="row g-2 align-items-end">
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Tài khoản MBBank</label>
                        <input class="form-control" name="mbbank_username" value="{{ $account->username }}" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Số tài khoản</label>
                        <input class="form-control" name="mbbank_account_no" value="{{ $account->account }}" inputmode="numeric" required>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Chủ tài khoản</label>
                        <input class="form-control" name="mbbank_name" value="{{ $account->name }}">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label small mb-1">Mật khẩu mới</label>
                        <input class="form-control" name="mbbank_password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                      </div>
                      <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <button class="btn btn-outline-secondary btn-touch" type="button" data-edit-close="mbbank-{{ $account->id }}">Hủy</button>
                        <button class="btn btn-primary btn-touch" type="submit">
                          <i class="bx bx-save"></i> Lưu MBBank
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            @endforeach
            @if($acbReceiverAccounts->isEmpty() && $vcbReceiverAccounts->isEmpty() && $vpbankReceiverAccounts->isEmpty() && $techcombankReceiverAccounts->isEmpty() && $mbbankReceiverAccounts->isEmpty())
              <div class="text-center text-muted py-4">
                Chưa có account hệ thống.
                <div class="mt-3">
                  <button class="btn btn-primary btn-touch" type="button" data-recharge-tab-jump="add">
                    <i class="bx bx-plus"></i> Thêm account nhận nạp
                  </button>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="recharge-tab-pane {{ $activeRechargeTab === 'add' ? 'is-active' : '' }}" data-recharge-tab-pane="add" role="tabpanel">
      <div class="connect-panel add-bank-panel">
        <div class="row g-0">
          <div class="col-12 col-lg-8">
            <div class="connect-main">
              <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mb-4">
                <div>
                  <div class="text-muted small mb-1">Super Admin</div>
                  <h5 class="mb-1">Kết nối tài khoản nhận nạp</h5>
                  <div class="text-muted small">Thêm account hệ thống để quét giao dịch tự động.</div>
                </div>
                <span class="status-chip status-ok align-self-start">ACB / VCB / VPBank / Techcombank / MBBank</span>
              </div>

          <div class="step-row mb-4">
            <div class="step-item is-active">
              <div class="d-flex align-items-center gap-2">
                <span class="step-index">1</span>
                <div>
                  <div class="fw-semibold">Thông tin</div>
                  <div class="text-muted small">Bank và tài khoản</div>
                </div>
              </div>
            </div>
            <div class="step-item {{ (!empty($pendingVcb) || !empty($pendingVpbank) || !empty($pendingTechcombank)) ? 'is-active' : '' }}" data-add-bank-step="otp">
              <div class="d-flex align-items-center gap-2">
                <span class="step-index">2</span>
                <div>
                  <div class="fw-semibold">Xác thực</div>
                  <div class="text-muted small">Login hoặc OTP</div>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Chọn ngân hàng</label>
            <div class="bank-picker-grid">
              <label class="bank-option">
                <input class="bank-option-input" type="radio" name="add_bank_type" value="acb" data-add-bank-radio @checked($addBankType === 'acb')>
                <span class="bank-option-card d-flex gap-3">
                  <span class="bank-icon"><i class="bx bx-credit-card"></i></span>
                  <span>
                    <span class="d-block fw-semibold">ACB</span>
                    <span class="d-block text-muted small">Đăng nhập một bước.</span>
                  </span>
                </span>
              </label>
              <label class="bank-option">
                <input class="bank-option-input" type="radio" name="add_bank_type" value="vcb" data-add-bank-radio @checked($addBankType === 'vcb')>
                <span class="bank-option-card d-flex gap-3">
                  <span class="bank-icon"><i class="bx bx-bank"></i></span>
                  <span>
                    <span class="d-block fw-semibold">Vietcombank</span>
                    <span class="d-block text-muted small">Gửi OTP rồi xác thực.</span>
                  </span>
                </span>
              </label>
              <label class="bank-option">
                <input class="bank-option-input" type="radio" name="add_bank_type" value="vpbank" data-add-bank-radio @checked($addBankType === 'vpbank')>
                <span class="bank-option-card d-flex gap-3">
                  <span class="bank-icon"><i class="bx bx-buildings"></i></span>
                  <span>
                    <span class="d-block fw-semibold">VPBank</span>
                    <span class="d-block text-muted small">Gửi OTP nếu thiết bị chưa tin cậy.</span>
                  </span>
                </span>
              </label>
              <label class="bank-option">
                <input class="bank-option-input" type="radio" name="add_bank_type" value="techcombank" data-add-bank-radio @checked($addBankType === 'techcombank')>
                <span class="bank-option-card d-flex gap-3">
                  <span class="bank-icon"><i class="bx bx-building-house"></i></span>
                  <span>
                    <span class="d-block fw-semibold">Techcombank</span>
                    <span class="d-block text-muted small">Duyệt đăng nhập trên app Mobile.</span>
                  </span>
                </span>
              </label>
              <label class="bank-option">
                <input class="bank-option-input" type="radio" name="add_bank_type" value="mbbank" data-add-bank-radio @checked($addBankType === 'mbbank')>
                <span class="bank-option-card d-flex gap-3">
                  <span class="bank-icon"><i class="bx bx-bank"></i></span>
                  <span>
                    <span class="d-block fw-semibold">MBBank</span>
                    <span class="d-block text-muted small">Tự giải captcha, đăng nhập một bước.</span>
                  </span>
                </span>
              </label>
            </div>
          </div>

          <div class="info-strip mb-4" data-add-bank-note>
            <div class="fw-semibold mb-1">
              @if($addBankType === 'techcombank')
                Techcombank xác nhận trên app
              @elseif($addBankType === 'mbbank')
                MBBank kết nối trực tiếp
              @elseif($addBankType === 'vpbank')
                VPBank có thể cần OTP
              @elseif($addBankType === 'vcb')
                Vietcombank cần OTP
              @else
                ACB kết nối trực tiếp
              @endif
            </div>
            <div class="text-muted small">
              @if($addBankType === 'techcombank')
                Nhập tài khoản Techcombank và số tài khoản nhận, sau đó duyệt trên app Mobile.
              @elseif($addBankType === 'mbbank')
                Nhập tài khoản MBBank, mật khẩu và số tài khoản nhận, hệ thống tự giải captcha qua apibank.com.vn.
              @elseif($addBankType === 'vpbank')
                Nhập tài khoản VPBank, mật khẩu và số tài khoản nhận để gửi OTP khi cần.
              @elseif($addBankType === 'vcb')
                Nhập tài khoản VCB, mật khẩu và số tài khoản nhận để gửi OTP.
              @else
                Nhập số điện thoại ACB, mật khẩu và số tài khoản nhận nạp.
              @endif
            </div>
          </div>

          <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form {{ $addBankType === 'acb' ? 'is-active' : '' }}" data-add-bank-form="acb">
            @csrf
            <input type="hidden" name="step" value="init">
            <input type="hidden" name="bank_code" value="acb">
            <input type="hidden" name="system_receiver" value="1">
            <input type="hidden" name="return_to" value="recharge_settings">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold" for="receiver_phone">Tài khoản ACB</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input class="form-control" id="receiver_phone" name="username" value="{{ old('username') }}" autocomplete="off" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="receiver_password">Mật khẩu ACB</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-lock-alt"></i></span>
                  <input class="form-control" id="receiver_password" name="password" type="password" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                    <i class="bx bx-show"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="receiver_stk">Số tài khoản nhận</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input class="form-control" id="receiver_stk" name="account_no" value="{{ old('account_no') }}" inputmode="numeric" required>
                </div>
              </div>
              <div class="col-12 connect-actions">
                <button class="btn btn-primary btn-touch w-100" type="submit">
                  <i class="bx bx-plus"></i> Lưu token ACB
                </button>
              </div>
            </div>
          </form>

          <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form {{ $addBankType === 'vcb' ? 'is-active' : '' }}" data-add-bank-form="vcb">
            @csrf
            <input type="hidden" name="step" value="init">
            <input type="hidden" name="bank_code" value="vcb">
            <input type="hidden" name="system_receiver" value="1">
            <input type="hidden" name="return_to" value="recharge_settings">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold" for="vcb_username">Tài khoản VCB</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input class="form-control" id="vcb_username" name="username" value="{{ old('username', $pendingVcb['username'] ?? '') }}" autocomplete="off" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="vcb_password">Mật khẩu VCB</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-lock-alt"></i></span>
                  <input class="form-control" id="vcb_password" name="password" type="password" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                    <i class="bx bx-show"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="vcb_account_no">Số tài khoản nhận</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input class="form-control" id="vcb_account_no" name="account_no" value="{{ old('account_no', $pendingVcb['account_no'] ?? '') }}" inputmode="numeric" required>
                </div>
              </div>
              <div class="col-12 connect-actions">
                <button class="btn btn-outline-primary btn-touch w-100" type="submit">
                  <i class="bx bx-message-square-dots"></i> Lưu token / gửi OTP VCB
                </button>
              </div>
            </div>
          </form>

          <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form {{ $addBankType === 'vpbank' ? 'is-active' : '' }}" data-add-bank-form="vpbank">
            @csrf
            <input type="hidden" name="step" value="init">
            <input type="hidden" name="bank_code" value="vpbank">
            <input type="hidden" name="system_receiver" value="1">
            <input type="hidden" name="return_to" value="recharge_settings">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold" for="vpbank_username">Tài khoản VPBank</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input class="form-control" id="vpbank_username" name="username" value="{{ old('username', $pendingVpbank['username'] ?? '') }}" autocomplete="off" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="vpbank_password">Mật khẩu VPBank</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-lock-alt"></i></span>
                  <input class="form-control" id="vpbank_password" name="password" type="password" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                    <i class="bx bx-show"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="vpbank_account_no">Số tài khoản nhận</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input class="form-control" id="vpbank_account_no" name="account_no" value="{{ old('account_no', $pendingVpbank['account_no'] ?? '') }}" inputmode="numeric" required>
                </div>
              </div>
              <div class="col-12 connect-actions">
                <button class="btn btn-outline-primary btn-touch w-100" type="submit">
                  <i class="bx bx-message-square-dots"></i> Lưu token / gửi OTP VPBank
                </button>
              </div>
            </div>
          </form>

          <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form {{ $addBankType === 'techcombank' ? 'is-active' : '' }}" data-add-bank-form="techcombank">
            @csrf
            <input type="hidden" name="step" value="init">
            <input type="hidden" name="bank_code" value="techcombank">
            <input type="hidden" name="system_receiver" value="1">
            <input type="hidden" name="return_to" value="recharge_settings">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold" for="techcombank_username">Tài khoản Techcombank</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input class="form-control" id="techcombank_username" name="username" value="{{ old('username', $pendingTechcombank['username'] ?? '') }}" autocomplete="off" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="techcombank_account_no">Số tài khoản nhận</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input class="form-control" id="techcombank_account_no" name="account_no" value="{{ old('account_no', $pendingTechcombank['account_no'] ?? '') }}" inputmode="numeric" required>
                </div>
              </div>
              <div class="col-12 connect-actions">
                <button class="btn btn-outline-primary btn-touch w-100" type="submit">
                  <i class="bx bx-log-in-circle"></i> Tạo link / sửa token Techcombank
                </button>
              </div>
            </div>
          </form>

          <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form {{ $addBankType === 'mbbank' ? 'is-active' : '' }}" data-add-bank-form="mbbank">
            @csrf
            <input type="hidden" name="step" value="init">
            <input type="hidden" name="bank_code" value="mbbank">
            <input type="hidden" name="system_receiver" value="1">
            <input type="hidden" name="return_to" value="recharge_settings">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold" for="mbbank_username">Tài khoản MBBank</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-user"></i></span>
                  <input class="form-control" id="mbbank_username" name="username" value="{{ old('username') }}" autocomplete="off" required>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="mbbank_password">Mật khẩu MBBank</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-lock-alt"></i></span>
                  <input class="form-control" id="mbbank_password" name="password" type="password" autocomplete="new-password" required>
                  <button class="btn btn-outline-secondary" type="button" data-toggle-password>
                    <i class="bx bx-show"></i>
                  </button>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="mbbank_account_no">Số tài khoản nhận</label>
                <div class="input-group">
                  <span class="input-group-text field-icon"><i class="bx bx-hash"></i></span>
                  <input class="form-control" id="mbbank_account_no" name="account_no" value="{{ old('account_no') }}" inputmode="numeric" required>
                </div>
              </div>
              <div class="col-12 connect-actions">
                <button class="btn btn-primary btn-touch w-100" type="submit">
                  <i class="bx bx-plus"></i> Lưu token MBBank
                </button>
              </div>
            </div>
          </form>

          @if(!empty($pendingVcb))
            <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form otp-panel mt-4 {{ $addBankType === 'vcb' ? 'is-active' : '' }}" data-add-bank-form="vcb">
              @csrf
              <input type="hidden" name="step" value="otp">
              <input type="hidden" name="bank_code" value="vcb">
              <input type="hidden" name="system_receiver" value="1">
              <input type="hidden" name="return_to" value="recharge_settings">
              <input type="hidden" name="username" value="{{ $pendingVcb['username'] ?? '' }}">
              <input type="hidden" name="password" value="{{ $pendingVcb['password'] ?? '' }}">
              <input type="hidden" name="account_no" value="{{ $pendingVcb['account_no'] ?? '' }}">
              <div class="d-flex gap-2 mb-3">
                <i class="bx bx-message-rounded-dots fs-4 text-warning"></i>
                <div>
                  <div class="fw-semibold">Nhập OTP Vietcombank</div>
                  <div class="text-muted small">OTP cho {{ $pendingVcb['username'] ?? '-' }} · {{ $pendingVcb['account_no'] ?? '-' }}</div>
                </div>
              </div>
              <label class="form-label fw-semibold" for="vcb_otp_code">Mã OTP</label>
              <input class="form-control otp-code-input mb-3" id="vcb_otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" required>
              <button class="btn btn-primary btn-touch w-100" type="submit">
                <i class="bx bx-check-shield"></i> Xác thực và lưu token VCB
              </button>
            </form>
          @endif
          @if(!empty($pendingVpbank))
            <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form otp-panel mt-4 {{ $addBankType === 'vpbank' ? 'is-active' : '' }}" data-add-bank-form="vpbank">
              @csrf
              <input type="hidden" name="step" value="otp">
              <input type="hidden" name="bank_code" value="vpbank">
              <input type="hidden" name="system_receiver" value="1">
              <input type="hidden" name="return_to" value="recharge_settings">
              <input type="hidden" name="username" value="{{ $pendingVpbank['username'] ?? '' }}">
              <input type="hidden" name="password" value="{{ $pendingVpbank['password'] ?? '' }}">
              <input type="hidden" name="account_no" value="{{ $pendingVpbank['account_no'] ?? '' }}">
              <div class="d-flex gap-2 mb-3">
                <i class="bx bx-message-rounded-dots fs-4 text-warning"></i>
                <div>
                  <div class="fw-semibold">Nhập OTP VPBank</div>
                  <div class="text-muted small">OTP cho {{ $pendingVpbank['username'] ?? '-' }} · {{ $pendingVpbank['account_no'] ?? '-' }}</div>
                </div>
              </div>
              <label class="form-label fw-semibold" for="vpbank_otp_code">Mã OTP</label>
              <input class="form-control otp-code-input mb-3" id="vpbank_otp_code" name="otp_code" inputmode="numeric" autocomplete="one-time-code" required>
              <button class="btn btn-primary btn-touch w-100" type="submit">
                <i class="bx bx-check-shield"></i> Xác thực và lưu token VPBank
              </button>
            </form>
          @endif
          @if(!empty($pendingTechcombank))
            <form method="POST" action="{{ route('bank.accounts.store') }}" class="add-bank-form otp-panel mt-4 {{ $addBankType === 'techcombank' ? 'is-active' : '' }}" data-add-bank-form="techcombank">
              @csrf
              <input type="hidden" name="step" value="otp">
              <input type="hidden" name="bank_code" value="techcombank">
              <input type="hidden" name="system_receiver" value="1">
              <input type="hidden" name="return_to" value="recharge_settings">
              <input type="hidden" name="username" value="{{ $pendingTechcombank['username'] ?? '' }}">
              <input type="hidden" name="password" value="{{ $pendingTechcombank['password'] ?? '' }}">
              <input type="hidden" name="account_no" value="{{ $pendingTechcombank['account_no'] ?? '' }}">
              <div class="d-flex gap-2 mb-3">
                <i class="bx bx-mobile-alt fs-4 text-warning"></i>
                <div>
                  <div class="fw-semibold">Hoàn tất Techcombank</div>
                  <div class="text-muted small">Mở Techcombank cho {{ $pendingTechcombank['username'] ?? '-' }} · {{ $pendingTechcombank['account_no'] ?? '-' }}, đăng nhập, duyệt Mobile rồi dán URL xác nhận.</div>
                </div>
              </div>
              @if(!empty($pendingTechcombank['auth_url']))
                <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                  <a class="btn btn-outline-primary btn-touch" href="{{ $pendingTechcombank['auth_url'] }}" target="_blank" rel="noopener">
                    <i class="bx bx-log-in-circle"></i> Mở Techcombank
                  </a>
                  <button class="btn btn-outline-secondary btn-touch" type="button" data-copy-text="{{ $pendingTechcombank['auth_url'] }}">
                    <i class="bx bx-copy"></i> Copy link
                  </button>
                </div>
              @endif
              <label class="form-label fw-semibold" for="techcombank_redirect_url">URL sau khi xác nhận</label>
              <textarea class="form-control mb-3"
                        id="techcombank_redirect_url"
                        name="redirect_url"
                        rows="3"
                        placeholder="Dán toàn bộ URL có code=... sau khi đăng nhập và duyệt app Mobile"
                        required>{{ old('redirect_url') }}</textarea>
              <button class="btn btn-primary btn-touch w-100" type="submit">
                <i class="bx bx-check-shield"></i> Lưu token Techcombank
              </button>
            </form>
          @endif
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="connect-aside">
              <div class="aside-block">
                <div class="d-flex gap-2">
                  <i class="bx bx-shield-quarter fs-4 text-primary"></i>
                  <div>
                    <div class="fw-semibold mb-1">Account nhận tiền hệ thống</div>
                    <div class="text-muted small">Account thêm tại đây thuộc Super Admin và có thể chọn làm nguồn nhận nạp.</div>
                  </div>
                </div>
              </div>
              <div class="aside-block">
                <div class="d-flex gap-2">
                  <i class="bx bx-refresh fs-4 text-success"></i>
                  <div>
                    <div class="fw-semibold mb-1">Quét tự động</div>
                    <div class="text-muted small">Sau khi chọn account active, cron dùng đúng tài khoản này để đối soát giao dịch.</div>
                  </div>
                </div>
              </div>
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
(() => {
  const input = document.getElementById('noidungnap');
  const preview = document.getElementById('preview');
  const topPreview = document.getElementById('topPreview');
  const topBank = document.getElementById('topBank');
  const topAccount = document.getElementById('topAccount');
  const receiver = document.getElementById('receiver_account_id');
  const bankType = document.getElementById('receiver_bank_type');
  const accountNumber = document.getElementById('accountNumber');
  const accountName = document.getElementById('accountName');
  const receiverId = document.getElementById('receiverId');
  const receiverAccountNo = document.getElementById('receiverAccountNo');
  const receiverName = document.getElementById('receiverName');
  const receiverLogin = document.getElementById('receiverLogin');
  const receiverStatus = document.getElementById('receiverStatus');
  const receiverBankChip = document.getElementById('receiverBankChip');
  const tabButtons = document.querySelectorAll('[data-recharge-tab]');
  const tabPanes = document.querySelectorAll('[data-recharge-tab-pane]');
  const tabJumps = document.querySelectorAll('[data-recharge-tab-jump]');
  const addBankRadios = document.querySelectorAll('[data-add-bank-radio]');
  const addBankForms = document.querySelectorAll('[data-add-bank-form]');
  const addBankNote = document.querySelector('[data-add-bank-note]');
  const addBankOtpStep = document.querySelector('[data-add-bank-step="otp"]');
  const inlineTokenEditButtons = document.querySelectorAll('[data-inline-token-edit]');
  const id = @json($exampleUserId);
  const pendingVcb = @json(!empty($pendingVcb));
  const pendingVpbank = @json(!empty($pendingVpbank));
  const pendingTechcombank = @json(!empty($pendingTechcombank));

  const setRechargeTab = (tab, updateHash = true) => {
    tabButtons.forEach((button) => {
      const active = button.dataset.rechargeTab === tab;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });

    tabPanes.forEach((pane) => {
      pane.classList.toggle('is-active', pane.dataset.rechargeTabPane === tab);
    });

    if (updateHash && window.history?.replaceState) {
      window.history.replaceState(null, '', `#${tab}`);
    }
  };

  tabButtons.forEach((button) => {
    button.addEventListener('click', () => setRechargeTab(button.dataset.rechargeTab));
  });

  tabJumps.forEach((button) => {
    button.addEventListener('click', () => setRechargeTab(button.dataset.rechargeTabJump));
  });

  const initialHashTab = window.location.hash.replace('#', '');
  if (['settings', 'accounts', 'add'].includes(initialHashTab)) {
    setRechargeTab(initialHashTab, false);
  }

  if (input) {
    input.addEventListener('input', () => {
      const code = input.value + id;
      preview.textContent = code;
      topPreview.textContent = code;
    });
  }

  if (receiver) {
    receiver.addEventListener('change', () => {
      const option = receiver.options[receiver.selectedIndex];
      if (!option) return;
      const bank = option.dataset.bank || 'ACB';
      const stk = option.dataset.stk || '';
      const name = option.dataset.name || '';
      const login = option.dataset.login || '-';
      const hasSession = option.dataset.session === '1';

      bankType.value = bank;
      topBank.textContent = bank;
      topAccount.textContent = stk || '-';
      accountNumber.textContent = stk || '-';
      accountName.textContent = name || 'Chưa có tên';
      receiverId.textContent = option.value || '-';
      receiverAccountNo.textContent = stk || '-';
      receiverName.textContent = name || 'Chưa có tên';
      receiverLogin.textContent = login;
      receiverStatus.textContent = hasSession ? 'Sẵn sàng quét' : 'Cần đăng nhập lại';
      receiverStatus.classList.toggle('status-ok', hasSession);
      receiverStatus.classList.toggle('status-warn', !hasSession);
      receiverBankChip.textContent = bank;
      receiverBankChip.classList.toggle('bank-chip-acb', bank === 'ACB');
      receiverBankChip.classList.toggle('bank-chip-vcb', bank === 'VCB');
      receiverBankChip.classList.toggle('bank-chip-vpbank', bank === 'VPBANK');
      receiverBankChip.classList.toggle('bank-chip-techcombank', bank === 'TECHCOMBANK');
      receiverBankChip.classList.toggle('bank-chip-mbbank', bank === 'MBBANK');
    });
  }

  const addBankCopy = {
    acb: {
      title: 'ACB kết nối trực tiếp',
      body: 'Nhập số điện thoại ACB, mật khẩu và số tài khoản nhận nạp.'
    },
    vcb: {
      title: 'Vietcombank cần OTP',
      body: 'Nhập tài khoản VCB, mật khẩu và số tài khoản nhận để gửi OTP.'
    },
    vpbank: {
      title: 'VPBank có thể cần OTP',
      body: 'Nhập tài khoản VPBank, mật khẩu và số tài khoản nhận để gửi OTP khi cần.'
    },
    techcombank: {
      title: 'Techcombank đăng nhập bằng trình duyệt thật',
      body: 'Nhập tài khoản và số tài khoản nhận, tạo link, đăng nhập trực tiếp trên Techcombank rồi dán URL xác nhận.'
    },
    mbbank: {
      title: 'MBBank kết nối trực tiếp',
      body: 'Nhập tài khoản MBBank, mật khẩu và số tài khoản nhận, hệ thống tự giải captcha qua apibank.com.vn.'
    }
  };

  const setAddBank = (bank) => {
    addBankForms.forEach((form) => {
      form.classList.toggle('is-active', form.dataset.addBankForm === bank);
    });

    if (addBankNote) {
      const title = addBankNote.querySelector('.fw-semibold');
      const body = addBankNote.querySelector('.text-muted');
      const copy = addBankCopy[bank] || addBankCopy.acb;
      if (title && body) {
        title.textContent = copy.title;
        body.textContent = copy.body;
      }
    }

    if (addBankOtpStep) {
      addBankOtpStep.classList.toggle('is-active', (bank === 'vcb' && pendingVcb) || (bank === 'vpbank' && pendingVpbank) || (bank === 'techcombank' && pendingTechcombank));
    }
  };

  addBankRadios.forEach((radio) => {
    radio.addEventListener('change', () => setAddBank(radio.value));
    if (radio.checked) setAddBank(radio.value);
  });

  const fillReceiverTokenForm = (bank, username, accountNo) => {
    const radio = document.querySelector('[data-add-bank-radio][value="' + bank + '"]');
    if (radio) {
      radio.checked = true;
      setAddBank(bank);
    }

    const selectors = {
      acb: ['#receiver_phone', '#receiver_stk', '#receiver_password'],
      vcb: ['#vcb_username', '#vcb_account_no', '#vcb_password'],
      vpbank: ['#vpbank_username', '#vpbank_account_no', '#vpbank_password'],
      techcombank: ['#techcombank_username', '#techcombank_account_no', null],
      mbbank: ['#mbbank_username', '#mbbank_account_no', '#mbbank_password']
    };
    const [usernameSelector, accountSelector, passwordSelector] = selectors[bank] || selectors.acb;
    const usernameInput = document.querySelector(usernameSelector);
    const accountInput = document.querySelector(accountSelector);
    const passwordInput = passwordSelector ? document.querySelector(passwordSelector) : null;

    if (usernameInput) usernameInput.value = username || '';
    if (accountInput) accountInput.value = accountNo || '';
    if (passwordInput) passwordInput.value = '';

    setRechargeTab('add');
    setTimeout(() => (passwordInput || accountInput || usernameInput)?.focus(), 80);
  };

  inlineTokenEditButtons.forEach((button) => {
    button.addEventListener('click', () => {
      fillReceiverTokenForm(button.dataset.bank || 'acb', button.dataset.username || '', button.dataset.accountNo || '');
    });
  });

  document.querySelectorAll('[data-edit-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const key = button.dataset.editToggle;
      document.querySelectorAll('[data-edit-panel]').forEach((panel) => {
        panel.classList.toggle('is-open', panel.dataset.editPanel === key && !panel.classList.contains('is-open'));
      });
    });
  });

  document.querySelectorAll('[data-edit-close]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelector('[data-edit-panel="' + button.dataset.editClose + '"]')?.classList.remove('is-open');
    });
  });

  document.querySelectorAll('[data-delete-account]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!confirm('Xóa account hệ thống này?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', () => {
      const value = button.dataset.copyText || '';
      if (!value) return;
      navigator.clipboard?.writeText(value);
    });
  });

  document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.closest('.input-group')?.querySelector('input');
      const icon = button.querySelector('i');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon?.classList.toggle('bx-show', !show);
      icon?.classList.toggle('bx-hide', show);
    });
  });
})();
</script>
@endsection
