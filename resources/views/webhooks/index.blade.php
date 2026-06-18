@extends('layouts/contentNavbarLayout')

@section('title', 'Webhook')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
  <div>
    <div class="text-muted small mb-1">Tích hợp realtime</div>
    <h4 class="mb-1">Webhook</h4>
    <div class="text-muted">Nhận sự kiện giao dịch, số dư và trạng thái phiên từ APIBank.</div>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@php
  $quanly = $quanlyIntegration ?? [];
  $quanlyLast = $quanly['last_delivery'] ?? null;
  $quanlyLink = $quanly['link'] ?? null;
  $quanlySetting = $quanly['setting'] ?? null;
  $defaultQuanlyEvents = ['transaction.created', 'transaction.updated', 'balance.updated', 'account.session_expired'];
  $quanlyEvents = old('quanly_events', !empty($quanly['events']) ? $quanly['events'] : $defaultQuanlyEvents);
  $quanlyUrl = old('quanly_url', $quanly['url'] ?? '');
  $quanlySecret = old('quanly_secret', ($quanly['secret'] ?? '') ?: $defaultQuanlySecret);
  $quanlyActive = (bool) old('quanly_is_active', (bool) ($quanlySetting?->is_active ?? false));
  $formatTime = function ($value) {
      if (empty($value)) return '—';
      try { return \Carbon\Carbon::parse($value, 'Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y'); }
      catch (\Throwable $e) { return (string) $value; }
  };
@endphp

