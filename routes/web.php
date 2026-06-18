<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Controllers
use App\Http\Controllers\layouts\WithoutMenu;
use App\Http\Controllers\layouts\WithoutNavbar;
use App\Http\Controllers\layouts\Fluid;
use App\Http\Controllers\layouts\Container;
use App\Http\Controllers\layouts\Blank;
use App\Http\Controllers\pages\AccountSettingsAccount;
use App\Http\Controllers\pages\AccountSettingsNotifications;
use App\Http\Controllers\pages\AccountSettingsConnections;
use App\Http\Controllers\pages\MiscError;
use App\Http\Controllers\pages\MiscUnderMaintenance;
use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\authentications\ForgotPasswordBasic;
use App\Http\Controllers\authentications\SocialAuthController;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayinController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\BankAccountsController;
use App\Http\Controllers\CachedBankApiController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\QuanlyAccountLinkController;
use App\Http\Controllers\WebhookEndpointController;
// ----------------------------------------------------------------------
// Các route public (khách)
// ----------------------------------------------------------------------

// (Mới) Lấy số dư VCB
Route::get('/v1/vcb/balance/{token}', [CachedBankApiController::class, 'vcbBalance'])->name('api.vcb.balance');
// (Mới) Lấy số dư ACB
Route::get('/v1/acb/balance/{token}', [CachedBankApiController::class, 'acbBalance'])->name('payment.acb.balance');
// (Mới) Lấy số dư VPBank
Route::get('/v1/vpbank/balance/{token}', [CachedBankApiController::class, 'vpbankBalance'])->name('api.vpbank.balance');
// (Mới) Lấy số dư Techcombank
Route::get('/v1/techcombank/balance/{token}', [CachedBankApiController::class, 'techcombankBalance'])->name('api.techcombank.balance');
// (Mới) Lấy số dư MBBank
Route::get('/v1/mbbank/balance/{token}', [CachedBankApiController::class, 'mbbankBalance'])->name('api.mbbank.balance');

// **Route mới**: Lấy giao dịch VCB (api)
Route::get('/v1/vcb/transhistory/{token}', [CachedBankApiController::class, 'vcbTransHistory'])->name('v1.vcb.transhistory');
// **Route mới**: Lấy giao dịch ACB (api)
Route::get('/v1/acb/transhistory/{token}', [CachedBankApiController::class, 'acbTransHistory'])->name('v1.acb.transhistory');
// **Route mới**: Lấy giao dịch VPBank (api)
Route::get('/v1/vpbank/transhistory/{token}', [CachedBankApiController::class, 'vpbankTransHistory'])->name('v1.vpbank.transhistory');
// **Route mới**: Lấy giao dịch Techcombank (api)
Route::get('/v1/techcombank/transhistory/{token}', [CachedBankApiController::class, 'techcombankTransHistory'])->name('v1.techcombank.transhistory');
// **Route mới**: Lấy giao dịch MBBank (api)
Route::get('/v1/mbbank/transhistory/{token}', [CachedBankApiController::class, 'mbbankTransHistory'])->name('v1.mbbank.transhistory');

// API Bank v2.0 aliases: current app.pro.vn API after moving to apibank.com.vn.
Route::get('/v2/vcb/balance/{token}', [CachedBankApiController::class, 'vcbBalance'])->name('v2.vcb.balance');
Route::get('/v2/acb/balance/{token}', [CachedBankApiController::class, 'acbBalance'])->name('v2.acb.balance');
Route::get('/v2/vpbank/balance/{token}', [CachedBankApiController::class, 'vpbankBalance'])->name('v2.vpbank.balance');
Route::get('/v2/techcombank/balance/{token}', [CachedBankApiController::class, 'techcombankBalance'])->name('v2.techcombank.balance');
Route::get('/v2/mbbank/balance/{token}', [CachedBankApiController::class, 'mbbankBalance'])->name('v2.mbbank.balance');
Route::get('/v2/vcb/transhistory/{token}', [CachedBankApiController::class, 'vcbTransHistory'])->name('v2.vcb.transhistory');
Route::get('/v2/acb/transhistory/{token}', [CachedBankApiController::class, 'acbTransHistory'])->name('v2.acb.transhistory');
Route::get('/v2/vpbank/transhistory/{token}', [CachedBankApiController::class, 'vpbankTransHistory'])->name('v2.vpbank.transhistory');
Route::get('/v2/techcombank/transhistory/{token}', [CachedBankApiController::class, 'techcombankTransHistory'])->name('v2.techcombank.transhistory');
Route::get('/v2/mbbank/transhistory/{token}', [CachedBankApiController::class, 'mbbankTransHistory'])->name('v2.mbbank.transhistory');

