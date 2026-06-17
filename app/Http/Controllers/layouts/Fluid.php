<?php

namespace App\Http\Controllers\layouts;

use App\Http\Controllers\Controller;
use App\Support\ApiPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Fluid extends Controller
{
  public function index()
  {
    $user = Auth::user();

    $stats = [
      'bank_total' => 0,
      'bank_acb' => 0,
      'bank_vcb' => 0,
      'bank_vpbank' => 0,
      'bank_techcombank' => 0,
      'bank_mbbank' => 0,
      'has_token' => 0,
      'invoices' => 0,
      'paid_total' => 0,
    ];

    $latestInvoices = collect();

    if ($user) {
      $acb = DB::table('account_acb')->where('user_id', $user->id);
      $vcb = DB::table('account_vietcombank')->where('user_id', $user->id);
      $vpbank = DB::table('account_vpbank')->where('user_id', $user->id);
      $techcombank = DB::table('account_techcombank')->where('user_id', $user->id);
      $mbbank = DB::table('account_mbbank')->where('user_id', $user->id);

      $stats['bank_acb'] = (clone $acb)->count();
      $stats['bank_vcb'] = (clone $vcb)->count();
      $stats['bank_vpbank'] = (clone $vpbank)->count();
      $stats['bank_techcombank'] = (clone $techcombank)->count();
      $stats['bank_mbbank'] = (clone $mbbank)->count();
      $stats['bank_total'] = $stats['bank_acb'] + $stats['bank_vcb'] + $stats['bank_vpbank'] + $stats['bank_techcombank'] + $stats['bank_mbbank'];
      $stats['has_token'] = (clone $acb)->whereNotNull('token')->where('token', '<>', '')->count()
        + (clone $vcb)->whereNotNull('token')->where('token', '<>', '')->count()
        + (clone $vpbank)->whereNotNull('token')->where('token', '<>', '')->count()
        + (clone $techcombank)->whereNotNull('token')->where('token', '<>', '')->count()
        + (clone $mbbank)->whereNotNull('token')->where('token', '<>', '')->count();
      $stats['invoices'] = DB::table('invoices')->where('user_id', $user->id)->count();
      $stats['paid_total'] = (int) DB::table('invoices')
        ->where('user_id', $user->id)
        ->where('status', 1)
        ->sum('amount');

      $latestInvoices = DB::table('invoices')
        ->where('user_id', $user->id)
        ->orderByDesc('create_time')
        ->limit(5)
        ->get();
    }

    $monthlyPrice = (int) config('services.api_package.monthly_price', 20000);
    $accountLimit = ApiPackage::userLimit($user);

    return view('fluid', compact('user', 'stats', 'latestInvoices', 'monthlyPrice', 'accountLimit'));
  }
}
