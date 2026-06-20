<?php

namespace App\Http\Controllers;

use App\Support\WalletLedger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function index(Request $request)
    {
        return $this->dashboard($request, 'overview');
    }

    public function users(Request $request)
    {
        return $this->dashboard($request, 'users');
    }

    public function sessions(Request $request)
    {
        return $this->dashboard($request, 'sessions');
    }

    public function recharges(Request $request)
    {
        return $this->dashboard($request, 'recharges');
    }

    public function logs(Request $request)
    {
        return $this->dashboard($request, 'logs');
    }

    public function wallet(Request $request)
    {
        return $this->dashboard($request, 'wallet');
    }

    public function grantWallet(Request $request)
    {
        $admin = Auth::user();
        abort_unless($admin && (int) $admin->role === 1, 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'user_id.exists' => 'Không tìm thấy user cần tặng tiền.',
            'amount.min' => 'Số tiền tặng tối thiểu là 1.000đ.',
            'amount.max' => 'Số tiền tặng tối đa mỗi lần là 1.000.000.000đ.',
            'note.required' => 'Vui lòng ghi lý do tặng tiền.',
        ]);

        if (!WalletLedger::available()) {
            return redirect()
                ->route('admin.wallet')
                ->with('error', 'Bảng wallet_ledgers chưa sẵn sàng, chưa thể tặng tiền.');
        }

        try {
            $message = DB::transaction(function () use ($validated, $admin, $request) {
                $target = DB::table('users')
                    ->where('id', (int) $validated['user_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$target) {
                    throw new \RuntimeException('Không tìm thấy user cần tặng tiền.');
                }

                $amount = (int) $validated['amount'];
                $before = (int) ($target->amount ?? 0);
                $after = $before + $amount;
                $note = trim((string) $validated['note']);
                $reference = WalletLedger::makeReference('admin_grant', (int) $target->id);

                WalletLedger::ensureOpeningBalance((int) $target->id, $before);

                DB::table('users')->where('id', (int) $target->id)->update([
                    'amount' => $after,
                    'updated_at' => now(),
                ]);

                WalletLedger::record(
                    (int) $target->id,
                    $amount,
                    'admin_grant',
                    $reference,
                    $note,
                    (int) $admin->id,
                    $before,
                    $after,
                    [
                        'admin_id' => (int) $admin->id,
                        'admin_email' => (string) ($admin->email ?? ''),
                        'target_email' => (string) ($target->email ?? ''),
                    ]
                );

                DB::table('xlogs')->insert([
                    'ip' => $request->ip(),
                    'user' => (int) $admin->id,
                    'log' => 'Tặng tiền ví user',
                    'notes' => sprintf(
                        'Admin #%d tặng %sđ cho user #%d. Trước: %sđ, sau: %sđ. Lý do: %s. Ref: %s',
                        $admin->id,
                        number_format($amount),
                        $target->id,
                        number_format($before),
                        number_format($after),
                        $note,
                        $reference
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 'Đã tặng ' . number_format($amount) . 'đ cho user #' . $target->id . '.';
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.wallet')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.wallet')
            ->with('success', $message);
    }


    public function impersonate(Request $request, int $user)
    {
        $admin = Auth::user();
        abort_unless($admin && (int) $admin->role === 1, 403);

        if ($request->session()->has('impersonator_id')) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Bạn đang ở chế độ vào vai. Hãy quay lại admin trước khi vào vai user khác.');
        }

        $target = User::query()->findOrFail($user);
        if ((int) $target->id === (int) $admin->id) {
            return redirect()
                ->route('admin.users')
                ->with('warning', 'Không cần vào vai chính tài khoản đang dùng.');
        }

        $targetLabel = $target->display_name ?: $target->name ?: $target->email ?: ('User #' . $target->id);
        $adminLabel = $admin->display_name ?: $admin->name ?: $admin->email ?: ('Admin #' . $admin->id);

        $request->session()->put('impersonator_id', (int) $admin->id);
        $request->session()->put('impersonator_name', $adminLabel);
        $request->session()->put('impersonated_user_id', (int) $target->id);
        $request->session()->put('impersonated_user_name', $targetLabel);

        $this->writeImpersonationLog(
            $request,
            (int) $admin->id,
            'Bắt đầu vào vai user',
            sprintf('Admin #%d (%s) vào vai user #%d (%s).', $admin->id, $adminLabel, $target->id, $targetLabel)
        );

        Auth::loginUsingId($target->id);
        $request->session()->regenerate();

        return redirect()
            ->route('v2')
            ->with('success', 'Đang đăng nhập dưới dạng ' . $targetLabel . '.');
    }

    public function stopImpersonating(Request $request)
    {
        $adminId = (int) $request->session()->get('impersonator_id', 0);
        if ($adminId <= 0) {
            return redirect()->route('v2')->with('info', 'Không có phiên vào vai nào đang chạy.');
        }

        $admin = User::query()->find($adminId);
        abort_unless($admin && (int) $admin->role === 1, 403);

        $current = Auth::user();
        $targetId = (int) ($request->session()->get('impersonated_user_id') ?: ($current->id ?? 0));
        $targetLabel = (string) ($request->session()->get('impersonated_user_name') ?: ($current->email ?? ('User #' . $targetId)));
        $adminLabel = $admin->display_name ?: $admin->name ?: $admin->email ?: ('Admin #' . $admin->id);

        $this->writeImpersonationLog(
            $request,
            (int) $admin->id,
            'Kết thúc vào vai user',
            sprintf('Admin #%d (%s) quay lại từ user #%d (%s).', $admin->id, $adminLabel, $targetId, $targetLabel)
        );

        Auth::loginUsingId($admin->id);
        $request->session()->forget([
            'impersonator_id',
            'impersonator_name',
            'impersonated_user_id',
            'impersonated_user_name',
        ]);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.users')
            ->with('success', 'Đã quay lại tài khoản super admin.');
    }

    private function dashboard(Request $request, string $section)
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $todayTimestamp = $today->timestamp;
        $sessionLifetime = max((int) config('session.lifetime', 120), 15);
        $activeSince = $now->copy()->subMinutes($sessionLifetime)->timestamp;
        $search = trim((string) $request->query('q', ''));
        $users = null;
        $sessions = null;
        $recharges = null;
        $logs = null;
        $walletLedgers = null;
        $ledgerAlerts = collect();
        $ledgerStats = [
            'available' => WalletLedger::available(),
            'baseline_created' => 0,
            'wallet_total' => 0,
            'ledger_total' => 0,
            'delta_total' => 0,
            'alert_count' => 0,
            'grant_today' => 0,
        ];

        $stats = [
            'users_total' => DB::table('users')->count(),
            'users_today' => DB::table('users')->where('created_at', '>=', $today)->count(),
            'users_verified' => DB::table('users')->whereNotNull('email_verified_at')->count(),
            'users_pending' => DB::table('users')->where('role', 9)->count(),
            'online_sessions' => DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', $activeSince)
                ->count(),
            'wallet_balance' => (int) DB::table('users')->sum('amount'),
            'recharge_today' => (int) DB::table('invoices')->where('create_time', '>=', $todayTimestamp)->sum('amount'),
            'recharge_total' => (int) DB::table('invoices')->sum('amount'),
            'invoices_today' => DB::table('invoices')->where('create_time', '>=', $todayTimestamp)->count(),
            'api_accounts' => DB::table('account_acb')->count()
                + DB::table('account_vietcombank')->count()
                + DB::table('account_vpbank')->count()
                + DB::table('account_techcombank')->count()
                + DB::table('account_mbbank')->count(),
            'api_tokens' => $this->tokenCount('account_acb', 'token')
                + $this->tokenCount('account_vietcombank', 'token')
                + $this->tokenCount('account_vpbank', 'token')
                + $this->tokenCount('account_techcombank', 'token')
                + $this->tokenCount('account_mbbank', 'token'),
            'packages_active' => DB::table('users')->whereRaw('CAST(COALESCE(time_end, 0) AS UNSIGNED) > ?', [time()])->count(),
        ];

        if ($section === 'users') {
            $usersQuery = DB::table('users')
                ->select([
                    'id',
                    'name',
                    'email',
                    'display_name',
                    'phone',
                    'role',
                    'amount',
                    'total_paid',
                    'banned',
                    'ip',
                    'last_activity',
                    'time_end',
                    'email_verified_at',
                    'created_at',
                ]);

            if ($search !== '') {
                $usersQuery->where(function ($query) use ($search) {
                    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('display_name', 'like', $like)
                        ->orWhere('phone', 'like', $like);

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            }

            $users = $usersQuery
                ->orderByDesc('id')
                ->simplePaginate(20, ['*'], 'users_page')
                ->appends($request->query());
        }

        if ($section === 'sessions') {
            $sessions = DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select([
                    'sessions.id',
                    'sessions.user_id',
                    'sessions.ip_address',
                    'sessions.user_agent',
                    'sessions.last_activity',
                    'users.name',
                    'users.email',
                    'users.role',
                ])
                ->whereNotNull('sessions.user_id')
                ->orderByDesc('sessions.last_activity')
                ->simplePaginate(20, ['*'], 'sessions_page')
                ->appends($request->query());
        }

        if ($section === 'recharges') {
            $recharges = DB::table('invoices')
                ->leftJoin('users', 'invoices.user_id', '=', 'users.id')
                ->select([
                    'invoices.id',
                    'invoices.user_id',
                    'invoices.trans_id',
                    'invoices.payment_method',
                    'invoices.amount',
                    'invoices.description',
                    'invoices.status',
                    'invoices.create_time',
                    'users.name',
                    'users.email',
                ])
                ->orderByDesc('invoices.create_time')
                ->simplePaginate(20, ['*'], 'recharges_page')
                ->appends($request->query());
        }

        if ($section === 'logs') {
            $logs = DB::table('xlogs')
                ->leftJoin('users', 'xlogs.user', '=', 'users.id')
                ->select([
                    'xlogs.xkey',
                    'xlogs.ip',
                    'xlogs.user',
                    'xlogs.log',
                    'xlogs.notes',
                    'xlogs.created_at',
                    'users.name',
                    'users.email',
                ])
                ->orderByDesc('xlogs.xkey')
                ->simplePaginate(20, ['*'], 'logs_page')
                ->appends($request->query());
        }

        if ($section === 'wallet') {
            $ledgerStats['wallet_total'] = (int) DB::table('users')->sum('amount');

            if ($ledgerStats['available']) {
                $ledgerStats['baseline_created'] = WalletLedger::ensureOpeningBalances();
                $ledgerStats['ledger_total'] = (int) DB::table('wallet_ledgers')->sum('amount');
                $ledgerStats['delta_total'] = $ledgerStats['wallet_total'] - $ledgerStats['ledger_total'];
                $ledgerStats['grant_today'] = (int) DB::table('wallet_ledgers')
                    ->where('type', 'admin_grant')
                    ->where('created_at', '>=', $today)
                    ->sum('amount');

                $alertsQuery = $this->ledgerAlertQuery();
                $ledgerStats['alert_count'] = (clone $alertsQuery)->count();
                $ledgerAlerts = (clone $alertsQuery)
                    ->orderByRaw('ABS(COALESCE(users.amount, 0) - COALESCE(wallet_totals.ledger_amount, 0)) DESC')
                    ->orderByDesc('users.id')
                    ->limit(100)
                    ->get();

                $walletLedgers = DB::table('wallet_ledgers')
                    ->leftJoin('users', 'wallet_ledgers.user_id', '=', 'users.id')
                    ->leftJoin('users as actors', 'wallet_ledgers.actor_id', '=', 'actors.id')
                    ->select([
                        'wallet_ledgers.id',
                        'wallet_ledgers.user_id',
                        'wallet_ledgers.actor_id',
                        'wallet_ledgers.type',
                        'wallet_ledgers.direction',
                        'wallet_ledgers.amount',
                        'wallet_ledgers.balance_before',
                        'wallet_ledgers.balance_after',
                        'wallet_ledgers.reference',
                        'wallet_ledgers.description',
                        'wallet_ledgers.created_at',
                        'users.name',
                        'users.email',
                        'users.display_name',
                        'actors.name as actor_name',
                        'actors.email as actor_email',
                    ])
                    ->orderByDesc('wallet_ledgers.id')
                    ->simplePaginate(25, ['*'], 'ledger_page')
                    ->appends($request->query());
            }
        }

        $roleLabels = [
            1 => ['label' => 'Super Admin', 'class' => 'bg-label-danger'],
            2 => ['label' => 'Admin', 'class' => 'bg-label-warning'],
            3 => ['label' => 'Khách hàng', 'class' => 'bg-label-primary'],
            9 => ['label' => 'Chưa kích hoạt', 'class' => 'bg-label-secondary'],
        ];

        return view('admin.dashboard', compact(
            'activeSince',
            'logs',
            'ledgerAlerts',
            'ledgerStats',
            'recharges',
            'roleLabels',
            'search',
            'sessions',
            'section',
            'sessionLifetime',
            'stats',
            'users',
            'walletLedgers'
        ));
    }


    private function writeImpersonationLog(Request $request, int $actorId, string $action, string $notes): void
    {
        try {
            DB::table('xlogs')->insert([
                'ip' => $request->ip(),
                'user' => $actorId,
                'log' => $action,
                'notes' => mb_substr($notes . ' IP: ' . $request->ip(), 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function tokenCount(string $table, string $column): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->count();
    }

    private function ledgerAlertQuery()
    {
        $walletTotals = DB::table('wallet_ledgers')
            ->select([
                'user_id',
                DB::raw('SUM(amount) as ledger_amount'),
                DB::raw('COUNT(*) as ledger_count'),
                DB::raw('MAX(created_at) as last_ledger_at'),
            ])
            ->groupBy('user_id');

        return DB::table('users')
            ->leftJoinSub($walletTotals, 'wallet_totals', function ($join) {
                $join->on('users.id', '=', 'wallet_totals.user_id');
            })
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.display_name',
                'users.amount as wallet_amount',
                DB::raw('COALESCE(wallet_totals.ledger_amount, 0) as ledger_amount'),
                DB::raw('COALESCE(wallet_totals.ledger_count, 0) as ledger_count'),
                DB::raw('wallet_totals.last_ledger_at as last_ledger_at'),
                DB::raw('(COALESCE(users.amount, 0) - COALESCE(wallet_totals.ledger_amount, 0)) as ledger_delta'),
            ])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('wallet_ledgers as opening_ledgers')
                    ->whereColumn('opening_ledgers.user_id', 'users.id')
                    ->where('opening_ledgers.type', 'opening_balance');
            })
            ->where(function ($query) {
                $query->whereRaw('ABS(COALESCE(users.amount, 0) - COALESCE(wallet_totals.ledger_amount, 0)) >= 1')
                    ->orWhereRaw('COALESCE(users.amount, 0) < 0');
            });
    }
}