Route::get('/api/historyvietcombank/{token}', [CachedBankApiController::class, 'vcbTransHistory'])->name('api.vcb.history');
Route::get('/api/historyvietcombankbalance/{token}', [CachedBankApiController::class, 'vcbBalance'])->name('api.vcb.balance.legacy');
Route::get('/api/historyacb/{token}', [CachedBankApiController::class, 'acbTransHistory'])->name('api.acb.history');
Route::get('/api/historyacbbalance/{token}', [CachedBankApiController::class, 'acbBalance'])->name('api.acb.balance.legacy');
Route::get('/api/historyvpbank/{token}', [CachedBankApiController::class, 'vpbankTransHistory'])->name('api.vpbank.history');
Route::get('/api/historyvpbankbalance/{token}', [CachedBankApiController::class, 'vpbankBalance'])->name('api.vpbank.balance.legacy');
Route::get('/api/historytechcombank/{token}', [CachedBankApiController::class, 'techcombankTransHistory'])->name('api.techcombank.history');
Route::get('/api/historytechcombankbalance/{token}', [CachedBankApiController::class, 'techcombankBalance'])->name('api.techcombank.balance.legacy');
Route::get('/api/historymbbank/{token}', [CachedBankApiController::class, 'mbbankTransHistory'])->name('api.mbbank.history');
Route::get('/api/historymbbankbalance/{token}', [CachedBankApiController::class, 'mbbankBalance'])->name('api.mbbank.balance.legacy');

Route::get('/cron/acb', [CronController::class, 'cronNapACB'])->name('cron.acb');
Route::get('/cron/vcb', [CronController::class, 'cronNapVCB'])->name('cron.vcb');
Route::get('/cron/vpbank', [CronController::class, 'cronNapVPBANK'])->name('cron.vpbank');
Route::get('/cron/techcombank', [CronController::class, 'cronNapTECHCOMBANK'])->name('cron.techcombank');
Route::get('/cron/techcombank-refresh', [CronController::class, 'cronRefreshTECHCOMBANK'])->name('cron.techcombank.refresh');
Route::get('/cron/mbbank', [CronController::class, 'cronNapMBBANK'])->name('cron.mbbank');
// Route AJAX thay cho file imgvietqr.php
Route::get('/ajaxs/client/imgvietqr', [PayinController::class, 'imgVietQr']);

// Route AJAX thay cho file checknaptien.php
Route::get('/ajaxs/client/checknaptien', [PayinController::class, 'checkNapTien']);


// Tắt login/register mặc định ...
Auth::routes([
    'verify'   => true,
    'login'    => false,
    'logout' => false,
    'register' => false,
    'reset'    => false,
]);

// Alias login => ...
Route::get('/login', [LoginBasic::class, 'index'])->name('login');

// Nhóm dành cho khách
Route::middleware('guest')->group(function () {
    // Login
    Route::get('/auth/login-basic', [LoginBasic::class, 'index'])
         ->name('auth-login-basic');
    Route::post('/auth/login-basic', [LoginBasic::class, 'login'])
         ->name('auth-login-basic-post');

    Route::get('/auth/register-basic', [RegisterBasic::class, 'index'])
         ->name('auth-register-basic');
    Route::post('/auth/register-basic', [RegisterBasic::class, 'store'])
         ->name('auth-register-basic-post');
});

