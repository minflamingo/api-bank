<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        $roleLabels = [
            1 => ['label' => 'Super Admin', 'class' => 'bg-label-danger'],
            2 => ['label' => 'Admin', 'class' => 'bg-label-warning'],
            3 => ['label' => 'Khách hàng', 'class' => 'bg-label-primary'],
            9 => ['label' => 'Chưa kích hoạt', 'class' => 'bg-label-secondary'],
        ];

        return view('admin.dashboard', compact(
            'activeSince',
            'logs',
            'recharges',
            'roleLabels',
            'search',
            'sessions',
            'section',
            'sessionLifetime',
            'stats',
            'users'
        ));
    }

    private function tokenCount(string $table, string $column): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->count();
    }
}
