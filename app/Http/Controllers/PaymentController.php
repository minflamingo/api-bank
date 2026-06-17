<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\XLog;
use App\Models\Transaction;
use App\Models\User;
use App\Models\AccountVietcombank;
use App\Models\AccountAcb;
use App\Models\AccountVpbank;
use App\Models\AccountMbbank;
use App\Models\AccountTechcombank;
use App\Support\ApiPackage;
use App\Support\BankTransactionRecorder;
use Carbon\Carbon;         // nếu cần dùng xử lý thời gian

/** THÊM 2 use DƯỚI ĐÂY CHO PHPSecLib v3 **/
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

class PaymentController extends Controller
{
    // -------------------------------------------------------------------------
    //  HẰNG SỐ & KHÓA MẶC ĐỊNH (PublicKey, PrivateKey... v.v)
    // -------------------------------------------------------------------------
    private $defaultPublicKey = '';
    // Lưu ý: đây là base64, bạn có thể để nguyên string

    private $clientPublicKey  = '';
    // PublicKey của client (thường là Base64 raw)
    
    private $clientPrivateKey = '';

    private $captcha = '';

    public function __construct()
    {
        $this->defaultPublicKey = (string) config('services.vcb_rsa.default_public_key', '');
        $this->clientPublicKey = (string) config('services.vcb_rsa.client_public_key', '');
        $this->clientPrivateKey = str_replace('\n', "\n", (string) config('services.vcb_rsa.client_private_key', ''));
        $this->captcha = (string) config('services.vcb_captcha.api_key', '');
    }

    private function userBankAccountCount(int $userId): int
    {
        return AccountVietcombank::where('user_id', $userId)->count()
            + AccountAcb::where('user_id', $userId)->count()
            + AccountVpbank::where('user_id', $userId)->count()
            + AccountMbbank::where('user_id', $userId)->count()
            + AccountTechcombank::where('user_id', $userId)->count();
    }

    private function accountLimitMessage(int $accountLimit): string
    {
        return "Gói hiện tại cho phép tối đa {$accountLimit} tài khoản ngân hàng";
    }

    private function bankHistoryPartyNameFilter(Request $request): string
    {
        $name = trim((string) $request->query('party_name', ''));
        $name = preg_replace('/\s+/u', ' ', $name) ?: $name;

        return mb_substr($name, 0, 120, 'UTF-8');
    }

    private function prepareBankHistoryTransactions(Request $request, array $transactions, string $bank, string $accountNo = ''): array
    {
        $partyName = $this->bankHistoryPartyNameFilter($request);
        $prepared = [];

        foreach ($transactions as $item) {
            if (is_array($item)) {
                $prepared[] = $this->prepareBankHistoryTransaction($item, $bank, $accountNo);
            }
        }

        if ($partyName !== '') {
            $prepared = array_values(array_filter($prepared, function (array $item) use ($partyName) {
                return $this->bankHistoryTransactionMatchesParty($item, $partyName);
            }));
        }

        $totalIn = 0;
        $totalOut = 0;
        foreach ($prepared as $item) {
            $amount = abs($this->bankHistoryTransactionAmount($item, $bank));
            if ($this->bankHistoryTransactionIsCredit($item, $bank, $amount)) {
                $totalIn += $amount;
            } else {
                $totalOut += $amount;
            }
        }

        return [
            'transactions' => $prepared,
            'partyName' => $partyName,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => $totalIn - $totalOut,
        ];
    }

    private function prepareBankHistoryTransaction(array $item, string $bank, string $accountNo = ''): array
    {
        $description = $this->bankHistoryTransactionDescription($item);
        $amount = $this->bankHistoryTransactionAmount($item, $bank);
        $isCredit = $this->bankHistoryTransactionIsCredit($item, $bank, $amount);

        $item['_description'] = (string) ($item['_description'] ?? $description);
        $item['_amount'] = $amount;
        $item['_is_credit'] = $isCredit;
        $item['_party_info'] = $this->bankHistoryPartyInfo($item, $bank, $accountNo, $isCredit);

        return $item;
    }

    private function bankHistoryTransactionMatchesParty(array $item, string $partyName): bool
    {
        $needle = mb_strtolower($partyName, 'UTF-8');
        $party = is_array($item['_party_info'] ?? null) ? $item['_party_info'] : [];
        $parts = [
            $party['name'] ?? '',
            $party['account'] ?? '',
            $party['bank'] ?? '',
            $item['_description'] ?? '',
            json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $haystack = mb_strtolower(implode(' ', array_filter(array_map('strval', $parts))), 'UTF-8');

        return $needle !== '' && str_contains($haystack, $needle);
    }

    private function bankHistoryTransactionDescription(array $item): string
    {
        return $this->bankHistoryCleanText($this->bankHistoryFirstNestedValue($item, [
            '_description',
            'Description',
            'description',
            'TransactionDescription',
            'transactionDesc',
            'Narrative',
            'Remark',
            'Content',
            'note',
            'remittanceInformation',
        ]));
    }

    private function bankHistoryTransactionAmount(array $item, string $bank): int
    {
        if (isset($item['_amount'])) {
            return (int) $item['_amount'];
        }

        $bank = strtolower($bank);
        if ($bank === 'vcb') {
            $amount = abs($this->bankHistoryMoneyValue($item['Amount'] ?? 0));
            return (($item['CD'] ?? '') === '+') ? $amount : -$amount;
        }

        if ($bank === 'acb') {
            $amount = abs($this->bankHistoryMoneyValue($item['amount'] ?? 0));
            return (($item['type'] ?? '') === 'IN') ? $amount : -$amount;
        }

        $credit = $this->bankHistoryMoneyValue($this->bankHistoryFirstNestedValue($item, [
            'creditAmount',
            'CreditAmount',
        ]));
        if ($credit > 0) {
            return $credit;
        }

        $debit = $this->bankHistoryMoneyValue($this->bankHistoryFirstNestedValue($item, [
            'debitAmount',
            'DebitAmount',
        ]));
        if ($debit > 0) {
            return -abs($debit);
        }

        return $this->bankHistoryMoneyValue($this->bankHistoryFirstNestedValue($item, [
            'amount',
            'Amount',
            'transactionAmount',
            'TransactionAmount',
        ]));
    }

    private function bankHistoryTransactionIsCredit(array $item, string $bank, int $amount = 0): bool
    {
        if (array_key_exists('_is_credit', $item)) {
            return (bool) $item['_is_credit'];
        }

        $bank = strtolower($bank);
        if ($bank === 'vcb') {
            return (($item['CD'] ?? '') === '+');
        }
        if ($bank === 'acb') {
            return (($item['type'] ?? '') === 'IN');
        }

        if ($amount > 0) {
            return true;
        }
        if ($amount < 0) {
            return false;
        }

        $direction = strtoupper((string) $this->bankHistoryFirstNestedValue($item, [
            'creditDebitIndicator',
            'CreditDebitIndicator',
            'DebitCreditIndicator',
            'TransactionType',
            'type',
            'Type',
            'CD',
        ]));

        return in_array($direction, ['+', 'C', 'CR', 'CRDT', 'CREDIT', 'IN'], true);
    }

    private function bankHistoryPartyInfo(array $item, string $bank, string $accountNo, bool $isCredit): array
    {
        $label = $isCredit ? 'Người gửi' : 'Người nhận';
        $name = '';
        $account = '';
        $bankName = '';

        if (strtolower($bank) === 'techcombank') {
            $additions = is_array($item['additions'] ?? null) ? $item['additions'] : [];
            $creditNo = (string) ($additions['creditAcctNo'] ?? '');
            $debitNo = (string) ($additions['debitAcctNo'] ?? '');
            $useDebit = $isCredit || ($creditNo !== '' && $creditNo === $accountNo);

            $name = $this->bankHistoryCleanText((string) ($useDebit ? ($additions['debitAcctName'] ?? '') : ($additions['creditAcctName'] ?? '')));
            $account = $this->bankHistoryCleanText((string) ($useDebit ? $debitNo : $creditNo));
            $bankName = $this->bankHistoryCleanText((string) ($useDebit ? ($additions['debitBankName'] ?? '') : ($additions['creditBankName'] ?? '')));
        }

        if ($name === '') {
            $name = $this->bankHistoryCleanText($this->bankHistoryFirstNestedValue($item, $isCredit ? [
                'senderName',
                'fromName',
                'remitterName',
                'payerName',
                'debitAcctName',
                'debitAccountName',
                'sourceAccountName',
                'fromAccountName',
            ] : [
                'receiverName',
                'toName',
                'beneficiaryName',
                'beneficiaryAccountName',
                'benAccountName',
                'creditAcctName',
                'creditAccountName',
                'destinationAccountName',
                'toAccountName',
            ]));
        }

        if ($account === '') {
            $account = $this->bankHistoryCleanText($this->bankHistoryFirstNestedValue($item, $isCredit ? [
                'senderAccount',
                'fromAccount',
                'remitterAccount',
                'payerAccount',
                'debitAcctNo',
                'debitAccountNo',
                'sourceAccount',
                'fromAccountNumber',
            ] : [
                'receiverAccount',
                'toAccount',
                'beneficiaryAccount',
                'beneficiaryAccountNo',
                'benAccountNo',
                'creditAcctNo',
                'creditAccountNo',
                'destinationAccount',
                'toAccountNumber',
            ]));
        }

        if ($bankName === '') {
            $bankName = $this->bankHistoryCleanText($this->bankHistoryFirstNestedValue($item, $isCredit ? [
                'senderBankName',
                'fromBankName',
                'remitterBankName',
                'payerBankName',
                'debitBankName',
            ] : [
                'receiverBankName',
                'toBankName',
                'beneficiaryBankName',
                'benBankName',
                'creditBankName',
            ]));
        }

        if ($name === '' && $account === '') {
            $parsed = $this->bankHistoryPartyFromDescription((string) ($item['_description'] ?? ''), $isCredit);
            $name = $parsed['name'];
            $account = $parsed['account'];
            $bankName = $bankName !== '' ? $bankName : $parsed['bank'];
        }

        return [
            'label' => $label,
            'name' => $name,
            'account' => $account,
            'bank' => $bankName,
        ];
    }

    private function bankHistoryPartyFromDescription(string $description, bool $isCredit): array
    {
        $description = $this->bankHistoryCleanText($description);
        if ($description === '') {
            return ['name' => '', 'account' => '', 'bank' => ''];
        }

        $patterns = $isCredit ? [
            '/(?:tu|từ|from)\s+(?:tk|stk|tai khoan|tài khoản)?\s*([0-9]{5,})?\s*[-: ]*\s*([^|;,]+?)(?:\s+tai\s+|\s+tại\s+|$)/iu',
            '/(?:nguoi gui|người gửi|sender|remitter|payer)[:\s-]+([^|;,]+?)(?:[|;,]|$)/iu',
        ] : [
            '/(?:den|đến|to)\s+(?:tk|stk|tai khoan|tài khoản)?\s*([0-9]{5,})?\s*[-: ]*\s*([^|;,]+?)(?:\s+tai\s+|\s+tại\s+|$)/iu',
            '/(?:nguoi nhan|người nhận|receiver|beneficiary|payee)[:\s-]+([^|;,]+?)(?:[|;,]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $description, $match)) {
                $account = '';
                $name = '';
                if (count($match) >= 3) {
                    $account = $this->bankHistoryCleanText((string) ($match[1] ?? ''));
                    $name = $this->bankHistoryCleanText((string) ($match[2] ?? ''));
                } else {
                    $split = $this->bankHistorySplitNameAndAccount((string) ($match[1] ?? ''));
                    $name = $split['name'];
                    $account = $split['account'];
                }

                return [
                    'name' => $name,
                    'account' => $account,
                    'bank' => '',
                ];
            }
        }

        return ['name' => '', 'account' => '', 'bank' => ''];
    }

    private function bankHistorySplitNameAndAccount(string $text): array
    {
        $text = $this->bankHistoryCleanText($text);
        $account = '';

        if (preg_match('/\b([0-9]{5,})\b/u', $text, $match)) {
            $account = $match[1];
            $text = $this->bankHistoryCleanText(str_replace($match[0], '', $text));
        }

        return [
            'name' => $text,
            'account' => $account,
        ];
    }

    private function bankHistoryFirstNestedValue(array $item, array $keys): string
    {
        $wanted = array_map(fn ($key) => strtolower((string) $key), $keys);
        $stack = [$item];

        while (!empty($stack)) {
            $current = array_pop($stack);
            foreach ($current as $key => $value) {
                if (in_array(strtolower((string) $key), $wanted, true) && !is_array($value) && $value !== null && $value !== '') {
                    return trim((string) $value);
                }
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return '';
    }

    private function bankHistoryMoneyValue($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $raw = trim((string) $value);
        $negative = str_contains($raw, '-') || (str_starts_with($raw, '(') && str_ends_with($raw, ')'));
        $normalised = str_replace([',', ' ', 'VND', 'vnd', 'đ'], '', $raw);
        if (substr_count($normalised, '.') > 1) {
            $normalised = str_replace('.', '', $normalised);
        }
        $normalised = preg_replace('/[^0-9.\-]/', '', $normalised) ?: '0';
        $amount = (int) round((float) $normalised);

        return $negative ? -abs($amount) : $amount;
    }

    private function bankHistoryCleanText(string $text): string
    {
        $text = trim(strip_tags($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        return trim($text, " \t\n\r\0\x0B-:;,.|/");
    }


    private function storeBankTransactions(string $bank, ?int $accountId, ?int $userId, string $accountNo, array $transactions): void
    {
        try {
            app(BankTransactionRecorder::class)->save($bank, $accountId, $userId, $accountNo, $transactions);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // -------------------------------------------------------------------------
    //  Hàm gọi API chung
    // -------------------------------------------------------------------------
    private function callApi($url, $headers, $data = null, $method = 'POST', $useDecryption = false, $timeout = 10)
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING       => ""
        ];

        if ($method == 'POST' && !empty($data)) {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = $data;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return null; // Tuỳ log lỗi
        }

        if ($useDecryption) {
            $decode = json_decode($body, true);
            if (isset($decode['k']) && isset($decode['d'])) {
                return $this->decryptResponse($decode['k'], $decode['d']);
            } else {
                return $body;
            }
        } else {
            return $body;
        }
    }

    // =========================================================================
    // =============           V I E T C O M B A N K         ====================
    // =========================================================================

    // -------------------------------------------------------------------------
    //  (A) Danh sách tài khoản VCB
    // -------------------------------------------------------------------------
    public function vcbIndex()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error','Vui lòng đăng nhập');
        }
        $accounts = AccountVietcombank::where('user_id', $user->id)->get();
        return view('payment.vcb', compact('accounts','user'));
    }

    // -------------------------------------------------------------------------
    //  (B) Lịch sử giao dịch VCB (trả về VIEW)
    // -------------------------------------------------------------------------
    public function vcbHistory(Request $request, $account)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập');
        }
        $acc = AccountVietcombank::where('account', $account)
            ->where('user_id', $user->id)
            ->first();
        if (!$acc) {
            return redirect()->back()->with('error', 'Không tìm thấy tài khoản VCB');
        }

        $jsonLSGD = $this->get_lsgd($acc);
        $arrLSGD  = json_decode($jsonLSGD, true);

        // (Nếu code != 00 => session hết hạn => reLogin, vv.)
        if (!isset($arrLSGD['code']) || $arrLSGD['code'] != '00') {
            // Thử reLogin
            $loginOk = $this->reLoginIfNeed($acc);
            if ($loginOk) {
                $jsonLSGD = $this->get_lsgd($acc->refresh());
                $arrLSGD  = json_decode($jsonLSGD, true);
                if (!isset($arrLSGD['code']) || $arrLSGD['code'] != '00') {
                    return redirect()->back()->with('error', 'Không thể lấy lịch sử (session hết hạn)');
                }
            } else {
                return redirect()->back()->with('error','Không auto re-login được');
            }
        }

        $this->storeBankTransactions('vcb', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $arrLSGD['transactions'] ?? []);

        $transactions = $this->prepareBankHistoryTransactions($request, $arrLSGD['transactions'] ?? [], 'vcb', (string) $acc->account);

        return view('payment.vcbhistory', ['acc' => $acc] + $transactions);
    }

