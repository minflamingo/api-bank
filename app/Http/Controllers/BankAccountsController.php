<?php

namespace App\Http\Controllers;

use App\Models\AccountAcb;
use App\Models\AccountMbbank;
use App\Models\AccountTechcombank;
use App\Models\AccountVpbank;
use App\Models\AccountVietcombank;
use App\Support\ApiPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BankAccountsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập');
        }

        $vcb = AccountVietcombank::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->vcbItem($row));

        $acb = AccountAcb::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->acbItem($row));

        $vpbank = AccountVpbank::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->vpbankItem($row));

        $techcombank = AccountTechcombank::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->techcombankItem($row));

        $mbbank = AccountMbbank::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->mbbankItem($row));

        $accounts = collect($vcb->all())->merge($acb->all())->merge($vpbank->all())->merge($techcombank->all())->merge($mbbank->all())
            ->sortByDesc('sort_time')
            ->values();

        $stats = [
            'total' => $accounts->count(),
            'vcb' => $accounts->where('bank', 'vcb')->count(),
            'acb' => $accounts->where('bank', 'acb')->count(),
            'vpbank' => $accounts->where('bank', 'vpbank')->count(),
            'techcombank' => $accounts->where('bank', 'techcombank')->count(),
            'mbbank' => $accounts->where('bank', 'mbbank')->count(),
            'has_token' => $accounts->filter(fn ($a) => !empty($a->token))->count(),
        ];

        return view('bank-accounts.index', compact('accounts', 'stats'));
    }

    public function adminIndex(Request $request)
    {
        abort_unless(Auth::check() && (int) Auth::user()->role === 1, 403);

        $bankFilter = in_array((string) $request->query('bank'), ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'], true)
            ? (string) $request->query('bank')
            : 'all';
        $keyword = trim((string) $request->query('q', ''));
        $receiverBank = DB::table('bank')
            ->whereIn('receiver_bank_type', ['ACB', 'VCB', 'VPBANK', 'TECHCOMBANK', 'MBBANK'])
            ->whereNotNull('receiver_account_id')
            ->first();

        $query = DB::query()->fromSub(
            $this->adminAccountsUnion($bankFilter, $keyword),
            'bank_accounts'
        );

        $accounts = $query
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('sort_time')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $accounts->getCollection()->transform(function ($row) use ($receiverBank) {
            return $this->adminAccountItem($row, $receiverBank);
        });

        $stats = [
            'total' => DB::table('account_acb')->count()
                + DB::table('account_vietcombank')->count()
                + DB::table('account_vpbank')->count()
                + DB::table('account_techcombank')->count()
                + DB::table('account_mbbank')->count(),
            'acb' => DB::table('account_acb')->count(),
            'vcb' => DB::table('account_vietcombank')->count(),
            'vpbank' => DB::table('account_vpbank')->count(),
            'techcombank' => DB::table('account_techcombank')->count(),
            'mbbank' => DB::table('account_mbbank')->count(),
            'system' => DB::table('account_acb')->whereNull('user_id')->count()
                + DB::table('account_vietcombank')->whereNull('user_id')->count()
                + DB::table('account_vpbank')->whereNull('user_id')->count()
                + DB::table('account_techcombank')->whereNull('user_id')->count()
                + DB::table('account_mbbank')->whereNull('user_id')->count(),
            'has_token' => DB::table('account_acb')->whereNotNull('token')->where('token', '<>', '')->count()
                + DB::table('account_vietcombank')->whereNotNull('token')->where('token', '<>', '')->count()
                + DB::table('account_vpbank')->whereNotNull('token')->where('token', '<>', '')->count()
                + DB::table('account_techcombank')->whereNotNull('token')->where('token', '<>', '')->count()
                + DB::table('account_mbbank')->whereNotNull('token')->where('token', '<>', '')->count(),
        ];

        return view('admin.bank-accounts', compact(
            'accounts',
            'stats',
            'bankFilter',
            'keyword',
            'receiverBank'
        ));
    }

    public function create(Request $request)
    {
        $defaultBank = in_array((string) $request->query('bank'), ['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'], true)
            ? (string) $request->query('bank')
            : 'acb';
        $editAccount = $this->bankAccountEditDefaults($request, $defaultBank);
        if ($editAccount) {
            $defaultBank = (string) $editAccount['bank_code'];
        }

        return view('bank-accounts.create', [
            'defaultBank' => $defaultBank,
            'pendingVcb' => (array) session('bank_accounts_vcb_pending', []),
            'pendingVpbank' => (array) session('bank_accounts_vpbank_pending', []),
            'pendingTechcombank' => (array) session('bank_accounts_techcombank_pending', []),
            'editAccount' => $editAccount,
            'accountLimit' => ApiPackage::userLimit(Auth::user()),
        ]);
    }

    public function store(Request $request, PaymentController $payment)
    {
        $step = (string) $request->input('step', 'init');

        if ($step === 'otp') {
            $data = $request->validate([
                'step' => ['required', Rule::in(['otp'])],
                'bank_code' => ['required', Rule::in(['vcb', 'vpbank', 'techcombank'])],
                'username' => ['required', 'string', 'max:64'],
                'password' => ['required', 'string', 'max:128'],
                'account_no' => ['required', 'string', 'max:32'],
                'otp_code' => ['nullable', 'string', 'max:12'],
            ], $this->validationMessages(), $this->validationAttributes());

            $request->merge([
                'account' => $data['username'],
                'password' => $data['password'],
                'stk' => $data['account_no'],
                'otp' => $data['otp_code'] ?? '',
            ]);

            $payload = $this->payloadFrom(match ($data['bank_code']) {
                'vpbank' => $payment->vpbankLoginOTP($request),
                'techcombank' => $payment->techcombankConfirmLogin($request),
                default => $payment->vcbLoginOTP($request),
            });
            $ok = (string) ($payload['status'] ?? '') === '2';

            if ($ok) {
                session()->forget(match ($data['bank_code']) {
                    'vpbank' => 'bank_accounts_vpbank_pending',
                    'techcombank' => 'bank_accounts_techcombank_pending',
                    default => 'bank_accounts_vcb_pending',
                });
            }

            return $this->respondConnect($request, [
                'ok' => $ok,
                'message' => (string) ($payload['msg'] ?? ($ok ? 'Đã lưu tài khoản ngân hàng.' : 'Không xác thực được OTP.')),
                'redirect_url' => route('bank.accounts.index'),
            ], $ok ? 200 : 422);
        }

        $data = $request->validate([
            'step' => ['required', Rule::in(['init'])],
                'bank_code' => ['required', Rule::in(['acb', 'vcb', 'vpbank', 'techcombank', 'mbbank'])],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
            'account_no' => ['required', 'string', 'max:32'],
        ], $this->validationMessages(), $this->validationAttributes());

        $request->merge([
            'account' => $data['username'],
            'password' => $data['password'],
            'stk' => $data['account_no'],
        ]);

        if ($data['bank_code'] === 'acb') {
            $payload = $this->payloadFrom($payment->acbLogin($request));
            $ok = (string) ($payload['status'] ?? '') === '2';

            return $this->respondConnect($request, [
                'ok' => $ok,
                'message' => (string) ($payload['msg'] ?? ($ok ? 'Đã thêm tài khoản ACB.' : 'Không thêm được tài khoản ACB.')),
                'redirect_url' => route('bank.accounts.index'),
            ], $ok ? 200 : 422);
        }

        if ($data['bank_code'] === 'vpbank') {
            $payload = $this->payloadFrom($payment->vpbankLogin($request));
            $status = (string) ($payload['status'] ?? '');

            if ($status === '3') {
                session()->forget('bank_accounts_vpbank_pending');

                return $this->respondConnect($request, [
                    'ok' => true,
                    'message' => (string) ($payload['msg'] ?? 'Đăng nhập VPBank thành công.'),
                    'redirect_url' => route('bank.accounts.index'),
                ]);
            }

            if ($status === '2') {
                session()->put('bank_accounts_vpbank_pending', [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'account_no' => $data['account_no'],
                ]);

                return $this->respondConnect($request, [
                    'ok' => false,
                    'needs_otp' => true,
                    'bank_code' => 'vpbank',
                    'message' => (string) ($payload['msg'] ?? 'VPBank yêu cầu OTP.'),
                ]);
            }

            return $this->respondConnect($request, [
                'ok' => false,
                'message' => (string) ($payload['msg'] ?? 'Không kết nối được VPBank.'),
            ], 422);
        }

        if ($data['bank_code'] === 'techcombank') {
            $payload = $this->payloadFrom($payment->techcombankLogin($request));
            $status = (string) ($payload['status'] ?? '');

            if ($status === '2') {
                session()->put('bank_accounts_techcombank_pending', [
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'account_no' => $data['account_no'],
                ]);

                return $this->respondConnect($request, [
                    'ok' => false,
                    'needs_otp' => true,
                    'bank_code' => 'techcombank',
                    'message' => (string) ($payload['msg'] ?? 'Techcombank đã gửi yêu cầu xác nhận tới app Mobile.'),
                ]);
            }

            return $this->respondConnect($request, [
                'ok' => false,
                'message' => (string) ($payload['msg'] ?? 'Không kết nối được Techcombank.'),
            ], 422);
        }

        if ($data['bank_code'] === 'mbbank') {
            $payload = $this->payloadFrom($payment->mbbankLogin($request));
            $ok = (string) ($payload['status'] ?? '') === '2';

            return $this->respondConnect($request, [
                'ok' => $ok,
                'message' => (string) ($payload['msg'] ?? ($ok ? 'Đã thêm tài khoản MBBank.' : 'Không thêm được tài khoản MBBank.')),
                'redirect_url' => route('bank.accounts.index'),
            ], $ok ? 200 : 422);
        }

        $payload = $this->payloadFrom($payment->vcbGetOtp($request));
        $status = (string) ($payload['status'] ?? '');

        if ($status === '3') {
            session()->forget('bank_accounts_vcb_pending');

            return $this->respondConnect($request, [
                'ok' => true,
                'message' => (string) ($payload['msg'] ?? 'Đăng nhập Vietcombank thành công.'),
                'redirect_url' => route('bank.accounts.index'),
            ]);
        }

        if ($status === '2') {
            session()->put('bank_accounts_vcb_pending', [
                'username' => $data['username'],
                'password' => $data['password'],
                'account_no' => $data['account_no'],
            ]);

            return $this->respondConnect($request, [
                'ok' => false,
                'needs_otp' => true,
                'bank_code' => 'vcb',
                'message' => (string) ($payload['msg'] ?? 'Vietcombank đã gửi OTP.'),
            ]);
        }

        return $this->respondConnect($request, [
            'ok' => false,
            'message' => (string) ($payload['msg'] ?? 'Không kết nối được Vietcombank.'),
        ], 422);
    }

    public function token(Request $request, PaymentController $payment, string $bank, int $id)
    {
        $request->merge(['id' => $id]);
        $payload = $this->payloadFrom(match ($bank) {
            'acb' => $payment->acbSendToken($request),
            'vpbank' => $payment->vpbankSendToken($request),
            'techcombank' => $payment->techcombankSendToken($request),
            'mbbank' => $payment->mbbankSendToken($request),
            default => $payment->vcbSendToken($request),
        });

        return response()->json($payload, (string) ($payload['status'] ?? '') === '2' ? 200 : 422);
    }

    public function destroy(Request $request, PaymentController $payment, string $bank, int $id)
    {
        $request->merge(['id' => $id]);
        $payload = $this->payloadFrom(match ($bank) {
            'acb' => $payment->acbRemove($request),
            'vpbank' => $payment->vpbankRemove($request),
            'techcombank' => $payment->techcombankRemove($request),
            'mbbank' => $payment->mbbankRemove($request),
            default => $payment->vcbRemove($request),
        });

        return response()->json($payload, (string) ($payload['status'] ?? '') === '2' ? 200 : 422);
    }

    private function respondConnect(Request $request, array $payload, int $status = 200)
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json($payload, $status);
        }

        if (!empty($payload['needs_otp'])) {
            return redirect()
                ->route('bank.accounts.create', ['bank' => $payload['bank_code'] ?? 'vcb', 'otp' => 1])
                ->with('warning', (string) $payload['message']);
        }

        if (!empty($payload['ok'])) {
            return redirect($payload['redirect_url'] ?? route('bank.accounts.index'))
                ->with('success', (string) $payload['message']);
        }

        return back()
            ->withInput($request->except('password'))
            ->withErrors(['bank' => (string) ($payload['message'] ?? 'Thao tác thất bại.')]);
    }

    private function payloadFrom($response): array
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

    private function validationMessages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute không hợp lệ.',
            'max' => ':attribute tối đa :max ký tự.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    private function validationAttributes(): array
    {
        return [
            'step' => 'bước xử lý',
            'bank_code' => 'ngân hàng',
            'username' => 'tài khoản đăng nhập',
            'password' => 'mật khẩu ngân hàng',
            'account_no' => 'số tài khoản',
            'otp_code' => 'mã OTP',
        ];
    }

    private function adminAccountsUnion(string $bankFilter, string $keyword)
    {
        $vcb = DB::table('account_vietcombank')
            ->leftJoin('users', 'account_vietcombank.user_id', '=', 'users.id')
            ->selectRaw("
                'vcb' as bank,
                account_vietcombank.id,
                account_vietcombank.user_id,
                account_vietcombank.username as login_name,
                account_vietcombank.account as account_no,
                account_vietcombank.name as account_name,
                account_vietcombank.token,
                account_vietcombank.session_id as session_value,
                account_vietcombank.create_date as created_raw,
                COALESCE(UNIX_TIMESTAMP(account_vietcombank.create_date), account_vietcombank.id) as sort_time,
                users.name as owner_name,
                users.email as owner_email,
                users.role as owner_role
            ");

        $acb = DB::table('account_acb')
            ->leftJoin('users', 'account_acb.user_id', '=', 'users.id')
            ->selectRaw("
                'acb' as bank,
                account_acb.id,
                account_acb.user_id,
                account_acb.phone as login_name,
                account_acb.stk as account_no,
                account_acb.name as account_name,
                account_acb.token,
                account_acb.sessionId as session_value,
                account_acb.time as created_raw,
                CAST(COALESCE(account_acb.time, account_acb.id) AS UNSIGNED) as sort_time,
                users.name as owner_name,
                users.email as owner_email,
                users.role as owner_role
            ");

        $vpbank = DB::table('account_vpbank')
            ->leftJoin('users', 'account_vpbank.user_id', '=', 'users.id')
            ->selectRaw("
                'vpbank' as bank,
                account_vpbank.id,
                account_vpbank.user_id,
                account_vpbank.username as login_name,
                account_vpbank.account as account_no,
                account_vpbank.name as account_name,
                account_vpbank.token,
                account_vpbank.token_key as session_value,
                account_vpbank.create_date as created_raw,
                COALESCE(UNIX_TIMESTAMP(account_vpbank.create_date), account_vpbank.id) as sort_time,
                users.name as owner_name,
                users.email as owner_email,
                users.role as owner_role
            ");

        $techcombank = DB::table('account_techcombank')
            ->leftJoin('users', 'account_techcombank.user_id', '=', 'users.id')
            ->selectRaw("
                'techcombank' as bank,
                account_techcombank.id,
                account_techcombank.user_id,
                account_techcombank.username as login_name,
                account_techcombank.account as account_no,
                account_techcombank.name as account_name,
                account_techcombank.token,
                account_techcombank.refresh_token as session_value,
                account_techcombank.create_date as created_raw,
                COALESCE(UNIX_TIMESTAMP(account_techcombank.create_date), account_techcombank.id) as sort_time,
                users.name as owner_name,
                users.email as owner_email,
                users.role as owner_role
            ");

        $mbbank = DB::table('account_mbbank')
            ->leftJoin('users', 'account_mbbank.user_id', '=', 'users.id')
            ->selectRaw("
                'mbbank' as bank,
                account_mbbank.id,
                account_mbbank.user_id,
                account_mbbank.username as login_name,
                account_mbbank.account as account_no,
                account_mbbank.name as account_name,
                account_mbbank.token,
                account_mbbank.session_id as session_value,
                account_mbbank.create_date as created_raw,
                COALESCE(UNIX_TIMESTAMP(account_mbbank.create_date), account_mbbank.id) as sort_time,
                users.name as owner_name,
                users.email as owner_email,
                users.role as owner_role
            ");

        $this->applyAdminAccountSearch($vcb, $keyword, 'account_vietcombank', ['username', 'account', 'name']);
        $this->applyAdminAccountSearch($acb, $keyword, 'account_acb', ['phone', 'stk', 'name']);
        $this->applyAdminAccountSearch($vpbank, $keyword, 'account_vpbank', ['username', 'account', 'name']);
        $this->applyAdminAccountSearch($techcombank, $keyword, 'account_techcombank', ['username', 'account', 'name']);
        $this->applyAdminAccountSearch($mbbank, $keyword, 'account_mbbank', ['username', 'account', 'name']);

        if ($bankFilter === 'vcb') {
            return $vcb;
        }

        if ($bankFilter === 'acb') {
            return $acb;
        }

        if ($bankFilter === 'vpbank') {
            return $vpbank;
        }

        if ($bankFilter === 'techcombank') {
            return $techcombank;
        }

        if ($bankFilter === 'mbbank') {
            return $mbbank;
        }

        return $vcb->unionAll($acb)->unionAll($vpbank)->unionAll($techcombank)->unionAll($mbbank);
    }

    private function applyAdminAccountSearch($query, string $keyword, string $table, array $columns): void
    {
        if ($keyword === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
        $query->where(function ($subQuery) use ($like, $table, $columns) {
            $subQuery
                ->where('users.name', 'like', $like)
                ->orWhere('users.email', 'like', $like);

            foreach ($columns as $column) {
                $subQuery->orWhere($table . '.' . $column, 'like', $like);
            }
        });
    }

    private function adminAccountItem($row, $receiverBank): object
    {
        $bank = (string) $row->bank;
        $isAcb = $bank === 'acb';
        $createdRaw = (string) ($row->created_raw ?? '');
        $createdTime = $isAcb
            ? (int) $createdRaw
            : (strtotime($createdRaw) ?: (int) ($row->sort_time ?? 0));
        $bankLabel = match ($bank) {
            'acb' => 'ACB',
            'vpbank' => 'VPBank',
            'techcombank' => 'Techcombank',
            'mbbank' => 'MBBank',
            default => 'Vietcombank',
        };
        $bankBadge = match ($bank) {
            'acb' => 'ACB',
            'vpbank' => 'VPB',
            'techcombank' => 'TCB',
            'mbbank' => 'MBB',
            default => 'VCB',
        };

        return (object) [
            'bank' => $bank,
            'bank_label' => $bankLabel,
            'bank_badge' => $bankBadge,
            'id' => (int) $row->id,
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'owner_name' => (string) ($row->owner_name ?: ''),
            'owner_email' => (string) ($row->owner_email ?: ''),
            'owner_role' => $row->owner_role === null ? null : (int) $row->owner_role,
            'login_name' => (string) ($row->login_name ?: '-'),
            'account_no' => (string) ($row->account_no ?: '-'),
            'account_name' => (string) ($row->account_name ?: 'Chưa cập nhật'),
            'token' => (string) ($row->token ?: ''),
            'has_token' => trim((string) $row->token) !== '',
            'has_session' => trim((string) $row->session_value) !== '',
            'created_text' => $createdTime > 0 ? date('H:i d/m/Y', $createdTime) : '-',
            'is_receiver' => $receiverBank
                && $this->receiverBankTypeMatchesRow((string) ($receiverBank->receiver_bank_type ?? ''), $bank)
                && (int) ($receiverBank->receiver_account_id ?? 0) === (int) $row->id,
        ];
    }

    private function receiverBankTypeMatchesRow(string $receiverBankType, string $rowBank): bool
    {
        return match ($receiverBankType) {
            'ACB' => $rowBank === 'acb',
            'VCB' => $rowBank === 'vcb',
            'VPBANK' => $rowBank === 'vpbank',
            'TECHCOMBANK' => $rowBank === 'techcombank',
            'MBBANK' => $rowBank === 'mbbank',
            default => false,
        };
    }

    private function vcbItem(AccountVietcombank $row): object
    {
        $time = strtotime((string) $row->create_date) ?: 0;

        return (object) [
            'bank' => 'vcb',
            'bank_label' => 'Vietcombank',
            'bank_badge' => 'VCB',
            'id' => (int) $row->id,
            'username' => (string) $row->username,
            'account_no' => (string) $row->account,
            'account_name' => (string) ($row->name ?: 'Chưa cập nhật'),
            'token' => (string) $row->token,
            'created_text' => $row->create_date ? date('H:i d/m/Y', $time ?: time()) : '-',
            'sort_time' => $time,
            'balance_url' => route('api.vcb.balance', $row->token ?: 'missing'),
            'history_url' => route('payment.vcb.history', $row->account),
            'token_url' => route('bank.accounts.token', ['bank' => 'vcb', 'id' => $row->id]),
            'delete_url' => route('bank.accounts.destroy', ['bank' => 'vcb', 'id' => $row->id]),
            'edit_url' => route('bank.accounts.create', ['bank' => 'vcb', 'edit' => $row->id]),
        ];
    }

    private function acbItem(AccountAcb $row): object
    {
        $time = (int) ($row->time ?: 0);

        return (object) [
            'bank' => 'acb',
            'bank_label' => 'ACB',
            'bank_badge' => 'ACB',
            'id' => (int) $row->id,
            'username' => (string) $row->phone,
            'account_no' => (string) $row->stk,
            'account_name' => (string) ($row->name ?: 'Chưa cập nhật'),
            'token' => (string) $row->token,
            'created_text' => $time > 0 ? date('H:i d/m/Y', $time) : '-',
            'sort_time' => $time,
            'balance_url' => route('payment.acb.balance', $row->token ?: 'missing'),
            'history_url' => route('payment.acb.history', $row->stk),
            'token_url' => route('bank.accounts.token', ['bank' => 'acb', 'id' => $row->id]),
            'delete_url' => route('bank.accounts.destroy', ['bank' => 'acb', 'id' => $row->id]),
            'edit_url' => route('bank.accounts.create', ['bank' => 'acb', 'edit' => $row->id]),
        ];
    }

    private function vpbankItem(AccountVpbank $row): object
    {
        $time = strtotime((string) $row->create_date) ?: 0;

        return (object) [
            'bank' => 'vpbank',
            'bank_label' => 'VPBank',
            'bank_badge' => 'VPB',
            'id' => (int) $row->id,
            'username' => (string) $row->username,
            'account_no' => (string) $row->account,
            'account_name' => (string) ($row->name ?: 'Chưa cập nhật'),
            'token' => (string) $row->token,
            'created_text' => $row->create_date ? date('H:i d/m/Y', $time ?: time()) : '-',
            'sort_time' => $time,
            'balance_url' => route('api.vpbank.balance', $row->token ?: 'missing'),
            'history_url' => route('payment.vpbank.history', $row->account),
            'token_url' => route('bank.accounts.token', ['bank' => 'vpbank', 'id' => $row->id]),
            'delete_url' => route('bank.accounts.destroy', ['bank' => 'vpbank', 'id' => $row->id]),
            'edit_url' => route('bank.accounts.create', ['bank' => 'vpbank', 'edit' => $row->id]),
        ];
    }

    private function techcombankItem(AccountTechcombank $row): object
    {
        $time = strtotime((string) $row->create_date) ?: 0;

        return (object) [
            'bank' => 'techcombank',
            'bank_label' => 'Techcombank',
            'bank_badge' => 'TCB',
            'id' => (int) $row->id,
            'username' => (string) $row->username,
            'account_no' => (string) $row->account,
            'account_name' => (string) ($row->name ?: 'Chưa cập nhật'),
            'token' => (string) $row->token,
            'created_text' => $row->create_date ? date('H:i d/m/Y', $time ?: time()) : '-',
            'sort_time' => $time,
            'balance_url' => route('api.techcombank.balance', $row->token ?: 'missing'),
            'history_url' => route('payment.techcombank.history', $row->account),
            'token_url' => route('bank.accounts.token', ['bank' => 'techcombank', 'id' => $row->id]),
            'delete_url' => route('bank.accounts.destroy', ['bank' => 'techcombank', 'id' => $row->id]),
            'edit_url' => route('bank.accounts.create', ['bank' => 'techcombank', 'edit' => $row->id]),
        ];
    }

    private function mbbankItem(AccountMbbank $row): object
    {
        $time = strtotime((string) $row->create_date) ?: 0;

        return (object) [
            'bank' => 'mbbank',
            'bank_label' => 'MBBank',
            'bank_badge' => 'MBB',
            'id' => (int) $row->id,
            'username' => (string) $row->username,
            'account_no' => (string) $row->account,
            'account_name' => (string) ($row->name ?: 'Chưa cập nhật'),
            'token' => (string) $row->token,
            'created_text' => $row->create_date ? date('H:i d/m/Y', $time ?: time()) : '-',
            'sort_time' => $time,
            'balance_url' => route('api.mbbank.balance', $row->token ?: 'missing'),
            'history_url' => route('payment.mbbank.history', $row->account),
            'token_url' => route('bank.accounts.token', ['bank' => 'mbbank', 'id' => $row->id]),
            'delete_url' => route('bank.accounts.destroy', ['bank' => 'mbbank', 'id' => $row->id]),
            'edit_url' => route('bank.accounts.create', ['bank' => 'mbbank', 'edit' => $row->id]),
        ];
    }

    private function bankAccountEditDefaults(Request $request, string $bank): ?array
    {
        $editId = (int) $request->query('edit', 0);
        $userId = Auth::id();
        if ($editId <= 0 || !$userId) {
            return null;
        }

        $row = match ($bank) {
            'acb' => AccountAcb::where('id', $editId)->where('user_id', $userId)->first(),
            'vcb' => AccountVietcombank::where('id', $editId)->where('user_id', $userId)->first(),
            'vpbank' => AccountVpbank::where('id', $editId)->where('user_id', $userId)->first(),
            'techcombank' => AccountTechcombank::where('id', $editId)->where('user_id', $userId)->first(),
            'mbbank' => AccountMbbank::where('id', $editId)->where('user_id', $userId)->first(),
            default => null,
        };

        if (!$row) {
            return null;
        }

        return [
            'bank_code' => $bank,
            'username' => $bank === 'acb' ? (string) $row->phone : (string) $row->username,
            'account_no' => $bank === 'acb' ? (string) $row->stk : (string) $row->account,
            'account_name' => (string) ($row->name ?? ''),
        ];
    }
}
