<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountSettingsNotifications extends Controller
{
  public function index()
  {
    $logs = collect();

    if (Auth::check()) {
      $logs = DB::table('xlogs')
        ->where('user', Auth::id())
        ->orderByDesc('xkey')
        ->limit(12)
        ->get();
    }

    return view('content.pages.pages-account-settings-notifications', compact('logs'));
  }
}