    // -------------------------------------------------------------------------
    //  (C) Lấy số dư VCB qua API (trả về JSON)
    // -------------------------------------------------------------------------
    public function vcbGetBalanceAPI(Request $request, $token)
    {
        $accountVcb = AccountVietcombank::where('token', $token)->first();
        if (!$accountVcb) {
            return response()->json([
                'status' => 'false',
                'msg'    => 'Không tồn tại tài khoản token này'
            ]);
        }

        $user = User::find($accountVcb->user_id);
        if (!$user) {
            return response()->json([
                'status' => 'false',
                'msg'    => 'Không tìm thấy user'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        // Lấy số dư
        $balanceJson = $this->getBalanceVcb($accountVcb);
        $balanceArr  = json_decode($balanceJson, true);

        if (!isset($balanceArr['code']) || $balanceArr['code'] != '00') {
            // re-login
            $loginOk = $this->reLoginIfNeed($accountVcb);
            if ($loginOk) {
                $balanceJson = $this->getBalanceVcb($accountVcb->refresh());
                $balanceArr  = json_decode($balanceJson, true);
                if (!isset($balanceArr['code']) || $balanceArr['code'] != '00') {
                    return response()->json([
                        'status' => '99',
                        'SoDu'   => '0',
                        'msg'    => 'Lỗi đăng nhập lại, code != 00'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => '99',
                    'SoDu'   => '0',
                    'msg'    => 'Không auto re-login'
                ]);
            }
        }

        // parse
        $soDuRaw = $balanceArr['accountDetail']['availBalance'] ?? '0';
        $soDuNum = (int) str_replace(',', '', $soDuRaw);

        return response()->json([
            'status' => 200,
            'SoDu'   => $soDuNum
        ]);
    }

    // -------------------------------------------------------------------------
    //  (D) GET OTP
    // -------------------------------------------------------------------------
    public function vcbGetOtp(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account  = $request->input('account');
        $password = $request->input('password');
        $stk      = $request->input('stk');

        if (empty($account) || empty($password) || empty($stk)) {
            return response()->json([
                'status' => '1',
                'msg'    => 'Thiếu thông tin tài khoản VCB'
            ]);
        }

        $isSystemReceiver = $request->boolean('system_receiver') && (int) ($user->role ?? 0) === 1;
        $accountLimit = ApiPackage::userLimit($user);
        $existingVcb = AccountVietcombank::where('user_id', $user->id)->where('username', $account)->first();
        $exists = (bool) $existingVcb;
        if (!$isSystemReceiver && !$exists && $accountLimit > 0 && $this->userBankAccountCount((int) $user->id) >= $accountLimit) {
            return response()->json([
                'status' => '1',
                'msg'    => $this->accountLimitMessage($accountLimit)
            ]);
        }

        $response = $this->getCaptcha($this->vcbCaptchaApiKey());
        $jsonCap  = json_decode($response, true);

        $captcha_id  = $jsonCap['data']['captcha_id'] ?? '';
        $captcha_val = $jsonCap['data']['captcha']    ?? '';

        if ($captcha_id === '' || $captcha_val === '') {
            return response()->json([
                'status' => '1',
                'msg'    => $jsonCap['msg'] ?? 'Không giải được captcha Vietcombank, vui lòng thử lại'
            ]);
        }

        // Tạo token session
        $tokenGenerated = $existingVcb->token ?? md5(uniqid() . time());

        // Gọi login
        $loginJson = $this->loginVcb($account, $password, $captcha_id, $captcha_val);
        if (!$loginJson) {
            return response()->json([
                'status' => '1',
                'msg'    => 'Không nhận được phản hồi server VCB'
            ]);
        }
        $login = json_decode($loginJson, true);


        if (isset($login['code']) && $login['code'] === '00') {
            // Ko cần OTP
            AccountVietcombank::updateOrCreate(
                [
                    'user_id'  => $user->id,
                    'username' => $account,
                ],
                [
                    'password'    => $password,
                    'account'     => $stk,
                    'session_id'  => $login['sessionId'] ?? '',
                    'access_key'  => $login['accessKey'] ?? '',
                    'client_id'   => $login['userInfo']['clientId'] ?? '',
                    'mobile_id'   => $login['userInfo']['mobileId'] ?? '',
                    'cif'         => $login['userInfo']['cif'] ?? '',
                    'name'        => $login['userInfo']['cusName'] ?? '',
                    'token'       => $tokenGenerated,
                    'create_date' => now(),
                ]
            );

            return response()->json(['status' => '3', 'msg' => 'Đăng nhập thành công (không cần OTP)']);
        }
        elseif (isset($login['code']) && $login['code'] !== '00') {
            if ($login['code'] == '16' || $login['code'] == '0127') {
                return response()->json(['status'=>'1','msg'=>$login['des']]);
            }

            $browserToken = $login['browserToken'] ?? '';
            if ($browserToken === '') {
                return response()->json([
                    'status' => '1',
                    'msg'    => $login['des'] ?? 'Vietcombank không trả browserToken để gửi OTP'
                ]);
            }

            $checkDevice = json_decode($this->initLoginNewBrowser($account, $browserToken), true);
            if (($checkDevice['code'] ?? '') !== '00') {
                return response()->json([
                    'status' => '1',
                    'msg'    => $checkDevice['des'] ?? 'Không khởi tạo được trình duyệt mới Vietcombank'
                ]);
            }

            $tranId = $checkDevice['transaction']['tranId'] ?? '';
            if ($tranId === '') {
                return response()->json([
                    'status' => '1',
                    'msg'    => 'Vietcombank không trả mã giao dịch OTP'
                ]);
            }

            $getOtp = json_decode($this->getOtpVcb($account, $tranId, $browserToken), true);
            if (($getOtp['code'] ?? '') !== '00') {
                return response()->json([
                    'status' => '1',
                    'msg'    => $getOtp['des'] ?? 'Không gửi được OTP Vietcombank'
                ]);
            }

            AccountVietcombank::updateOrCreate(
                [
                    'user_id'  => $user->id,
                    'username' => $account,
                ],
                [
                    'password'     => $password,
                    'account'      => $stk,
                    'tranId'       => $tranId,
                    'browserToken' => $browserToken,
                    'token'        => $tokenGenerated,
                    'create_date'  => now(),
                ]
            );

            return response()->json(['status'=>'2','msg'=>'Đã gửi OTP về số điện thoại của bạn']);
        }
        else {
            return response()->json([
                'status' => '1',
                'msg'    => $login['des'] ?? 'Lỗi đăng nhập'
            ]);
        }
    }

    // -------------------------------------------------------------------------
    //  (E) Xác thực OTP
    // -------------------------------------------------------------------------
    public function vcbLoginOTP(Request $request)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1','msg' => 'Chưa đăng nhập']);
        }

        $account  = $request->input('account');
        $password = $request->input('password');
        $stk      = $request->input('stk');
        $otp      = $request->input('otp');

        if (empty($account) || empty($password) || empty($stk) || empty($otp)) {
            return response()->json(['status'=>'1','msg'=>'Thiếu thông tin OTP']);
        }

        $data = AccountVietcombank::where('user_id', $user->id)
                ->where('username', $account)
                ->first();
        if (!$data) {
            return response()->json(['status'=>'1','msg'=>'Không tìm thấy tài khoản']);
        }

        $verify = json_decode(
            $this->veryOtpLoginNewBrowser($otp, $account, $data->tranId, $data->browserToken),
            true
        );

        if (($verify['code'] ?? '') === '00') {
            $data->name       = $verify['userInfo']['cusName'] ?? '';
            $data->session_id = $verify['sessionId'] ?? '';
            $data->access_key = $verify['accessKey'] ?? '';
            $data->client_id  = $verify['userInfo']['clientId'] ?? '';
            $data->mobile_id  = $verify['userInfo']['mobileId'] ?? '';
            $data->cif        = $verify['userInfo']['cif'] ?? '';
            $data->save();

            $this->saveLoginNewBrowser(
                $account,
                $data->cif,
                $data->client_id,
                $data->mobile_id,
                $data->session_id
            );

            return response()->json(['status'=>'2','msg'=>'Đăng nhập OTP thành công']);
        }
        else {
            return response()->json(['status'=>'1','msg'=> $verify['des'] ?? 'Lỗi verify OTP']);
        }
    }

    // -------------------------------------------------------------------------
    //  (F) Gửi token VCB qua email
    // -------------------------------------------------------------------------
    public function vcbSendToken(Request $request)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=>'1','msg'=>'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status'=>'1','msg'=>'Thiếu ID']);
        }

        $row = AccountVietcombank::where('id',$id)->where('user_id',$user->id)->first();
        if (!$row) {
            return response()->json(['status'=>'1','msg'=>'Không tìm thấy']);
        }

        $tokenVCB = $row->token;
        // Gửi mail tuỳ ý, ví dụ

        return response()->json([
            'status'=>'2',
            'msg'=>"Token VCB: {$tokenVCB}\nAPI số dư: " . url("/v2/vcb/balance/{$tokenVCB}") . "\nAPI giao dịch: " . url("/v2/vcb/transhistory/{$tokenVCB}")
        ]);
    }

    // -------------------------------------------------------------------------
    //  (G) Xóa tài khoản VCB
    // -------------------------------------------------------------------------
    public function vcbRemove(Request $request)
    {
        XLog::create([
            'ip'         => $request->ip(),
            'user'       => Auth::id() ?? 0,
            'log'        => 'Xoá tài khoản VCB',
            'notes'      => 'User bấm xoá VCB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=>'1','msg'=>'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status'=>'1','msg'=>'Thiếu ID']);
        }

        $acc = AccountVietcombank::where('id', $id)
               ->where('user_id', $user->id)
               ->first();
        if (!$acc) {
            return response()->json(['status'=>'1','msg'=>'Không tìm thấy']);
        }
        $acc->delete();

        return response()->json(['status'=>'2','msg'=>'Xoá thành công']);
    }

    // -------------------------------------------------------------------------
    //  (H) DEMO nạp tiền => transactions
    // -------------------------------------------------------------------------
    public function demoNapTien(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=>'1','msg'=>'Chưa đăng nhập']);
        }

        $sotien = 10000;

        Transaction::create([
            'transaction_date' => now(),
            'transaction_type' => 'thu',
            'amount'           => $sotien,
            'description'      => "Nạp DEMO user #{$user->id}",
            'category'         => 'Khác',
            'payment_method'   => 'cash',
            'transid'          => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Giả sử user có cột money => $user->money += $sotien; $user->save();

        return response()->json([
            'status'=>'2',
            'msg'=>"Đã nạp $sotien cho user"
        ]);
    }

    // -------------------------------------------------------------------------
    //  (I) vcbGetTransHistoryAPI - MỚI (Lấy giao dịch VCB qua token)
    //      => Mỗi lần load -> LƯU giao dịch vào bảng transactions
    // -------------------------------------------------------------------------
    public function vcbGetTransHistoryAPI(Request $request, $token)
    {
        // Log sự kiện

        // Tìm account
        $acc = AccountVietcombank::where('token', $token)->first();
        if (!$acc) {
            return response()->json([
                'status'=>'false',
                'msg'=>'Không tìm thấy tài khoản VCB theo token'
            ]);
        }
        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json([
                'status'=>'false',
                'msg'=>'Không tìm thấy user sở hữu token'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

		$jsonLSGD = $this->get_lsgd($acc);
        $arrLSGD  = json_decode($jsonLSGD, true);

        // (Nếu code != 00 => session hết hạn => reLogin, vv.)
        if (!isset($arrLSGD['code']) || $arrLSGD['code'] != '00') {
            // Thử reLogin
            $loginOk = $this->reLoginIfNeed($acc);
            if ($loginOk) {
                $jsonLSGD = $this->get_lsgd($acc->refresh());
                $arrLSGD  = json_decode($jsonLSGD, true);
                if (!isset($arrLSGD['code']) || $arrLSGD['code'] != '00') {
                    return redirect()->back()->with('error', 'Không thể lấy lịch sử (session hết hạn)');
                }
            } else {
                return redirect()->back()->with('error','Không auto re-login được');
            }
        }

        $this->storeBankTransactions('vcb', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $arrLSGD['transactions'] ?? []);

        return response()->json($arrLSGD);
    }

    public function internalVcbTransactionHistoryForReceiver(int $accountId): array
    {
        $acc = AccountVietcombank::where('id', $accountId)->first();
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản VCB nhận tiền'];
        }

        $jsonLSGD = $this->get_lsgd($acc);
        $arrLSGD = json_decode((string) $jsonLSGD, true);

        if (!isset($arrLSGD['code']) || $arrLSGD['code'] !== '00') {
            $loginOk = $this->reLoginIfNeed($acc);
            if (!$loginOk) {
                return ['ok' => false, 'message' => 'Không auto re-login được VCB nhận tiền'];
            }

            $acc->refresh();
            $jsonLSGD = $this->get_lsgd($acc);
            $arrLSGD = json_decode((string) $jsonLSGD, true);

            if (!isset($arrLSGD['code']) || $arrLSGD['code'] !== '00') {
                return ['ok' => false, 'message' => 'Không thể lấy lịch sử VCB sau khi login lại'];
            }
        }

        $this->storeBankTransactions('vcb', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $arrLSGD['transactions'] ?? []);

        return ['ok' => true, 'data' => $arrLSGD];
    }


    // =========================================================================
    //  Các hàm hỗ trợ (Captcha, login, AES/RSA...)  
    // =========================================================================

    // getCaptcha
    private function getCaptcha($key_captcha)
    {
        $captchaToken = $this->gen_uuid();
        $theme = (string) config('services.vcb_captcha.theme', 'MASS');
        $image = $this->fetchVcbCaptchaImage($captchaToken, $theme);
        if ($image === '') {
            return json_encode([
                'status' => '1',
                'msg'    => 'Không tải được ảnh captcha Vietcombank',
                'data'   => ['captcha_id' => '', 'captcha' => '']
            ], JSON_UNESCAPED_UNICODE);
        }

        $curl = curl_init();
        $dataPost = [
            "api_key"    => $key_captcha,
            "img_base64" => base64_encode($image),
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL            => (string) config('services.vcb_captcha.api_url', 'https://captcha.apibank.com.vn/api/vcb'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $dataPost,
            CURLOPT_TIMEOUT        => 40,
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($response === false || $err) {
            return json_encode([
                'status' => '1',
                'msg'    => 'Không kết nối được API captcha apibank',
                'data'   => ['captcha_id' => '', 'captcha' => '']
            ], JSON_UNESCAPED_UNICODE);
        }

        $json = json_decode($response, true);
        $captchaValue = $this->captchaValueFromSolverResponse(is_array($json) ? $json : []);
        if ($captchaValue === '') {
            return json_encode([
                'status' => '1',
                'msg'    => $json['msg'] ?? $json['message'] ?? 'API captcha không trả kết quả hợp lệ',
                'data'   => ['captcha_id' => '', 'captcha' => '']
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'status' => 'success',
            'data'   => [
                'captcha_id' => $captchaToken,
                'captcha'    => $captchaValue,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function fetchVcbCaptchaImage($captchaToken, $theme)
    {
        $url = 'https://digiapp.vietcombank.com.vn/utility-service/v2/captcha/' . rawurlencode($theme) . '/' . rawurlencode($captchaToken);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Referer: https://vcbdigibank.vietcombank.com.vn/',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            ],
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => 20,
        ]);
        $image = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300 || !is_string($image) || $image === '') {
            return '';
        }

        return $image;
    }

    private function captchaValueFromSolverResponse(array $json)
    {
        $status = $json['status'] ?? null;
        $isSuccess = $status === true || in_array(strtolower((string) $status), ['success', 'true', '1', 'ok'], true);
        if (!$isSuccess) {
            return '';
        }

        $candidates = [
            $json['captcha'] ?? null,
            $json['result'] ?? null,
            $json['data']['captcha'] ?? null,
            $json['data']['result'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function vcbCaptchaApiKey()
    {
        $key = (string) config('services.vcb_captcha.api_key', $this->captcha);
        return $key !== '' ? $key : $this->captcha;
    }

    // login VCB
    private function loginVcb($username, $password, $captcha_token, $captcha_value)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/login";
        $headers = [
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Authorization: Bearer null'
        ];
        $data = [
            "DT"           => "Windows",
            "OV"           => "10",
            "PM"           => "Chrome 104.0.0.0",
            "captchaToken" => $captcha_token,
            "captchaValue" => $captcha_value,
            "browserId"    => md5($username),
            "checkAcctPkg" => "1",
            "lang"         => "vi",
            "mid"          => 6,
            "password"     => $password,
            "user"         => $username,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $data);
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    private function reLoginIfNeed(AccountVietcombank $acc)
    {
        try {
            $captchaResp = $this->getCaptcha($this->vcbCaptchaApiKey());
            $jsonCap     = json_decode($captchaResp, true);
            $captcha_id  = $jsonCap['data']['captcha_id'] ?? '';
            $captcha_val = $jsonCap['data']['captcha']    ?? '';
            if ($captcha_id === '' || $captcha_val === '') {
                return false;
            }

            // login
            $loginJson = $this->loginVcb($acc->username, $acc->password, $captcha_id, $captcha_val);
            $loginArr  = json_decode($loginJson, true);
            if (isset($loginArr['code']) && $loginArr['code'] === '00') {
                $acc->session_id = $loginArr['sessionId'] ?? '';
                $acc->access_key = $loginArr['accessKey'] ?? '';
                $acc->client_id  = $loginArr['userInfo']['clientId'] ?? '';
                $acc->mobile_id  = $loginArr['userInfo']['mobileId'] ?? '';
                $acc->cif        = $loginArr['userInfo']['cif'] ?? '';
                $acc->save();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // getBalance VCB
    private function getBalanceVcb(AccountVietcombank $acc)
    {
        $bodyArr = [
            "DT"        => "Windows",
            "PM"        => "Chrome 104.0.0.0",
            "OV"        => "10",
            "lang"      => "vi",
            "accountNo" => $acc->account,
            "accountType" => "D",
            "mid"       => 13,
            "cif"       => $acc->cif,
            "user"      => $acc->username,
            "mobileId"  => $acc->mobile_id,
            "clientId"  => $acc->client_id,
            "sessionId" => $acc->session_id,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $bodyArr);
        $headers = [
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Authorization: Bearer null'
        ];
        $url = "https://digiapp.vietcombank.com.vn/bank-service/v1/get-account-detail";
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    // get lsgd (history) VCB
    private function get_lsgd(AccountVietcombank $acc)
    {
        $url = "https://digiapp.vietcombank.com.vn/bank-service/v1/transaction-history";
        $headers = [
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Authorization: Bearer null'
        ];
        $data = [
            "DT"         => "Windows",
            "PM"         => "Chrome 104.0.0.0",
            "OV"         => "10",
            "lang"       => "vi",
            "accountNo"  => $acc->account,
            "accountType"=> "D",
            "fromDate"   => date("d/m/Y", time()-3600*24*7),
            "toDate"     => date("d/m/Y"),
            "pageIndex"  => 0,
            "lengthInPage"=> 999999,
            "stmtDate"   => "",
            "stmtType"   => "",
            "mid"        => 14,
            "cif"        => $acc->cif,
            "user"       => $acc->username,
            "mobileId"   => $acc->mobile_id,
            "clientId"   => $acc->client_id,
            "sessionId"  => $acc->session_id,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(),  $data);
        $response = $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
        return $response;
    }

    private function initLoginNewBrowser($accountNo, $browserToken)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3008";
        $headers = $this->vcbAuthHeaders();
        $data = [
            "user"         => $accountNo,
            "browserToken" => $browserToken,
            "mid"          => 3008,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $data);
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    private function getOtpVcb($accountNo, $tranId, $browserToken)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3010";
        $headers = $this->vcbAuthHeaders();
        $data = [
            "DT"           => "Windows",
            "OV"           => "10",
            "PM"           => "Chrome 104.0.0.0",
            "user"         => $accountNo,
            "tranId"       => $tranId,
            "type"         => "1",
            "browserToken" => $browserToken,
            "mid"          => 3010,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $data);
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    private function veryOtpLoginNewBrowser($otp, $user, $tranId, $browserToken)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3011";
        $headers = $this->vcbAuthHeaders();
        $data = [
            "DT"           => "Windows",
            "OV"           => "10",
            "PM"           => "Chrome 104.0.0.0",
            "user"         => $user,
            "tranId"       => $tranId,
            "browserToken" => $browserToken,
            "otp"          => $otp,
            "mid"          => 3011,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $data);
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    private function saveLoginNewBrowser($user, $cif, $clientId, $mobileId, $sessionId)
    {
        $url = "https://digiapp.vietcombank.com.vn/authen-service/v1/api-3009";
        $headers = $this->vcbAuthHeaders();
        $data = [
            "DT"          => "Windows",
            "OV"          => "10",
            "PM"          => "Chrome 104.0.0.0",
            "user"        => $user,
            "browserId"   => md5($user),
            "browserName" => "Chrome " . $user,
            "mid"         => 3009,
            "cif"         => $cif,
            "clientId"    => $clientId,
            "mobileId"    => $mobileId,
            "sessionId"   => $sessionId,
        ];
        $encrypt = $this->encryptRequest($this->gen_uuid(), $data);
        return $this->callApi($url, $headers, json_encode($encrypt), 'POST', true, 10);
    }

    private function vcbAuthHeaders()
    {
        return [
            'Host: digiapp.vietcombank.com.vn',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: vi',
            'Content-Type: application/json;charset=utf-8',
            'Referer: https://vcbdigibank.vietcombank.com.vn/',
            'Origin: https://vcbdigibank.vietcombank.com.vn',
            'X-Channel: Web',
            'X-Request-ID: 166170894708822',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'sec-ch-ua: "Chromium";v="104", " Not A;Brand";v="99", "Google Chrome";v="104"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer null',
        ];
    }

    // Mã hoá request => array("k"=>..., "d"=>...)
    private function encryptRequest($randomKey, $plainData)
    {
        $rsaEncryptedKey = $this->encryptKey($randomKey);
        $aesEncrypted    = $this->encryptAES($randomKey, $plainData);
        return ["k" => $rsaEncryptedKey, "d" => $aesEncrypted];
    }
    private function encryptKey($randomKey)
    {
        $randomKey32 = substr($randomKey, 0, 32);
        $randomKeyB64= base64_encode($randomKey32);
        $serverPubPem = base64_decode($this->defaultPublicKey);
        $rsa = PublicKeyLoader::load($serverPubPem)->withPadding(RSA::ENCRYPTION_PKCS1);
        $encrypted = $rsa->encrypt($randomKeyB64);
        return base64_encode($encrypted);
    }
    private function encryptAES($key, $data)
    {
        $key32 = substr($key, 0, 32);
        $iv    = substr($key32, 0, 16);
        if (is_array($data)) {
            $data['clientPubKey'] = $this->clientPublicKey;
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $cipher = 'aes-256-ctr';
        $rawEncrypted = openssl_encrypt($data, $cipher, $key32, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $rawEncrypted);
    }
    private function decryptResponse($encryptedKey, $encryptedData)
    {
        $rawAesKey = $this->decodeRSA($encryptedKey, $this->clientPrivateKey);
        $aesKey32  = base64_decode($rawAesKey);
        return $this->decryptAES($aesKey32, $encryptedData);
    }
    private function decodeRSA($base64Cipher, $privateKeyPem)
    {
        $cipher = base64_decode($base64Cipher);
        $rsa = PublicKeyLoader::load($privateKeyPem)->withPadding(RSA::ENCRYPTION_PKCS1);
        return $rsa->decrypt($cipher);
    }
    private function decryptAES($key32, $dataBase64)
    {
        $binData = base64_decode($dataBase64);
        $iv      = substr($binData, 0, 16);
        $cipher  = substr($binData, 16);
        return openssl_decrypt($cipher, 'aes-256-ctr', $key32, OPENSSL_RAW_DATA, $iv);
    }
    public function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

  
















    // =========================================================================
    // =============           V P B A N K                 =====================
    // =========================================================================

    public function vpbankIndex()
    {
        return redirect()->route('bank.accounts.index');
    }

    public function vpbankLogin(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account  = $request->input('account');
        $password = $request->input('password');
        $stk      = $request->input('stk');

        if (empty($account) || empty($password) || empty($stk)) {
            return response()->json([
                'status' => '1',
                'msg'    => 'Vui lòng nhập đủ account, password, stk'
            ]);
        }

        $accountLimit = ApiPackage::userLimit($user);
        $existingVpbank = AccountVpbank::where('user_id', $user->id)->where('username', $account)->first();
        if (!$existingVpbank && $accountLimit > 0 && $this->userBankAccountCount((int) $user->id) >= $accountLimit) {
            return response()->json([
                'status' => '1',
                'msg'    => $this->accountLimitMessage($accountLimit)
            ]);
        }

        $login = $this->vpbankLoginRequest($account, $password);
        if (empty($login['success'])) {
            return response()->json([
                'status' => '1',
                'msg'    => (string) ($login['message'] ?? 'Không thể kết nối VPBank')
            ]);
        }

        $acc = AccountVpbank::updateOrCreate(
            [
                'user_id' => $user->id,
                'username' => $account,
            ],
            [
                'password' => $password,
                'account' => $stk,
                'name' => $existingVpbank->name ?? null,
                'token_key' => (string) ($login['token_key'] ?? ''),
                'csrf' => (string) ($login['csrf'] ?? ''),
                'cookie' => (string) ($login['cookie'] ?? ''),
                'is_login' => empty($login['needs_otp']),
                'token' => $existingVpbank->token ?? md5(uniqid() . time()),
                'create_date' => now(),
            ]
        );

        if (!empty($login['needs_otp'])) {
            return response()->json([
                'status' => '2',
                'msg' => (string) ($login['message'] ?? 'VPBank yêu cầu OTP từ điện thoại')
            ]);
        }

        $name = $this->vpbankAccountName($acc->refresh());
        if ($name !== '') {
            $acc->name = $name;
            $acc->save();
        }

        return response()->json(['status' => '3', 'msg' => 'Thêm VPBank thành công']);
    }

    public function vpbankLoginOTP(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account = $request->input('account');
        $otp = $request->input('otp') ?: $request->input('otp_code');

        if (empty($account) || empty($otp)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu tài khoản hoặc OTP']);
        }

        $acc = AccountVpbank::where('user_id', $user->id)->where('username', $account)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy phiên VPBank đang chờ OTP']);
        }

        $confirm = $this->vpbankConfirmOtp($acc, (string) $otp);
        if (empty($confirm['success'])) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($confirm['message'] ?? 'Không xác thực được OTP VPBank')
            ]);
        }

        $acc->is_login = true;
        $name = $this->vpbankAccountName($acc);
        if ($name !== '') {
            $acc->name = $name;
        }
        $acc->save();

        return response()->json(['status' => '2', 'msg' => 'Thêm VPBank thành công']);
    }

    public function vpbankGetBalanceAPI(Request $request, $token)
    {
        $acc = AccountVpbank::where('token', $token)->first();
        if (!$acc) {
            return response()->json([
                'status' => 'false',
                'msg' => 'Token không hợp lệ'
            ]);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json([
                'status' => 'false',
                'msg' => 'Không tìm thấy chủ tài khoản'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $balance = $this->getBalanceVpbank($acc);
        if ((int) ($balance['code'] ?? 0) !== 200) {
            $ok = $this->vpbankReLoginIfNeed($acc);
            if ($ok) {
                $balance = $this->getBalanceVpbank($acc->refresh());
            }
        }

        if ((int) ($balance['code'] ?? 0) !== 200) {
            return response()->json([
                'status' => '99',
                'SoDu' => 0,
                'msg' => (string) ($balance['message'] ?? 'Không lấy được số dư VPBank')
            ]);
        }

        return response()->json([
            'status' => 200,
            'SoDu' => (int) ($balance['data']['balance'] ?? 0),
            'accountDescription' => (string) ($balance['data']['account_name'] ?? $acc->name ?? ''),
        ]);
    }

    public function vpbankHistory(Request $request, $account)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Chưa đăng nhập');
        }

        $acc = AccountVpbank::where('account', $account)->where('user_id', $user->id)->first();
        if (!$acc) {
            return redirect()->back()->with('error', 'Không tìm thấy tài khoản VPBank');
        }

        $history = $this->getTransactionHistoryVpbank(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate')
        );

        if ((int) ($history['code'] ?? 0) !== 200) {
            $ok = $this->vpbankReLoginIfNeed($acc);
            if ($ok) {
                $history = $this->getTransactionHistoryVpbank(
                    $acc->refresh(),
                    $request->query('from_date') ?: $request->query('fromDate'),
                    $request->query('to_date') ?: $request->query('toDate')
                );
            }
        }

        if ((int) ($history['code'] ?? 0) !== 200) {
            return redirect()->back()->with('error', (string) ($history['message'] ?? 'Không thể lấy lịch sử VPBank'));
        }

        $acc->refresh();

        $this->storeBankTransactions('vpbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        $transactions = $this->prepareBankHistoryTransactions($request, $history['data']['transactions'] ?? [], 'vpbank', (string) $acc->account);

        return view('payment.vpbankhistory', ['acc' => $acc] + $transactions);
    }

    public function vpbankSendToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountVpbank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        if (empty($acc->token)) {
            $acc->token = md5(uniqid() . time());
            $acc->save();
        }

        $token = $acc->token;

        return response()->json([
            'status' => '2',
            'msg' => "Token VPBank: {$token}\nAPI số dư: " . url("/v2/vpbank/balance/{$token}") . "\nAPI giao dịch: " . url("/v2/vpbank/transhistory/{$token}")
        ]);
    }

    public function vpbankRemove(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountVpbank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        $acc->delete();

        return response()->json(['status' => '2', 'msg' => 'Đã xoá tài khoản VPBank!']);
    }

    public function vpbankGetTransHistoryAPI(Request $request, $token)
    {
        $acc = AccountVpbank::where('token', $token)->first();
        if (!$acc) {
            return response()->json([
                'status' => 'false',
                'msg' => 'Không tìm thấy tài khoản VPBank theo token'
            ]);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json([
                'status' => 'false',
                'msg' => 'Không tìm thấy user sở hữu token'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $history = $this->getTransactionHistoryVpbank(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate')
        );

        if ((int) ($history['code'] ?? 0) !== 200) {
            $ok = $this->vpbankReLoginIfNeed($acc);
            if ($ok) {
                $history = $this->getTransactionHistoryVpbank(
                    $acc->refresh(),
                    $request->query('from_date') ?: $request->query('fromDate'),
                    $request->query('to_date') ?: $request->query('toDate')
                );
            }
        }

        $this->storeBankTransactions('vpbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return response()->json($history);
    }

    public function internalVpbankTransactionHistoryForReceiver(int $accountId): array
    {
        $acc = AccountVpbank::where('id', $accountId)->first();
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản VPBank nhận tiền'];
        }

        $history = $this->getTransactionHistoryVpbank($acc);
        if ((int) ($history['code'] ?? 0) !== 200) {
            $ok = $this->vpbankReLoginIfNeed($acc);
            if (!$ok) {
                return ['ok' => false, 'message' => 'Không auto re-login được VPBank nhận tiền'];
            }

            $history = $this->getTransactionHistoryVpbank($acc->refresh());
        }

        if ((int) ($history['code'] ?? 0) !== 200) {
            return ['ok' => false, 'message' => (string) ($history['message'] ?? 'Không thể lấy lịch sử VPBank sau khi login lại')];
        }

        $this->storeBankTransactions('vpbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return ['ok' => true, 'data' => $history];
    }

    private function vpbankLoginRequest(string $username, string $password): array
    {
        $requestId = $this->vpbankRequestId();
        $payload = [
            'Id' => '',
            'UserName' => $username,
            'AppType' => 'Consumers',
            'ChannelType' => 'Web',
            'Password' => $password,
            'UserLocale' => [
                'Country' => 'VN',
                'Language' => 'vi',
            ],
        ];

        $response = $this->vpbankHttp(
            'POST',
            'https://neo.vpbank.com.vn/cb/odata/ns/authenticationservice/SecureUsers?action=init',
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'Accept: application/json',
                'Accept-Language: vi',
                'X-Security-Request: required',
                'sap-cancel-on-close: false',
                'Content-Type: application/json',
                'Captcha:',
                'ServiceChannel:',
                'TrackingId:',
                'device-id: B4D079A6-655E-408B-94EF-91E357A82BAC',
                'device-os: Windows 10 - Mozilla Firefox',
                'device-version: Firefox 114',
                'description: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'notify-token-key-id: NONE',
                'sap-contextid-accept: header',
                'DataServiceVersion: 2.0',
                'MaxDataServiceVersion: 2.0',
                'Origin: https://neo.vpbank.com.vn',
                'Referer: https://neo.vpbank.com.vn/main.html',
                'Pragma: no-cache',
                'Cache-Control: no-cache',
                'X-Request-ID: ' . $requestId,
            ],
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return [
                'success' => false,
                'code' => (int) ($response['status'] ?? 0),
                'message' => 'Không đọc được phản hồi VPBank',
            ];
        }

        if (isset($body['error'])) {
            return [
                'success' => false,
                'code' => (int) ($response['status'] ?? 0),
                'message' => (string) ($body['error']['message']['value'] ?? 'Tài khoản hoặc mật khẩu VPBank không đúng'),
            ];
        }

        if (!isset($body['d'])) {
            return [
                'success' => false,
                'code' => 444,
                'message' => 'Tài khoản hoặc mật khẩu VPBank không đúng',
            ];
        }

        $needsOtp = (bool) ($body['d']['TRUSTED_DEVICE_ENABLED'] ?? false);

        return [
            'success' => true,
            'code' => $needsOtp ? 302 : 200,
            'needs_otp' => $needsOtp,
            'message' => $needsOtp ? 'Vui lòng nhập mã xác thực từ điện thoại' : 'Đăng nhập VPBank thành công',
            'token_key' => (string) ($response['headers']['tokenkey'] ?? ''),
            'csrf' => (string) ($response['headers']['x-csrf-token'] ?? ''),
            'cookie' => (string) ($response['cookie'] ?? ''),
        ];
    }

    private function vpbankConfirmOtp(AccountVpbank $acc, string $otp): array
    {
        $response = $this->vpbankHttp(
            'GET',
            'https://neo.vpbank.com.vn/cb/odata/services/retailuserservice/AuthorizeTrustedDevice',
            [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0',
                'Accept: application/json',
                'Accept-Language: vi',
                'X-Security-Request: required',
                'sap-cancel-on-close: true',
                'channelType: Web',
                'TokenKey: ' . $acc->token_key,
                'Pragma: no-cache',
                'Expires: -1',
                'X-Request-ID: ' . $this->vpbankRequestId(),
                'AuthorizationToken: ' . $otp,
                'sap-contextid-accept: header',
                'x-csrf-token: ' . $acc->csrf,
                'DataServiceVersion: 2.0',
                'MaxDataServiceVersion: 2.0',
                'Referer: https://neo.vpbank.com.vn/main.html',
            ],
            null,
            (string) $acc->cookie
        );

        if ((int) ($response['status'] ?? 0) === 403) {
            return ['success' => false, 'code' => 401, 'message' => 'Unauthorized VPBank'];
        }

        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'VPBank không phản hồi hợp lệ'];
        }

        if (isset($body['error'])) {
            return [
                'success' => false,
                'code' => (int) ($response['status'] ?? 0),
                'message' => (string) ($body['error']['message']['value'] ?? 'OTP VPBank không hợp lệ'),
            ];
        }

        if (($body['d']['StatusCode'] ?? null) === 0) {
            return ['success' => true, 'code' => 200, 'message' => 'Đăng nhập VPBank thành công'];
        }

        return ['success' => false, 'code' => 520, 'message' => 'Không xác thực được OTP VPBank'];
    }

    private function getBalanceVpbank(AccountVpbank $acc): array
    {
        $list = $this->vpbankListAccounts($acc);
        if (empty($list['success'])) {
            return $list;
        }

        $account = $this->vpbankFindAccount($list['accounts'] ?? [], (string) $acc->account);
        if (!$account) {
            return ['code' => 404, 'success' => false, 'message' => 'Không tìm thấy số tài khoản VPBank'];
        }

        $balance = (int) ($this->vpbankArrayValue($account, ['AvailableBalance', 'Balance'], 0));
        if ($balance < 0) {
            return [
                'code' => 448,
                'success' => false,
                'message' => 'Tài khoản VPBank đang có số dư âm',
                'data' => ['balance' => $balance],
            ];
        }

        $accountName = $this->vpbankNameFromAccount($account);
        if ($accountName !== '' && trim((string) $acc->name) === '') {
            $acc->name = $accountName;
            $acc->save();
        }

        return [
            'code' => 200,
            'success' => true,
            'message' => 'Thành công',
            'data' => [
                'account_number' => (string) $acc->account,
                'account_name' => $accountName ?: (string) ($acc->name ?? ''),
                'balance' => $balance,
            ],
        ];
    }

    private function getTransactionHistoryVpbank(AccountVpbank $acc, ?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDate = $fromDate ?: Carbon::now()->subDays(7)->format('d/m/Y');
        $toDate = $toDate ?: Carbon::now()->format('d/m/Y');

        $list = $this->vpbankListAccounts($acc);
        if (empty($list['success'])) {
            return $list;
        }

        $account = $this->vpbankFindAccount($list['accounts'] ?? [], (string) $acc->account);
        if (!$account) {
            return ['code' => 404, 'success' => false, 'message' => 'Không tìm thấy số tài khoản VPBank'];
        }

        $accountId = (string) ($account['Id'] ?? '');
        if ($accountId === '') {
            return ['code' => 404, 'success' => false, 'message' => 'Thiếu VPBank account id'];
        }

        $batchHeader = 'batch_' . substr((string) str_replace('-', '', uniqid('', true)), 0, 12);
        $requestId = $this->vpbankRequestId();
        $batchRequest = "--{$batchHeader}\r\n"
            . "Content-Type: application/http\r\n"
            . "Content-Transfer-Encoding: binary\r\n\r\n"
            . "GET DepositAccounts('{$accountId}')?\$expand=DepositAccountTransactions&fromDate={$fromDate}&toDate={$toDate} HTTP/1.1\r\n"
            . "sap-cancel-on-close: true\r\n"
            . "channelType: Web\r\n"
            . "TokenKey: {$acc->token_key}\r\n"
            . "Pragma: no-cache\r\n"
            . "Expires: -1\r\n"
            . "Cache-Control: no-cache,no-store,must-revalidate\r\n"
            . "X-Request-ID: {$requestId}\r\n"
            . "sap-contextid-accept: header\r\n"
            . "Accept: application/json\r\n"
            . "x-csrf-token: {$acc->csrf}\r\n"
            . "Accept-Language: vi\r\n"
            . "DataServiceVersion: 2.0\r\n"
            . "MaxDataServiceVersion: 2.0\r\n\r\n\r\n"
            . "--{$batchHeader}--\r\n";

        $response = $this->vpbankHttp(
            'POST',
            'https://neo.vpbank.com.vn/cb/odata/services/accountservice/$batch',
            [
                'Accept: multipart/mixed',
                'Accept-Language: vi',
                'Cache-Control: no-cache,no-store,must-revalidate',
                'Content-Type: multipart/mixed;boundary=' . $batchHeader,
                'DataServiceVersion: 2.0',
                'Expires: -1',
                'MaxDataServiceVersion: 2.0',
                'Origin: https://neo.vpbank.com.vn',
                'Pragma: no-cache',
                'Referer: https://neo.vpbank.com.vn/main.html',
                'TokenKey: ' . $acc->token_key,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
                'X-Request-ID: ' . $requestId,
                'X-Security-Request: required',
                'channelType: Web',
                'sap-cancel-on-close: true',
                'sap-contextid-accept: header',
                'x-csrf-token: ' . $acc->csrf,
            ],
            $batchRequest,
            (string) $acc->cookie
        );

        $bodyText = (string) ($response['body'] ?? '');
        $start = strpos($bodyText, '{');
        $end = strrpos($bodyText, '}');
        if ($start === false || $end === false || $end < $start) {
            return ['code' => 503, 'success' => false, 'message' => 'VPBank không trả lịch sử hợp lệ'];
        }

        $body = json_decode(substr($bodyText, $start, $end - $start + 1), true);
        if (!is_array($body)) {
            return ['code' => 503, 'success' => false, 'message' => 'Không đọc được lịch sử VPBank'];
        }

        if (isset($body['d']['DepositAccountTransactions']['results'])) {
            $transactions = $this->normaliseVpbankTransactions(
                $body['d']['DepositAccountTransactions']['results'],
                (string) $acc->account
            );
            $accountName = $this->vpbankNameFromAccount($account)
                ?: $this->vpbankNameFromTransactions($transactions, (string) $acc->account);
            if ($accountName !== '' && trim((string) $acc->name) === '') {
                $acc->name = $accountName;
                $acc->save();
            }

            return [
                'code' => 200,
                'codeStatus' => 200,
                'success' => true,
                'message' => 'Thành công',
                'data' => ['transactions' => $transactions],
                'transactions' => $transactions,
            ];
        }

        if (isset($body['error'])) {
            $code = (string) ($body['error']['code'] ?? '');
            return [
                'code' => $code === 'UAF' ? 401 : 400,
                'success' => false,
                'message' => (string) ($body['error']['message']['value'] ?? 'Không lấy được lịch sử VPBank'),
            ];
        }

        return ['code' => 503, 'success' => false, 'message' => 'VPBank không có dữ liệu lịch sử'];
    }

    private function vpbankListAccounts(AccountVpbank $acc): array
    {
        if (empty($acc->token_key) || empty($acc->csrf) || empty($acc->cookie)) {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên VPBank chưa sẵn sàng'];
        }

        $response = $this->vpbankHttp(
            'GET',
            'https://neo.vpbank.com.vn/cb/odata/services/accountservice/Accounts?%24top=500',
            [
                'Accept: application/json',
                'Accept-Language: vi',
                'Cache-Control: no-cache,no-store,must-revalidate',
                'DataServiceVersion: 2.0',
                'Expires: -1',
                'MaxDataServiceVersion: 2.0',
                'Pragma: no-cache',
                'Referer: https://neo.vpbank.com.vn/main.html',
                'TokenKey: ' . $acc->token_key,
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
                'X-Request-ID: ' . $this->vpbankRequestId(),
                'X-Security-Request: required',
                'channelType: Web',
                'sap-cancel-on-close: true',
                'sap-contextid-accept: header',
                'x-csrf-token: ' . $acc->csrf,
            ],
            null,
            (string) $acc->cookie
        );

        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'Không đọc được danh sách tài khoản VPBank'];
        }

        if (isset($body['error'])) {
            return [
                'success' => false,
                'code' => (string) ($body['error']['code'] ?? '') === 'UAF' ? 401 : 400,
                'message' => (string) ($body['error']['message']['value'] ?? 'Phiên VPBank hết hạn'),
            ];
        }

        if (isset($body['d']['results']) && is_array($body['d']['results'])) {
            return ['success' => true, 'code' => 200, 'accounts' => $body['d']['results']];
        }

        return ['success' => false, 'code' => 520, 'message' => 'VPBank không trả danh sách tài khoản'];
    }

    private function vpbankFindAccount(array $accounts, string $accountNo): ?array
    {
        foreach ($accounts as $account) {
            if ((string) $this->vpbankArrayValue($account, ['Number', 'AccountNumber'], '') === $accountNo) {
                return $account;
            }
        }

        return null;
    }

    private function vpbankAccountName(AccountVpbank $acc): string
    {
        $list = $this->vpbankListAccounts($acc);
        if (empty($list['success'])) {
            return '';
        }

        $account = $this->vpbankFindAccount($list['accounts'] ?? [], (string) $acc->account);
        return $account ? $this->vpbankNameFromAccount($account) : '';
    }

    private function vpbankNameFromAccount(array $account): string
    {
        $value = trim((string) $this->vpbankArrayValue($account, [
            'Name',
            'AccountName',
            'AccountHolderName',
            'AccountHolder',
            'CustomerName',
            'OwnerName',
            'ClientName',
            'AcctName',
            'DisplayName',
            'Description',
        ], ''));

        if ($value !== '') {
            return $value;
        }

        return '';
    }

    private function normaliseVpbankTransactions(array $transactions, string $accountNo): array
    {
        return array_map(function ($item) use ($accountNo) {
            if (!is_array($item)) {
                return $item;
            }

            $description = (string) $this->vpbankArrayValue($item, ['Description', 'TransactionDescription', 'Narrative', 'Remark', 'Content'], '');
            $reference = (string) $this->vpbankArrayValue($item, ['Reference', 'ReferenceNumber', 'TransactionId', 'Id', 'TransactionNumber', 'SeqNo'], '');
            $dateRaw = (string) $this->vpbankArrayValue($item, ['BookingDate', 'TransactionDate', 'PostingDate', 'ValueDate', 'Date'], '');
            $timeRaw = (string) $this->vpbankArrayValue($item, ['BookingTime', 'TransactionTime', 'PostingTime'], '');
            $amount = $this->vpbankTransactionAmount($item);
            $isCredit = $this->vpbankTransactionIsCredit($item, $amount, $description);

            $item['_description'] = $description;
            $item['_reference'] = $reference;
            $item['_date_text'] = $this->vpbankDateText($dateRaw, $timeRaw);
            $item['_date_key'] = $this->vpbankDateKey($dateRaw);
            $item['_amount'] = $amount;
            $item['_is_credit'] = $isCredit;
            $item['_account_name'] = $this->vpbankNameFromText($description, $accountNo);

            return $item;
        }, $transactions);
    }

    private function vpbankTransactionAmount(array $item): int
    {
        $credit = $this->vpbankMoneyValue($this->vpbankArrayValue($item, ['CreditAmount'], null));
        if ($credit > 0) {
            return abs($credit);
        }

        $debit = $this->vpbankMoneyValue($this->vpbankArrayValue($item, ['DebitAmount'], null));
        if ($debit > 0) {
            return -abs($debit);
        }

        return $this->vpbankMoneyValue($this->vpbankArrayValue($item, [
            'Amount',
            'TransactionAmount',
            'TransactionAmountValue',
            'AmountInTransactionCurrency',
        ], 0));
    }

    private function vpbankTransactionIsCredit(array $item, int $amount, string $description): bool
    {
        $direction = strtoupper(trim((string) $this->vpbankArrayValue($item, [
            'CD',
            'CreditDebitIndicator',
            'DebitCreditIndicator',
            'TransactionType',
            'Type',
        ], '')));

        if (in_array($direction, ['+', 'C', 'CR', 'CREDIT', 'IN'], true)) {
            return true;
        }
        if (in_array($direction, ['-', 'D', 'DR', 'DEBIT', 'OUT'], true)) {
            return false;
        }

        if ($amount > 0) {
            return true;
        }
        if ($amount < 0) {
            return false;
        }

        $upperDescription = mb_strtoupper($description, 'UTF-8');
        return str_contains($upperDescription, 'NHAN TU')
            || str_contains($upperDescription, 'NHẬN TỪ')
            || str_contains($upperDescription, 'NHAN TIEN')
            || str_contains($upperDescription, 'RECEIVE');
    }

    private function vpbankMoneyValue($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $raw = trim((string) $value);
        $negative = str_contains($raw, '-') || (str_starts_with($raw, '(') && str_ends_with($raw, ')'));
        $normalised = str_replace([',', ' ', 'VND', 'vnd', 'đ'], '', $raw);
        $normalised = preg_replace('/[^0-9.\-]/', '', $normalised) ?: '0';
        $amount = (int) round((float) $normalised);

        return $negative ? -abs($amount) : $amount;
    }

    private function vpbankDateText(string $dateRaw, string $timeRaw = ''): string
    {
        $dateRaw = trim($dateRaw);
        $timeRaw = trim($timeRaw);

        if ($dateRaw === '') {
            return '';
        }

        try {
            if (preg_match('/\/Date\((\d+)(?:[+-]\d+)?\)\//', $dateRaw, $match)) {
                $date = Carbon::createFromTimestamp((int) floor(((int) $match[1]) / 1000), config('app.timezone', 'Asia/Ho_Chi_Minh'));
                return $date->format('d/m/Y') . ($timeRaw !== '' ? ' ' . $this->vpbankTimeText($timeRaw) : '');
            }

            if (preg_match('/^\d{13}$/', $dateRaw)) {
                return Carbon::createFromTimestamp((int) floor(((int) $dateRaw) / 1000), config('app.timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
            }

            if (preg_match('/^\d{10}$/', $dateRaw)) {
                return Carbon::createFromTimestamp((int) $dateRaw, config('app.timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
                return Carbon::parse($dateRaw)->format('d/m/Y') . ($timeRaw !== '' ? ' ' . $this->vpbankTimeText($timeRaw) : '');
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateRaw)) {
                return $dateRaw . ($timeRaw !== '' ? ' ' . $this->vpbankTimeText($timeRaw) : '');
            }

            return Carbon::parse($dateRaw)->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return $dateRaw . ($timeRaw !== '' ? ' ' . $timeRaw : '');
        }
    }

    private function vpbankDateKey(string $dateRaw): string
    {
        $text = $this->vpbankDateText($dateRaw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $text, $match)) {
            return $match[1] . $match[2] . substr($match[3], -2);
        }

        return date('dmy');
    }

    private function vpbankTimeText(string $timeRaw): string
    {
        $timeRaw = trim($timeRaw);
        if (preg_match('/^\d{6}$/', $timeRaw)) {
            return substr($timeRaw, 0, 2) . ':' . substr($timeRaw, 2, 2) . ':' . substr($timeRaw, 4, 2);
        }

        return $timeRaw;
    }

    private function vpbankNameFromTransactions(array $transactions, string $accountNo): string
    {
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }

            $name = trim((string) ($transaction['_account_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }

            $name = $this->vpbankNameFromText((string) ($transaction['_description'] ?? ''), $accountNo);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function vpbankNameFromText(string $text, string $accountNo): string
    {
        if ($text === '' || $accountNo === '') {
            return '';
        }

        $pattern = '/(?:toi|to)\s+' . preg_quote($accountNo, '/') . '\s+(.+?)\s+tai\s+VPBANK/iu';
        if (preg_match($pattern, $text, $match)) {
            return trim(preg_replace('/\s+/', ' ', $match[1]));
        }

        return '';
    }

    private function vpbankArrayValue(array $item, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                return $item[$key];
            }
        }

        $lowerMap = [];
        foreach ($item as $key => $value) {
            $lowerMap[strtolower((string) $key)] = $value;
        }

        foreach ($keys as $key) {
            $lowerKey = strtolower((string) $key);
            if (array_key_exists($lowerKey, $lowerMap) && $lowerMap[$lowerKey] !== null && $lowerMap[$lowerKey] !== '') {
                return $lowerMap[$lowerKey];
            }
        }

        return $default;
    }

    private function vpbankReLoginIfNeed(AccountVpbank $acc): bool
    {
        try {
            $login = $this->vpbankLoginRequest((string) $acc->username, (string) $acc->password);
            if (!empty($login['success']) && empty($login['needs_otp'])) {
                $acc->token_key = (string) ($login['token_key'] ?? '');
                $acc->csrf = (string) ($login['csrf'] ?? '');
                $acc->cookie = (string) ($login['cookie'] ?? '');
                $acc->is_login = true;
                $acc->create_date = now();
                $acc->save();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function vpbankHttp(string $method, string $url, array $headers = [], ?string $body = null, string $cookie = ''): array
    {
        $ch = curl_init();
        $responseHeaders = [];
        $setCookieHeaders = [];

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => '',
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$responseHeaders, &$setCookieHeaders) {
                $length = strlen($header);
                $parts = explode(':', trim($header), 2);
                if (count($parts) === 2) {
                    $name = strtolower($parts[0]);
                    $value = trim($parts[1]);
                    $responseHeaders[$name] = $value;
                    if ($name === 'set-cookie') {
                        $setCookieHeaders[] = $value;
                    }
                }
                return $length;
            },
        ];

        if ($cookie !== '') {
            $opts[CURLOPT_COOKIE] = $cookie;
        }

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $cookieList = curl_getinfo($ch, CURLINFO_COOKIELIST) ?: [];
        curl_close($ch);

        $cookieHeader = $this->vpbankCookieHeader(is_array($cookieList) ? $cookieList : [], $cookie);
        if ($cookieHeader === $cookie && $setCookieHeaders) {
            $cookieHeader = $this->vpbankSetCookieHeader($setCookieHeaders, $cookie);
        }

        return [
            'ok' => $error === '',
            'status' => $status,
            'body' => $responseBody === false ? '' : (string) $responseBody,
            'headers' => $responseHeaders,
            'cookie' => $cookieHeader,
            'error' => $error,
        ];
    }

    private function vpbankCookieHeader(array $cookieList, string $fallback = ''): string
    {
        $cookies = [];
        if ($fallback !== '') {
            foreach (explode(';', $fallback) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) === 2 && $pair[0] !== '') {
                    $cookies[$pair[0]] = $pair[1];
                }
            }
        }

        foreach ($cookieList as $line) {
            $parts = explode("\t", (string) $line);
            if (count($parts) >= 7) {
                $name = trim((string) $parts[5]);
                $value = trim((string) $parts[6]);
                if ($name !== '') {
                    $cookies[$name] = $value;
                }
            }
        }

        if (!$cookies) {
            return $fallback;
        }

        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    private function vpbankSetCookieHeader(array $headers, string $fallback = ''): string
    {
        $cookies = [];
        if ($fallback !== '') {
            foreach (explode(';', $fallback) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) === 2 && $pair[0] !== '') {
                    $cookies[$pair[0]] = $pair[1];
                }
            }
        }

        foreach ($headers as $header) {
            $firstPart = explode(';', (string) $header, 2)[0] ?? '';
            $pair = explode('=', trim($firstPart), 2);
            if (count($pair) === 2 && $pair[0] !== '') {
                $cookies[$pair[0]] = $pair[1];
            }
        }

        if (!$cookies) {
            return $fallback;
        }

        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        return implode('; ', $pairs);
    }

    private function vpbankRequestId(int $length = 15): string
    {
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= (string) random_int(0, 9);
        }

        return $id;
    }


    // =========================================================================
    // =============             M B B A N K                 ====================
    // =========================================================================

    public function mbbankIndex()
    {
        return redirect()->route('bank.accounts.index');
    }

    public function mbbankLogin(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account = trim((string) $request->input('account'));
        $password = (string) $request->input('password');
        $stk = trim((string) $request->input('stk'));

        if ($account === '' || $password === '' || $stk === '') {
            return response()->json(['status' => '1', 'msg' => 'Vui lòng nhập đủ tài khoản, mật khẩu và số tài khoản MBBank']);
        }

        $isSystemReceiver = $request->boolean('system_receiver') && (int) ($user->role ?? 0) === 1;
        $accountLimit = ApiPackage::userLimit($user);
        $existing = AccountMbbank::where('user_id', $user->id)->where('account', $stk)->first();
        if (!$isSystemReceiver && !$existing && $accountLimit > 0 && $this->userBankAccountCount((int) $user->id) >= $accountLimit) {
            return response()->json([
                'status' => '1',
                'msg' => $this->accountLimitMessage($accountLimit),
            ]);
        }

        $deviceId = $existing && $existing->device_id ? (string) $existing->device_id : $this->mbbankDeviceId();
        $login = $this->mbbankLoginRequest($account, $password, $deviceId);
        if (empty($login['success'])) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($login['message'] ?? 'Không thể kết nối MBBank'),
            ]);
        }

        $sessionId = (string) ($login['session_id'] ?? '');
        $balance = $this->getBalanceMbbankRaw($account, $sessionId, $deviceId);
        if ((int) ($balance['code'] ?? 0) !== 200) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($balance['message'] ?? 'Đăng nhập được nhưng không lấy được số dư MBBank'),
            ]);
        }

        $accountInfo = $this->mbbankFindBalanceAccount($balance['data'] ?? [], $stk);
        if (!$accountInfo) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy số tài khoản MBBank này']);
        }

        AccountMbbank::updateOrCreate(
            [
                'user_id' => $user->id,
                'account' => $stk,
            ],
            [
                'username' => $account,
                'password' => $password,
                'name' => (string) ($accountInfo['name'] ?? ($existing->name ?? '')),
                'session_id' => $sessionId,
                'device_id' => $deviceId,
                'token' => $existing->token ?? md5(uniqid('', true) . time()),
                'balance' => (int) ($accountInfo['balance'] ?? 0),
                'create_date' => now(),
            ]
        );

        return response()->json(['status' => '2', 'msg' => 'Thêm MBBank thành công']);
    }

    public function mbbankGetBalanceAPI(Request $request, $token)
    {
        $acc = AccountMbbank::where('token', $token)->first();
        if (!$acc) {
            return response()->json(['status' => 'false', 'msg' => 'Token MBBank không hợp lệ']);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy chủ tài khoản']);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $balance = $this->getBalanceMbbankWithSessionRetry($acc);

        if ((int) ($balance['code'] ?? 0) !== 200) {
            return response()->json([
                'status' => '99',
                'SoDu' => 0,
                'msg' => (string) ($balance['message'] ?? 'Phiên MBBank hết hạn, vui lòng kết nối lại tài khoản'),
            ]);
        }

        return response()->json([
            'status' => 200,
            'SoDu' => (int) ($balance['data']['balance'] ?? 0),
            'accountDescription' => (string) ($balance['data']['account_name'] ?? $acc->name ?? ''),
        ]);
    }

    public function mbbankHistory(Request $request, $account)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Chưa đăng nhập');
        }

        $acc = AccountMbbank::where('account', $account)->where('user_id', $user->id)->first();
        if (!$acc) {
            return redirect()->back()->with('error', 'Không tìm thấy tài khoản MBBank');
        }

        $history = $this->getTransactionHistoryMbbankWithSessionRetry(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate')
        );

        if ((int) ($history['code'] ?? 0) !== 200) {
            return redirect()->back()->with('error', (string) ($history['message'] ?? 'Không thể lấy lịch sử MBBank'));
        }

        $acc->refresh();
        $this->storeBankTransactions('mbbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        $transactions = $this->prepareBankHistoryTransactions($request, $history['data']['transactions'] ?? [], 'mbbank', (string) $acc->account);

        return view('payment.mbbankhistory', ['acc' => $acc] + $transactions);
    }

    public function mbbankSendToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountMbbank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        if (empty($acc->token)) {
            $acc->token = md5(uniqid('', true) . time());
            $acc->save();
        }

        $token = $acc->token;

        return response()->json([
            'status' => '2',
            'msg' => "Token MBBank: {$token}\nAPI số dư: " . url("/v2/mbbank/balance/{$token}") . "\nAPI giao dịch: " . url("/v2/mbbank/transhistory/{$token}"),
        ]);
    }

    public function mbbankRemove(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountMbbank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        $acc->delete();

        return response()->json(['status' => '2', 'msg' => 'Đã xoá tài khoản MBBank!']);
    }

    public function mbbankGetTransHistoryAPI(Request $request, $token)
    {
        $acc = AccountMbbank::where('token', $token)->first();
        if (!$acc) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy tài khoản MBBank theo token']);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy user sở hữu token']);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $history = $this->getTransactionHistoryMbbankWithSessionRetry(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate'),
            min(500, max(20, (int) $request->query('limit', 100)))
        );

        $this->storeBankTransactions('mbbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return response()->json($history);
    }

    public function internalMbbankTransactionHistoryForReceiver(int $accountId): array
    {
        $acc = AccountMbbank::where('id', $accountId)->first();
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản MBBank nhận tiền'];
        }

        $history = $this->getTransactionHistoryMbbankWithSessionRetry($acc, null, null, 100);

        if ((int) ($history['code'] ?? 0) !== 200) {
            return ['ok' => false, 'message' => (string) ($history['message'] ?? 'Không thể lấy lịch sử MBBank')];
        }

        $acc->refresh();
        $this->storeBankTransactions('mbbank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return ['ok' => true, 'data' => $history];
    }

    private function mbbankLoginRequest(string $username, string $password, string $deviceId): array
    {
        $captcha = $this->mbbankCaptcha($username, $deviceId);
        if (empty($captcha['success'])) {
            return ['success' => false, 'message' => (string) ($captcha['message'] ?? 'Không lấy được captcha MBBank')];
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $refNo = $this->mbbankTimeNow();
            $requestData = [
                'userId' => $username,
                'password' => md5($password),
                'captcha' => (string) ($captcha['captcha'] ?? ''),
                'ibAuthen2faString' => 'c7a1beebb9400375bb187daa33de9659',
                'sessionId' => null,
                'refNo' => $refNo,
                'deviceIdCommon' => $deviceId,
            ];
            $encrypted = $this->mbbankEncrypt($requestData);
            if (empty($encrypted['dataEnc'])) {
                return ['success' => false, 'message' => (string) ($encrypted['message'] ?? 'Không mã hoá được dữ liệu MBBank')];
            }

            $response = $this->mbbankHttp(
                'POST',
                'https://online.mbbank.com.vn/api/retail_web/internetbanking/v2.0/doLogin',
                $this->mbbankHeaders($refNo, $deviceId),
                json_encode(['dataEnc' => $encrypted['dataEnc']])
            );
            $body = json_decode((string) ($response['body'] ?? ''), true);
            if (is_array($body) && !empty($body['result']['ok']) && !empty($body['sessionId'])) {
                return ['success' => true, 'session_id' => (string) $body['sessionId']];
            }

            $code = (string) ($body['result']['responseCode'] ?? '');
            if ($code === 'GW283' && $attempt === 0) {
                $captcha = $this->mbbankCaptcha($username, $deviceId);
                if (empty($captcha['success'])) {
                    break;
                }
                continue;
            }

            return [
                'success' => false,
                'message' => (string) ($body['result']['message'] ?? $body['message'] ?? 'Đăng nhập MBBank thất bại'),
            ];
        }

        return ['success' => false, 'message' => 'Captcha MBBank không chính xác, vui lòng thử lại'];
    }

    private function mbbankCaptcha(string $username, string $deviceId): array
    {
        $refNo = $this->mbbankRefNo($username);
        $captchaResponse = $this->mbbankHttp(
            'POST',
            'https://online.mbbank.com.vn/api/retail-internetbankingms/getCaptchaImage',
            $this->mbbankHeaders($refNo, $deviceId),
            json_encode([
                'sessionId' => '',
                'refNo' => $refNo,
                'deviceIdCommon' => $deviceId,
            ])
        );
        $captchaBody = json_decode((string) ($captchaResponse['body'] ?? ''), true);
        $image = (string) ($captchaBody['imageString'] ?? '');
        if ($image === '') {
            return ['success' => false, 'message' => 'MBBank không trả captcha'];
        }

        $captchaApi = $this->mbbankCaptchaApi($image);
        if ($captchaApi === '') {
            return ['success' => false, 'message' => 'Không giải được captcha MBBank'];
        }

        return ['success' => true, 'captcha' => $captchaApi];
    }

    private function mbbankCaptchaApi(string $imageBase64): string
    {
        $url = (string) (config('services.mbbank.captcha_url') ?: env('MBBANK_CAPTCHA_API_URL', 'https://captcha.apibank.com.vn/api/mbb'));
        $apiKey = (string) (config('services.mbbank.captcha_key') ?: env('MBBANK_CAPTCHA_API_KEY', env('VCB_CAPTCHA_API_KEY', '')));
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $response = $this->simpleCurl('POST', $url, $headers, json_encode(['base64' => $imageBase64]), 20);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return '';
        }

        return trim((string) ($body['captcha'] ?? $body['result'] ?? $body['data']['captcha'] ?? ''));
    }

    private function getBalanceMbbank(AccountMbbank $acc): array
    {
        $result = $this->getBalanceMbbankRaw((string) $acc->username, (string) $acc->session_id, (string) $acc->device_id);
        if ((int) ($result['code'] ?? 0) !== 200) {
            return $result;
        }

        $account = $this->mbbankFindBalanceAccount($result['data'] ?? [], (string) $acc->account);
        if (!$account) {
            return ['success' => false, 'code' => 404, 'message' => 'Không tìm thấy số tài khoản MBBank'];
        }

        $acc->balance = (int) ($account['balance'] ?? 0);
        if (trim((string) $acc->name) === '' && !empty($account['name'])) {
            $acc->name = (string) $account['name'];
        }
        $acc->save();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Thành công',
            'data' => [
                'account_number' => (string) $acc->account,
                'account_name' => (string) ($account['name'] ?? $acc->name ?? ''),
                'balance' => (int) ($account['balance'] ?? 0),
            ],
        ];
    }

    private function getBalanceMbbankRaw(string $username, string $sessionId, string $deviceId): array
    {
        if ($sessionId === '' || $deviceId === '') {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên MBBank chưa sẵn sàng'];
        }

        $refNo = $this->mbbankRefNo($username);
        $response = $this->mbbankHttp(
            'POST',
            'https://online.mbbank.com.vn/api/retail-accountms/accountms/getBalance',
            $this->mbbankHeaders($refNo, $deviceId, 'https://online.mbbank.com.vn/information-account/source-account'),
            json_encode([
                'sessionId' => $sessionId,
                'refNo' => $refNo,
                'deviceIdCommon' => $deviceId,
            ])
        );
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'Không đọc được số dư MBBank'];
        }

        if (!empty($body['result']['ok'])) {
            return [
                'success' => true,
                'code' => 200,
                'message' => 'Thành công',
                'data' => $body,
            ];
        }

        return [
            'success' => false,
            'code' => 401,
            'message' => (string) ($body['result']['message'] ?? $body['message'] ?? 'Phiên MBBank hết hạn'),
        ];
    }

    private function getTransactionHistoryMbbank(AccountMbbank $acc, ?string $fromDate = null, ?string $toDate = null, int $limit = 100): array
    {
        if (empty($acc->session_id) || empty($acc->device_id)) {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên MBBank chưa sẵn sàng'];
        }

        $refNo = $this->mbbankRefNo((string) $acc->username);
        $response = $this->mbbankHttp(
            'POST',
            'https://online.mbbank.com.vn/api/retail-transactionms/transactionms/get-account-transaction-history',
            $this->mbbankHeaders($refNo, (string) $acc->device_id, 'https://online.mbbank.com.vn/information-account/source-account'),
            json_encode([
                'accountNo' => (string) $acc->account,
                'fromDate' => $this->mbbankDateParam($fromDate, Carbon::now()->subDays(7)),
                'toDate' => $this->mbbankDateParam($toDate, Carbon::now()),
                'sessionId' => (string) $acc->session_id,
                'refNo' => $refNo,
                'deviceIdCommon' => (string) $acc->device_id,
            ])
        );
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'Không đọc được lịch sử MBBank'];
        }
        if (empty($body['result']['ok'])) {
            return [
                'success' => false,
                'code' => 401,
                'message' => (string) ($body['result']['message'] ?? $body['message'] ?? 'Phiên MBBank hết hạn'),
            ];
        }

        $transactions = $this->normaliseMbbankTransactions(
            array_slice((array) ($body['transactionHistoryList'] ?? []), 0, max(1, min(500, $limit)))
        );

        return [
            'success' => true,
            'code' => 200,
            'codeStatus' => 200,
            'message' => 'Thành công',
            'data' => ['transactions' => $transactions],
            'transactions' => $transactions,
        ];
    }

    private function getBalanceMbbankWithSessionRetry(AccountMbbank $acc): array
    {
        $balance = $this->getBalanceMbbank($acc);
        if ((int) ($balance['code'] ?? 0) === 200) {
            return $balance;
        }

        return $this->retryMbbankAfterLogin($acc, function (AccountMbbank $freshAcc) {
            return $this->getBalanceMbbank($freshAcc);
        }, $balance);
    }

    private function getTransactionHistoryMbbankWithSessionRetry(AccountMbbank $acc, ?string $fromDate = null, ?string $toDate = null, int $limit = 100): array
    {
        $history = $this->getTransactionHistoryMbbank($acc, $fromDate, $toDate, $limit);
        if ((int) ($history['code'] ?? 0) === 200) {
            return $history;
        }

        return $this->retryMbbankAfterLogin($acc, function (AccountMbbank $freshAcc) use ($fromDate, $toDate, $limit) {
            return $this->getTransactionHistoryMbbank($freshAcc, $fromDate, $toDate, $limit);
        }, $history);
    }

    private function retryMbbankAfterLogin(AccountMbbank $acc, callable $reader, array $fallback): array
    {
        $result = $fallback;

        for ($loginAttempt = 0; $loginAttempt < 2; $loginAttempt++) {
            if (!$this->mbbankReLoginIfNeed($acc)) {
                break;
            }

            foreach ([800000, 1400000] as $delay) {
                usleep($delay);
                $result = $reader($acc->refresh());
                if ((int) ($result['code'] ?? 0) === 200) {
                    return $result;
                }
            }
        }

        return $result;
    }

    private function mbbankReLoginIfNeed(AccountMbbank $acc): bool
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $deviceId = $attempt === 0 && !empty($acc->device_id)
                    ? (string) $acc->device_id
                    : $this->mbbankDeviceId();

                if ($attempt > 0) {
                    usleep(450000);
                }

                $login = $this->mbbankLoginRequest((string) $acc->username, (string) $acc->password, $deviceId);
                if (!empty($login['success']) && !empty($login['session_id'])) {
                    $acc->session_id = (string) $login['session_id'];
                    $acc->device_id = $deviceId;
                    $acc->create_date = now();
                    $acc->save();

                    usleep(250000);
                    return true;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return false;
    }

    private function mbbankFindBalanceAccount(array $data, string $accountNo): ?array
    {
        $accounts = array_merge((array) ($data['acct_list'] ?? []), (array) ($data['internationalAcctList'] ?? []));
        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            if ((string) ($account['acctNo'] ?? '') === $accountNo) {
                return [
                    'number' => (string) ($account['acctNo'] ?? ''),
                    'name' => (string) ($account['acctNm'] ?? ''),
                    'balance' => (int) str_replace([',', '.00'], '', (string) ($account['currentBalance'] ?? 0)),
                ];
            }
        }

        return null;
    }

    private function normaliseMbbankTransactions(array $transactions): array
    {
        return array_map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }

            $credit = (int) str_replace([',', '.00'], '', (string) ($item['creditAmount'] ?? 0));
            $debit = (int) str_replace([',', '.00'], '', (string) ($item['debitAmount'] ?? 0));
            $amount = $credit > 0 ? $credit : -abs($debit);
            $dateText = (string) ($item['transactionDate'] ?? $item['postingDate'] ?? '');
            $reference = (string) ($item['refNo'] ?? '');

            $item['_description'] = (string) ($item['description'] ?? $item['transactionDesc'] ?? '');
            $item['_reference'] = $reference;
            $item['_date_text'] = $dateText;
            $item['_date_key'] = preg_replace('/\D+/', '', $dateText) ?: date('dmy');
            $item['_amount'] = $amount;
            $item['_is_credit'] = $credit > 0;

            return $item;
        }, $transactions);
    }

    private function mbbankEncrypt(array $payload): array
    {
        $url = (string) (config('services.mbbank.encrypt_url') ?: env('MBBANK_ENCRYPT_URL', 'http://127.0.0.1:3197/encrypt'));
        $response = $this->simpleCurl('POST', $url, ['Content-Type: application/json'], json_encode($payload), 20);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body) || empty($body['dataEnc'])) {
            return ['success' => false, 'message' => (string) ($body['message'] ?? 'MBBank encrypt service không phản hồi')];
        }

        return ['success' => true, 'dataEnc' => (string) $body['dataEnc']];
    }

    private function mbbankHeaders(string $refNo, string $deviceId = '', string $referer = 'https://online.mbbank.com.vn/'): array
    {
        $headers = [
            'Host: online.mbbank.com.vn',
            'Cache-Control: no-cache',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: vi,vi-VN;q=0.9,en-US;q=0.8,en;q=0.7',
            'Authorization: Basic RU1CUkVUQUlMV0VCOlNEMjM0ZGZnMzQlI0BGR0AzNHNmc2RmNDU4NDNm',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Origin: https://online.mbbank.com.vn',
            'Referer: ' . $referer,
            'Content-Type: application/json; charset=UTF-8',
            'app: MB_WEB',
            'X-Request-Id: ' . $refNo,
            'RefNo: ' . $refNo,
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'sec-ch-ua: "Chromium";v="134", "Not:A-Brand";v="24", "Google Chrome";v="134"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'elastic-apm-traceparent: 00-' . bin2hex(random_bytes(16)) . '-' . bin2hex(random_bytes(8)) . '-01',
            'priority: u=1, i',
        ];

        if ($deviceId !== '') {
            $headers[] = 'Deviceid: ' . $deviceId;
            $headers[] = 'deviceid: ' . $deviceId;
            $headers[] = 'deviceId: ' . $deviceId;
        }

        return $headers;
    }

    private function mbbankHttp(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        return $this->simpleCurl($method, $url, $headers, $body, 20);
    }

    private function simpleCurl(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 20): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '',
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'ok' => $error === '',
            'status' => $status,
            'body' => $responseBody === false ? '' : (string) $responseBody,
            'error' => $error,
        ];
    }

    private function mbbankDateParam(?string $value, Carbon $default): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default->format('d/m/Y');
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return $value;
            }

            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $default->format('d/m/Y');
        }
    }

    private function mbbankDeviceId(): string
    {
        return $this->generateRandomHex(8) . '-' . $this->generateRandomHex(4) . '-0000-0000-' . $this->mbbankTimeNow();
    }

    private function mbbankRefNo(string $username): string
    {
        return $username . '-' . $this->mbbankTimeNow();
    }

    private function mbbankTimeNow(): string
    {
        $micro = microtime(true);
        return date('YmdHis', (int) $micro) . substr(str_pad((string) floor(($micro - floor($micro)) * 1000), 3, '0', STR_PAD_LEFT), 0, 2);
    }

    private function generateRandomHex(int $length): string
    {
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= dechex(random_int(0, 15));
        }

        return $value;
    }

    // =========================================================================
    // =============           T E C H C O M B A N K       =====================
    // =========================================================================

    public function techcombankIndex()
    {
        return redirect()->route('bank.accounts.index');
    }

    public function techcombankLogin(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account = trim((string) $request->input('account'));
        $password = (string) $request->input('password');
        $stk = trim((string) $request->input('stk'));

        if ($account === '' || $password === '' || $stk === '') {
            return response()->json(['status' => '1', 'msg' => 'Vui lòng nhập đủ tài khoản, mật khẩu và số tài khoản Techcombank']);
        }

        $isSystemReceiver = $request->boolean('system_receiver') && (int) ($user->role ?? 0) === 1;
        $accountLimit = ApiPackage::userLimit($user);
        $existing = AccountTechcombank::where('user_id', $user->id)->where('account', $stk)->first();
        if (!$isSystemReceiver && !$existing && $accountLimit > 0 && $this->userBankAccountCount((int) $user->id) >= $accountLimit) {
            return response()->json([
                'status' => '1',
                'msg' => $this->accountLimitMessage($accountLimit),
            ]);
        }

        $login = $this->techcombankLoginRequest($account, $password);
        if (empty($login['success'])) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($login['message'] ?? 'Không thể kết nối Techcombank'),
            ]);
        }

        AccountTechcombank::updateOrCreate(
            [
                'user_id' => $user->id,
                'account' => $stk,
            ],
            [
                'username' => $account,
                'password' => $password,
                'name' => $existing->name ?? null,
                'auth_token' => null,
                'refresh_token' => null,
                'arrangement_id' => null,
                'cookie' => (string) ($login['cookie'] ?? ''),
                'login_url' => (string) ($login['login_url'] ?? ''),
                'code_verifier' => (string) ($login['code_verifier'] ?? ''),
                'code_challenge' => (string) ($login['code_challenge'] ?? ''),
                'state' => (string) ($login['state'] ?? ''),
                'nonce' => (string) ($login['nonce'] ?? ''),
                'is_login' => false,
                'token' => $existing->token ?? md5(uniqid('', true) . time()),
                'balance' => $existing->balance ?? null,
                'create_date' => now(),
            ]
        );

        return response()->json([
            'status' => '2',
            'msg' => 'Techcombank đã gửi yêu cầu xác nhận tới app Mobile. Hãy duyệt trên điện thoại rồi bấm Hoàn tất xác nhận.',
        ]);
    }

    public function techcombankConfirmLogin(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account = trim((string) $request->input('account'));
        $stk = trim((string) $request->input('stk'));
        if ($account === '' || $stk === '') {
            return response()->json(['status' => '1', 'msg' => 'Thiếu phiên xác nhận Techcombank']);
        }

        $acc = AccountTechcombank::where('user_id', $user->id)
            ->where('username', $account)
            ->where('account', $stk)
            ->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy phiên Techcombank đang chờ xác nhận']);
        }

        $confirm = $this->techcombankCompleteLogin($acc);
        if (empty($confirm['success'])) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($confirm['message'] ?? 'Chưa xác nhận được trên app Techcombank'),
            ]);
        }

        $balance = $this->getBalanceTechcombank($acc->refresh());
        if ((int) ($balance['code'] ?? 0) !== 200) {
            return response()->json([
                'status' => '1',
                'msg' => (string) ($balance['message'] ?? 'Đăng nhập được nhưng không tìm thấy số tài khoản Techcombank này'),
            ]);
        }

        return response()->json(['status' => '2', 'msg' => 'Thêm Techcombank thành công']);
    }

    public function techcombankGetBalanceAPI(Request $request, $token)
    {
        $acc = AccountTechcombank::where('token', $token)->first();
        if (!$acc) {
            return response()->json(['status' => 'false', 'msg' => 'Token Techcombank không hợp lệ']);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy chủ tài khoản']);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $balance = $this->getBalanceTechcombank($acc);
        if ((int) ($balance['code'] ?? 0) !== 200 && $this->techcombankRefreshIfNeed($acc)) {
            $balance = $this->getBalanceTechcombank($acc->refresh());
        }

        if ((int) ($balance['code'] ?? 0) !== 200) {
            return response()->json([
                'status' => '99',
                'SoDu' => 0,
                'msg' => (string) ($balance['message'] ?? 'Phiên Techcombank hết hạn, vui lòng kết nối lại tài khoản'),
            ]);
        }

        return response()->json([
            'status' => 200,
            'SoDu' => (int) ($balance['data']['balance'] ?? 0),
            'accountDescription' => (string) ($balance['data']['account_name'] ?? $acc->name ?? ''),
        ]);
    }

    public function techcombankHistory(Request $request, $account)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Chưa đăng nhập');
        }

        $acc = AccountTechcombank::where('account', $account)->where('user_id', $user->id)->first();
        if (!$acc) {
            return redirect()->back()->with('error', 'Không tìm thấy tài khoản Techcombank');
        }

        $history = $this->getTransactionHistoryTechcombank(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate')
        );
        if ((int) ($history['code'] ?? 0) !== 200 && $this->techcombankRefreshIfNeed($acc)) {
            $history = $this->getTransactionHistoryTechcombank(
                $acc->refresh(),
                $request->query('from_date') ?: $request->query('fromDate'),
                $request->query('to_date') ?: $request->query('toDate')
            );
        }

        if ((int) ($history['code'] ?? 0) !== 200) {
            return redirect()->back()->with('error', (string) ($history['message'] ?? 'Không thể lấy lịch sử Techcombank'));
        }

        $acc->refresh();

        $this->storeBankTransactions('techcombank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        $transactions = $this->prepareBankHistoryTransactions($request, $history['data']['transactions'] ?? [], 'techcombank', (string) $acc->account);

        return view('payment.techcombankhistory', ['acc' => $acc] + $transactions);
    }

    public function techcombankSendToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountTechcombank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        if (empty($acc->token)) {
            $acc->token = md5(uniqid('', true) . time());
            $acc->save();
        }

        $token = $acc->token;

        return response()->json([
            'status' => '2',
            'msg' => "Token Techcombank: {$token}\nAPI số dư: " . url("/v2/techcombank/balance/{$token}") . "\nAPI giao dịch: " . url("/v2/techcombank/transhistory/{$token}"),
        ]);
    }

    public function techcombankRemove(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status' => '1', 'msg' => 'Thiếu ID']);
        }

        $acc = AccountTechcombank::where('id', $id)->where('user_id', $user->id)->first();
        if (!$acc) {
            return response()->json(['status' => '1', 'msg' => 'Không tìm thấy']);
        }

        $acc->delete();

        return response()->json(['status' => '2', 'msg' => 'Đã xoá tài khoản Techcombank!']);
    }

    public function techcombankGetTransHistoryAPI(Request $request, $token)
    {
        $acc = AccountTechcombank::where('token', $token)->first();
        if (!$acc) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy tài khoản Techcombank theo token']);
        }

        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json(['status' => 'false', 'msg' => 'Không tìm thấy user sở hữu token']);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $history = $this->getTransactionHistoryTechcombank(
            $acc,
            $request->query('from_date') ?: $request->query('fromDate'),
            $request->query('to_date') ?: $request->query('toDate'),
            min(500, max(20, (int) $request->query('limit', 100)))
        );
        if ((int) ($history['code'] ?? 0) !== 200 && $this->techcombankRefreshIfNeed($acc)) {
            $history = $this->getTransactionHistoryTechcombank(
                $acc->refresh(),
                $request->query('from_date') ?: $request->query('fromDate'),
                $request->query('to_date') ?: $request->query('toDate'),
                min(500, max(20, (int) $request->query('limit', 100)))
            );
        }

        $this->storeBankTransactions('techcombank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return response()->json($history);
    }

    public function internalTechcombankTransactionHistoryForReceiver(int $accountId): array
    {
        $acc = AccountTechcombank::where('id', $accountId)->first();
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản Techcombank nhận tiền'];
        }

        $history = $this->getTransactionHistoryTechcombank($acc, null, null, 100);
        if ((int) ($history['code'] ?? 0) !== 200 && $this->techcombankRefreshIfNeed($acc)) {
            $history = $this->getTransactionHistoryTechcombank($acc->refresh(), null, null, 100);
        }

        if ((int) ($history['code'] ?? 0) !== 200) {
            return ['ok' => false, 'message' => (string) ($history['message'] ?? 'Không thể lấy lịch sử Techcombank')];
        }

        $this->storeBankTransactions('techcombank', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->account, $history['data']['transactions'] ?? []);

        return ['ok' => true, 'data' => $history];
    }

    public function internalTechcombankRefreshAccount(int $accountId): array
    {
        $acc = AccountTechcombank::where('id', $accountId)->first();
        if (!$acc) {
            return ['ok' => false, 'message' => 'Không tìm thấy tài khoản Techcombank'];
        }

        if (empty($acc->refresh_token)) {
            return ['ok' => false, 'message' => 'Tài khoản Techcombank chưa có refresh token'];
        }

        if (!$this->techcombankRefreshIfNeed($acc)) {
            $acc->is_login = false;
            $acc->save();

            return ['ok' => false, 'message' => 'Refresh token Techcombank hết hạn, cần xác nhận lại trên app'];
        }

        return ['ok' => true, 'message' => 'Đã refresh Techcombank'];
    }

    private function techcombankLoginRequest(string $username, string $password): array
    {
        $state = $this->techcombankNonce();
        $nonce = $this->techcombankNonce();
        $codeVerifier = $this->techcombankRandomString(96);
        $codeChallenge = $this->techcombankCodeChallenge($codeVerifier);
        $authUrl = 'https://identity-tcb.techcombank.com.vn/auth/realms/backbase/protocol/openid-connect/auth'
            . '?client_id=tcb-web-client'
            . '&redirect_uri=https%3A%2F%2Fonlinebanking.techcombank.com.vn%2Flogin'
            . '&state=' . rawurlencode($state)
            . '&response_mode=fragment&response_type=code%20id_token%20token&scope=openid'
            . '&nonce=' . rawurlencode($nonce)
            . '&ui_locales=en-US%20vi'
            . '&code_challenge=' . rawurlencode($codeChallenge)
            . '&code_challenge_method=S256';

        $first = $this->techcombankHttp('GET', $authUrl, $this->techcombankIdentityHeaders());
        $loginUrl = $this->techcombankFormAction((string) ($first['body'] ?? ''));
        if ($loginUrl === '') {
            return ['success' => false, 'message' => 'Techcombank không trả form đăng nhập'];
        }

        $post = $this->techcombankHttp(
            'POST',
            $loginUrl,
            $this->techcombankIdentityHeaders([
                'Origin: null',
                'Content-Type: application/x-www-form-urlencoded',
            ]),
            http_build_query([
                'username' => $username,
                'password' => $password,
                'threatMetrixBrowserType' => 'DESKTOP_BROWSER',
            ]),
            (string) ($first['cookie'] ?? '')
        );

        $body = (string) ($post['body'] ?? '');
        if (str_contains($body, 'Incorrect username or password')) {
            return ['success' => false, 'message' => 'Tài khoản hoặc mật khẩu Techcombank không đúng'];
        }
        if (str_contains($body, 'Please download, register with Techcombank Mobile app and relogin')) {
            return ['success' => false, 'message' => 'Tài khoản cần đăng ký Techcombank Mobile trước khi dùng web'];
        }

        $pendingUrl = $this->techcombankFormAction($body);
        $isPending = str_contains($body, 'verification request has been sent')
            || str_contains($body, 'yêu cầu xác thực đăng nhập')
            || $pendingUrl !== '';

        if (!$isPending || $pendingUrl === '') {
            return ['success' => false, 'message' => 'Techcombank chưa gửi được yêu cầu xác nhận mobile'];
        }

        return [
            'success' => true,
            'login_url' => $pendingUrl,
            'cookie' => (string) ($post['cookie'] ?? $first['cookie'] ?? ''),
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge,
        ];
    }

    private function techcombankCompleteLogin(AccountTechcombank $acc): array
    {
        if (empty($acc->login_url) || empty($acc->code_verifier)) {
            return ['success' => false, 'message' => 'Phiên Techcombank chưa sẵn sàng'];
        }

        $url = (string) $acc->login_url;
        $cookie = (string) $acc->cookie;
        $status = 'PENDING';

        for ($i = 0; $i < 12; $i++) {
            $poll = $this->techcombankHttp(
                'POST',
                $url,
                $this->techcombankApprovalHeaders(),
                http_build_query(['oob-authn-action' => 'confirmation-poll']),
                $cookie
            );
            $cookie = (string) ($poll['cookie'] ?? $cookie);
            $payload = json_decode((string) ($poll['body'] ?? ''), true);

            if (is_array($payload)) {
                $url = (string) ($payload['actionUrl'] ?? $url);
                $status = strtoupper((string) ($payload['status'] ?? ''));
                if ($status !== 'PENDING') {
                    break;
                }
            } else {
                $status = 'READY';
                break;
            }

            sleep(2);
        }

        if ($status === 'PENDING') {
            $acc->cookie = $cookie;
            $acc->login_url = $url;
            $acc->save();

            return ['success' => false, 'message' => 'Chưa thấy xác nhận trên app Techcombank. Hãy duyệt trên điện thoại rồi bấm lại.'];
        }

        $continue = $this->techcombankHttp(
            'POST',
            $url,
            $this->techcombankApprovalHeaders(),
            http_build_query(['oob-authn-action' => 'confirmation-continue']),
            $cookie,
            false
        );
        $cookie = (string) ($continue['cookie'] ?? $cookie);
        $location = $this->techcombankRedirectLocation($continue);
        if ($location === '') {
            $acc->cookie = $cookie;
            $acc->login_url = $url;
            $acc->save();

            return ['success' => false, 'message' => 'Techcombank chưa trả mã xác thực sau khi xác nhận app'];
        }

        $code = $this->techcombankExtractCode($location);
        if ($code === '') {
            return ['success' => false, 'message' => 'Không đọc được mã xác thực Techcombank'];
        }

        $token = $this->techcombankTokenRequest($acc, $code, $cookie);
        if (empty($token['access_token'])) {
            return ['success' => false, 'message' => (string) ($token['error_description'] ?? 'Không lấy được token Techcombank')];
        }

        $acc->auth_token = (string) $token['access_token'];
        $acc->refresh_token = (string) ($token['refresh_token'] ?? $acc->refresh_token);
        $acc->cookie = (string) ($token['_cookie'] ?? $cookie);
        $acc->is_login = true;
        $acc->login_url = null;
        $acc->create_date = now();
        $acc->save();

        return ['success' => true];
    }

    private function getBalanceTechcombank(AccountTechcombank $acc): array
    {
        $info = $this->techcombankAccounts($acc);
        if (empty($info['success'])) {
            return $info;
        }

        $account = $this->techcombankFindAccount($info['accounts'] ?? [], (string) $acc->account);
        if (!$account) {
            return ['success' => false, 'code' => 404, 'message' => 'Không tìm thấy số tài khoản Techcombank'];
        }

        $balance = (int) round((float) $this->techcombankArrayValue($account, ['availableBalance', 'availableFunds', 'bookedBalance', 'currentBalance'], 0));
        $name = $this->techcombankNameFromAccount($account);

        $acc->arrangement_id = (string) $this->techcombankArrayValue($account, ['id', 'arrangementId'], $acc->arrangement_id);
        $acc->balance = $balance;
        if ($name !== '' && trim((string) $acc->name) === '') {
            $acc->name = $name;
        }
        $acc->is_login = true;
        $acc->save();

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Thành công',
            'data' => [
                'account_number' => (string) $acc->account,
                'account_name' => $name ?: (string) ($acc->name ?? ''),
                'balance' => $balance,
            ],
        ];
    }

    private function getTransactionHistoryTechcombank(AccountTechcombank $acc, ?string $fromDate = null, ?string $toDate = null, int $limit = 100): array
    {
        $fromDate = $this->techcombankDateParam($fromDate, Carbon::now()->subDays(7));
        $toDate = $this->techcombankDateParam($toDate, Carbon::now()->addDay());
        $arrangementId = trim((string) $acc->arrangement_id);

        if ($arrangementId === '') {
            $balance = $this->getBalanceTechcombank($acc);
            if ((int) ($balance['code'] ?? 0) !== 200) {
                return $balance;
            }
            $acc->refresh();
            $arrangementId = trim((string) $acc->arrangement_id);
        }

        if ($arrangementId === '') {
            return ['success' => false, 'code' => 404, 'message' => 'Thiếu arrangementId Techcombank'];
        }

        $this->techcombankSync($acc);
        $this->techcombankRefreshArrangement($acc);

        $url = 'https://onlinebanking.techcombank.com.vn/api/transaction-manager/client-api/v2/transactions'
            . '?bookingDateGreaterThan=' . rawurlencode($fromDate)
            . '&bookingDateLessThan=' . rawurlencode($toDate)
            . '&arrangementId=' . rawurlencode($arrangementId)
            . '&from=0&size=' . max(1, min(500, $limit))
            . '&orderBy=bookingDate&direction=DESC';

        $response = $this->techcombankHttp('GET', $url, $this->techcombankApiHeaders($acc), null, (string) $acc->cookie);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ((int) ($response['status'] ?? 0) === 401 || (is_array($body) && (($body['error'] ?? '') === 'Unauthorized'))) {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên Techcombank hết hạn'];
        }
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'Không đọc được lịch sử Techcombank'];
        }

        $transactions = $this->normaliseTechcombankTransactions($body, (string) $acc->account);
        $name = $this->techcombankNameFromTransactions($transactions, (string) $acc->account);
        if ($name !== '' && trim((string) $acc->name) === '') {
            $acc->name = $name;
            $acc->save();
        }

        return [
            'success' => true,
            'code' => 200,
            'codeStatus' => 200,
            'message' => 'Thành công',
            'data' => ['transactions' => $transactions],
            'transactions' => $transactions,
        ];
    }

    private function techcombankAccounts(AccountTechcombank $acc): array
    {
        if (empty($acc->auth_token)) {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên Techcombank chưa sẵn sàng'];
        }

        $url = 'https://onlinebanking.techcombank.com.vn/api/arrangement-manager/client-api/v2/productsummary/context/arrangements'
            . '?businessFunction=Product%20Summary&resourceName=Product%20Summary&privilege=view&productKindName=Current%20Account&from=0&size=1000000';
        $response = $this->techcombankHttp('GET', $url, $this->techcombankApiHeaders($acc), null, (string) $acc->cookie);
        $body = json_decode((string) ($response['body'] ?? ''), true);
        if ((int) ($response['status'] ?? 0) === 401) {
            return ['success' => false, 'code' => 401, 'message' => 'Phiên Techcombank hết hạn'];
        }
        if (!is_array($body)) {
            return ['success' => false, 'code' => 503, 'message' => 'Không đọc được danh sách tài khoản Techcombank'];
        }

        return ['success' => true, 'code' => 200, 'accounts' => $body];
    }

    private function techcombankRefreshIfNeed(AccountTechcombank $acc): bool
    {
        if (empty($acc->refresh_token)) {
            return false;
        }

        $response = $this->techcombankHttp(
            'POST',
            'https://identity-tcb.techcombank.com.vn/auth/realms/backbase/protocol/openid-connect/token',
            $this->techcombankIdentityHeaders([
                'Accept: */*',
                'Origin: https://onlinebanking.techcombank.com.vn',
                'Referer: https://onlinebanking.techcombank.com.vn/',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . (string) $acc->auth_token,
            ]),
            http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => 'tcb-web-client',
                'refresh_token' => (string) $acc->refresh_token,
                'scope' => 'openid',
                'ui_locales' => 'en-US',
            ]),
            (string) $acc->cookie
        );

        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body) || empty($body['access_token'])) {
            return false;
        }

        $acc->auth_token = (string) $body['access_token'];
        $acc->refresh_token = (string) ($body['refresh_token'] ?? $acc->refresh_token);
        $acc->cookie = (string) ($response['cookie'] ?? $acc->cookie);
        $acc->is_login = true;
        $acc->save();

        return true;
    }

    private function techcombankTokenRequest(AccountTechcombank $acc, string $code, string $cookie): array
    {
        $response = $this->techcombankHttp(
            'POST',
            'https://identity-tcb.techcombank.com.vn/auth/realms/backbase/protocol/openid-connect/token',
            $this->techcombankIdentityHeaders([
                'Origin: https://onlinebanking.techcombank.com.vn',
                'Referer: https://onlinebanking.techcombank.com.vn/',
                'Content-Type: application/x-www-form-urlencoded',
            ]),
            http_build_query([
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => 'tcb-web-client',
                'redirect_uri' => 'https://onlinebanking.techcombank.com.vn/login',
                'code_verifier' => (string) $acc->code_verifier,
            ]),
            $cookie
        );

        $body = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($body)) {
            return ['error_description' => 'Techcombank token response không hợp lệ'];
        }
        $body['_cookie'] = (string) ($response['cookie'] ?? $cookie);

        return $body;
    }

    private function techcombankSync(AccountTechcombank $acc): void
    {
        $this->techcombankHttp(
            'POST',
            'https://onlinebanking.techcombank.com.vn/api/bb-ingestion-service/client-api/v2/accounts/sync',
            $this->techcombankApiHeaders($acc, ['Content-Type: application/json']),
            json_encode(['types' => ['ACCOUNT'], 'refreshAll' => true]),
            (string) $acc->cookie
        );
    }

    private function techcombankRefreshArrangement(AccountTechcombank $acc): void
    {
        $this->techcombankHttp(
            'POST',
            'https://onlinebanking.techcombank.com.vn/api/sync-dis/client-api/v1/transactions/refresh/arrangements',
            $this->techcombankApiHeaders($acc, ['Content-Type: application/json']),
            json_encode(['externalArrangementIds' => [(string) $acc->account]]),
            (string) $acc->cookie
        );
    }

    private function techcombankFindAccount(array $accounts, string $accountNo): ?array
    {
        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            $number = (string) $this->techcombankArrayValue($account, ['BBAN', 'accountNumber', 'accountNo', 'number'], '');
            if ($number === $accountNo) {
                return $account;
            }
        }

        return null;
    }

    private function techcombankNameFromAccount(array $account): string
    {
        return trim((string) $this->techcombankArrayValue($account, [
            'accountHolderNames',
            'accountHolderName',
            'accountName',
            'name',
            'displayName',
            'alias',
        ], ''));
    }

    private function normaliseTechcombankTransactions(array $transactions, string $accountNo): array
    {
        return array_map(function ($item) use ($accountNo) {
            if (!is_array($item)) {
                return $item;
            }

            $amountRaw = $item['transactionAmountCurrency']['amount'] ?? $this->techcombankArrayValue($item, ['amount', 'transactionAmount'], 0);
            $amount = abs((int) round((float) $amountRaw));
            $direction = strtoupper((string) $this->techcombankArrayValue($item, ['creditDebitIndicator', 'type'], ''));
            $isCredit = in_array($direction, ['CRDT', 'CREDIT', 'C'], true);
            if (!$isCredit) {
                $amount = -$amount;
            }

            $description = (string) $this->techcombankArrayValue($item, ['description', 'note', 'remittanceInformation'], '');
            $reference = (string) $this->techcombankArrayValue($item, ['reference', 'id', 'transactionId'], '');
            $dateRaw = (string) $this->techcombankArrayValue($item, ['bookingDate', 'valueDate', 'creationTime'], '');
            $item['_description'] = $description;
            $item['_reference'] = $reference;
            $item['_date_text'] = $this->techcombankDateText($dateRaw);
            $item['_date_key'] = $this->techcombankDateKey($dateRaw);
            $item['_amount'] = $amount;
            $item['_is_credit'] = $isCredit;
            $item['_account_name'] = $this->techcombankNameFromTransaction($item, $accountNo);

            return $item;
        }, $transactions);
    }

    private function techcombankNameFromTransaction(array $item, string $accountNo): string
    {
        $additions = isset($item['additions']) && is_array($item['additions']) ? $item['additions'] : [];
        $creditNo = (string) ($additions['creditAcctNo'] ?? '');
        $debitNo = (string) ($additions['debitAcctNo'] ?? '');
        if ($creditNo === $accountNo) {
            return trim((string) ($additions['creditAcctName'] ?? ''));
        }
        if ($debitNo === $accountNo) {
            return trim((string) ($additions['debitAcctName'] ?? ''));
        }

        return '';
    }

    private function techcombankNameFromTransactions(array $transactions, string $accountNo): string
    {
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }
            $name = trim((string) ($transaction['_account_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    private function techcombankArrayValue(array $item, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                return $item[$key];
            }
        }

        $lower = [];
        foreach ($item as $key => $value) {
            $lower[strtolower((string) $key)] = $value;
        }
        foreach ($keys as $key) {
            $needle = strtolower((string) $key);
            if (array_key_exists($needle, $lower) && $lower[$needle] !== null && $lower[$needle] !== '') {
                return $lower[$needle];
            }
        }

        return $default;
    }

    private function techcombankFormAction(string $html): string
    {
        if (!preg_match('/<form[^>]+action="([^"]+)"/i', $html, $match)) {
            return '';
        }

        $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $url . (str_contains($url, '?') ? '&' : '?') . 'kc_locale=en-US';
    }

    private function techcombankExtractCode(string $url): string
    {
        if (preg_match('/[?#&]code=([^&]+)/', $url, $match)) {
            return urldecode($match[1]);
        }

        if (preg_match('/"code"\s*:\s*"([^"]+)"/i', $url, $match)) {
            return stripcslashes($match[1]);
        }

        return '';
    }

    private function techcombankRedirectLocation(array $response): string
    {
        $headers = $response['headers'] ?? [];
        $location = (string) (($headers['location'] ?? '') ?: ($headers['Location'] ?? ''));
        if ($location !== '') {
            return $location;
        }

        $redirectUrl = (string) ($response['redirect_url'] ?? '');
        if ($redirectUrl !== '') {
            return $redirectUrl;
        }

        $body = (string) ($response['body'] ?? '');
        if ($this->techcombankExtractCode($body) !== '') {
            return $body;
        }

        if (preg_match('/https?:\\\\?\\/\\\\?\\/[^\\s"\\\']*code=[^\\s"\\\']+/i', $body, $match)) {
            return stripcslashes($match[0]);
        }

        return '';
    }

    private function techcombankDateParam(?string $value, Carbon $default): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default->format('Y-m-d');
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $default->format('Y-m-d');
        }
    }

    private function techcombankDateText(string $dateRaw): string
    {
        $dateRaw = trim($dateRaw);
        if ($dateRaw === '') {
            return '';
        }

        try {
            return Carbon::parse($dateRaw)->timezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            return $dateRaw;
        }
    }

    private function techcombankDateKey(string $dateRaw): string
    {
        $text = $this->techcombankDateText($dateRaw);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $text, $match)) {
            return $match[1] . $match[2] . substr($match[3], -2);
        }

        return date('dmy');
    }

    private function techcombankIdentityHeaders(array $extra = []): array
    {
        return $this->techcombankHeaders(array_merge([
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,vi;q=0.8',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Host: identity-tcb.techcombank.com.vn',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: ' . $this->techcombankUserAgent(),
            'sec-ch-ua: "Google Chrome";v="107", "Chromium";v="107", "Not=A?Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
        ], $extra));
    }

    private function techcombankApprovalHeaders(array $extra = []): array
    {
        return $this->techcombankIdentityHeaders(array_merge([
            'Accept: */*',
            'Origin: null',
            'Content-Type: application/x-www-form-urlencoded',
            'Sec-Fetch-User: ?1',
        ], $extra));
    }

    private function techcombankApiHeaders(AccountTechcombank $acc, array $extra = []): array
    {
        return $this->techcombankHeaders(array_merge([
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9,vi;q=0.8',
            'Connection: keep-alive',
            'Host: onlinebanking.techcombank.com.vn',
            'Referer: https://onlinebanking.techcombank.com.vn/',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-site',
            'User-Agent: ' . $this->techcombankUserAgent(),
            'sec-ch-ua: "Google Chrome";v="107", "Chromium";v="107", "Not=A?Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Authorization: Bearer ' . (string) $acc->auth_token,
        ], $extra));
    }

    private function techcombankHeaders(array $headers): array
    {
        $seen = [];
        $deduped = [];

        foreach (array_reverse($headers) as $header) {
            $name = strtolower(trim(strtok((string) $header, ':')));
            $key = $name !== '' ? $name : (string) $header;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $header;
        }

        return array_reverse($deduped);
    }

    private function techcombankHttp(string $method, string $url, array $headers = [], ?string $body = null, string $cookie = '', bool $follow = true): array
    {
        $ch = curl_init();
        $responseHeaders = [];
        $setCookieHeaders = [];
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => '',
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$responseHeaders, &$setCookieHeaders) {
                $length = strlen($header);
                $parts = explode(':', trim($header), 2);
                if (count($parts) === 2) {
                    $name = strtolower($parts[0]);
                    $value = trim($parts[1]);
                    $responseHeaders[$name] = $value;
                    if ($name === 'set-cookie') {
                        $setCookieHeaders[] = $value;
                    }
                }
                return $length;
            },
        ];

        if ($cookie !== '') {
            $opts[CURLOPT_COOKIE] = $cookie;
        }
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectUrl = defined('CURLINFO_REDIRECT_URL') ? (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL) : '';
        $cookieList = curl_getinfo($ch, CURLINFO_COOKIELIST) ?: [];
        curl_close($ch);

        $cookieHeader = $this->vpbankCookieHeader(is_array($cookieList) ? $cookieList : [], $cookie);
        if ($cookieHeader === $cookie && $setCookieHeaders) {
            $cookieHeader = $this->vpbankSetCookieHeader($setCookieHeaders, $cookie);
        }

        return [
            'ok' => $error === '',
            'status' => $status,
            'body' => $responseBody === false ? '' : (string) $responseBody,
            'headers' => $responseHeaders,
            'cookie' => $cookieHeader,
            'effective_url' => $effectiveUrl,
            'redirect_url' => $redirectUrl,
            'error' => $error,
        ];
    }

    private function techcombankCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function techcombankRandomString(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $value;
    }

    private function techcombankNonce(): string
    {
        return strtoupper($this->gen_uuid());
    }

    private function techcombankUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36';
    }


    // =========================================================================
    // =============           A C B   B A N K            =======================
    // =========================================================================

    public function acbIndex()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập');
        }
        $accounts = AccountAcb::where('user_id', $user->id)->get();
        return view('payment.acb', compact('accounts','user'));
    }

    public function acbLogin(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => '1', 'msg' => 'Chưa đăng nhập']);
        }

        $account  = $request->input('account');
        $password = $request->input('password');
        $stk      = $request->input('stk');

        if (empty($account) || empty($password) || empty($stk)) {
            return response()->json([
                'status' => '1',
                'msg'    => 'Vui lòng nhập đủ account, password, stk'
            ]);
        }

        $accountLimit = ApiPackage::userLimit($user);
        $existingAcb = AccountAcb::where('user_id', $user->id)->where('phone', $account)->first();
        $exists = (bool) $existingAcb;
        if (!$exists && $accountLimit > 0 && $this->userBankAccountCount((int) $user->id) >= $accountLimit) {
            return response()->json([
                'status' => '1',
                'msg'    => $this->accountLimitMessage($accountLimit)
            ]);
        }

        // Gọi loginAcb()
        $login = $this->loginAcb($account, $password);
        if (!is_array($login)) {
            return response()->json(['status'=>'1','msg'=>'Không thể kết nối ACB']);
        }

        // Kiểm tra
        if (isset($login['identity']['active']) && $login['identity']['active'] == 1) {
            $accessToken = $login['accessToken'] ?? '';
            $displayName = $login['identity']['displayName'] ?? 'ACB NAME';

            AccountAcb::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'phone'   => $account,
                ],
                [
                    'password'  => $password,
                    'stk'       => $stk,
                    'name'      => $displayName,
                    'sessionId' => $accessToken,
                    'deviceId'  => '',
                    'token'     => $existingAcb->token ?? md5(uniqid().time()),
                    'time'      => time(),
                ]
            );

            return response()->json(['status'=>'2','msg'=>'Thêm ACB thành công']);
        } else {
            $mess = $login['message'] ?? 'Tài khoản không hợp lệ (active!=1)';
            return response()->json(['status'=>'1','msg'=> $mess]);
        }
    }

    public function acbGetBalanceAPI(Request $request, $token)
    {
        $acc = AccountAcb::where('token', $token)->first();
        if (!$acc) {
            return response()->json([
                'status' => 'false',
                'msg'    => 'Token không hợp lệ'
            ]);
        }
        $user = User::find($acc->user_id);
        if (!$user) {
            return response()->json([
                'status' => 'false',
                'msg'    => 'Không tìm thấy chủ tài khoản'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $balanceJson = $this->getBalanceAcb($acc->sessionId);
        $balanceArr  = json_decode($balanceJson, true);


        if (!isset($balanceArr['codeStatus']) || $balanceArr['codeStatus'] != 200) {
            // Thử re-login
            $ok = $this->acbReLoginIfNeed($acc);
            if ($ok) {
                $acc->refresh(); 
                $balanceJson = $this->getBalanceAcb($acc->sessionId);
                $balanceArr  = json_decode($balanceJson, true);
                if (!isset($balanceArr['codeStatus']) || $balanceArr['codeStatus'] != 200) {
                    return response()->json([
                        'status' => '99',
                        'SoDu'   => '0',
                        'msg'    => 'Lỗi đăng nhập lại ACB'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => '99',
                    'SoDu'   => '0',
                    'msg'    => 'Không auto re-login ACB'
                ]);
            }
        }

        $listData            = $balanceArr['data'] ?? [];
        $soDuNum            = 0;
        $accountDescription = '';

        foreach($listData as $item) {
            if (($item['accountNumber'] ?? '') == $acc->stk) {
                $raw = $item['balance'] ?? '0';
                $soDuNum = (int) str_replace(',', '', $raw);
                $accountDescription = $item['accountDescription'] ?? '';
                break;
            }
        }

        return response()->json([
            'status'             => '200',
            'SoDu'               => $soDuNum,
            'accountDescription' => $accountDescription,
        ]);
    }

    public function acbHistory(Request $request, $stk)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error','Chưa đăng nhập');
        }


        $acc = AccountAcb::where('stk',$stk)->where('user_id',$user->id)->first();
        if (!$acc) {
            return redirect()->back()->with('error','Không tìm thấy tài khoản ACB');
        }

        $jsonHistory = $this->getTransactionHistoryAcb($acc->stk, $acc->sessionId, 20);
        $arrLSGD     = json_decode($jsonHistory, true);

        if (!isset($arrLSGD['codeStatus']) || $arrLSGD['codeStatus'] != 200) {
            $ok = $this->acbReLoginIfNeed($acc);
            if ($ok) {
                $acc->refresh();
                $jsonHistory = $this->getTransactionHistoryAcb($acc->stk, $acc->sessionId, 20);
                $arrLSGD     = json_decode($jsonHistory, true);
                if (!isset($arrLSGD['codeStatus']) || $arrLSGD['codeStatus'] != 200) {
                    return redirect()->back()->with('error','Không thể lấy LSGD sau login lại');
                }
            } else {
                return redirect()->back()->with('error','Không thể login lại ACB');
            }
        }

        $this->storeBankTransactions('acb', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->stk, $arrLSGD['data'] ?? []);

        $transactions = $this->prepareBankHistoryTransactions($request, $arrLSGD['data'] ?? [], 'acb', (string) $acc->stk);

        return view('payment.acbhistory', ['acc' => $acc] + $transactions);
    }

    public function acbSendToken(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=>'1','msg'=>'Chưa đăng nhập']);
        }


        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status'=>'1','msg'=>'Thiếu ID']);
        }
        $acc = AccountAcb::where('id',$id)->where('user_id',$user->id)->first();
        if (!$acc) {
            return response()->json(['status'=>'1','msg'=>'Không tìm thấy']);
        }

        $tokenAcb = $acc->token;
        // Gửi mail...

        return response()->json([
            'status'=>'2',
            'msg'=>"Token ACB: {$tokenAcb}\nAPI số dư: " . url("/v2/acb/balance/{$tokenAcb}") . "\nAPI giao dịch: " . url("/v2/acb/transhistory/{$tokenAcb}")
        ]);
    }

    public function acbRemove(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['status'=>'1','msg'=>'Chưa đăng nhập']);
        }

        XLog::create([
            'ip'   => $request->ip(),
            'user' => $user->id,
            'log'  => 'Xoá ACB',
            'notes'=> 'User bấm xoá ACB',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $id = $request->input('id');
        if (empty($id)) {
            return response()->json(['status'=>'1','msg'=>'Thiếu ID']);
        }

        $acc = AccountAcb::where('id', $id)
               ->where('user_id', $user->id)
               ->first();
        if (!$acc) {
            return response()->json(['status'=>'1','msg'=>'Không tìm thấy']);
        }
        $acc->delete();

        return response()->json(['status'=>'2','msg'=>'Đã xoá tài khoản ACB!']);
    }

    // -------------------------------------------------------------------------
    //  (Mới) Lấy lịch sử giao dịch ACB qua API (token)
    //      => Mỗi lần load -> Lưu giao dịch vào bảng transactions
    // -------------------------------------------------------------------------
    public function acbGetTransHistoryAPI(Request $request, $token)
    {
        $acc = AccountAcb::where('token', $token)->first();
        if(!$acc){
            return response()->json([
                'status'=>'false',
                'msg'=>'Không tìm thấy tài khoản ACB theo token'
            ]);
        }
        $user = User::find($acc->user_id);
        if(!$user){
            return response()->json([
                'status'=>'false',
                'msg'=>'Không tìm thấy user sở hữu token'
            ]);
        }
        if ($this->isApiPackageExpired($user)) {
            return $this->apiTokenExpiredResponse($user);
        }

        $jsonHistory = $this->getTransactionHistoryAcb($acc->stk, $acc->sessionId, 20);
        $arrLSGD     = json_decode($jsonHistory, true);

        if (!isset($arrLSGD['codeStatus']) || $arrLSGD['codeStatus'] != 200) {
            $ok = $this->acbReLoginIfNeed($acc);
            if ($ok) {
                $acc->refresh();
                $jsonHistory = $this->getTransactionHistoryAcb($acc->stk, $acc->sessionId, 20);
                $arrLSGD     = json_decode($jsonHistory, true);
                if (!isset($arrLSGD['codeStatus']) || $arrLSGD['codeStatus'] != 200) {
                    return redirect()->back()->with('error','Không thể lấy LSGD sau login lại');
                }
            } else {
                return redirect()->back()->with('error','Không thể login lại ACB');
            }
        }

        $this->storeBankTransactions('acb', (int) $acc->id, $acc->user_id === null ? null : (int) $acc->user_id, (string) $acc->stk, $arrLSGD['data'] ?? []);

        return response()->json($arrLSGD);
    }

    private function isApiPackageExpired(?User $user): bool
    {
        if (!$user) {
            return true;
        }

        $freshUser = ApiPackage::applyDueScheduledPlan($user) ?: $user;

        return (int) ($freshUser->time_end ?? 0) <= time();
    }

    private function apiTokenExpiredResponse(?User $user)
    {
        if ($user) {
            $user = $user->fresh() ?: $user;
        }

        return response()->json([
            'status' => 'false',
            'code' => 'TOKEN_EXPIRED',
            'msg' => 'Token hết hạn, vui lòng gia hạn tài khoản để tiếp tục sử dụng API',
            'time_end' => (int) ($user->time_end ?? 0),
            'renew_url' => url('/client/upgrade'),
        ]);
    }

  // -------------------------------------------------------------------------
    //  Hỗ trợ ACB
    // -------------------------------------------------------------------------
    private function loginAcb($username, $password)
    {
        $url = "https://apiapp.acb.com.vn/mb/v2/auth/tokens";
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ];
        $data = [
            "clientId" => "iuSuHYVufIUuNIREV0FB9EoLn9kHsDbm",
            "username" => $username,
            "password" => $password
        ];
        $res = $this->callApi($url, $header, json_encode($data), 'POST', false, 20);
        return json_decode($res, true);
    }
    private function getBalanceAcb($token)
    {
        $url = "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/list/account-payment";
        $header = [
            'Content-Type: application/json',
            'Host: apiapp.acb.com.vn',
            "Authorization: bearer $token"
        ];
        $res = $this->callApi($url, $header, null, 'GET', false, 20);
        return $res;
    }
    private function getTransactionHistoryAcb($accountNo, $token, $rows=20)
    {
        $url = "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history?maxRows={$rows}&account={$accountNo}";
        $header = [
            'Host: apiapp.acb.com.vn',
            'Accept: application/json, text/plain, */*',
            'User-Agent: ACB-MBA/2 CFNetwork/1474 Darwin/23.0.0',
            'Accept-Language: vi',
            "Authorization: bearer $token",
            'x-app-version: 3.12.4'
        ];
        $res = $this->callApi($url, $header, null, 'GET', false, 20);
        return $res;
    }
    private function acbReLoginIfNeed(AccountAcb $acc)
    {
        try {
            $login = $this->loginAcb($acc->phone, $acc->password);
            if (isset($login['identity']['active']) && $login['identity']['active'] == 1) {
                $acc->sessionId = $login['accessToken'] ?? '';
                $acc->time      = time();
                $acc->save();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
	
	
	
	
	
	
	
	
	
	
	
	
}