<div class="row g-4">
  <div class="col-xl-5">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Thêm endpoint</h5>
      </div>
      <form method="POST" action="{{ route('client.webhooks.store') }}">
        @csrf
        <div class="card-body d-grid gap-3">
          <div>
            <label class="form-label">Tên endpoint</label>
            <input class="form-control" name="name" value="{{ old('name', 'Webhook chính') }}" maxlength="120" required>
          </div>
          <div>
            <label class="form-label">URL nhận webhook</label>
            <input class="form-control" name="url" value="{{ old('url') }}" placeholder="https://example.com/webhooks/apibank" required>
          </div>
          <div>
            <label class="form-label">Secret ký HMAC</label>
            <input class="form-control font-monospace" name="secret" value="{{ old('secret', $defaultSecret) }}" required>
          </div>
          <div>
            <label class="form-label d-block">Event</label>
            <div class="row g-2">
              @foreach($events as $event)
                <div class="col-md-6">
                  <label class="form-check border rounded p-2 h-100">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', ['transaction.created', 'transaction.updated', 'balance.updated']), true))>
                    <span class="form-check-label font-monospace small">{{ $event }}</span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>
          <label class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Đang bật</span>
          </label>
        </div>
        <div class="card-footer text-end">
          <button class="btn btn-primary" type="submit"><i class="bx bx-plus-circle me-1"></i>Thêm webhook</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="card">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Endpoint</th>
              <th>Event</th>
              <th>Trạng thái</th>
              <th class="text-end">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr class="table-light">
              <td>
                <div class="fw-semibold">Quanly.3W nội bộ</div>
                <div class="text-muted small text-break">{{ $quanlyUrl ?: 'Chưa nhập receiver Quanly' }}</div>
                <div class="font-monospace small text-muted text-break">{{ !empty($quanly['secret_configured']) ? $quanlySecret : 'Chưa cấu hình secret HMAC' }}</div>
                @if($quanlyLink)
                  <div class="small text-muted mt-1">Quanly user #{{ (int) $quanlyLink->quanly_user_id }} · tenant #{{ (int) ($quanlyLink->quanly_tenant_id ?? 0) }}</div>
                @else
                  <div class="small text-warning mt-1">Chưa link user Quanly</div>
                @endif
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  @forelse($quanlyEvents ?: [] as $event)
                    <span class="badge bg-label-primary font-monospace">{{ $event }}</span>
                  @empty
                    <span class="text-muted small">Chưa chọn event.</span>
                  @endforelse
                </div>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1 mb-1">
                  <span class="badge bg-label-{{ $quanlySetting ? 'info' : 'secondary' }}">{{ $quanlySetting ? 'MySQL' : 'Chưa lưu' }}</span>
                  <span class="badge bg-label-{{ !empty($quanly['enabled']) ? 'success' : 'danger' }}">{{ !empty($quanly['enabled']) ? 'Bật' : 'Tắt' }}</span>
                  <span class="badge bg-label-{{ !empty($quanlyLink) ? 'primary' : 'secondary' }}">{{ !empty($quanlyLink) ? 'Đã link' : 'Chưa link' }}</span>
                </div>
                <div class="small text-muted">
                  Delivery {{ number_format((int) ($quanly['deliveries_delivered'] ?? 0)) }}/{{ number_format((int) ($quanly['deliveries_total'] ?? 0)) }}
                  · Pending {{ number_format((int) ($quanly['deliveries_pending'] ?? 0)) }}
                  · Lỗi {{ number_format((int) ($quanly['deliveries_failed'] ?? 0)) }}
                </div>
                @if($quanlyLast)
                  <div class="small text-muted mt-1">
                    Gần nhất: <span class="font-monospace">{{ $quanlyLast->event }}</span>
                    · HTTP {{ $quanlyLast->response_status ?: '—' }}
                    · {{ $formatTime($quanlyLast->delivered_at ?: $quanlyLast->failed_at ?: $quanlyLast->created_at) }}
                  </div>
                  @if($quanlyLast->last_error)
                    <div class="small text-danger mt-1">{{ $quanlyLast->last_error }}</div>
                  @endif
                @endif
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#quanlyWebhookEdit">
                  <i class="bx bx-edit"></i>
                </button>
              </td>
            </tr>
            <tr class="collapse" id="quanlyWebhookEdit">
              <td colspan="4">
                <form class="row g-3" method="POST" action="{{ route('client.webhooks.quanly.update') }}">
                  @csrf
                  @method('PUT')
                  <div class="col-md-8">
                    <label class="form-label">Receiver Quanly</label>
                    <input class="form-control font-monospace" name="quanly_url" value="{{ $quanlyUrl }}" placeholder="https://quanly.3w.com.vn/webhooks/apibank/transactions">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Secret HMAC</label>
                    <input class="form-control font-monospace" name="quanly_secret" value="{{ $quanlySecret }}" placeholder="whsec_...">
                  </div>
                  <div class="col-12">
                    <div class="row g-2">
                      @foreach($events as $event)
                        <div class="col-md-4">
                          <label class="form-check border rounded p-2 h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="quanly_events[]" value="{{ $event }}" @checked(in_array($event, $quanlyEvents ?: [], true))>
                            <span class="form-check-label font-monospace small">{{ $event }}</span>
                          </label>
                        </div>
                      @endforeach
                    </div>
                  </div>
                  <div class="col-12 d-flex justify-content-between">
                    <label class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" name="quanly_is_active" value="1" @checked($quanlyActive)>
                      <span class="form-check-label">Bật liên kết Quanly cho user này</span>
                    </label>
                    <button class="btn btn-primary" type="submit">Lưu Quanly</button>
                  </div>
                </form>
              </td>
            </tr>

            @foreach($endpoints as $endpoint)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $endpoint->name }}</div>
                  <div class="text-muted small text-break">{{ $endpoint->url }}</div>
                  <div class="font-monospace small text-muted text-break">{{ $endpoint->secret }}</div>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    @foreach(($endpoint->events ?: []) as $event)
                      <span class="badge bg-label-primary font-monospace">{{ $event }}</span>
                    @endforeach
                  </div>
                </td>
                <td>
                  <span class="badge bg-label-{{ $endpoint->is_active ? 'success' : 'secondary' }}">{{ $endpoint->is_active ? 'Bật' : 'Tắt' }}</span>
                  @if($endpoint->last_error)
                    <div class="small text-danger mt-1">{{ $endpoint->last_error }}</div>
                  @endif
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#webhookEdit{{ $endpoint->id }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  <form class="d-inline" method="POST" action="{{ route('client.webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Xoá webhook này?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bx bx-trash"></i></button>
                  </form>
                </td>
              </tr>
              <tr class="collapse" id="webhookEdit{{ $endpoint->id }}">
                <td colspan="4">
                  <form class="row g-3" method="POST" action="{{ route('client.webhooks.update', $endpoint) }}">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4">
                      <label class="form-label">Tên</label>
                      <input class="form-control" name="name" value="{{ $endpoint->name }}" required>
                    </div>
                    <div class="col-md-8">
                      <label class="form-label">URL</label>
                      <input class="form-control" name="url" value="{{ $endpoint->url }}" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Secret</label>
                      <input class="form-control font-monospace" name="secret" value="{{ $endpoint->secret }}" required>
                    </div>
                    <div class="col-12">
                      <div class="row g-2">
                        @foreach($events as $event)
                          <div class="col-md-4">
                            <label class="form-check border rounded p-2 h-100">
                              <input class="form-check-input ms-0 me-2" type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, $endpoint->events ?: [], true))>
                              <span class="form-check-label font-monospace small">{{ $event }}</span>
                            </label>
                          </div>
                        @endforeach
                      </div>
                    </div>
                    <div class="col-12 d-flex justify-content-between">
                      <label class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($endpoint->is_active)>
                        <span class="form-check-label">Đang bật</span>
                      </label>
                      <button class="btn btn-primary" type="submit">Lưu</button>
                    </div>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($endpoints->hasPages())
        <div class="card-footer">{{ $endpoints->links() }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
