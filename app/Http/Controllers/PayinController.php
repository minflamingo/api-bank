<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ApiPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayinController extends Controller
{
    private const ACB_BIN = '970416';
    private const VCB_BIN = '970436';
    private const VPBANK_BIN = '970432';
    private const TECHCOMBANK_BIN = '970407';
    private const MBBANK_BIN = '970422';
    private const DEFAULT_TEMPLATE = 'IRuAFR6';
    private const DEFAULT_MIN_AMOUNT = 10000;
    private const DEFAULT_QUICK_AMOUNTS = '50000,100000,200000,500000,1000000';
    private const DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS = 2;

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập');
        }

        $bank = $this->rechargeBank();
        $addInfo = $this->rechargeCode($bank, $user);
        $qrUrl = $this->vietQrImageUrl($bank, $addInfo);
        $quickAmounts = $this->quickAmounts($bank);
        $minAmount = (int) ($bank->min_amount ?: self::DEFAULT_MIN_AMOUNT);
        $instructions = trim((string) ($bank->instructions ?? ''));
        $bankLabel = $this->bankLabel((string) ($bank->receiver_bank_type ?: $bank->short_name ?: 'ACB'));
        $scanInterval = max(1, min(60, (int) ($bank->recharge_scan_interval_seconds ?: self::DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS)));
        $balance = (int) ($user->amount ?? 0);
        $invoices = Invoice::where('user_id', $user->id)
            ->orderBy('create_time', 'desc')
            ->limit(10)
            ->get();

        return view('payin', compact(
            'user',
            'bank',
            'addInfo',
            'qrUrl',
            'quickAmounts',
            'minAmount',
            'instructions',
            'bankLabel',
            'scanInterval',
            'balance',
            'invoices'
        ));
    }

    public function syncAcb(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập',
            ], 401);
        }

        $startedAt = time() - 2;

        try {
            $bank = $this->rechargeBank();
            $message = match ((string) ($bank->receiver_bank_type ?? 'ACB')) {
                'VCB' => app(CronController::class)->cronNapVCB($request),
                'VPBANK' => app(CronController::class)->cronNapVPBANK($request),
                'TECHCOMBANK' => app(CronController::class)->cronNapTECHCOMBANK($request),
                'MBBANK' => app(CronController::class)->cronNapMBBANK($request),
                default => app(CronController::class)->cronNapACB($request),
            };
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Không thể đồng bộ giao dịch lúc này. Vui lòng thử lại sau.',
            ], 500);
        }

        $latestInvoice = Invoice::where('user_id', $user->id)
            ->orderBy('create_time', 'desc')
            ->first();
        $freshUser = User::find($user->id);

        return response()->json([
            'success' => true,
            'message' => (string) $message,
            'found' => $latestInvoice && (int) $latestInvoice->create_time >= $startedAt,
            'invoice' => $latestInvoice ? $this->invoicePayload($latestInvoice) : null,
            'balance' => (int) ($freshUser->amount ?? 0),
        ]);
    }

    public function rechargeSettings()
    {
        abort_unless($this->isSuperAdmin(), 403);

        $bank = $this->rechargeBank();
        $quickAmounts = implode(',', $this->quickAmounts($bank));
        $selectedReceiverBankType = (string) old('receiver_bank_type', $bank->receiver_bank_type ?: 'ACB');
        $selectedReceiverAccountId = (int) old('receiver_account_id', $bank->receiver_account_id ?? 0);
        $acbReceiverAccounts = $this->acbReceiverAccounts($bank);
        $vcbReceiverAccounts = $this->vcbReceiverAccounts($bank);
        $vpbankReceiverAccounts = $this->vpbankReceiverAccounts($bank);
        $techcombankReceiverAccounts = $this->techcombankReceiverAccounts($bank);
        $mbbankReceiverAccounts = $this->mbbankReceiverAccounts($bank);

        return view('admin.recharge-settings', compact(
            'bank',
            'quickAmounts',
            'acbReceiverAccounts',
            'vcbReceiverAccounts',
            'vpbankReceiverAccounts',
            'techcombankReceiverAccounts',
            'mbbankReceiverAccounts',
            'selectedReceiverBankType',
            'selectedReceiverAccountId'
        ));
    }

    public function updateRechargeSettings(Request $request)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'receiver_bank_type' => ['required', 'in:ACB,VCB,VPBANK,TECHCOMBANK,MBBANK'],
            'receiver_account_id' => ['required', 'integer', 'min:1'],
            'noidungnap' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'vietqr_template' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'min_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'quick_amounts' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'recharge_scan_interval_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'receiver_bank_type.required' => 'Vui lòng chọn ngân hàng nhận nạp.',
            'receiver_bank_type.in' => 'Ngân hàng nhận nạp không hợp lệ.',
            'receiver_account_id.required' => 'Vui lòng chọn tài khoản hệ thống nhận tiền.',
            'noidungnap.required' => 'Vui lòng nhập tiền tố nội dung nạp.',
            'noidungnap.regex' => 'Nội dung nạp chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.',
            'vietqr_template.regex' => 'Mẫu QR chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.',
            'quick_amounts.max' => 'Danh sách số tiền nhanh quá dài.',
            'recharge_scan_interval_seconds.min' => 'Chu kỳ quét tối thiểu là 1 giây.',
            'recharge_scan_interval_seconds.max' => 'Chu kỳ quét tối đa là 60 giây.',
        ]);

        $quickAmounts = $this->normaliseQuickAmounts((string) ($validated['quick_amounts'] ?? ''));
        if (!$quickAmounts) {
            return back()
                ->withErrors(['quick_amounts' => 'Vui lòng nhập ít nhất một mốc tiền hợp lệ.'])
                ->withInput();
        }

        $receiverBankType = (string) $validated['receiver_bank_type'];
        $receiverAccount = match ($receiverBankType) {
            'VCB' => $this->selectedVcbReceiverAccount((int) $validated['receiver_account_id']),
            'VPBANK' => $this->selectedVpbankReceiverAccount((int) $validated['receiver_account_id']),
            'TECHCOMBANK' => $this->selectedTechcombankReceiverAccount((int) $validated['receiver_account_id']),
            'MBBANK' => $this->selectedMbbankReceiverAccount((int) $validated['receiver_account_id']),
            default => $this->selectedAcbReceiverAccount((int) $validated['receiver_account_id']),
        };

        if (!$receiverAccount || !is_null($receiverAccount->user_id)) {
            return back()
                ->withErrors(['receiver_account_id' => 'Tài khoản nhận tiền phải là tài khoản hệ thống do Super Admin thêm.'])
                ->withInput();
        }

        $receiverAccountNumber = trim((string) match ($receiverBankType) {
            'VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK' => $receiverAccount->account,
            default => $receiverAccount->stk,
        });
        if ($receiverAccountNumber === '') {
            return back()
                ->withErrors(['receiver_account_id' => 'Tài khoản nhận tiền chưa có số tài khoản.'])
                ->withInput();
        }
        $sessionField = match ($receiverBankType) {
            'VCB' => 'session_id',
            'VPBANK' => 'token_key',
            'TECHCOMBANK' => 'refresh_token',
            'MBBANK' => 'session_id',
            default => 'sessionId',
        };
        if ($receiverBankType === 'TECHCOMBANK' && empty($receiverAccount->{$sessionField})) {
            return back()
                ->withErrors(['receiver_account_id' => 'Tài khoản Techcombank nhận tiền cần xác nhận app để có refresh token trước khi chọn nhận nạp.'])
                ->withInput();
        }
        if (empty($receiverAccount->{$sessionField}) && empty($receiverAccount->password)) {
            return back()
                ->withErrors(['receiver_account_id' => 'Tài khoản nhận tiền chưa có session hoặc mật khẩu để cron đăng nhập lại.'])
                ->withInput();
        }

        $bank = match ($receiverBankType) {
            'VCB' => $this->vcbRechargeBank(),
            'VPBANK' => $this->vpbankRechargeBank(),
            'TECHCOMBANK' => $this->techcombankRechargeBank(),
            'MBBANK' => $this->mbbankRechargeBank(),
            default => $this->acbRechargeBank(),
        };
        $displayName = trim((string) ($receiverAccount->name ?: ($receiverBankType . ' RECEIVER')));
        $bank->fill([
            'short_name' => $this->bankLabel($receiverBankType),
            'image' => $bank->image ?: $this->bankImage($receiverBankType),
            'accountNumber' => $receiverAccountNumber,
            'accountName' => $displayName,
            'codebank' => $this->bankBin($receiverBankType),
            'noidungnap' => trim($validated['noidungnap']),
            'vietqr_template' => trim($validated['vietqr_template'] ?? '') ?: self::DEFAULT_TEMPLATE,
            'min_amount' => (int) ($validated['min_amount'] ?? self::DEFAULT_MIN_AMOUNT),
            'quick_amounts' => implode(',', $quickAmounts),
            'instructions' => trim((string) ($validated['instructions'] ?? '')),
            'recharge_scan_interval_seconds' => (int) ($validated['recharge_scan_interval_seconds'] ?? self::DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS),
            'receiver_bank_type' => $receiverBankType,
            'receiver_account_id' => (int) $receiverAccount->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks((string) $bank->codebank);

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Cấu hình nạp tiền ' . $receiverBankType,
            'notes' => 'Cập nhật tài khoản nhận nạp ' . $receiverBankType . ' ' . $bank->accountNumber . ' | account #' . $receiverAccount->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã lưu cấu hình nạp tiền ' . $receiverBankType . '.');
    }

    public function updateReceiverToken(Request $request)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'receiver_bank_type' => ['required', 'in:ACB,VCB,VPBANK,TECHCOMBANK,MBBANK'],
            'receiver_token' => ['required', 'string', 'max:4096'],
        ], [
            'receiver_bank_type.required' => 'Vui lòng chọn ngân hàng nhận nạp.',
            'receiver_bank_type.in' => 'Ngân hàng nhận nạp không hợp lệ.',
            'receiver_token.required' => 'Vui lòng dán token API ngân hàng.',
            'receiver_token.max' => 'Token quá dài.',
        ]);

        $receiverBankType = (string) $validated['receiver_bank_type'];
        $receiverToken = trim((string) $validated['receiver_token']);
        $receiverAccount = $this->receiverAccountByToken($receiverBankType, $receiverToken);

        if (!$receiverAccount) {
            return back()
                ->withErrors(['receiver_token' => 'Không tìm thấy token này trong account ' . $this->bankLabel($receiverBankType) . '.'])
                ->withInput();
        }

        if (!is_null($receiverAccount->user_id)) {
            return back()
                ->withErrors(['receiver_token' => 'Token này thuộc account khách hàng, không dùng làm tài khoản nhận nạp hệ thống. Hãy dùng token account hệ thống của Super Admin.'])
                ->withInput();
        }

        $receiverAccountNumber = $this->receiverAccountNumber($receiverBankType, $receiverAccount);
        if ($receiverAccountNumber === '') {
            return back()
                ->withErrors(['receiver_token' => 'Account tìm thấy chưa có số tài khoản nhận.'])
                ->withInput();
        }

        $sessionField = $this->receiverSessionField($receiverBankType);
        if ($receiverBankType === 'TECHCOMBANK' && empty($receiverAccount->{$sessionField})) {
            return back()
                ->withErrors(['receiver_token' => 'Token Techcombank này chưa có refresh token, cần xác nhận app trước khi dùng nhận nạp.'])
                ->withInput();
        }
        if (empty($receiverAccount->{$sessionField}) && empty($receiverAccount->password)) {
            return back()
                ->withErrors(['receiver_token' => 'Account của token này chưa có session hoặc mật khẩu để cron đăng nhập lại.'])
                ->withInput();
        }

        $bank = $this->receiverRechargeBank($receiverBankType);
        $displayName = $this->receiverAccountName($receiverAccount) ?: ($this->bankLabel($receiverBankType) . ' RECEIVER');
        $bank->fill([
            'short_name' => $this->bankLabel($receiverBankType),
            'image' => $bank->image ?: $this->bankImage($receiverBankType),
            'accountNumber' => $receiverAccountNumber,
            'accountName' => $displayName,
            'codebank' => $this->bankBin($receiverBankType),
            'noidungnap' => trim((string) ($bank->noidungnap ?: 'NAP3W')),
            'vietqr_template' => trim((string) ($bank->vietqr_template ?: self::DEFAULT_TEMPLATE)),
            'min_amount' => (int) ($bank->min_amount ?: self::DEFAULT_MIN_AMOUNT),
            'quick_amounts' => trim((string) ($bank->quick_amounts ?: self::DEFAULT_QUICK_AMOUNTS)),
            'instructions' => trim((string) ($bank->instructions ?? '')),
            'recharge_scan_interval_seconds' => (int) ($bank->recharge_scan_interval_seconds ?: self::DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS),
            'receiver_bank_type' => $receiverBankType,
            'receiver_account_id' => (int) $receiverAccount->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks((string) $bank->codebank);

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Cập nhật token nhận nạp hệ thống',
            'notes' => $receiverBankType . ' #' . $receiverAccount->id . ' | STK ' . $receiverAccountNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->to(route('admin.recharge-settings.edit', ['tab' => 'accounts']) . '#accounts')
            ->with('success', 'Đã chọn token ' . $this->bankLabel($receiverBankType) . ' làm tài khoản nhận nạp.');
    }

    public function storeReceiverAcbAccount(Request $request)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'stk' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'name' => ['nullable', 'string', 'max:128'],
        ], [
            'phone.required' => 'Vui lòng nhập tài khoản đăng nhập ACB.',
            'password.required' => 'Vui lòng nhập mật khẩu ACB.',
            'stk.required' => 'Vui lòng nhập số tài khoản ACB nhận tiền.',
            'stk.regex' => 'Số tài khoản ACB chỉ gồm chữ số.',
        ]);

        $login = $this->loginAcb(trim($validated['phone']), (string) $validated['password']);
        if (!is_array($login)) {
            return back()
                ->withErrors(['phone' => 'Không thể kết nối ACB.'])
                ->withInput($request->except('password'));
        }
        if (!isset($login['identity']['active']) || (int) $login['identity']['active'] !== 1) {
            return back()
                ->withErrors(['phone' => $login['message'] ?? 'Tài khoản ACB không hợp lệ hoặc chưa active.'])
                ->withInput($request->except('password'));
        }

        $accessToken = (string) ($login['accessToken'] ?? '');
        if ($accessToken === '') {
            return back()
                ->withErrors(['phone' => 'ACB không trả về session đăng nhập.'])
                ->withInput($request->except('password'));
        }

        $stk = trim($validated['stk']);
        $accountFromAcb = $this->findAcbAccountByNumber($accessToken, $stk);
        if (!$accountFromAcb) {
            return back()
                ->withErrors(['stk' => 'Số tài khoản này không nằm trong tài khoản ACB vừa đăng nhập.'])
                ->withInput($request->except('password'));
        }

        $displayName = trim((string) ($validated['name'] ?? ''))
            ?: trim((string) ($login['identity']['displayName'] ?? ''))
            ?: trim((string) ($accountFromAcb['accountName'] ?? ''))
            ?: trim((string) ($accountFromAcb['accountDescription'] ?? ''))
            ?: 'ACB RECEIVER';

        $existing = DB::table('account_acb')
            ->whereNull('user_id')
            ->where('stk', $stk)
            ->first();

        $token = $existing->token ?? md5(uniqid('', true) . time());
        $payload = [
            'user_id' => null,
            'phone' => trim($validated['phone']),
            'stk' => $stk,
            'name' => $displayName,
            'password' => (string) $validated['password'],
            'sessionId' => $accessToken,
            'deviceId' => '',
            'token' => $token,
            'time' => time(),
        ];

        if ($existing) {
            DB::table('account_acb')->where('id', $existing->id)->update($payload);
            $accountId = (int) $existing->id;
        } else {
            $accountId = (int) DB::table('account_acb')->insertGetId($payload);
        }

        $bank = $this->acbRechargeBank();
        $bank->fill([
            'short_name' => 'ACB',
            'image' => $bank->image ?: 'public/assets/storage/images/bankABM.png',
            'accountNumber' => $stk,
            'accountName' => $displayName,
            'codebank' => self::ACB_BIN,
            'receiver_bank_type' => 'ACB',
            'receiver_account_id' => $accountId,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks(self::ACB_BIN);

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Thêm tài khoản ACB nhận tiền hệ thống',
            'notes' => 'account_acb #' . $accountId . ' | STK ' . $stk,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm và chọn tài khoản ACB nhận tiền hệ thống.');
    }

    public function requestReceiverVcbOtp(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ], [
            'username.required' => 'Vui lòng nhập tài khoản Vietcombank.',
            'password.required' => 'Vui lòng nhập mật khẩu Vietcombank.',
            'account_no.required' => 'Vui lòng nhập số tài khoản Vietcombank nhận tiền.',
            'account_no.regex' => 'Số tài khoản Vietcombank chỉ gồm chữ số.',
        ]);

        $request->merge([
            'account' => trim($validated['username']),
            'password' => (string) $validated['password'],
            'stk' => trim($validated['account_no']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->vcbGetOtp($request));
        $status = (string) ($payload['status'] ?? '');

        if ($status === '3') {
            $account = $this->promoteVcbReceiverAccount(trim($validated['username']), trim($validated['account_no']));
            if (!$account) {
                return back()
                    ->withErrors(['vcb' => 'Đăng nhập VCB thành công nhưng không tìm thấy account vừa lưu.'])
                    ->withInput($request->except('password'));
            }

            $this->selectVcbReceiverBank($account);
            session()->forget('recharge_receiver_vcb_pending');

            return back()->with('success', 'Đã thêm và chọn Vietcombank nhận nạp.');
        }

        if ($status === '2') {
            session()->put('recharge_receiver_vcb_pending', [
                'username' => trim($validated['username']),
                'password' => (string) $validated['password'],
                'account_no' => trim($validated['account_no']),
            ]);

            return back()
                ->with('success', (string) ($payload['msg'] ?? 'Đã gửi OTP Vietcombank. Nhập OTP để hoàn tất.'));
        }

        return back()
            ->withErrors(['vcb' => (string) ($payload['msg'] ?? 'Không gửi được OTP Vietcombank.')])
            ->withInput($request->except('password'));
    }

    public function storeReceiverVcbAccount(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $pending = (array) session('recharge_receiver_vcb_pending', []);
        if (!$pending) {
            return back()->withErrors(['otp_code' => 'Chưa có phiên OTP Vietcombank. Vui lòng gửi OTP lại.']);
        }

        $validated = $request->validate([
            'otp_code' => ['required', 'string', 'max:12'],
        ], [
            'otp_code.required' => 'Vui lòng nhập OTP Vietcombank.',
        ]);

        $request->merge([
            'account' => (string) ($pending['username'] ?? ''),
            'password' => (string) ($pending['password'] ?? ''),
            'stk' => (string) ($pending['account_no'] ?? ''),
            'otp' => trim($validated['otp_code']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->vcbLoginOTP($request));
        if ((string) ($payload['status'] ?? '') !== '2') {
            return back()
                ->withErrors(['otp_code' => (string) ($payload['msg'] ?? 'OTP Vietcombank không hợp lệ.')]);
        }

        $account = $this->promoteVcbReceiverAccount((string) $pending['username'], (string) $pending['account_no']);
        if (!$account) {
            return back()
                ->withErrors(['vcb' => 'Xác thực OTP thành công nhưng không tìm thấy account Vietcombank vừa lưu.']);
        }

        $this->selectVcbReceiverBank($account);
        session()->forget('recharge_receiver_vcb_pending');

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Thêm tài khoản VCB nhận tiền hệ thống',
            'notes' => 'account_vietcombank #' . $account->id . ' | STK ' . $account->account,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm và chọn Vietcombank nhận nạp.');
    }

    public function requestReceiverVpbankOtp(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'vpbank_username' => ['required', 'string', 'max:64'],
            'vpbank_password' => ['required', 'string', 'max:255'],
            'vpbank_account_no' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ], [
            'vpbank_username.required' => 'Vui lòng nhập tài khoản VPBank.',
            'vpbank_password.required' => 'Vui lòng nhập mật khẩu VPBank.',
            'vpbank_account_no.required' => 'Vui lòng nhập số tài khoản VPBank nhận tiền.',
            'vpbank_account_no.regex' => 'Số tài khoản VPBank chỉ gồm chữ số.',
        ]);

        $request->merge([
            'account' => trim($validated['vpbank_username']),
            'password' => (string) $validated['vpbank_password'],
            'stk' => trim($validated['vpbank_account_no']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->vpbankLogin($request));
        $status = (string) ($payload['status'] ?? '');

        if ($status === '3') {
            $account = $this->promoteVpbankReceiverAccount(trim($validated['vpbank_username']), trim($validated['vpbank_account_no']));
            if (!$account) {
                return back()
                    ->withErrors(['vpbank' => 'Đăng nhập VPBank thành công nhưng không tìm thấy account vừa lưu.'])
                    ->withInput($request->except(['vpbank_password', 'password']));
            }

            $this->selectVpbankReceiverBank($account);
            session()->forget('recharge_receiver_vpbank_pending');

            return back()->with('success', 'Đã thêm và chọn VPBank nhận nạp.');
        }

        if ($status === '2') {
            session()->put('recharge_receiver_vpbank_pending', [
                'username' => trim($validated['vpbank_username']),
                'password' => (string) $validated['vpbank_password'],
                'account_no' => trim($validated['vpbank_account_no']),
            ]);

            return back()
                ->with('success', (string) ($payload['msg'] ?? 'VPBank yêu cầu OTP. Nhập OTP để hoàn tất.'));
        }

        return back()
            ->withErrors(['vpbank' => (string) ($payload['msg'] ?? 'Không kết nối được VPBank.')])
            ->withInput($request->except(['vpbank_password', 'password']));
    }

    public function storeReceiverVpbankAccount(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $pending = (array) session('recharge_receiver_vpbank_pending', []);
        if (!$pending) {
            return back()->withErrors(['vpbank_otp_code' => 'Chưa có phiên OTP VPBank. Vui lòng gửi OTP lại.']);
        }

        $validated = $request->validate([
            'vpbank_otp_code' => ['required', 'string', 'max:12'],
        ], [
            'vpbank_otp_code.required' => 'Vui lòng nhập OTP VPBank.',
        ]);

        $request->merge([
            'account' => (string) ($pending['username'] ?? ''),
            'password' => (string) ($pending['password'] ?? ''),
            'stk' => (string) ($pending['account_no'] ?? ''),
            'otp' => trim($validated['vpbank_otp_code']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->vpbankLoginOTP($request));
        if ((string) ($payload['status'] ?? '') !== '2') {
            return back()
                ->withErrors(['vpbank_otp_code' => (string) ($payload['msg'] ?? 'OTP VPBank không hợp lệ.')]);
        }

        $account = $this->promoteVpbankReceiverAccount((string) $pending['username'], (string) $pending['account_no']);
        if (!$account) {
            return back()
                ->withErrors(['vpbank' => 'Xác thực OTP thành công nhưng không tìm thấy account VPBank vừa lưu.']);
        }

        $this->selectVpbankReceiverBank($account);
        session()->forget('recharge_receiver_vpbank_pending');

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Thêm tài khoản VPBank nhận tiền hệ thống',
            'notes' => 'account_vpbank #' . $account->id . ' | STK ' . $account->account,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm và chọn VPBank nhận nạp.');
    }

    public function requestReceiverTechcombankConfirm(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'techcombank_username' => ['required', 'string', 'max:64'],
            'techcombank_password' => ['nullable', 'string', 'max:255'],
            'techcombank_account_no' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ], [
            'techcombank_username.required' => 'Vui lòng nhập tài khoản Techcombank.',
            'techcombank_account_no.required' => 'Vui lòng nhập số tài khoản Techcombank nhận tiền.',
            'techcombank_account_no.regex' => 'Số tài khoản Techcombank chỉ gồm chữ số.',
        ]);

        $request->merge([
            'account' => trim($validated['techcombank_username']),
            'password' => (string) ($validated['techcombank_password'] ?? ''),
            'stk' => trim($validated['techcombank_account_no']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->techcombankLogin($request));
        if ((string) ($payload['status'] ?? '') !== '2') {
            return back()
                ->withErrors(['techcombank' => (string) ($payload['msg'] ?? 'Không kết nối được Techcombank.')])
                ->withInput($request->except(['techcombank_password', 'password']));
        }

        session()->put('recharge_receiver_techcombank_pending', [
            'username' => trim($validated['techcombank_username']),
            'password' => (string) ($validated['techcombank_password'] ?? ''),
            'account_no' => trim($validated['techcombank_account_no']),
            'auth_url' => (string) ($payload['auth_url'] ?? ''),
        ]);

        return back()->with('success', (string) ($payload['msg'] ?? 'Đã tạo link đăng nhập Techcombank. Mở link, duyệt Mobile rồi dán URL xác nhận.'));
    }

    public function storeReceiverTechcombankAccount(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $pending = (array) session('recharge_receiver_techcombank_pending', []);
        if (!$pending) {
            return back()->withErrors(['techcombank' => 'Chưa có phiên xác nhận Techcombank. Vui lòng gửi yêu cầu lại.']);
        }

        $validated = $request->validate([
            'techcombank_redirect_url' => ['required', 'string', 'max:4096'],
        ], [
            'techcombank_redirect_url.required' => 'Vui lòng dán URL sau khi xác nhận Techcombank.',
        ]);

        $request->merge([
            'account' => (string) ($pending['username'] ?? ''),
            'password' => (string) ($pending['password'] ?? ''),
            'stk' => (string) ($pending['account_no'] ?? ''),
            'system_receiver' => 1,
            'redirect_url' => trim((string) $validated['techcombank_redirect_url']),
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->techcombankConfirmLogin($request));
        if ((string) ($payload['status'] ?? '') !== '2') {
            return back()
                ->withErrors(['techcombank' => (string) ($payload['msg'] ?? 'Chưa xác nhận được Techcombank trên app Mobile.')]);
        }

        $account = $this->promoteTechcombankReceiverAccount((string) $pending['username'], (string) $pending['account_no']);
        if (!$account) {
            return back()
                ->withErrors(['techcombank' => 'Xác nhận thành công nhưng không tìm thấy account Techcombank vừa lưu.']);
        }

        $this->selectTechcombankReceiverBank($account);
        session()->forget('recharge_receiver_techcombank_pending');

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Thêm tài khoản Techcombank nhận tiền hệ thống',
            'notes' => 'account_techcombank #' . $account->id . ' | STK ' . $account->account,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm và chọn Techcombank nhận nạp.');
    }

    public function storeReceiverMbbankAccount(Request $request, PaymentController $payment)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $validated = $request->validate([
            'mbbank_username' => ['required', 'string', 'max:64'],
            'mbbank_password' => ['required', 'string', 'max:255'],
            'mbbank_account_no' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
        ], [
            'mbbank_username.required' => 'Vui lòng nhập tài khoản MBBank.',
            'mbbank_password.required' => 'Vui lòng nhập mật khẩu MBBank.',
            'mbbank_account_no.required' => 'Vui lòng nhập số tài khoản MBBank nhận tiền.',
            'mbbank_account_no.regex' => 'Số tài khoản MBBank chỉ gồm chữ số.',
        ]);

        $request->merge([
            'account' => trim($validated['mbbank_username']),
            'password' => (string) $validated['mbbank_password'],
            'stk' => trim($validated['mbbank_account_no']),
            'system_receiver' => 1,
        ]);

        $payload = $this->payloadFromPaymentResponse($payment->mbbankLogin($request));
        if ((string) ($payload['status'] ?? '') !== '2') {
            return back()
                ->withErrors(['mbbank' => (string) ($payload['msg'] ?? 'Không kết nối được MBBank.')])
                ->withInput($request->except(['mbbank_password', 'password']));
        }

        $account = $this->promoteMbbankReceiverAccount(trim($validated['mbbank_username']), trim($validated['mbbank_account_no']));
        if (!$account) {
            return back()
                ->withErrors(['mbbank' => 'Đăng nhập MBBank thành công nhưng không tìm thấy account vừa lưu.'])
                ->withInput($request->except(['mbbank_password', 'password']));
        }

        $this->selectMbbankReceiverBank($account);

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Thêm tài khoản MBBank nhận tiền hệ thống',
            'notes' => 'account_mbbank #' . $account->id . ' | STK ' . $account->account,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm và chọn MBBank nhận nạp.');
    }

    public function updateReceiverAccount(Request $request, string $bank, int $id)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $receiverType = $this->receiverTypeFromBankParam($bank);
        abort_unless($receiverType !== null, 404);

        $account = $this->receiverAccountRow($receiverType, $id);
        if (!$account) {
            return $this->redirectRechargeAccounts()
                ->withErrors(['account' => 'Không tìm thấy account hệ thống cần sửa.']);
        }

        $validated = $this->validateReceiverAccountUpdate($request, $receiverType);
        $payload = $this->receiverAccountUpdatePayload($validated, $receiverType);

        DB::table($this->receiverAccountTable($receiverType))
            ->where('id', $id)
            ->whereNull('user_id')
            ->update($payload);

        $updated = $this->receiverAccountRow($receiverType, $id);
        $this->syncActiveRechargeBankIfNeeded($receiverType, $updated);

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Sửa account nhận tiền hệ thống',
            'notes' => $receiverType . ' #' . $id . ' | STK ' . $this->receiverAccountNumber($receiverType, $updated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectRechargeAccounts('Đã cập nhật account ' . $this->bankLabel($receiverType) . '.');
    }

    public function destroyReceiverAccount(string $bank, int $id)
    {
        abort_unless($this->isSuperAdmin(), 403);

        $receiverType = $this->receiverTypeFromBankParam($bank);
        abort_unless($receiverType !== null, 404);

        $account = $this->receiverAccountRow($receiverType, $id);
        if (!$account) {
            return $this->redirectRechargeAccounts()
                ->withErrors(['account' => 'Không tìm thấy account hệ thống cần xóa.']);
        }

        $active = Bank::where('receiver_bank_type', $receiverType)
            ->where('receiver_account_id', $id)
            ->first();
        if ($active) {
            return $this->redirectRechargeAccounts()
                ->withErrors(['account' => 'Không thể xóa account đang nhận nạp. Hãy chọn account khác trước.']);
        }

        DB::table($this->receiverAccountTable($receiverType))
            ->where('id', $id)
            ->whereNull('user_id')
            ->delete();

        DB::table('xlogs')->insert([
            'ip' => request()->ip(),
            'user' => Auth::id(),
            'log' => 'Xóa account nhận tiền hệ thống',
            'notes' => $receiverType . ' #' . $id . ' | STK ' . $this->receiverAccountNumber($receiverType, $account),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->redirectRechargeAccounts('Đã xóa account ' . $this->bankLabel($receiverType) . '.');
    }

    public function upgradeIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập');
        }

        $user = ApiPackage::applyDueScheduledPlan($user) ?: $user;
        $plans = ApiPackage::plans();
        $accountLimit = ApiPackage::userLimit($user);
        $baseLimit = ApiPackage::userBaseLimit($user);
        $extraSlots = ApiPackage::userExtraSlots($user);
        $currentPlan = ApiPackage::plan((string) ($user->api_plan ?? ''));
        $isCustomPlan = ApiPackage::isCustomPlan($user);
        $currentPlanName = ApiPackage::currentPlanName($user);
        $nextPlan = ApiPackage::plan((string) ($user->api_next_plan ?? ''));
        $nextPlanMonths = (int) ($user->api_next_plan_months ?? 0);
        $nextPlanPrice = (int) ($user->api_next_plan_price ?? 0);

        return view('upgrade', compact(
            'user',
            'plans',
            'accountLimit',
            'baseLimit',
            'extraSlots',
            'currentPlan',
            'isCustomPlan',
            'currentPlanName',
            'nextPlan',
            'nextPlanMonths',
            'nextPlanPrice'
        ));
    }

    public function upgradeTotal(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user = ApiPackage::applyDueScheduledPlan($user) ?: $user;
        }
        $planKey = (string) $request->input('plan', '');
        $months = (int) $request->input('months');
        $plan = ApiPackage::plan($planKey);
        $total = ApiPackage::packagePrice($planKey, $months);

        if (!$plan || $total === null) {
            return response()->json(['status' => '1', 'msg' => 'Gói API không hợp lệ']);
        }

        $preview = $this->upgradePreview($user, $planKey, $plan, $total, $months);

        return response()->json([
            'status' => '2',
            'action' => $preview['action'],
            'action_text' => $preview['action_text'],
            'total' => $total,
            'total_text' => number_format($total) . ' VNĐ',
            'refund' => $preview['refund'],
            'refund_text' => number_format($preview['refund']) . ' VNĐ',
            'payable' => $preview['payable'],
            'payable_text' => number_format($preview['payable']) . ' VNĐ',
            'balance_after' => $preview['balance_after'],
            'balance_after_text' => number_format($preview['balance_after']) . ' VNĐ',
            'remaining_days' => $preview['remaining_days'],
            'effective_at' => $preview['effective_at'],
            'effective_text' => $preview['effective_text'],
            'label' => $plan['name'] . ' / ' . ApiPackage::durationLabel($months),
        ]);
    }

    public function upgradeStore(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Vui lòng đăng nhập']);
        }

        $planKey = (string) $request->input('plan', '');
        $months = (int) $request->input('months');
        $plan = ApiPackage::plan($planKey);
        $total = ApiPackage::packagePrice($planKey, $months);

        if (!$plan || $total === null) {
            return response()->json(['status' => '1', 'msg' => 'Vui lòng chọn đúng gói API và thời gian gia hạn']);
        }

        try {
            $message = DB::transaction(function () use ($user, $months, $total, $planKey, $plan) {
                $lockedUser = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
                $preview = $this->upgradePreview($lockedUser, $planKey, $plan, $total, $months);
                $wallet = (int) ($lockedUser->amount ?? 0);

                if ($preview['action'] === 'schedule_downgrade') {
                    DB::table('users')->where('id', $user->id)->update([
                        'api_next_plan' => $planKey,
                        'api_next_plan_months' => $months,
                        'api_next_plan_price' => $total,
                        'api_next_plan_scheduled_at' => time(),
                    ]);

                    DB::table('xlogs')->insert([
                        'ip' => request()->ip(),
                        'user' => $user->id,
                        'log' => 'Lên lịch hạ cấp API',
                        'notes' => $plan['name']
                            . ' - ' . $months . ' tháng, phí kỳ sau ' . $total
                            . ', hiệu lực ' . $preview['effective_text']
                            . ', limit ' . (int) $plan['limit'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 'Đã lên lịch hạ cấp xuống ' . $plan['name']
                        . '. Gói mới sẽ có hiệu lực khi gói hiện tại hết hạn.';
                }

                if ($preview['action'] === 'renew') {
                    if ($wallet < $total) {
                        throw new \RuntimeException(
                            'Số dư không đủ. Cần thanh toán '
                            . number_format($total)
                            . ' VNĐ để gia hạn thêm gói hiện tại'
                        );
                    }

                    $startedAt = time();
                    $baseTime = ((int) ($lockedUser->time_end ?? 0) > $startedAt)
                        ? (int) $lockedUser->time_end
                        : $startedAt;
                    $newTimeEnd = $baseTime + (86400 * 30 * $months);

                    DB::table('users')->where('id', $user->id)->update([
                        'amount' => $wallet - $total,
                        'time_end' => $newTimeEnd,
                        'api_plan' => $planKey,
                        'api_account_limit' => (int) $plan['limit'],
                        'api_plan_started_at' => $startedAt,
                        'api_plan_months' => $months,
                        'api_plan_paid_amount' => $total,
                        'api_next_plan' => null,
                        'api_next_plan_months' => 0,
                        'api_next_plan_price' => 0,
                        'api_next_plan_scheduled_at' => 0,
                    ]);

                    DB::table('xlogs')->insert([
                        'ip' => request()->ip(),
                        'user' => $user->id,
                        'log' => 'Gia hạn cùng gói API',
                        'notes' => $plan['name']
                            . ' - cộng thêm ' . $months . ' tháng, phí ' . $total
                            . ', hạn cũ ' . date('H:i d/m/Y', $baseTime)
                            . ', hạn mới ' . date('H:i d/m/Y', $newTimeEnd)
                            . ', limit ' . (int) $plan['limit'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 'Gia hạn thêm ' . $plan['name'] . ' thành công';
                }

                $refund = $this->currentPackageRefund($lockedUser);
                $payable = max(0, $total - $refund['amount']);

                if (($wallet + $refund['amount']) < $total) {
                    throw new \RuntimeException(
                        'Số dư không đủ. Cần thanh toán thêm '
                        . number_format($payable)
                        . ' VNĐ sau khi hoàn gói cũ '
                        . number_format($refund['amount'])
                        . ' VNĐ'
                    );
                }

                $startedAt = time();
                $newTimeEnd = $startedAt + (86400 * 30 * $months);

                DB::table('users')->where('id', $user->id)->update([
                    'amount' => $wallet + $refund['amount'] - $total,
                    'time_end' => $newTimeEnd,
                    'api_plan' => $planKey,
                    'api_account_limit' => (int) $plan['limit'],
                    'api_plan_started_at' => $startedAt,
                    'api_plan_months' => $months,
                    'api_plan_paid_amount' => $total,
                    'api_next_plan' => null,
                    'api_next_plan_months' => 0,
                    'api_next_plan_price' => 0,
                    'api_next_plan_scheduled_at' => 0,
                ]);

                DB::table('xlogs')->insert([
                    'ip' => request()->ip(),
                    'user' => $user->id,
                    'log' => 'Đổi gói API',
                    'notes' => $plan['name']
                        . ' - ' . $months . ' tháng, phí ' . $total
                        . ', hoàn gói cũ ' . $refund['amount']
                        . ', ví trừ ròng ' . $payable
                        . ', hạn mới ' . date('H:i d/m/Y', $newTimeEnd)
                        . ', limit ' . (int) $plan['limit'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 'Gia hạn ' . $plan['name'] . ' thành công';
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => '1', 'msg' => $e->getMessage()]);
        }

        return response()->json(['status' => '2', 'msg' => $message]);
    }

    private function upgradePreview(?object $user, string $targetPlanKey, array $plan, int $newPackageTotal, int $months): array
    {
        $wallet = (int) ($user->amount ?? 0);
        $action = $this->upgradeAction($user, $targetPlanKey, $plan);
        if ($action === 'schedule_downgrade') {
            $timeEnd = (int) ($user->time_end ?? 0);

            return [
                'action' => $action,
                'action_text' => 'Hạ cấp kỳ sau',
                'refund' => 0,
                'payable' => 0,
                'balance_after' => $wallet,
                'remaining_days' => max(0, (int) ceil(($timeEnd - time()) / 86400)),
                'effective_at' => $timeEnd,
                'effective_text' => $timeEnd > time() ? date('H:i d/m/Y', $timeEnd) : 'kỳ sau',
            ];
        }

        $refund = $this->currentPackageRefund($user);
        if ($action === 'renew') {
            $timeEnd = (int) ($user->time_end ?? 0);
            $baseTime = $timeEnd > time() ? $timeEnd : time();
            $newTimeEnd = $baseTime + (86400 * 30 * $months);

            return [
                'action' => $action,
                'action_text' => 'Gia hạn thêm thời gian',
                'refund' => 0,
                'payable' => $newPackageTotal,
                'balance_after' => $wallet - $newPackageTotal,
                'remaining_days' => max(0, (int) ceil(($timeEnd - time()) / 86400)),
                'effective_at' => $newTimeEnd,
                'effective_text' => 'cộng thêm vào hạn hiện tại',
            ];
        }

        $payable = max(0, $newPackageTotal - $refund['amount']);

        return [
            'action' => $action,
            'action_text' => match ($action) {
                'upgrade' => 'Nâng cấp ngay',
                'purchase' => 'Mua gói mới',
                default => 'Gia hạn ngay',
            },
            'refund' => $refund['amount'],
            'payable' => $payable,
            'balance_after' => $wallet + $refund['amount'] - $newPackageTotal,
            'remaining_days' => $refund['remaining_days'],
            'effective_at' => time(),
            'effective_text' => 'ngay sau khi thanh toán',
        ];
    }

    private function upgradeAction(?object $user, string $targetPlanKey, array $targetPlan): string
    {
        $now = time();
        if (!$user || (int) ($user->time_end ?? 0) <= $now) {
            return 'purchase';
        }

        $currentPlanKey = trim((string) ($user->api_plan ?? ''));
        if ($currentPlanKey !== '' && hash_equals($currentPlanKey, $targetPlanKey)) {
            return 'renew';
        }

        $currentLimit = $this->currentPackageLimit($user);
        $targetLimit = (int) ($targetPlan['limit'] ?? 0);

        if ($currentLimit > 0 && $targetLimit < $currentLimit) {
            return 'schedule_downgrade';
        }

        if ($targetLimit > $currentLimit) {
            return 'upgrade';
        }

        return 'renew';
    }

    private function currentPackageLimit(?object $user): int
    {
        if (!$user) {
            return 0;
        }

        $currentPlan = ApiPackage::plan((string) ($user->api_plan ?? ''));
        if ($currentPlan) {
            return (int) $currentPlan['limit'];
        }

        return max(0, (int) ($user->api_account_limit ?? 0));
    }

    private function currentPackageRefund(?object $user): array
    {
        $now = time();
        $timeEnd = (int) ($user->time_end ?? 0);
        if (!$user || $timeEnd <= $now) {
            return ['amount' => 0, 'remaining_days' => 0];
        }

        $plan = ApiPackage::plan((string) ($user->api_plan ?? ''));
        if (!$plan) {
            return ['amount' => 0, 'remaining_days' => (int) ceil(($timeEnd - $now) / 86400)];
        }

        $remainingSeconds = max(0, $timeEnd - $now);
        $paidAmount = (int) ($user->api_plan_paid_amount ?? 0);
        $paidMonths = (int) ($user->api_plan_months ?? 0);

        if ($paidAmount > 0 && $paidMonths > 0) {
            $packageSeconds = max(1, $paidMonths * 30 * 86400);
            $refund = (int) floor($paidAmount * $remainingSeconds / $packageSeconds);
        } else {
            $refund = (int) floor((int) $plan['price'] * $remainingSeconds / (30 * 86400));
        }

        return [
            'amount' => max(0, $refund),
            'remaining_days' => (int) ceil($remainingSeconds / 86400),
        ];
    }

    public function imgVietQr(Request $request)
    {
        $bank = Bank::where('id', $request->query('bankId'))->first() ?: $this->rechargeBank();
        $userId = (int) $request->query('userid', Auth::id());
        $addInfo = trim((string) $bank->noidungnap) . $userId;

        return response($this->vietQrImageUrl($bank, $addInfo), 200)
            ->header('Content-Type', 'text/plain');
    }

    public function checkNapTien(Request $request)
    {
        if ($request->query('action') !== 'checkTransaction') {
            return response('INVALID ACTION', 400);
        }

        $userId = Auth::id() ?: (int) $request->query('userId');
        $currentTime = (int) $request->query('currentTime');

        $invoice = DB::table('invoices')
            ->where('user_id', $userId)
            ->where('create_time', '>', $currentTime)
            ->orderByDesc('create_time')
            ->first();

        if ($this->wantsJson($request)) {
            $freshUser = $userId ? User::find($userId) : null;

            return response()->json([
                'success' => true,
                'found' => (bool) $invoice,
                'invoice' => $invoice ? $this->invoicePayload($invoice) : null,
                'balance' => (int) ($freshUser->amount ?? 0),
            ]);
        }

        return $invoice ? response($invoice->create_time) : response('FAIL');
    }

    private function rechargeBank(): Bank
    {
        $bank = Bank::whereIn('receiver_bank_type', ['ACB', 'VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK'])
            ->whereNotNull('receiver_account_id')
            ->orderBy('id')
            ->first();

        if (!$bank) {
            $bank = $this->acbRechargeBank();
        }

        return $this->ensureRechargeDefaults($bank);
    }

    private function acbRechargeBank(): Bank
    {
        return $this->bankByCode(self::ACB_BIN, [
            'short_name' => 'ACB',
            'image' => 'public/assets/storage/images/bankABM.png',
            'accountNumber' => '203888888',
            'accountName' => 'DINH VIET CUONG',
            'noidungnap' => 'NAP3W',
        ]);
    }

    private function vcbRechargeBank(): Bank
    {
        return $this->bankByCode(self::VCB_BIN, [
            'short_name' => 'Vietcombank',
            'image' => 'public/assets/storage/images/bankVCB.png',
            'accountNumber' => '9888567890',
            'accountName' => 'DINH VIET CUONG',
            'noidungnap' => 'NAP3W',
        ]);
    }

    private function vpbankRechargeBank(): Bank
    {
        return $this->bankByCode(self::VPBANK_BIN, [
            'short_name' => 'VPBank',
            'image' => 'public/assets/storage/images/bankVPB.png',
            'accountNumber' => '000000000',
            'accountName' => 'DINH VIET CUONG',
            'noidungnap' => 'NAP3W',
        ]);
    }

    private function techcombankRechargeBank(): Bank
    {
        return $this->bankByCode(self::TECHCOMBANK_BIN, [
            'short_name' => 'Techcombank',
            'image' => 'public/assets/storage/images/bankTCB.png',
            'accountNumber' => '000000000',
            'accountName' => 'DINH VIET CUONG',
            'noidungnap' => 'NAP3W',
        ]);
    }

    private function mbbankRechargeBank(): Bank
    {
        return $this->bankByCode(self::MBBANK_BIN, [
            'short_name' => 'MBBank',
            'image' => 'public/assets/storage/images/bankMBB.png',
            'accountNumber' => '000000000',
            'accountName' => 'DINH VIET CUONG',
            'noidungnap' => 'NAP3W',
        ]);
    }

    private function receiverRechargeBank(string $type): Bank
    {
        return match ($type) {
            'VCB' => $this->vcbRechargeBank(),
            'VPBANK' => $this->vpbankRechargeBank(),
            'TECHCOMBANK' => $this->techcombankRechargeBank(),
            'MBBANK' => $this->mbbankRechargeBank(),
            default => $this->acbRechargeBank(),
        };
    }

    private function bankBin(string $type): string
    {
        return match ($type) {
            'VCB' => self::VCB_BIN,
            'VPBANK' => self::VPBANK_BIN,
            'TECHCOMBANK' => self::TECHCOMBANK_BIN,
            'MBBANK' => self::MBBANK_BIN,
            default => self::ACB_BIN,
        };
    }

    private function bankLabel(string $type): string
    {
        return match ($type) {
            'VCB' => 'Vietcombank',
            'VPBANK' => 'VPBank',
            'TECHCOMBANK' => 'Techcombank',
            'MBBANK' => 'MBBank',
            default => 'ACB',
        };
    }

    private function bankImage(string $type): string
    {
        return match ($type) {
            'VCB' => 'public/assets/storage/images/bankVCB.png',
            'VPBANK' => 'public/assets/storage/images/bankVPB.png',
            'TECHCOMBANK' => 'public/assets/storage/images/bankTCB.png',
            'MBBANK' => 'public/assets/storage/images/bankMBB.png',
            default => 'public/assets/storage/images/bankABM.png',
        };
    }

    private function bankByCode(string $code, array $defaults): Bank
    {
        $bank = Bank::where('codebank', $code)->orderBy('id')->first();
        if ($bank) {
            return $this->ensureRechargeDefaults($bank);
        }

        return Bank::create([
            'short_name' => $defaults['short_name'],
            'image' => $defaults['image'],
            'accountNumber' => $defaults['accountNumber'],
            'accountName' => $defaults['accountName'],
            'codebank' => $code,
            'noidungnap' => $defaults['noidungnap'],
            'vietqr_template' => self::DEFAULT_TEMPLATE,
            'min_amount' => self::DEFAULT_MIN_AMOUNT,
            'quick_amounts' => self::DEFAULT_QUICK_AMOUNTS,
            'instructions' => '',
            'recharge_scan_interval_seconds' => self::DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS,
        ]);
    }

    private function ensureRechargeDefaults(Bank $bank): Bank
    {
        $dirty = false;
        foreach ([
            'vietqr_template' => self::DEFAULT_TEMPLATE,
            'min_amount' => self::DEFAULT_MIN_AMOUNT,
            'quick_amounts' => self::DEFAULT_QUICK_AMOUNTS,
            'recharge_scan_interval_seconds' => self::DEFAULT_RECHARGE_SCAN_INTERVAL_SECONDS,
        ] as $field => $default) {
            if ($bank->{$field} === null || $bank->{$field} === '') {
                $bank->{$field} = $default;
                $dirty = true;
            }
        }
        if (!$bank->noidungnap) {
            $bank->noidungnap = 'NAP3W';
            $dirty = true;
        }
        if ($dirty) {
            $bank->save();
        }

        return $bank;
    }

    private function deactivateOtherReceiverBanks(string $activeCode): void
    {
        Bank::where('codebank', '<>', $activeCode)->update([
            'receiver_bank_type' => null,
            'receiver_account_id' => null,
        ]);
    }

    private function acbReceiverAccounts(Bank $bank)
    {
        $selectedId = (int) ($bank->receiver_account_id ?? 0);

        return DB::table('account_acb')
            ->select([
                'account_acb.id',
                'account_acb.user_id',
                'account_acb.phone',
                'account_acb.stk',
                'account_acb.name',
                'account_acb.token',
                'account_acb.sessionId',
            ])
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('account_acb.user_id');

                if ($selectedId > 0) {
                    $query->orWhere('account_acb.id', $selectedId);
                }
            })
            ->orderByRaw('CASE WHEN account_acb.id = ? THEN 0 ELSE 1 END', [$selectedId])
            ->orderByDesc('account_acb.id')
            ->get();
    }

    private function selectedAcbReceiverAccount(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('account_acb')
            ->select([
                'account_acb.id',
                'account_acb.user_id',
                'account_acb.phone',
                'account_acb.stk',
                'account_acb.name',
                'account_acb.password',
                'account_acb.sessionId',
                'account_acb.token',
            ])
            ->where('account_acb.id', $id)
            ->first();
    }

    private function vcbReceiverAccounts(Bank $bank)
    {
        $selectedId = (($bank->receiver_bank_type ?? '') === 'VCB') ? (int) ($bank->receiver_account_id ?? 0) : 0;

        return DB::table('account_vietcombank')
            ->select([
                'account_vietcombank.id',
                'account_vietcombank.user_id',
                'account_vietcombank.username',
                'account_vietcombank.account',
                'account_vietcombank.name',
                'account_vietcombank.token',
                'account_vietcombank.session_id',
            ])
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('account_vietcombank.user_id');

                if ($selectedId > 0) {
                    $query->orWhere('account_vietcombank.id', $selectedId);
                }
            })
            ->orderByRaw('CASE WHEN account_vietcombank.id = ? THEN 0 ELSE 1 END', [$selectedId])
            ->orderByDesc('account_vietcombank.id')
            ->get();
    }

    private function selectedVcbReceiverAccount(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('account_vietcombank')
            ->select([
                'account_vietcombank.id',
                'account_vietcombank.user_id',
                'account_vietcombank.username',
                'account_vietcombank.password',
                'account_vietcombank.account',
                'account_vietcombank.name',
                'account_vietcombank.session_id',
                'account_vietcombank.access_key',
                'account_vietcombank.cif',
                'account_vietcombank.mobile_id',
                'account_vietcombank.client_id',
                'account_vietcombank.tranId',
                'account_vietcombank.browserToken',
                'account_vietcombank.token',
                'account_vietcombank.create_date',
            ])
            ->where('account_vietcombank.id', $id)
            ->first();
    }

    private function vpbankReceiverAccounts(Bank $bank)
    {
        $selectedId = (($bank->receiver_bank_type ?? '') === 'VPBANK') ? (int) ($bank->receiver_account_id ?? 0) : 0;

        return DB::table('account_vpbank')
            ->select([
                'account_vpbank.id',
                'account_vpbank.user_id',
                'account_vpbank.username',
                'account_vpbank.account',
                'account_vpbank.name',
                'account_vpbank.token',
                'account_vpbank.token_key',
                'account_vpbank.csrf',
            ])
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('account_vpbank.user_id');

                if ($selectedId > 0) {
                    $query->orWhere('account_vpbank.id', $selectedId);
                }
            })
            ->orderByRaw('CASE WHEN account_vpbank.id = ? THEN 0 ELSE 1 END', [$selectedId])
            ->orderByDesc('account_vpbank.id')
            ->get();
    }

    private function selectedVpbankReceiverAccount(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('account_vpbank')
            ->select([
                'account_vpbank.id',
                'account_vpbank.user_id',
                'account_vpbank.username',
                'account_vpbank.password',
                'account_vpbank.account',
                'account_vpbank.name',
                'account_vpbank.token_key',
                'account_vpbank.csrf',
                'account_vpbank.cookie',
                'account_vpbank.is_login',
                'account_vpbank.token',
                'account_vpbank.create_date',
            ])
            ->where('account_vpbank.id', $id)
            ->first();
    }

    private function techcombankReceiverAccounts(Bank $bank)
    {
        $selectedId = (($bank->receiver_bank_type ?? '') === 'TECHCOMBANK') ? (int) ($bank->receiver_account_id ?? 0) : 0;

        return DB::table('account_techcombank')
            ->select([
                'account_techcombank.id',
                'account_techcombank.user_id',
                'account_techcombank.username',
                'account_techcombank.account',
                'account_techcombank.name',
                'account_techcombank.token',
                'account_techcombank.refresh_token',
                'account_techcombank.arrangement_id',
            ])
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('account_techcombank.user_id');

                if ($selectedId > 0) {
                    $query->orWhere('account_techcombank.id', $selectedId);
                }
            })
            ->orderByRaw('CASE WHEN account_techcombank.id = ? THEN 0 ELSE 1 END', [$selectedId])
            ->orderByDesc('account_techcombank.id')
            ->get();
    }

    private function selectedTechcombankReceiverAccount(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('account_techcombank')
            ->select([
                'account_techcombank.id',
                'account_techcombank.user_id',
                'account_techcombank.username',
                'account_techcombank.password',
                'account_techcombank.account',
                'account_techcombank.name',
                'account_techcombank.auth_token',
                'account_techcombank.refresh_token',
                'account_techcombank.arrangement_id',
                'account_techcombank.cookie',
                'account_techcombank.is_login',
                'account_techcombank.token',
                'account_techcombank.create_date',
            ])
            ->where('account_techcombank.id', $id)
            ->first();
    }

    private function mbbankReceiverAccounts(Bank $bank)
    {
        $selectedId = (($bank->receiver_bank_type ?? '') === 'MBBANK') ? (int) ($bank->receiver_account_id ?? 0) : 0;

        return DB::table('account_mbbank')
            ->select([
                'account_mbbank.id',
                'account_mbbank.user_id',
                'account_mbbank.username',
                'account_mbbank.account',
                'account_mbbank.name',
                'account_mbbank.token',
                'account_mbbank.session_id',
                'account_mbbank.device_id',
            ])
            ->where(function ($query) use ($selectedId) {
                $query->whereNull('account_mbbank.user_id');

                if ($selectedId > 0) {
                    $query->orWhere('account_mbbank.id', $selectedId);
                }
            })
            ->orderByRaw('CASE WHEN account_mbbank.id = ? THEN 0 ELSE 1 END', [$selectedId])
            ->orderByDesc('account_mbbank.id')
            ->get();
    }

    private function selectedMbbankReceiverAccount(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('account_mbbank')
            ->select([
                'account_mbbank.id',
                'account_mbbank.user_id',
                'account_mbbank.username',
                'account_mbbank.password',
                'account_mbbank.account',
                'account_mbbank.name',
                'account_mbbank.session_id',
                'account_mbbank.device_id',
                'account_mbbank.token',
                'account_mbbank.create_date',
            ])
            ->where('account_mbbank.id', $id)
            ->first();
    }

    private function promoteVcbReceiverAccount(string $username, string $accountNo)
    {
        $row = DB::table('account_vietcombank')
            ->where('user_id', Auth::id())
            ->where('username', $username)
            ->first();

        if (!$row) {
            return null;
        }

        $existing = DB::table('account_vietcombank')
            ->whereNull('user_id')
            ->where('account', $accountNo)
            ->first();

        $payload = [
            'user_id' => null,
            'name' => $row->name,
            'username' => $row->username,
            'password' => $row->password,
            'account' => $accountNo,
            'session_id' => $row->session_id,
            'access_key' => $row->access_key,
            'cif' => $row->cif,
            'mobile_id' => $row->mobile_id,
            'client_id' => $row->client_id,
            'tranId' => $row->tranId,
            'browserToken' => $row->browserToken,
            'token' => $existing->token ?? ($row->token ?: md5(uniqid('', true) . time())),
            'create_date' => $row->create_date ?: now(),
        ];

        if ($existing) {
            DB::table('account_vietcombank')->where('id', $existing->id)->update($payload);
            if ((int) $existing->id !== (int) $row->id) {
                DB::table('account_vietcombank')->where('id', $row->id)->delete();
            }
            return $this->selectedVcbReceiverAccount((int) $existing->id);
        }

        DB::table('account_vietcombank')->where('id', $row->id)->update($payload);
        return $this->selectedVcbReceiverAccount((int) $row->id);
    }

    private function promoteVpbankReceiverAccount(string $username, string $accountNo)
    {
        $row = DB::table('account_vpbank')
            ->where('user_id', Auth::id())
            ->where('username', $username)
            ->first();

        if (!$row) {
            return null;
        }

        $existing = DB::table('account_vpbank')
            ->whereNull('user_id')
            ->where('account', $accountNo)
            ->first();

        $payload = [
            'user_id' => null,
            'username' => $row->username,
            'password' => $row->password,
            'account' => $accountNo,
            'name' => $row->name,
            'token_key' => $row->token_key,
            'csrf' => $row->csrf,
            'cookie' => $row->cookie,
            'is_login' => $row->is_login,
            'token' => $existing->token ?? ($row->token ?: md5(uniqid('', true) . time())),
            'create_date' => $row->create_date ?: now(),
        ];

        if ($existing) {
            DB::table('account_vpbank')->where('id', $existing->id)->update($payload);
            if ((int) $existing->id !== (int) $row->id) {
                DB::table('account_vpbank')->where('id', $row->id)->delete();
            }
            return $this->selectedVpbankReceiverAccount((int) $existing->id);
        }

        DB::table('account_vpbank')->where('id', $row->id)->update($payload);
        return $this->selectedVpbankReceiverAccount((int) $row->id);
    }

    private function promoteTechcombankReceiverAccount(string $username, string $accountNo)
    {
        $row = DB::table('account_techcombank')
            ->where('user_id', Auth::id())
            ->where('username', $username)
            ->where('account', $accountNo)
            ->first();

        if (!$row) {
            return null;
        }

        $existing = DB::table('account_techcombank')
            ->whereNull('user_id')
            ->where('account', $accountNo)
            ->first();

        $payload = [
            'user_id' => null,
            'username' => $row->username,
            'password' => $row->password,
            'account' => $accountNo,
            'name' => $row->name,
            'auth_token' => $row->auth_token,
            'refresh_token' => $row->refresh_token,
            'arrangement_id' => $row->arrangement_id,
            'cookie' => $row->cookie,
            'is_login' => $row->is_login,
            'token' => $existing->token ?? ($row->token ?: md5(uniqid('', true) . time())),
            'balance' => $row->balance,
            'create_date' => $row->create_date ?: now(),
        ];

        if ($existing) {
            DB::table('account_techcombank')->where('id', $existing->id)->update($payload);
            if ((int) $existing->id !== (int) $row->id) {
                DB::table('account_techcombank')->where('id', $row->id)->delete();
            }
            return $this->selectedTechcombankReceiverAccount((int) $existing->id);
        }

        DB::table('account_techcombank')->where('id', $row->id)->update($payload);
        return $this->selectedTechcombankReceiverAccount((int) $row->id);
    }

    private function promoteMbbankReceiverAccount(string $username, string $accountNo)
    {
        $row = DB::table('account_mbbank')
            ->where('user_id', Auth::id())
            ->where('username', $username)
            ->where('account', $accountNo)
            ->first();

        if (!$row) {
            return null;
        }

        $existing = DB::table('account_mbbank')
            ->whereNull('user_id')
            ->where('account', $accountNo)
            ->first();

        $payload = [
            'user_id' => null,
            'username' => $row->username,
            'password' => $row->password,
            'account' => $accountNo,
            'name' => $row->name,
            'session_id' => $row->session_id,
            'device_id' => $row->device_id,
            'token' => $existing->token ?? ($row->token ?: md5(uniqid('', true) . time())),
            'balance' => $row->balance,
            'create_date' => $row->create_date ?: now(),
        ];

        if ($existing) {
            DB::table('account_mbbank')->where('id', $existing->id)->update($payload);
            if ((int) $existing->id !== (int) $row->id) {
                DB::table('account_mbbank')->where('id', $row->id)->delete();
            }
            return $this->selectedMbbankReceiverAccount((int) $existing->id);
        }

        DB::table('account_mbbank')->where('id', $row->id)->update($payload);
        return $this->selectedMbbankReceiverAccount((int) $row->id);
    }

    private function selectVcbReceiverBank($account): void
    {
        $bank = $this->vcbRechargeBank();
        $bank->fill([
            'short_name' => 'Vietcombank',
            'image' => $bank->image ?: 'public/assets/storage/images/bankVCB.png',
            'accountNumber' => trim((string) $account->account),
            'accountName' => trim((string) ($account->name ?: 'VCB RECEIVER')),
            'codebank' => self::VCB_BIN,
            'receiver_bank_type' => 'VCB',
            'receiver_account_id' => (int) $account->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks(self::VCB_BIN);
    }

    private function selectVpbankReceiverBank($account): void
    {
        $bank = $this->vpbankRechargeBank();
        $bank->fill([
            'short_name' => 'VPBank',
            'image' => $bank->image ?: 'public/assets/storage/images/bankVPB.png',
            'accountNumber' => trim((string) $account->account),
            'accountName' => trim((string) ($account->name ?: 'VPBank RECEIVER')),
            'codebank' => self::VPBANK_BIN,
            'receiver_bank_type' => 'VPBANK',
            'receiver_account_id' => (int) $account->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks(self::VPBANK_BIN);
    }

    private function selectTechcombankReceiverBank($account): void
    {
        $bank = $this->techcombankRechargeBank();
        $bank->fill([
            'short_name' => 'Techcombank',
            'image' => $bank->image ?: 'public/assets/storage/images/bankTCB.png',
            'accountNumber' => trim((string) $account->account),
            'accountName' => trim((string) ($account->name ?: 'Techcombank RECEIVER')),
            'codebank' => self::TECHCOMBANK_BIN,
            'receiver_bank_type' => 'TECHCOMBANK',
            'receiver_account_id' => (int) $account->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks(self::TECHCOMBANK_BIN);
    }

    private function selectMbbankReceiverBank($account): void
    {
        $bank = $this->mbbankRechargeBank();
        $bank->fill([
            'short_name' => 'MBBank',
            'image' => $bank->image ?: 'public/assets/storage/images/bankMBB.png',
            'accountNumber' => trim((string) $account->account),
            'accountName' => trim((string) ($account->name ?: 'MBBank RECEIVER')),
            'codebank' => self::MBBANK_BIN,
            'receiver_bank_type' => 'MBBANK',
            'receiver_account_id' => (int) $account->id,
        ]);
        $bank->save();
        $this->deactivateOtherReceiverBanks(self::MBBANK_BIN);
    }


    private function payloadFromPaymentResponse($response): array
    {
        if ($response instanceof JsonResponse) {
            return (array) $response->getData(true);
        }

        if ($response instanceof Response || (is_object($response) && method_exists($response, 'getContent'))) {
            $decoded = json_decode((string) $response->getContent(), true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function loginAcb(string $username, string $password): ?array
    {
        $url = 'https://apiapp.acb.com.vn/mb/v2/auth/tokens';
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ];
        $payload = [
            'clientId' => 'iuSuHYVufIUuNIREV0FB9EoLn9kHsDbm',
            'username' => $username,
            'password' => $password,
        ];

        $response = $this->callApi($url, $headers, json_encode($payload), 'POST', 20);
        return $response ? json_decode($response, true) : null;
    }

    private function findAcbAccountByNumber(string $token, string $accountNumber): ?array
    {
        $url = 'https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/list/account-payment';
        $headers = [
            'Content-Type: application/json',
            'Host: apiapp.acb.com.vn',
            'Authorization: bearer ' . $token,
        ];

        $response = $this->callApi($url, $headers, null, 'GET', 20);
        $decoded = $response ? json_decode($response, true) : null;
        if (!is_array($decoded) || (int) ($decoded['codeStatus'] ?? 0) !== 200) {
            return null;
        }

        foreach (($decoded['data'] ?? []) as $account) {
            if (trim((string) ($account['accountNumber'] ?? '')) === $accountNumber) {
                return $account;
            }
        }

        return null;
    }

    private function callApi(string $url, array $headers, ?string $data = null, string $method = 'POST', int $timeout = 10): ?string
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => '',
        ];

        if ($method === 'POST' && $data !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $error ? null : $body;
    }

    private function rechargeCode(Bank $bank, User $user): string
    {
        return trim((string) $bank->noidungnap) . $user->id;
    }

    private function vietQrImageUrl(Bank $bank, string $addInfo, int $amount = 0): string
    {
        $template = trim((string) ($bank->vietqr_template ?: self::DEFAULT_TEMPLATE));
        $url = 'https://img.vietqr.io/image/'
            . rawurlencode((string) $bank->codebank)
            . '-'
            . rawurlencode((string) $bank->accountNumber)
            . '-'
            . rawurlencode($template)
            . '.png?addInfo='
            . rawurlencode($addInfo)
            . '&accountName='
            . rawurlencode((string) $bank->accountName);

        if ($amount > 0) {
            $url .= '&amount=' . (int) $amount;
        }

        return $url;
    }

    private function quickAmounts(Bank $bank): array
    {
        return $this->normaliseQuickAmounts((string) ($bank->quick_amounts ?: self::DEFAULT_QUICK_AMOUNTS));
    }

    private function normaliseQuickAmounts(string $value): array
    {
        $amounts = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $amounts = array_map(static function ($amount) {
            return (int) preg_replace('/\D+/', '', (string) $amount);
        }, $amounts ?: []);
        $amounts = array_values(array_unique(array_filter($amounts, static function ($amount) {
            return $amount > 0;
        })));
        sort($amounts);

        return $amounts;
    }

    private function invoicePayload($invoice): array
    {
        return [
            'id' => $invoice->id ?? null,
            'trans_id' => $invoice->trans_id ?? null,
            'payment_method' => $invoice->payment_method ?? null,
            'amount' => (int) ($invoice->amount ?? 0),
            'description' => $invoice->description ?? '',
            'create_time' => (int) ($invoice->create_time ?? 0),
        ];
    }

    private function receiverTypeFromBankParam(string $bank): ?string
    {
        return match ($bank) {
            'acb' => 'ACB',
            'vcb' => 'VCB',
            'vpbank' => 'VPBANK',
            'techcombank' => 'TECHCOMBANK',
            'mbbank' => 'MBBANK',
            default => null,
        };
    }

    private function receiverBankParam(string $type): string
    {
        return match ($type) {
            'VCB' => 'vcb',
            'VPBANK' => 'vpbank',
            'TECHCOMBANK' => 'techcombank',
            'MBBANK' => 'mbbank',
            default => 'acb',
        };
    }

    private function receiverAccountTable(string $type): string
    {
        return match ($type) {
            'VCB' => 'account_vietcombank',
            'VPBANK' => 'account_vpbank',
            'TECHCOMBANK' => 'account_techcombank',
            'MBBANK' => 'account_mbbank',
            default => 'account_acb',
        };
    }

    private function receiverAccountRow(string $type, int $id)
    {
        return DB::table($this->receiverAccountTable($type))
            ->where('id', $id)
            ->whereNull('user_id')
            ->first();
    }

    private function receiverAccountByToken(string $type, string $token)
    {
        return DB::table($this->receiverAccountTable($type))
            ->where('token', $token)
            ->first();
    }

    private function receiverAccountNumber(string $type, $account): string
    {
        return trim((string) match ($type) {
            'VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK' => $account->account ?? '',
            default => $account->stk ?? '',
        });
    }

    private function receiverAccountName($account): string
    {
        return trim((string) ($account->name ?? ''));
    }

    private function receiverSessionField(string $type): string
    {
        return match ($type) {
            'VCB' => 'session_id',
            'VPBANK' => 'token_key',
            'TECHCOMBANK' => 'refresh_token',
            'MBBANK' => 'session_id',
            default => 'sessionId',
        };
    }

    private function validateReceiverAccountUpdate(Request $request, string $type): array
    {
        if ($type === 'ACB') {
            return $request->validate([
                'phone' => ['required', 'string', 'max:64'],
                'stk' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
                'name' => ['nullable', 'string', 'max:128'],
                'password' => ['nullable', 'string', 'max:255'],
            ], [
                'phone.required' => 'Vui lòng nhập tài khoản ACB.',
                'stk.required' => 'Vui lòng nhập số tài khoản ACB.',
                'stk.regex' => 'Số tài khoản ACB chỉ gồm chữ số.',
            ]);
        }

        $prefix = match ($type) {
            'VCB' => 'vcb',
            'TECHCOMBANK' => 'techcombank',
            'MBBANK' => 'mbbank',
            default => 'vpbank',
        };
        $label = $this->bankLabel($type);

        return $request->validate([
            $prefix . '_username' => ['required', 'string', 'max:64'],
            $prefix . '_account_no' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            $prefix . '_name' => ['nullable', 'string', 'max:128'],
            $prefix . '_password' => ['nullable', 'string', 'max:255'],
        ], [
            $prefix . '_username.required' => 'Vui lòng nhập tài khoản ' . $label . '.',
            $prefix . '_account_no.required' => 'Vui lòng nhập số tài khoản ' . $label . '.',
            $prefix . '_account_no.regex' => 'Số tài khoản ' . $label . ' chỉ gồm chữ số.',
        ]);
    }

    private function receiverAccountUpdatePayload(array $validated, string $type): array
    {
        if ($type === 'ACB') {
            $payload = [
                'phone' => trim((string) $validated['phone']),
                'stk' => trim((string) $validated['stk']),
                'name' => trim((string) ($validated['name'] ?? '')),
            ];
            if (!empty($validated['password'])) {
                $payload['password'] = (string) $validated['password'];
            }

            return $payload;
        }

        $prefix = match ($type) {
            'VCB' => 'vcb',
            'TECHCOMBANK' => 'techcombank',
            'MBBANK' => 'mbbank',
            default => 'vpbank',
        };
        $payload = [
            'username' => trim((string) $validated[$prefix . '_username']),
            'account' => trim((string) $validated[$prefix . '_account_no']),
            'name' => trim((string) ($validated[$prefix . '_name'] ?? '')),
        ];
        if (!empty($validated[$prefix . '_password'])) {
            $payload['password'] = (string) $validated[$prefix . '_password'];
        }

        return $payload;
    }

    private function syncActiveRechargeBankIfNeeded(string $type, $account): void
    {
        if (!$account) {
            return;
        }

        $bank = Bank::where('receiver_bank_type', $type)
            ->where('receiver_account_id', (int) $account->id)
            ->first();
        if (!$bank) {
            return;
        }

        $accountNumber = $this->receiverAccountNumber($type, $account);
        $accountName = $this->receiverAccountName($account) ?: ($this->bankLabel($type) . ' RECEIVER');

        $bank->fill([
            'short_name' => $this->bankLabel($type),
            'image' => $bank->image ?: $this->bankImage($type),
            'accountNumber' => $accountNumber,
            'accountName' => $accountName,
            'codebank' => $this->bankBin($type),
        ]);
        $bank->save();
    }

    private function redirectRechargeAccounts(?string $success = null)
    {
        $redirect = redirect()->route('admin.recharge-settings.edit', ['tab' => 'accounts']);

        return $success ? $redirect->with('success', $success) : $redirect;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || str_contains((string) $request->header('Accept'), 'application/json');
    }

    private function isSuperAdmin(): bool
    {
        return Auth::check() && (int) Auth::user()->role === 1;
    }
}
