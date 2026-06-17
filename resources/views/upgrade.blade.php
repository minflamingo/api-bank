@extends('layouts/contentNavbarLayout')

@section('title', 'Gia hạn API')

@section('content')
@php
    $money = fn ($value) => number_format((int) $value) . ' đ';
    $currentPlanKey = trim((string) ($user->api_plan ?? ''));
    $currentPlanName = $currentPlan['name'] ?? 'Chưa chọn gói';
    $timeEnd = (int) ($user->time_end ?? 0);
    $nextPlanName = $nextPlan['name'] ?? '';
    $nextDurationLabel = (int) $nextPlanMonths > 0
        ? \App\Support\ApiPackage::durationLabel((int) $nextPlanMonths)
        : 'kỳ sau';
    $nextPlanSummary = $nextPlanName
        ? $nextPlanName . ' / ' . $nextDurationLabel . ' - ' . $money($nextPlanPrice)
        : '';
@endphp

<style>
  .upgrade-page { color: #1f2937; }
  .upgrade-page .page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 16px;
  }
  .upgrade-page h1 { font-size: 28px; font-weight: 850; margin: 0; letter-spacing: 0; }
  .upgrade-page .status-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
  }
  .upgrade-page .status-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 14px;
    background: #fff;
  }
  .upgrade-page .status-label { color: #6b7280; font-size: 13px; margin-bottom: 5px; }
  .upgrade-page .status-value { font-weight: 800; font-size: 16px; }
  .upgrade-page .notice {
    border-radius: 8px;
    background: #dc2626;
    color: #fff;
    font-weight: 800;
    padding: 14px 18px;
    margin-bottom: 16px;
  }
  .upgrade-page .plan-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
  }
  .upgrade-page .plan-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .05);
  }
  .upgrade-page .plan-head {
    padding: 18px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: flex-start;
  }
  .upgrade-page .plan-title {
    font-size: 20px;
    font-weight: 900;
    line-height: 1.15;
    margin: 0;
  }
  .upgrade-page .plan-limit {
    color: #059669;
    background: #d1fae5;
    border-radius: 999px;
    font-weight: 800;
    font-size: 12px;
    padding: 5px 9px;
    white-space: nowrap;
  }
  .upgrade-page .plan-price {
    padding: 18px;
    background: #f6f8fa;
  }
  .upgrade-page .price-number {
    color: #10b981;
    font-size: 32px;
    line-height: 1;
    font-weight: 900;
    letter-spacing: 0;
  }
  .upgrade-page .price-cycle {
    color: #111827;
    font-size: 14px;
    font-weight: 800;
    margin-top: 7px;
  }
  .upgrade-page .discount-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
  }
  .upgrade-page .discount-pill {
    color: #0f766e;
    background: #ccfbf1;
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 12px;
    font-weight: 800;
  }
  .upgrade-page .plan-body {
    padding: 16px 18px 18px;
    display: flex;
    flex: 1;
    flex-direction: column;
  }
  .upgrade-page .feature-list {
    list-style: none;
    padding: 0;
    margin: 0 0 18px;
    color: #475467;
    font-weight: 650;
    line-height: 1.75;
    font-size: 14px;
  }
  .upgrade-page .feature-list li {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .upgrade-page .feature-list i { color: #10b981; font-size: 18px; }
  .upgrade-page .plan-action {
    margin-top: auto;
    border: 1px solid #10b981;
    color: #065f46;
    background: #f0fdf4;
    border-radius: 6px;
    min-height: 42px;
    font-weight: 900;
  }
  .duration-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .duration-option {
    border: 2px solid #d1d5db;
    border-radius: 8px;
    background: #fff;
    text-align: left;
    padding: 14px;
    min-height: 84px;
    position: relative;
  }
  .duration-option.active {
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, .12);
  }
  .duration-option b { display: block; color: #111827; font-size: 17px; margin-bottom: 7px; }
  .duration-option span { color: #f43f5e; font-weight: 900; }
  .duration-option em {
    position: absolute;
    right: 10px;
    top: 10px;
    font-style: normal;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: 800;
  }
  .checkout-box {
    border-radius: 8px;
    background: #10b981;
    color: #fff;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
  }
  .checkout-box .total { font-size: 28px; font-weight: 900; line-height: 1; }
  .checkout-box .caption { font-weight: 800; margin-top: 6px; }
  @media (max-width: 1199px) {
    .upgrade-page .status-grid, .upgrade-page .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 767px) {
    .upgrade-page .page-head { align-items: flex-start; flex-direction: column; }
    .upgrade-page .status-grid, .upgrade-page .plan-grid, .duration-grid { grid-template-columns: 1fr; }
    .checkout-box { align-items: stretch; flex-direction: column; }
  }
</style>

<div class="upgrade-page">
  <div class="page-head">
    <div>
      <h1>Gia hạn API</h1>
      <div class="text-muted">Nâng cấp áp dụng ngay, hạ cấp chỉ có hiệu lực ở kỳ sau.</div>
    </div>
    <a class="btn btn-outline-primary" href="{{ url('/client/payin') }}">
      <i class="bx bx-wallet me-1"></i>Nạp tiền
    </a>
  </div>

  <div class="status-grid">
    <div class="status-item">
      <div class="status-label">Số dư ví</div>
      <div class="status-value">{{ $money((int) $user->amount) }}</div>
    </div>
    <div class="status-item">
      <div class="status-label">Hạn API</div>
      <div class="status-value">
        @if($timeEnd > time())
          {{ date('H:i d/m/Y', $timeEnd) }}
        @else
          Đã hết hạn
        @endif
      </div>
    </div>
    <div class="status-item">
      <div class="status-label">Gói hiện tại</div>
      <div class="status-value">{{ $currentPlanName }}</div>
    </div>
    <div class="status-item">
      <div class="status-label">Giới hạn tài khoản</div>
      <div class="status-value">{{ number_format($accountLimit) }} tài khoản</div>
    </div>
  </div>

  @if($nextPlanName)
    <div class="alert alert-info border mb-3">
      <b>Gói kỳ sau:</b> {{ $nextPlanSummary }}. Gói này sẽ tự kích hoạt khi gói hiện tại hết hạn và ví đủ số dư.
    </div>
  @endif

  <div class="notice">Lưu ý: Gia hạn cùng gói sẽ cộng thêm thời gian; hạ cấp không hoàn tiền giữa kỳ và chỉ lên lịch cho kỳ kế tiếp.</div>

  <div class="plan-grid">
    @foreach($plans as $key => $plan)
      <article class="plan-card">
        <div class="plan-head">
          <h2 class="plan-title">{{ $plan['name'] }}</h2>
          <span class="plan-limit">{{ number_format($plan['limit']) }} TK</span>
        </div>
        <div class="plan-price">
          <div class="price-number">{{ $money($plan['price']) }}</div>
          <div class="price-cycle">/1 tháng · {{ $plan['summary'] }}</div>
          <div class="discount-row">
            <span class="discount-pill">1 năm giảm 10%</span>
            <span class="discount-pill">2 năm giảm 20%</span>
          </div>
        </div>
        <div class="plan-body">
          <ul class="feature-list">
            @foreach($plan['features'] as $feature)
              <li><i class="bx bx-check-circle"></i>{{ $feature }}</li>
            @endforeach
          </ul>
          <button
            class="btn plan-action js-open-plan"
            type="button"
            data-plan="{{ $key }}"
          >
            @if($timeEnd > time() && $currentPlanKey !== '' && hash_equals($currentPlanKey, (string) $key))
              Gia Hạn Ngay
            @elseif($timeEnd > time() && (int) $plan['limit'] < (int) $baseLimit)
              Hạ Cấp Kỳ Sau
            @elseif($timeEnd > time() && (int) $plan['limit'] > (int) $baseLimit)
              Nâng Cấp Ngay
            @else
              Gia Hạn Ngay
            @endif
          </button>
        </div>
      </article>
    @endforeach
  </div>
</div>

<div class="modal fade" id="upgradeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title fw-bold" id="modalTitle">Nâng cấp gói API</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-lg-6">
            <h5 class="fw-bold mb-3">Thời gian</h5>
            <div class="duration-grid" id="durationGrid"></div>
          </div>
          <div class="col-lg-6">
            <h5 class="fw-bold text-center mb-3">Thông tin mua hàng</h5>
            <div class="list-group mb-3">
              <div class="list-group-item d-flex justify-content-between">
                <span>Số dư của tôi:</span>
                <b>{{ $money((int) $user->amount) }}</b>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span>Giới hạn gói:</span>
                <b id="modalLimit"></b>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span>Hình thức:</span>
                <b id="modalAction">Gia hạn ngay</b>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span>Giá gói mới:</span>
                <b id="modalPackageTotal">0 đ</b>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span>Hoàn gói cũ:</span>
                <b class="text-success" id="modalRefund">0 đ</b>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span>Số dư sau mua:</span>
                <b id="modalBalanceAfter">0 đ</b>
              </div>
            </div>
            <div class="checkout-box">
              <div>
                <div class="total" id="modalTotal">0 đ</div>
                <div class="caption" id="modalCycle">Cần thanh toán</div>
              </div>
              <button class="btn btn-dark btn-lg fw-bold" id="btnPayPlan" type="button">Thanh Toán</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const plans = @json($plans);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const modalEl = document.getElementById('upgradeModal');
  const modal = new bootstrap.Modal(modalEl);
  const durationGrid = document.getElementById('durationGrid');
  const modalTitle = document.getElementById('modalTitle');
  const modalLimit = document.getElementById('modalLimit');
  const modalAction = document.getElementById('modalAction');
  const modalPackageTotal = document.getElementById('modalPackageTotal');
  const modalRefund = document.getElementById('modalRefund');
  const modalBalanceAfter = document.getElementById('modalBalanceAfter');
  const modalTotal = document.getElementById('modalTotal');
  const modalCycle = document.getElementById('modalCycle');
  const btnPayPlan = document.getElementById('btnPayPlan');
  let selectedPlan = null;
  let selectedMonths = 1;

  const fmt = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';
  const durationLabel = months => {
    months = Number(months);
    return months % 12 === 0 ? (months / 12) + ' năm' : months + ' tháng';
  };
  const discountText = months => Number(months) === 12 ? 'Giảm 10%' : (Number(months) === 24 ? 'Giảm 20%' : '');

  function setButtonLoading(button, loading, text) {
    button.disabled = loading;
    if (loading) {
      button.dataset.beforeLoadingText = button.textContent;
      button.textContent = text;
      return;
    }
    button.textContent = button.dataset.beforeLoadingText || button.dataset.defaultText || button.textContent;
  }

  function renderModal(planKey) {
    selectedPlan = planKey;
    selectedMonths = 1;
    const plan = plans[planKey];
    modalTitle.textContent = 'Xử lý gói ' + plan.name;
    modalLimit.textContent = plan.limit + ' tài khoản ngân hàng';
    modalAction.textContent = 'Đang tính...';
    btnPayPlan.dataset.defaultText = 'Thanh Toán';
    btnPayPlan.textContent = btnPayPlan.dataset.defaultText;
    durationGrid.innerHTML = '';

    Object.entries(plan.durations).forEach(([months, price]) => {
      const sale = discountText(months);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'duration-option' + (Number(months) === selectedMonths ? ' active' : '');
      button.dataset.months = months;
      button.dataset.price = price;
      button.innerHTML = '<b>' + durationLabel(months) + '</b><span>Giá ' + fmt(price) + '</span>' + (sale ? '<em>' + sale + '</em>' : '');
      durationGrid.appendChild(button);
    });

    updateModalTotal();
    modal.show();
  }

  function updateModalTotal() {
    const active = durationGrid.querySelector('.duration-option.active');
    if (!active) return;
    selectedMonths = Number(active.dataset.months);
    modalPackageTotal.textContent = fmt(active.dataset.price);
    modalRefund.textContent = 'Đang tính...';
    modalBalanceAfter.textContent = 'Đang tính...';
    modalTotal.textContent = fmt(active.dataset.price);
    modalCycle.textContent = 'Cần thanh toán';
    modalAction.textContent = 'Đang tính...';
    btnPayPlan.dataset.defaultText = 'Thanh Toán';
    btnPayPlan.textContent = btnPayPlan.dataset.defaultText;

    fetch("{{ route('client.upgrade.total') }}", {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
      body: JSON.stringify({plan: selectedPlan, months: selectedMonths})
    })
      .then(response => response.json())
      .then(data => {
        if (data.status !== '2') return;
        const scheduled = data.action === 'schedule_downgrade';
        const renew = data.action === 'renew';
        modalPackageTotal.textContent = data.total_text || fmt(data.total);
        modalAction.textContent = data.action_text || (scheduled ? 'Hạ cấp kỳ sau' : 'Gia hạn ngay');
        modalRefund.textContent = (scheduled || renew) ? 'Không hoàn tiền' : (data.refund_text || fmt(data.refund));
        modalBalanceAfter.textContent = data.balance_after_text || fmt(data.balance_after);
        modalTotal.textContent = data.payable_text || fmt(data.payable);
        if (scheduled) {
          modalTotal.textContent = '0 VNĐ';
          modalCycle.textContent = 'Hiệu lực ' + (data.effective_text || 'kỳ sau');
          btnPayPlan.dataset.defaultText = 'Lên Lịch';
          btnPayPlan.textContent = btnPayPlan.dataset.defaultText;
          return;
        }

        btnPayPlan.dataset.defaultText = 'Thanh Toán';
        btnPayPlan.textContent = btnPayPlan.dataset.defaultText;
        if (renew) {
          modalCycle.textContent = data.effective_text || 'Cộng thêm vào hạn hiện tại';
          return;
        }

        modalCycle.textContent = Number(data.refund || 0) > 0
          ? 'Đã trừ hoàn ' + (data.remaining_days || 0) + ' ngày còn lại'
          : 'Cần thanh toán';
      })
      .catch(() => {
        modalRefund.textContent = '0 đ';
        modalBalanceAfter.textContent = '';
      });
  }

  document.querySelectorAll('.js-open-plan').forEach(button => {
    button.addEventListener('click', () => renderModal(button.dataset.plan));
  });

  durationGrid.addEventListener('click', event => {
    const option = event.target.closest('.duration-option');
    if (!option) return;
    durationGrid.querySelectorAll('.duration-option').forEach(el => el.classList.remove('active'));
    option.classList.add('active');
    updateModalTotal();
  });

  btnPayPlan.addEventListener('click', function () {
    if (!selectedPlan) return;
    setButtonLoading(btnPayPlan, true, 'Đang xử lý...');
    fetch("{{ route('client.upgrade.store') }}", {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
      body: JSON.stringify({plan: selectedPlan, months: selectedMonths})
    })
      .then(response => response.json())
      .then(data => {
        alert(data.msg || 'Đã xử lý');
        if (data.status === '2') location.reload();
      })
      .catch(() => alert('Lỗi kết nối'))
      .finally(() => setButtonLoading(btnPayPlan, false));
  });
});
</script>
@endsection
