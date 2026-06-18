<?php

namespace App\Http\Controllers;

use App\Services\BankRealtimeCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CachedBankApiController extends Controller
{
    public function __construct(private readonly BankRealtimeCacheService $cache) {}

    public function vcbBalance(Request $request, string $token): JsonResponse
    {
        return $this->balance('vcb', $token);
    }

    public function acbBalance(Request $request, string $token): JsonResponse
    {
        return $this->balance('acb', $token);
    }

    public function vpbankBalance(Request $request, string $token): JsonResponse
    {
        return $this->balance('vpbank', $token);
    }

    public function techcombankBalance(Request $request, string $token): JsonResponse
    {
        return $this->balance('techcombank', $token);
    }

    public function mbbankBalance(Request $request, string $token): JsonResponse
    {
        return $this->balance('mbbank', $token);
    }

    public function vcbTransHistory(Request $request, string $token): JsonResponse
    {
        return $this->history($request, 'vcb', $token);
    }

    public function acbTransHistory(Request $request, string $token): JsonResponse
    {
        return $this->history($request, 'acb', $token);
    }

    public function vpbankTransHistory(Request $request, string $token): JsonResponse
    {
        return $this->history($request, 'vpbank', $token);
    }

    public function techcombankTransHistory(Request $request, string $token): JsonResponse
    {
        return $this->history($request, 'techcombank', $token);
    }

    public function mbbankTransHistory(Request $request, string $token): JsonResponse
    {
        return $this->history($request, 'mbbank', $token);
    }

    private function balance(string $bank, string $token): JsonResponse
    {
        return response()->json($this->cache->balance($bank, $token));
    }

    private function history(Request $request, string $bank, string $token): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 100)));

        return response()->json($this->cache->transactionHistory($bank, $token, $limit));
    }
}
