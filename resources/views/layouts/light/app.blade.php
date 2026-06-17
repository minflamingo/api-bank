<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'API Bank')</title>
  <style>
    :root{color-scheme:light;--b:#dde3ea;--t:#17212b;--m:#607080;--p:#0b6bcb;--g:#087f5b;--r:#c92a2a;--bg:#f6f8fb;--mw:@yield('max_width','980px')}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--t);font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}a{color:var(--p);text-decoration:none}
    .nav{position:sticky;top:0;z-index:5;background:rgba(255,255,255,.96);border-bottom:1px solid var(--b);backdrop-filter:saturate(140%) blur(8px)}
    .navin,.w{max-width:var(--mw);margin:auto}.navin{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 18px}.brand{font-weight:800;color:var(--t)}
    .links,.actions,.line,.top,.row{display:flex;gap:8px;align-items:center}.links{flex-wrap:wrap}.nav a,.btn,button{font:inherit;border-radius:8px}.nav a,.btn,button{border:1px solid transparent;background:transparent;color:var(--t);padding:8px 10px}.nav a.on,.nav a:hover{background:#eef6ff;color:var(--p);border-color:#d7ebff}
    .w{padding:18px}.top{justify-content:space-between;margin-bottom:14px}.row{align-items:stretch}.col{flex:1;min-width:0}h1{font-size:22px;margin:0}.muted{color:var(--m)}
    .box{background:#fff;border:1px solid var(--b);border-radius:8px;padding:14px}.box+.box{margin-top:12px}.bal{font-size:28px;font-weight:800;color:var(--p);margin-top:4px}
    .label{font-size:13px;color:var(--m);margin-bottom:3px}.value{font-weight:700;word-break:break-word}.red{color:var(--r)}.green{color:var(--g)}
    button,.btn{border-color:var(--b);background:#fff;cursor:pointer}.primary{border-color:var(--p);background:var(--p);color:#fff}.btn:disabled,button:disabled{opacity:.6;cursor:wait}
    input,textarea,select{font:inherit;width:100%;border:1px solid var(--b);border-radius:8px;padding:10px;background:#fff;color:var(--t)}.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}.chips button{padding:7px 9px}
    .status{margin-top:10px;padding:10px 11px;border-radius:8px;border:1px solid var(--b);background:#eef6ff}.ok{background:#ebfbee;border-color:#b2f2bb}.bad{background:#fff5f5;border-color:#ffc9c9}
    table{width:100%;border-collapse:collapse}th,td{padding:9px 6px;border-bottom:1px solid var(--b);text-align:left;vertical-align:top}th{font-size:13px;color:var(--m);font-weight:600}.nowrap{white-space:nowrap}.hide{display:none}
    @stack('styles')
    @media(max-width:760px){.navin{display:block;padding:10px 12px}.links{margin-top:8px}.w{padding:12px}.top,.row{display:block}.top .actions{margin-top:10px}.col+.col{margin-top:12px}.bal{font-size:24px}.scroll{overflow:auto}table{min-width:660px}}
  </style>
</head>
<body>
@php
  $isSuperAdmin = (int) (Auth::user()->role ?? 0) === 1;
  $nav = [
    ['label' => 'Trang chủ', 'route' => 'v2', 'active' => request()->routeIs('v2')],
    ['label' => 'Ngân hàng', 'route' => 'bank.accounts.index', 'active' => request()->routeIs('bank.accounts.*')],
    ['label' => 'Nạp tiền', 'route' => 'client.payin', 'active' => request()->routeIs('client.payin') || request()->routeIs('client.recharge')],
    ['label' => 'Gia hạn', 'route' => 'client.upgrade', 'active' => request()->routeIs('client.upgrade')],
  ];
@endphp
<nav class="nav">
  <div class="navin">
    <a class="brand" href="{{ route('v2') }}">API Bank</a>
    <div class="links">
      @foreach($nav as $item)
        <a class="{{ $item['active'] ? 'on' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
      @endforeach
      @if($isSuperAdmin)
        <a class="{{ request()->routeIs('admin.recharge-settings.*') ? 'on' : '' }}" href="{{ route('admin.recharge-settings.edit') }}">Cấu hình nạp</a>
      @endif
      <a href="{{ route('logout') }}">Đăng xuất</a>
    </div>
  </div>
</nav>
<main class="w">
  @yield('content')
</main>
@stack('scripts')
</body>
</html>
