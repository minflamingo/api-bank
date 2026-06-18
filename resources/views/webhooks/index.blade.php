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
            @forelse($endpoints as $endpoint)
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
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-5">Chưa có webhook endpoint.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($endpoints->hasPages())
        <div class="card-footer">{{ $endpoints->links() }}</div>
      @endif
    </div>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2">
    <div>
      <div class="text-muted small mb-1">Cài đặt riêng tài khoản này</div>
      <h5 class="mb-1">Liên kết Quanly.3W</h5>
      <div class="text-muted">APIBank sẽ đẩy giao dịch mới sang receiver Quanly theo cấu hình lưu trong MySQL của user hiện tại.</div>
    </div>
    <div class="d-flex flex-wrap gap-1 align-items-start">
      <span class="badge bg-label-{{ $quanlySetting ? 'info' : 'secondary' }}">
        {{ $quanlySetting ? 'Đã lưu MySQL' : 'Chưa lưu MySQL' }}
      </span>
      <span class="badge bg-label-{{ !empty($quanly['enabled']) ? 'success' : 'danger' }}">
        {{ !empty($quanly['enabled']) ? 'Đang bật' : 'Đang tắt' }}
      </span>
      <span class="badge bg-label-{{ !empty($quanlyLink) ? 'primary' : 'secondary' }}">
        {{ !empty($quanlyLink) ? 'Đã liên kết user' : 'Chưa link user' }}
      </span>
    </div>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-xl-6">
        <form method="POST" action="{{ route('client.webhooks.quanly.update') }}" class="d-grid gap-3">
          @csrf
          @method('PUT')
          <div>
            <label class="form-label">Receiver Quanly</label>
            <input class="form-control font-monospace" name="quanly_url" value="{{ $quanlyUrl }}" placeholder="https://quanly.3w.com.vn/webhooks/apibank/transactions">
          </div>
          <div>
            <label class="form-label">Secret HMAC</label>
            <input class="form-control font-monospace" name="quanly_secret" value="{{ $quanlySecret }}" placeholder="whsec_...">
            <div class="form-text">Secret này phải trùng với cấu hình verify webhook bên Quanly.</div>
          </div>
          <div>
            <label class="form-label d-block">Event gửi sang Quanly</label>
            <div class="row g-2">
              @foreach($events as $event)
                <div class="col-md-6">
                  <label class="form-check border rounded p-2 h-100">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="quanly_events[]" value="{{ $event }}" @checked(in_array($event, $quanlyEvents ?: [], true))>
                    <span class="form-check-label font-monospace small">{{ $event }}</span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>
          <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
            <label class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="quanly_is_active" value="1" @checked($quanlyActive)>
              <span class="form-check-label">Bật liên kết Quanly cho user này</span>
            </label>
            <button class="btn btn-primary" type="submit">
              <i class="bx bx-save me-1"></i>Lưu cấu hình Quanly
            </button>
          </div>
        </form>
      </div>

      <div class="col-xl-6">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small mb-1">Tài khoản Quanly đã link</div>
              @if($quanlyLink)
                <div class="fw-semibold">Quanly user #{{ (int) $quanlyLink->quanly_user_id }}</div>
                <div class="small text-muted">Tenant #{{ (int) ($quanlyLink->quanly_tenant_id ?? 0) }}</div>
                <div class="small text-muted">Liên kết lúc {{ $formatTime($quanlyLink->linked_at ?? $quanlyLink->created_at ?? null) }}</div>
              @else
                <div class="text-muted">Hãy bấm “Kết nối APIBank” từ Quanly để tạo link user.</div>
              @endif
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small">Delivery user này</div>
              <div class="fw-semibold">{{ number_format((int) ($quanly['deliveries_delivered'] ?? 0)) }}/{{ number_format((int) ($quanly['deliveries_total'] ?? 0)) }} thành công</div>
              <div class="small text-muted">Pending {{ number_format((int) ($quanly['deliveries_pending'] ?? 0)) }} · Lỗi {{ number_format((int) ($quanly['deliveries_failed'] ?? 0)) }}</div>
            </div>
          </div>
          <div class="col-12">
            <div class="bg-label-secondary rounded p-3 h-100">
              <div class="text-muted small mb-1">Webhook Quanly gần nhất</div>
              @if($quanlyLast)
                <div class="d-flex flex-wrap gap-2 align-items-center">
                  <span class="badge bg-label-info font-monospace">{{ $quanlyLast->event }}</span>
                  <span class="small text-muted">HTTP {{ $quanlyLast->response_status ?: '—' }}</span>
                  <span class="small text-muted">{{ $formatTime($quanlyLast->delivered_at ?: $quanlyLast->failed_at ?: $quanlyLast->created_at) }}</span>
                </div>
                @if($quanlyLast->last_error)
                  <div class="small text-danger mt-1">{{ $quanlyLast->last_error }}</div>
                @endif
              @else
                <div class="text-muted">Chưa có webhook Quanly nào cho user này.</div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
