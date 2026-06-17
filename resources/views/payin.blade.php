@extends('layouts/contentNavbarLayout')

@section('title', 'Nạp tiền')

@section('page-style')
<style>
  .payin-page {
    --payin-border: rgba(67, 89, 113, .14);
    --payin-muted: #697a8d;
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
    box-shadow: 0 .25rem .85rem rgba(67, 89, 113, .06);
  }

  .payin-page .copy-value,
  .payin-page .history-description {
    overflow-wrap: anywhere;
  }

  .payin-page .recharge-code {
    color: #d92323;
    font-weight: 700;
  }

  .payin-page .balance-value {
    font-size: 1.55rem;
    font-weight: 700;
    letter-spacing: 0;
  }

  .payin-page .quick-amounts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .5rem;
  }

  .payin-page .payin-qr {
    display: block;
    width: 260px;
    max-width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: contain;
    margin: .85rem auto 0;
    border: 1px solid var(--payin-border);
    border-radius: .5rem;
    background: #fff;
  }

  @media (max-width: 575.98px) {
    .payin-page .quick-amounts {
      grid-template-columns: repeat(2, minmax(0, 1fr));
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
      <div class="text-muted">Chuyển khoản {{ $bankLabel }} đúng nội dung để hệ thống cộng tiền tự động.</div>
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

  <div class="row g-3 mb-4">
    <div class="col-lg-4">
      <div class="card soft-card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Số dư hiện tại</div>
          <div class="balance-value mb-3" id="balance">{{ number_format($balance) }} đ</div>
          <button class="btn btn-primary btn-touch w-100" id="sync" type="button">
            <i class="bx bx-refresh"></i> Tôi đã chuyển khoản
          </button>
          <div class="alert d-none mt-3 mb-0" id="status" role="alert"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card soft-card h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">Số tài khoản {{ $bankLabel }}</div>
          <div class="input-group mb-3">
            <div class="form-control copy-value" id="acc">{{ $bank->accountNumber }}</div>
            <button class="btn btn-outline-secondary btn-touch" type="button" data-copy="acc">
              <i class="bx bx-copy"></i> Copy
            </button>
          </div>

          <div class="text-muted small mb-1">Chủ tài khoản</div>
          <div class="fw-semibold mb-3">{{ $bank->accountName }}</div>

          <div class="text-muted small mb-1">Nội dung chuyển khoản</div>
          <div class="input-group">
            <div class="form-control copy-value recharge-code" id="code">{{ $addInfo }}</div>
            <button class="btn btn-outline-secondary btn-touch" type="button" data-copy="code">
              <i class="bx bx-copy"></i> Copy
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card soft-card h-100">
        <div class="card-body">
          <label class="form-label" for="amount">Số tiền</label>
          <input class="form-control" id="amount" type="number" min="{{ $minAmount }}" step="1000" inputmode="numeric" placeholder="{{ number_format($minAmount) }}">
          <div class="quick-amounts mt-3">
            @foreach($quickAmounts as $amount)
              <button class="btn btn-outline-primary btn-sm btn-touch" type="button" data-amount="{{ $amount }}">{{ number_format($amount) }}</button>
            @endforeach
          </div>
          <img class="payin-qr" id="qr" src="{{ $qrUrl }}" width="260" height="260" loading="eager" decoding="async" alt="QR {{ $bankLabel }}">
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
        <tbody>
        @forelse($invoices as $row)
          <tr>
            <td>{{ $row->payment_method }}</td>
            <td>{{ $row->trans_id }}</td>
            <td class="text-success fw-semibold">{{ number_format($row->amount) }} đ</td>
            <td>{{ date('H:i d-m-Y', $row->create_time) }}</td>
            <td class="history-description">{{ $row->description }}</td>
          </tr>
        @empty
          <tr>
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
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const base = @json($qrUrl);
  const syncUrl = @json(route('client.recharge.sync_acb'));
  const bankLabel = @json($bankLabel);
  const fmt = n => new Intl.NumberFormat('vi-VN').format(+n || 0) + ' đ';
  const status = $('status');

  function say(text, ok = false) {
    status.className = 'alert mt-3 mb-0 ' + (text ? (ok ? 'alert-success' : 'alert-danger') : 'd-none');
    status.textContent = text || '';
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
      say('Đã copy: ' + text, true);
    });
  });

  document.querySelectorAll('[data-amount]').forEach(button => {
    button.addEventListener('click', () => {
      $('amount').value = button.dataset.amount;
      updateQr();
    });
  });

  $('amount').addEventListener('input', updateQr);
  $('sync').addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    button.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Đang kiểm tra';
    say('Đang quét ' + bankLabel + '...', true);

    try {
      const response = await fetch(syncUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        body: '{}'
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Không kiểm tra được');
      }
      if (payload.balance !== undefined) {
        $('balance').textContent = fmt(payload.balance);
      }
      if (payload.found && payload.invoice) {
        say('Đã nhận ' + fmt(payload.invoice.amount) + '. Đang tải lại...', true);
        setTimeout(() => location.reload(), 900);
      } else {
        say(payload.message || 'Chưa thấy giao dịch mới.', true);
      }
    } catch (error) {
      say(error.message || 'Không kiểm tra được giao dịch');
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bx bx-refresh"></i> Tôi đã chuyển khoản';
    }
  });
})();
</script>
@endsection