Route::get('/auth/social/{provider}', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google')
    ->name('auth.social.redirect');

Route::match(['GET', 'POST'], '/auth/social/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google')
    ->name('auth.social.callback');

Route::get('/auth/quanly/connect', [QuanlyAccountLinkController::class, 'connect'])
    ->name('auth.quanly.connect');

// Check role=9 => need-activate ...
Route::view('/auth/need-activate', 'content.authentications.need-activate')
     ->name('need-activate')
     ->middleware(['auth','checkRole:9']);

// Resend verification ...
Route::get('/auth/resend-verification', [\App\Http\Controllers\NeedActivateController::class, 'resendVerification'])
     ->name('resend.verification')
     ->middleware('auth');

Route::post('/admin/impersonate/stop', [SuperAdminController::class, 'stopImpersonating'])
     ->name('admin.impersonate.stop')
     ->middleware('auth');

// ----------------------------------------------------------------------
// Admin routes => middleware(['auth','checkRole:1'])
// ----------------------------------------------------------------------
Route::middleware(['auth','checkRole:1,2,3'])->group(function () {

    // Dashboard
    Route::get('/', [Fluid::class, 'index'])->name('v2');
	
		Route::get('/client/payin', [PayinController::class, 'index'])->name('client.payin');
		Route::get('/client/recharge', [PayinController::class, 'index'])->name('client.recharge');
    Route::post('/client/recharge/sync-acb', [PayinController::class, 'syncAcb'])->name('client.recharge.sync_acb');
		Route::get('/client/upgrade', [PayinController::class, 'upgradeIndex'])->name('client.upgrade');
    Route::post('/client/upgrade/total', [PayinController::class, 'upgradeTotal'])->name('client.upgrade.total');
    Route::post('/client/upgrade', [PayinController::class, 'upgradeStore'])->name('client.upgrade.store');
    Route::get('/client/webhooks', [WebhookEndpointController::class, 'index'])->name('client.webhooks.index');
    Route::post('/client/webhooks', [WebhookEndpointController::class, 'store'])->name('client.webhooks.store');
    Route::put('/client/webhooks/{webhook}', [WebhookEndpointController::class, 'update'])->name('client.webhooks.update');
    Route::delete('/client/webhooks/{webhook}', [WebhookEndpointController::class, 'destroy'])->name('client.webhooks.destroy');

    Route::middleware('checkRole:1')->group(function () {
        Route::get('/admin', [SuperAdminController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/dashboard', [SuperAdminController::class, 'index'])->name('admin.dashboard.index');
        Route::get('/admin/users', [SuperAdminController::class, 'users'])->name('admin.users');
        Route::post('/admin/users/{user}/impersonate', [SuperAdminController::class, 'impersonate'])
            ->where(['user' => '[0-9]+'])
            ->name('admin.users.impersonate');
        Route::get('/admin/sessions', [SuperAdminController::class, 'sessions'])->name('admin.sessions');
        Route::get('/admin/recharges', [SuperAdminController::class, 'recharges'])->name('admin.recharges');
        Route::get('/admin/logs', [SuperAdminController::class, 'logs'])->name('admin.logs');
        Route::get('/admin/bank-accounts', [BankAccountsController::class, 'adminIndex'])->name('admin.bank-accounts.index');
        Route::get('/admin/recharge-settings', [PayinController::class, 'rechargeSettings'])->name('admin.recharge-settings.edit');
        Route::post('/admin/recharge-settings/acb-account', [PayinController::class, 'storeReceiverAcbAccount'])->name('admin.recharge-settings.acb-account.store');
        Route::post('/admin/recharge-settings/vcb-account/otp', [PayinController::class, 'requestReceiverVcbOtp'])->name('admin.recharge-settings.vcb-account.otp');
        Route::post('/admin/recharge-settings/vcb-account', [PayinController::class, 'storeReceiverVcbAccount'])->name('admin.recharge-settings.vcb-account.store');
        Route::post('/admin/recharge-settings/vpbank-account/otp', [PayinController::class, 'requestReceiverVpbankOtp'])->name('admin.recharge-settings.vpbank-account.otp');
        Route::post('/admin/recharge-settings/vpbank-account', [PayinController::class, 'storeReceiverVpbankAccount'])->name('admin.recharge-settings.vpbank-account.store');
        Route::post('/admin/recharge-settings/techcombank-account/confirm', [PayinController::class, 'requestReceiverTechcombankConfirm'])->name('admin.recharge-settings.techcombank-account.confirm');
        Route::post('/admin/recharge-settings/techcombank-account', [PayinController::class, 'storeReceiverTechcombankAccount'])->name('admin.recharge-settings.techcombank-account.store');
        Route::post('/admin/recharge-settings/mbbank-account', [PayinController::class, 'storeReceiverMbbankAccount'])->name('admin.recharge-settings.mbbank-account.store');
        Route::put('/admin/recharge-settings/account/{bank}/{id}', [PayinController::class, 'updateReceiverAccount'])
            ->where(['bank' => 'acb|vcb|vpbank|techcombank|mbbank', 'id' => '[0-9]+'])
            ->name('admin.recharge-settings.account.update');
        Route::delete('/admin/recharge-settings/account/{bank}/{id}', [PayinController::class, 'destroyReceiverAccount'])
            ->where(['bank' => 'acb|vcb|vpbank|techcombank|mbbank', 'id' => '[0-9]+'])
            ->name('admin.recharge-settings.account.destroy');
        Route::put('/admin/recharge-settings', [PayinController::class, 'updateRechargeSettings'])->name('admin.recharge-settings.update');
    });

    // Tài khoản ngân hàng/API
    Route::get('/bank-accounts', [BankAccountsController::class, 'index'])->name('bank.accounts.index');
    Route::get('/bank-accounts/create', [BankAccountsController::class, 'create'])->name('bank.accounts.create');
    Route::post('/bank-accounts', [BankAccountsController::class, 'store'])->name('bank.accounts.store');
    Route::post('/bank-accounts/{bank}/{id}/token', [BankAccountsController::class, 'token'])
         ->where(['bank' => 'acb|vcb|vpbank|techcombank|mbbank', 'id' => '[0-9]+'])
         ->name('bank.accounts.token');
    Route::delete('/bank-accounts/{bank}/{id}', [BankAccountsController::class, 'destroy'])
         ->where(['bank' => 'acb|vcb|vpbank|techcombank|mbbank', 'id' => '[0-9]+'])
         ->name('bank.accounts.destroy');

    // Vietcombank
    Route::get('/payment/vcb', [PaymentController::class, 'vcbIndex'])->name('payment.vcb.index');
    Route::post('/payment/vcb/get-otp', [PaymentController::class, 'vcbGetOtp'])->name('payment.vcb.getOtp');
    Route::post('/payment/vcb/login-otp', [PaymentController::class, 'vcbLoginOTP'])->name('payment.vcb.loginOtp');
    Route::post('/payment/vcb/sendToken', [PaymentController::class, 'vcbSendToken'])->name('payment.vcb.sendToken');
    Route::post('/payment/vcb/remove', [PaymentController::class, 'vcbRemove'])->name('payment.vcb.remove');
    Route::get('/client/viewhisvcb/{account}', [PaymentController::class, 'vcbHistory'])->name('payment.vcb.history');

    // ACB
    Route::get('/payment/acb', [PaymentController::class, 'acbIndex'])->name('payment.acb.index');
    Route::post('/payment/acb/login', [PaymentController::class,'acbLogin'])->name('payment.acb.login');
    Route::post('/payment/acb/sendToken', [PaymentController::class,'acbSendToken'])->name('payment.acb.sendToken');
    Route::post('/payment/acb/remove', [PaymentController::class,'acbRemove'])->name('payment.acb.remove');
    Route::get('/client/viewhisacb/{stk}', [PaymentController::class, 'acbHistory'])->name('payment.acb.history');

    // VPBank
    Route::get('/payment/vpbank', [PaymentController::class, 'vpbankIndex'])->name('payment.vpbank.index');
    Route::post('/payment/vpbank/login', [PaymentController::class, 'vpbankLogin'])->name('payment.vpbank.login');
    Route::post('/payment/vpbank/login-otp', [PaymentController::class, 'vpbankLoginOTP'])->name('payment.vpbank.loginOtp');
    Route::post('/payment/vpbank/sendToken', [PaymentController::class, 'vpbankSendToken'])->name('payment.vpbank.sendToken');
    Route::post('/payment/vpbank/remove', [PaymentController::class, 'vpbankRemove'])->name('payment.vpbank.remove');
    Route::get('/client/viewhisvpbank/{account}', [PaymentController::class, 'vpbankHistory'])->name('payment.vpbank.history');

    // Techcombank
    Route::get('/payment/techcombank', [PaymentController::class, 'techcombankIndex'])->name('payment.techcombank.index');
    Route::post('/payment/techcombank/login', [PaymentController::class, 'techcombankLogin'])->name('payment.techcombank.login');
    Route::post('/payment/techcombank/confirm-login', [PaymentController::class, 'techcombankConfirmLogin'])->name('payment.techcombank.confirmLogin');
    Route::post('/payment/techcombank/sendToken', [PaymentController::class, 'techcombankSendToken'])->name('payment.techcombank.sendToken');
    Route::post('/payment/techcombank/remove', [PaymentController::class, 'techcombankRemove'])->name('payment.techcombank.remove');
    Route::get('/client/viewhistechcombank/{account}', [PaymentController::class, 'techcombankHistory'])->name('payment.techcombank.history');

    // MBBank
    Route::get('/payment/mbbank', [PaymentController::class, 'mbbankIndex'])->name('payment.mbbank.index');
    Route::post('/payment/mbbank/login', [PaymentController::class, 'mbbankLogin'])->name('payment.mbbank.login');
    Route::post('/payment/mbbank/sendToken', [PaymentController::class, 'mbbankSendToken'])->name('payment.mbbank.sendToken');
    Route::post('/payment/mbbank/remove', [PaymentController::class, 'mbbankRemove'])->name('payment.mbbank.remove');
    Route::get('/client/viewhismbbank/{account}', [PaymentController::class, 'mbbankHistory'])->name('payment.mbbank.history');

    // Demo nạp tiền
    Route::post('/payment/demoNap', [PaymentController::class, 'demoNapTien'])->name('payment.demoNap');

    // Legacy template demo routes are kept as redirects so users do not land on unfinished demo pages.
    Route::redirect('/layouts/without-menu', '/')->name('layouts-without-menu');
    Route::redirect('/layouts/without-navbar', '/')->name('layouts-without-navbar');
    Route::redirect('/layouts/fluid', '/')->name('layouts-fluid');
    Route::redirect('/layouts/container', '/')->name('layouts-container');
    Route::redirect('/layouts/blank', '/')->name('layouts-blank');

    // Account settings ...
    Route::get('/pages/account-settings-account', [AccountSettingsAccount::class, 'index'])
         ->name('pages-account-settings-account');
    Route::post('/upload-avatar', [AccountSettingsAccount::class, 'store'])
         ->name('upload-avatar');
    Route::post('/delete-avatar', [AccountSettingsAccount::class, 'destroy'])
         ->name('delete-avatar');
    Route::put('/pages/account-settings-account', [AccountSettingsAccount::class, 'update'])
         ->name('pages-account-settings-account.update');
    Route::get('/pages/account-security', [AccountSettingsAccount::class, 'security'])
         ->name('pages-account-security');
    Route::put('/pages/account-security', [AccountSettingsAccount::class, 'updatePassword'])
         ->name('security.update');
    Route::get('/pages/account-settings-notifications', [AccountSettingsNotifications::class, 'index'])
         ->name('pages-account-settings-notifications');
    Route::get('/pages/account-settings-connections', [AccountSettingsConnections::class, 'index'])
         ->name('pages-account-settings-connections');

    Route::view('/support', 'content.pages.support')->name('pages-support');
    Route::view('/docs', 'content.pages.docs')->name('pages-docs');

    Route::get('/pages/misc-error', [MiscError::class, 'index'])->name('pages-misc-error');
    Route::get('/pages/misc-under-maintenance', [MiscUnderMaintenance::class, 'index'])->name('pages-misc-under-maintenance');
});

// Logout
Route::match(['GET', 'POST'], '/logout', [LoginBasic::class, 'logout'])
     ->name('logout')
     ->middleware('auth');
