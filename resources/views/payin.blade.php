@extends('layouts/contentNavbarLayout')

@section('title', 'Nạp tiền')

@section('page-style')
<style>
  .payin-page {
    --payin-border: rgba(67, 89, 113, .12);
    --payin-muted: #697a8d;
    --payin-soft: #f7f8fb;
    --payin-green: #28c76f;
    --payin-blue: #2962ff;
  }

  .payin-page .btn-touch {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    border-radius: .5rem;
    white-space: nowrap;
  }

  .payin-page .soft-card {
    border: 1px solid var(--payin-border);
    border-radius: .5rem;
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .055);
  }

  .payin-page .payin-shell {
    border: 1px solid var(--payin-border);
    border-radius: .65rem;
    background: #fff;
    box-shadow: 0 .35rem 1rem rgba(67, 89, 113, .06);
    overflow: hidden;
  }

  .payin-page .transfer-panel,
  .payin-page .qr-panel {
    padding: 1.15rem;
  }

  .payin-page .qr-panel {
    background: var(--payin-soft);
    border-top: 1px solid var(--payin-border);
  }

  .payin-page .balance-strip {
    border: 1px solid rgba(40, 199, 111, .22);
    border-radius: .55rem;
    background: rgba(40, 199, 111, .06);
    padding: .85rem;
  }

  .payin-page .balance-value {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: 0;
    color: #243447;
  }

  .payin-page .field-box {
    border: 1px solid var(--payin-border);
    border-radius: .5rem;
    background: #fff;
    padding: .72rem .8rem;
  }

  .payin-page .field-label {
    color: var(--payin-muted);
    font-size: .78rem;
    margin-bottom: .2rem;
  }

  .payin-page .copy-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .5rem;
    align-items: center;
  }

  .payin-page .copy-value,
  .payin-page .history-description {
    overflow-wrap: anywhere;
  }

  .payin-page .recharge-code {
    color: #d92323;
    font-weight: 700;
  }

  .payin-page .quick-amounts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .5rem;
  }

  .payin-page .quick-amounts .btn {
    font-weight: 700;
  }

  .payin-page .payin-qr {
    display: block;
    width: 260px;
    max-width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: contain;
    margin: 0 auto;
    border: 1px solid var(--payin-border);
    border-radius: .5rem;
    background: #fff;
  }

  .payin-page .realtime-box {
    border: 1px solid rgba(105, 108, 255, .18);
    border-radius: .55rem;
    background: #fff;
    padding: .85rem;
  }

  .payin-page .realtime-box.is-success {
    border-color: rgba(40, 199, 111, .3);
    background: rgba(40, 199, 111, .06);
  }

  .payin-page .realtime-box.is-error {
    border-color: rgba(255, 62, 29, .28);
    background: rgba(255, 62, 29, .06);
  }

  .payin-page .pulse-dot {
    width: .62rem;
    height: .62rem;
    border-radius: 999px;
    background: var(--payin-green);
    box-shadow: 0 0 0 .32rem rgba(40, 199, 111, .14);
    flex: 0 0 auto;
  }

  .payin-page .realtime-box.is-error .pulse-dot {
    background: #ff3e1d;
    box-shadow: 0 0 0 .32rem rgba(255, 62, 29, .12);
  }

  .payin-page .status-title {
    font-weight: 800;
    color: #243447;
  }

  .payin-page .status-text {
    color: var(--payin-muted);
    font-size: .85rem;
  }

  .payin-page .bank-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    min-height: 2rem;
    border-radius: .45rem;
    padding: .18rem .6rem;
    color: #0b4f3a;
    background: rgba(40, 199, 111, .1);
    font-weight: 800;
  }

  @media (max-width: 575.98px) {
    .payin-page .quick-amounts {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (min-width: 992px) {
    .payin-page .qr-panel {
      border-top: 0;
      border-left: 1px solid var(--payin-border);
    }
  }
</style>
@endsection

@section('content')
<div id="payinPage" class="payin-page">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <div class="text-muted small mb-1">Ví tài khoản</div>
      <h4 class="mb-1">Nạp tiền</h4>
      <div class="text-muted">Chuyển khoản đúng nội dung, hệ thống tự đối soát và cộng tiền.</div>
    </div>
    <div class="bank-badge">
      <i class="bx bx-refresh bx-spin"></i> Tự kiểm tra mỗi {{ $scanInterval }} giây
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
      {{ $errors->first() }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="payin-shell mb-4">
    <div class="row g-0">
      <div class="col-lg-7">
        <div class="transfer-panel">
          <div class="balance-strip d-flex flex-column flex-sm-row justify-content-between gap-2 mb-3">
            <div>
              <div class="field-label">Số dư hiện tại</div>
              <div class="balance-value" id="balance">{{ number_format($balance) }} đ</div>
            </div>
            <div class="text-sm-end">
              <div class="field-label">Ngân hàng nhận</div>
              <div class="fw-semibold">{{ $bankLabel }}</div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-7">
              <div class="field-box h-100">
                <div class="field-label">Số tài khoản {{ $bankLabel }}</div>
                <div class="copy-row">
                  <div class="copy-value fw-semibold" id="acc">{{ $bank->accountNumber }}</div>
                  <button class="btn btn-outline-secondary btn-touch" type="button" data-copy="acc">
                    <i class="bx bx-copy"></i> Copy
                  </button>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="field-box h-100">
                <div class="field-label">Chủ tài khoản</div>
                <div class="fw-semibold">{{ $bank->accountName }}</div>
              </div>
            </div>
            <div class="col-12">
              <div class="field-box">
                <div class="field-label">Nội dung chuyển khoản</div>
                <div class="copy-row">
                  <div class="copy-value recharge-code" id="code">{{ $addInfo }}</div>
                  <button class="btn btn-outline-primary btn-touch" type="button" data-copy="code">
                    <i class="bx bx-copy"></i> Copy
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label fw-semibold" for="amount">Số tiền tạo QR</label>
            <input class="form-control" id="amount" type="number" min="{{ $minAmount }}" step="1000" inputmode="numeric" placeholder="{{ number_format($minAmount) }}">
            <div class="quick-amounts mt-3">
              @foreach($quickAmounts as $amount)
                <button class="btn btn-outline-primary btn-sm btn-touch" type="button" data-amount="{{ $amount }}">{{ number_format($amount) }}</button>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="qr-panel h-100">
          <img class="payin-qr" id="qr" src="{{ $qrUrl }}" width="260" height="260" loading="eager" decoding="async" alt="QR {{ $bankLabel }}">
          <div class="realtime-box mt-3" id="status" role="status" aria-live="polite">
            <div class="d-flex gap-2 align-items-start">
              <span class="pulse-dot mt-1"></span>
              <div>
                <div class="status-title" id="statusTitle">Đang chờ giao dịch</div>
                <div class="status-text" id="statusText">APIBank tự đối soát {{ $scanInterval }} giây/lần. Giữ đúng nội dung chuyển khoản để được cộng tiền tự động.</div>
              </div>
            </div>
          </div>
          <div class="text-muted small mt-3">
            Cửa sổ này tự cập nhật bằng AJAX, không cần bấm xác nhận sau khi chuyển khoản.
          </div>
        </div>
      </div>
    </div>
  </div>

  @if($instructions)
    <div class="alert alert-info mb-4" role="alert">{{ $instructions }}</div>
  @endif

  <div class="card soft-card">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between gap-2">
      <div>
        <h5 class="mb-0">Lịch sử nạp tiền</h5>
        <div class="text-muted small">10 giao dịch gần nhất</div>
      </div>
    </div>
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Ngân hàng</th>
            <th>Mã GD</th>
            <th>Số tiền</th>
            <th>Thời gian</th>
            <th>Nội dung</th>
          </tr>
        </thead>
        <tbody id="invoiceRows">
        @forelse($invoices as $row)
          <tr data-invoice-id="{{ $row->id }}">
            <td>{{ $row->payment_method }}</td>
            <td>{{ $row->trans_id }}</td>
            <td class="text-success fw-semibold">{{ number_format($row->amount) }} đ</td>
            <td>{{ date('H:i d-m-Y', $row->create_time) }}</td>
            <td class="history-description">{{ $row->description }}</td>
          </tr>
        @empty
          <tr data-empty-invoice-row>
            <td colspan="5" class="text-muted">Chưa có giao dịch.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
(() => {
  const $ = id => document.getElementById(id);
  const base = @json($qrUrl);
  const checkUrl = @json(url('/ajaxs/client/checknaptien'));
  const startedAt = @json(time());
  const pollIntervalMs = @json($scanInterval * 1000);
  const fmt = n => new Intl.NumberFormat('vi-VN').format(+n || 0) + ' đ';
  const status = $('status');
  const statusTitle = $('statusTitle');
  const statusText = $('statusText');
  const invoiceRows = $('invoiceRows');
  let lastCheckTime = startedAt;
  let polling = false;

  function setStatus(title, text, type = 'waiting') {
    status.classList.toggle('is-success', type === 'success');
    status.classList.toggle('is-error', type === 'error');
    statusTitle.textContent = title;
    statusText.textContent = text;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function formatTime(timestamp) {
    const date = new Date((+timestamp || 0) * 1000);
    return new Intl.DateTimeFormat('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    }).format(date).replace(',', '');
  }

  function prependInvoice(invoice) {
    if (!invoice || !invoice.id || document.querySelector('[data-invoice-id="' + invoice.id + '"]')) {
      return;
    }

    document.querySelector('[data-empty-invoice-row]')?.remove();
    const row = document.createElement('tr');
    row.dataset.invoiceId = invoice.id;
    row.innerHTML = `
      <td>${escapeHtml(invoice.payment_method || '')}</td>
      <td>${escapeHtml(invoice.trans_id || '')}</td>
      <td class="text-success fw-semibold">${fmt(invoice.amount)}</td>
      <td>${formatTime(invoice.create_time)}</td>
      <td class="history-description">${escapeHtml(invoice.description || '')}</td>
    `;
    invoiceRows.prepend(row);
  }

  function updateQr() {
    const url = new URL(base, window.location.href);
    const value = +$('amount').value || 0;
    if (value > 0) {
      url.searchParams.set('amount', value);
    } else {
      url.searchParams.delete('amount');
    }
    $('qr').src = url.toString();
  }

  document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      const text = $(button.dataset.copy).textContent.trim();
      try {
        await navigator.clipboard.writeText(text);
      } catch (error) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
      }
      setStatus('Đã copy', text, 'success');
    });
  });

  document.querySelectorAll('[data-amount]').forEach(button => {
    button.addEventListener('click', () => {
      $('amount').value = button.dataset.amount;
      updateQr();
    });
  });

  $('amount').addEventListener('input', updateQr);

  async function pollRecharge() {
    if (polling) return;
    polling = true;

    try {
      const url = new URL(checkUrl, window.location.origin);
      url.searchParams.set('action', 'checkTransaction');
      url.searchParams.set('currentTime', lastCheckTime);
      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'application/json'
        }
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Không lấy được trạng thái nạp tiền');
      }

      if (payload.balance !== undefined) {
        $('balance').textContent = fmt(payload.balance);
      }

      if (payload.found && payload.invoice) {
        prependInvoice(payload.invoice);
        lastCheckTime = Math.max(lastCheckTime, +payload.invoice.create_time || lastCheckTime);
        setStatus('Đã nhận ' + fmt(payload.invoice.amount), 'Số dư và lịch sử nạp tiền đã được cập nhật.', 'success');
      } else {
        setStatus('Đang chờ giao dịch', 'Lần kiểm tra gần nhất ' + formatTime(Math.floor(Date.now() / 1000)) + '.', 'waiting');
      }
    } catch (error) {
      setStatus('Kết nối đối soát gián đoạn', error.message || 'Không kiểm tra được giao dịch.', 'error');
    } finally {
      polling = false;
    }
  }

  setTimeout(pollRecharge, 800);
  setInterval(pollRecharge, pollIntervalMs);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollRecharge();
  });
})();
</script>
@endsection
