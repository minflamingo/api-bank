@php
  $party = is_array($party ?? null) ? $party : [];
  $label = trim((string)($party['label'] ?? 'Người gửi/nhận'));
  $name = trim((string)($party['name'] ?? ''));
  $account = trim((string)($party['account'] ?? ''));
  $bank = trim((string)($party['bank'] ?? ''));
@endphp

<span class="badge bg-label-secondary">{{ $label }}</span>
@if($name !== '')
  <div class="small fw-semibold mt-1">{{ $name }}</div>
@endif
@if($account !== '' || $bank !== '')
  <div class="small text-muted">
    @if($account !== '') STK {{ $account }} @endif
    @if($account !== '' && $bank !== '') · @endif
    @if($bank !== '') {{ $bank }} @endif
  </div>
@endif
@if($name === '' && $account === '' && $bank === '')
  <div class="small text-muted mt-1">Không xác định</div>
@endif
