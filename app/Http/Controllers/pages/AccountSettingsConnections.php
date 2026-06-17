<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountSettingsConnections extends Controller
{
  public function index()
  {
    $stats = [
      'acb' => 0,
      'vcb' => 0,
      'vpbank' => 0,
      'techcombank' => 0,
      'mbbank' => 0,
      'acb_token' => 0,
      'vcb_token' => 0,
      'vpbank_token' => 0,
      'techcombank_token' => 0,
      'mbbank_token' => 0,
    ];

    if (Auth::check()) {
      $userId = Auth::id();
      $stats['acb'] = DB::table('account_acb')->where('user_id', $userId)->count();
      $stats['vcb'] = DB::table('account_vietcombank')->where('user_id', $userId)->count();
      $stats['vpbank'] = DB::table('account_vpbank')->where('user_id', $userId)->count();
      $stats['techcombank'] = DB::table('account_techcombank')->where('user_id', $userId)->count();
      $stats['mbbank'] = DB::table('account_mbbank')->where('user_id', $userId)->count();
      $stats['acb_token'] = DB::table('account_acb')->where('user_id', $userId)->whereNotNull('token')->where('token', '<>', '')->count();
      $stats['vcb_token'] = DB::table('account_vietcombank')->where('user_id', $userId)->whereNotNull('token')->where('token', '<>', '')->count();
      $stats['vpbank_token'] = DB::table('account_vpbank')->where('user_id', $userId)->whereNotNull('token')->where('token', '<>', '')->count();
      $stats['techcombank_token'] = DB::table('account_techcombank')->where('user_id', $userId)->whereNotNull('token')->where('token', '<>', '')->count();
      $stats['mbbank_token'] = DB::table('account_mbbank')->where('user_id', $userId)->whereNotNull('token')->where('token', '<>', '')->count();
    }

    return view('content.pages.pages-account-settings-connections', compact('stats'));
  }
}
