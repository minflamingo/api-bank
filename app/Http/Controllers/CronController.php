<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Bank;
use App\Support\BankTransactionRecorder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CronController extends Controller
{
    /**
     * Hàm cron quét giao dịch ACB
     * Lấy JSON => parse => cộng tiền => gửi Telegram
     */
    public function cronNapACB(Request $request)
    {
        $getbank = DB::table('bank')->where('codebank','970416')->first();
        if (!$getbank) {
            return "Chưa cấu hình ngân hàng ACB nhận tiền";
        }

        if (($getbank->receiver_bank_type ?? 'ACB') !== 'ACB' || empty($getbank->receiver_account_id)) {
            return "Chưa chọn tài khoản API ACB nhận tiền trong Super Admin";
        }

        $apiAccount = DB::table('account_acb')
            ->where('id', (int) $getbank->receiver_account_id)
            ->first();
        if (!$apiAccount) {
            return "Tài khoản ACB nhận tiền đã chọn không tồn tại";
        }
        if (!is_null($apiAccount->user_id)) {
            return "Tài khoản ACB nhận tiền phải là tài khoản hệ thống, không thuộc user khách";
        }
        if (trim((string) $apiAccount->stk) !== trim((string) $getbank->accountNumber)) {
            return "Tài khoản ACB nhận tiền không trùng STK hiển thị cho khách";
        }
        if (empty($apiAccount->sessionId) && empty($apiAccount->password)) {
            return "Tài khoản ACB nhận tiền chưa có session hoặc mật khẩu để cron đăng nhập lại";
        }

        $result = $this->getTransactionHistoryAcb($apiAccount->stk, $apiAccount->sessionId, 20);
        $result = json_decode($result, true);

        if (!isset($result['codeStatus']) || (int) $result['codeStatus'] !== 200) {
            if (!$this->acbReLoginReceiver($apiAccount)) {
                return "Không thể đăng nhập lại ACB nhận tiền hệ thống";
            }

            $apiAccount = DB::table('account_acb')
                ->where('id', (int) $getbank->receiver_account_id)
                ->first();
            $result = $this->getTransactionHistoryAcb($apiAccount->stk, $apiAccount->sessionId, 20);
            $result = json_decode($result, true);
        }

        if(!isset($result['data']) || !is_array($result['data'])){
            return "Không tìm thấy data ACB";
        }

        $this->storeBankTransactions('acb', (int) $apiAccount->id, $apiAccount->user_id === null ? null : (int) $apiAccount->user_id, (string) $apiAccount->stk, $result['data']);

        foreach($result['data'] as $data)
        {
            $loai     = $data['type']; // "IN" hoặc "OUT"...
            $comment  = $data['description'];
            $tranId   = $data['transactionNumber'].".".date("dmy", intval($data['postingDate']) / 1000);
            $amount   = str_replace(",","",$data['amount']); // Số tiền

            if ($loai !== "IN") {
                continue;
            }

            // Tách user_id từ nội dung
            $user_id  = $this->parse_order_id($comment, $getbank->noidungnap);

            if($user_id){
                $userRow = DB::table('users')->where('id',$user_id)->first();
                if($userRow){
                    // Check xem invoice đã tồn tại chưa
                    $checkInv = DB::table('invoices')->where('trans_id',$tranId)->first();
                    if(!$checkInv){
                        // Tạo invoice
                        DB::table('invoices')->insert([
                            'trans_id'        => $tranId,
                            'payment_method'  => "ACB",
                            'user_id'         => $userRow->id,
                            'description'     => $comment,
                            'amount'          => $amount,
                            'status'          => 1, // success
                            'create_time'     => time()
                        ]);

                        $note = "Nạp tiền tự động qua ACB (#$tranId *Nội dung: $comment *Số tiền: $amount)";
                        $this->plusCredits($userRow->id, $amount, $note);

                        // Gửi Telegram
                        $txttele = "GIAO DỊCH NẠP TIỀN\n";
                        $txttele .= "Người dùng: {$userRow->id}\n";
                        $txttele .= "Số Tiền: " . number_format($amount) . "\n";
                        $txttele .= "Mã GD: $tranId\n";
                        $txttele .= "Nội dung: $comment\n";
                        $txttele .= "Lúc: ".date("H:i d-m-Y");
                        $this->odertele($txttele);
                    }
                }
            }
        }

        return "OK - Quét ACB xong";
    }

    /**
     * Hàm cron quét giao dịch Vietcombank
     */
    public function cronNapVCB(Request $request)
    {
        $getbank = DB::table('bank')->where('codebank','970436')->first();
        if (!$getbank) {
            return "Chưa cấu hình ngân hàng VCB nhận tiền";
        }

        if (($getbank->receiver_bank_type ?? 'VCB') !== 'VCB' || empty($getbank->receiver_account_id)) {
            return "Chưa chọn tài khoản API VCB nhận tiền trong Super Admin";
        }

        $apiAccount = DB::table('account_vietcombank')
            ->where('id', (int) $getbank->receiver_account_id)
            ->first();
        if (!$apiAccount) {
            return "Tài khoản VCB nhận tiền đã chọn không tồn tại";
        }
        if (!is_null($apiAccount->user_id)) {
            return "Tài khoản VCB nhận tiền phải là tài khoản hệ thống, không thuộc user khách";
        }
        if (trim((string) $apiAccount->account) !== trim((string) $getbank->accountNumber)) {
            return "Tài khoản VCB nhận tiền không trùng STK hiển thị cho khách";
        }
        if (empty($apiAccount->session_id) && empty($apiAccount->password)) {
            return "Tài khoản VCB nhận tiền chưa có session hoặc mật khẩu để cron đăng nhập lại";
        }

        $history = app(PaymentController::class)->internalVcbTransactionHistoryForReceiver((int) $apiAccount->id);
        if (empty($history['ok'])) {
            return (string) ($history['message'] ?? 'Không lấy được lịch sử VCB nhận tiền');
        }

        $result = $history['data'] ?? [];

        if(!isset($result['transactions']) || !is_array($result['transactions'])){
            return "Không tìm thấy transactions";
        }

        foreach($result['transactions'] as $data)
        {
            $loai      = $data['CD']; // + hoặc -
            $comment   = $data['Description'];
            // convertDateFormat($data['TransactionDate']) => ddmmyy
            $tranId    = $data['SeqNo'].".".$this->convertDateFormat($data['TransactionDate']);
            $amount    = str_replace(",","",$data['Amount']);

            if ($loai !== "+") {
                continue;
            }
            
            $user_id   = $this->parse_order_id($comment, $getbank->noidungnap);

            if($user_id){
                $userRow = DB::table('users')->where('id',$user_id)->first();
                if($userRow){
                    $checkInv = DB::table('invoices')->where('trans_id',$tranId)->first();
                    if(!$checkInv){
                        DB::table('invoices')->insert([
                            'trans_id'       => $tranId,
                            'payment_method' => "VCB",
                            'user_id'        => $userRow->id,
                            'description'    => $comment,
                            'amount'         => $amount,
                            'status'         => 1,
                            'create_time'    => time()
                        ]);
                        $note = "Nạp tiền tự động qua VCB (#$tranId *Nội dung: $comment *Số tiền: $amount)";
                        $this->plusCredits($userRow->id, $amount, $note);

                        // Gửi Telegram
                        $txttele = "GIAO DỊCH NẠP TIỀN\n";
                        $txttele .= "Người dùng: {$userRow->id}\n";
                        $txttele .= "Số Tiền: ".number_format($amount)."\n";
                        $txttele .= "Mã GD: $tranId\n";
                        $txttele .= "Nội dung: $comment\n";
                        $txttele .= "Lúc: ".date("H:i d-m-Y");
                        $this->odertele($txttele);
                    }
                }
            }
        }

        return "OK - Quét VCB xong";
    }

    /**
     * Hàm cron quét giao dịch VPBank
     */
    public function cronNapVPBANK(Request $request)
    {
        $getbank = DB::table('bank')->where('codebank', '970432')->first();
        if (!$getbank) {
            return "Chưa cấu hình ngân hàng VPBank nhận tiền";
        }

        if (($getbank->receiver_bank_type ?? '') !== 'VPBANK' || empty($getbank->receiver_account_id)) {
            return "Chưa chọn tài khoản API VPBank nhận tiền trong Super Admin";
        }

        $apiAccount = DB::table('account_vpbank')
            ->where('id', (int) $getbank->receiver_account_id)
            ->first();
        if (!$apiAccount) {
            return "Tài khoản VPBank nhận tiền đã chọn không tồn tại";
        }
        if (!is_null($apiAccount->user_id)) {
            return "Tài khoản VPBank nhận tiền phải là tài khoản hệ thống, không thuộc user khách";
        }
        if (trim((string) $apiAccount->account) !== trim((string) $getbank->accountNumber)) {
            return "Tài khoản VPBank nhận tiền không trùng STK hiển thị cho khách";
        }
        if (empty($apiAccount->token_key) && empty($apiAccount->password)) {
            return "Tài khoản VPBank nhận tiền chưa có session hoặc mật khẩu để cron đăng nhập lại";
        }

        $history = app(PaymentController::class)->internalVpbankTransactionHistoryForReceiver((int) $apiAccount->id);
        if (empty($history['ok'])) {
            return (string) ($history['message'] ?? 'Không lấy được lịch sử VPBank nhận tiền');
        }

        $result = $history['data'] ?? [];
        $transactions = $result['transactions'] ?? ($result['data']['transactions'] ?? []);
        if (!is_array($transactions)) {
            return "Không tìm thấy transactions VPBank";
        }

        foreach ($transactions as $data) {
            $direction = strtoupper((string) $this->vpbankValue($data, ['CD', 'CreditDebitIndicator', 'DebitCreditIndicator', 'TransactionType'], ''));
            $amount = isset($data['_amount'])
                ? (int) $data['_amount']
                : (int) str_replace([',', '.00'], '', (string) $this->vpbankValue($data, ['Amount', 'TransactionAmount', 'CreditAmount'], 0));
            $isCredit = array_key_exists('_is_credit', $data)
                ? (bool) $data['_is_credit']
                : (
                    in_array($direction, ['+', 'C', 'CR', 'CREDIT', 'IN'], true)
                    || $amount > 0
                    || (int) str_replace(',', '', (string) ($data['CreditAmount'] ?? 0)) > 0
                );
            if (!$isCredit) {
                continue;
            }

            $comment = (string) ($data['_description'] ?? $this->vpbankValue($data, ['Description', 'TransactionDescription', 'Narrative', 'Remark', 'Content'], ''));
            $amount = abs($amount);
            if ($amount === 0) {
                continue;
            }

            $reference = (string) ($data['_reference'] ?? $this->vpbankValue($data, ['Reference', 'ReferenceNumber', 'TransactionId', 'Id', 'TransactionNumber', 'SeqNo'], ''));
            $tranDate = (string) ($data['_date_key'] ?? '');
            if ($tranDate === '') {
                $dateText = (string) $this->vpbankValue($data, ['BookingDate', 'TransactionDate', 'PostingDate', 'ValueDate', 'Date'], date('d/m/Y'));
                $tranDate = preg_replace('/\D+/', '', $dateText) ?: date('dmy');
            }
            $tranId = ($reference !== '' ? $reference : substr(md5(json_encode($data)), 0, 14)) . '.' . $tranDate;

            $user_id = $this->parse_order_id($comment, $getbank->noidungnap);
            if (!$user_id) {
                continue;
            }

            $userRow = DB::table('users')->where('id', $user_id)->first();
            if (!$userRow) {
                continue;
            }

            $checkInv = DB::table('invoices')->where('trans_id', $tranId)->first();
            if ($checkInv) {
                continue;
            }

            DB::table('invoices')->insert([
                'trans_id' => $tranId,
                'payment_method' => 'VPBank',
                'user_id' => $userRow->id,
                'description' => $comment,
                'amount' => $amount,
                'status' => 1,
                'create_time' => time(),
            ]);

            $note = "Nạp tiền tự động qua VPBank (#$tranId *Nội dung: $comment *Số tiền: $amount)";
            $this->plusCredits($userRow->id, $amount, $note);

            $txttele = "GIAO DỊCH NẠP TIỀN\n";
            $txttele .= "Người dùng: {$userRow->id}\n";
            $txttele .= "Số Tiền: " . number_format($amount) . "\n";
            $txttele .= "Mã GD: $tranId\n";
            $txttele .= "Nội dung: $comment\n";
            $txttele .= "Lúc: " . date("H:i d-m-Y");
            $this->odertele($txttele);
        }

        return "OK - Quét VPBank xong";
    }

    /**
     * Hàm cron quét giao dịch Techcombank
     */
    public function cronNapTECHCOMBANK(Request $request)
    {
        $getbank = DB::table('bank')->where('codebank', '970407')->first();
        if (!$getbank) {
            return "Chưa cấu hình ngân hàng Techcombank nhận tiền";
        }

        if (($getbank->receiver_bank_type ?? '') !== 'TECHCOMBANK' || empty($getbank->receiver_account_id)) {
            return "Chưa chọn tài khoản API Techcombank nhận tiền trong Super Admin";
        }

        $apiAccount = DB::table('account_techcombank')
            ->where('id', (int) $getbank->receiver_account_id)
            ->first();
        if (!$apiAccount) {
            return "Tài khoản Techcombank nhận tiền đã chọn không tồn tại";
        }
        if (!is_null($apiAccount->user_id)) {
            return "Tài khoản Techcombank nhận tiền phải là tài khoản hệ thống, không thuộc user khách";
        }
        if (trim((string) $apiAccount->account) !== trim((string) $getbank->accountNumber)) {
            return "Tài khoản Techcombank nhận tiền không trùng STK hiển thị cho khách";
        }
        if (empty($apiAccount->refresh_token)) {
            return "Tài khoản Techcombank nhận tiền chưa có refresh token, cần xác nhận lại trên app";
        }

        $history = app(PaymentController::class)->internalTechcombankTransactionHistoryForReceiver((int) $apiAccount->id);
        if (empty($history['ok'])) {
            return (string) ($history['message'] ?? 'Không lấy được lịch sử Techcombank nhận tiền');
        }

        $result = $history['data'] ?? [];
        $transactions = $result['transactions'] ?? ($result['data']['transactions'] ?? []);
        if (!is_array($transactions)) {
            return "Không tìm thấy transactions Techcombank";
        }

        foreach ($transactions as $data) {
            if (!is_array($data)) {
                continue;
            }

            $amount = (int) ($data['_amount'] ?? 0);
            $isCredit = array_key_exists('_is_credit', $data)
                ? (bool) $data['_is_credit']
                : strtoupper((string) $this->vpbankValue($data, ['creditDebitIndicator', 'type'], '')) === 'CRDT';
            if (!$isCredit) {
                continue;
            }

            $comment = (string) ($data['_description'] ?? $this->vpbankValue($data, ['description', 'remittanceInformation'], ''));
            $amount = abs($amount);
            if ($amount === 0) {
                continue;
            }

            $reference = (string) ($data['_reference'] ?? $this->vpbankValue($data, ['reference', 'id', 'transactionId'], ''));
            $tranDate = (string) ($data['_date_key'] ?? '');
            if ($tranDate === '') {
                $dateText = (string) $this->vpbankValue($data, ['bookingDate', 'valueDate', 'creationTime'], date('Y-m-d'));
                $tranDate = preg_replace('/\D+/', '', $dateText) ?: date('dmy');
            }
            $tranId = ($reference !== '' ? $reference : substr(md5(json_encode($data)), 0, 14)) . '.' . $tranDate;

            $user_id = $this->parse_order_id($comment, $getbank->noidungnap);
            if (!$user_id) {
                continue;
            }

            $userRow = DB::table('users')->where('id', $user_id)->first();
            if (!$userRow) {
                continue;
            }

            $checkInv = DB::table('invoices')->where('trans_id', $tranId)->first();
            if ($checkInv) {
                continue;
            }

            DB::table('invoices')->insert([
                'trans_id' => $tranId,
                'payment_method' => 'Techcombank',
                'user_id' => $userRow->id,
                'description' => $comment,
                'amount' => $amount,
                'status' => 1,
                'create_time' => time(),
            ]);

            $note = "Nạp tiền tự động qua Techcombank (#$tranId *Nội dung: $comment *Số tiền: $amount)";
            $this->plusCredits($userRow->id, $amount, $note);

            $txttele = "GIAO DỊCH NẠP TIỀN\n";
            $txttele .= "Người dùng: {$userRow->id}\n";
            $txttele .= "Số Tiền: " . number_format($amount) . "\n";
            $txttele .= "Mã GD: $tranId\n";
            $txttele .= "Nội dung: $comment\n";
            $txttele .= "Lúc: " . date("H:i d-m-Y");
            $this->odertele($txttele);
        }

        return "OK - Quét Techcombank xong";
    }

    public function cronRefreshTECHCOMBANK(Request $request)
    {
        $accounts = DB::table('account_techcombank')
            ->whereNotNull('refresh_token')
            ->where('refresh_token', '<>', '')
            ->select('id', 'account')
            ->get();

        if ($accounts->isEmpty()) {
            return "Không có tài khoản Techcombank cần refresh";
        }

        $payment = app(PaymentController::class);
        $ok = 0;
        $failed = 0;
        $failedAccounts = [];

        foreach ($accounts as $account) {
            $result = $payment->internalTechcombankRefreshAccount((int) $account->id);
            if (!empty($result['ok'])) {
                $ok++;
                continue;
            }

            $failed++;
            $failedAccounts[] = (string) $account->account;
        }

        $message = "OK - Refresh Techcombank: {$ok} thành công, {$failed} lỗi";
        if ($failedAccounts) {
            $message .= " (" . implode(', ', array_slice($failedAccounts, 0, 5)) . ")";
        }

        return $message;
    }

    /**
     * Hàm cron quét giao dịch MBBank
     */
    public function cronNapMBBANK(Request $request)
    {
        $getbank = DB::table('bank')->where('codebank', '970422')->first();
        if (!$getbank) {
            return "Chưa cấu hình ngân hàng MBBank nhận tiền";
        }

        if (($getbank->receiver_bank_type ?? '') !== 'MBBANK' || empty($getbank->receiver_account_id)) {
            return "Chưa chọn tài khoản API MBBank nhận tiền trong Super Admin";
        }

        $apiAccount = DB::table('account_mbbank')
            ->where('id', (int) $getbank->receiver_account_id)
            ->first();
        if (!$apiAccount) {
            return "Tài khoản MBBank nhận tiền đã chọn không tồn tại";
        }
        if (!is_null($apiAccount->user_id)) {
            return "Tài khoản MBBank nhận tiền phải là tài khoản hệ thống, không thuộc user khách";
        }
        if (trim((string) $apiAccount->account) !== trim((string) $getbank->accountNumber)) {
            return "Tài khoản MBBank nhận tiền không trùng STK hiển thị cho khách";
        }
        if (empty($apiAccount->session_id) && empty($apiAccount->password)) {
            return "Tài khoản MBBank nhận tiền chưa có session hoặc mật khẩu để cron đăng nhập lại";
        }

        $history = app(PaymentController::class)->internalMbbankTransactionHistoryForReceiver((int) $apiAccount->id);
        if (empty($history['ok'])) {
            return (string) ($history['message'] ?? 'Không lấy được lịch sử MBBank nhận tiền');
        }

        $result = $history['data'] ?? [];
        $transactions = $result['transactions'] ?? ($result['data']['transactions'] ?? []);
        if (!is_array($transactions)) {
            return "Không tìm thấy transactions MBBank";
        }

        foreach ($transactions as $data) {
            if (!is_array($data)) {
                continue;
            }

            $amount = (int) ($data['_amount'] ?? 0);
            $isCredit = array_key_exists('_is_credit', $data)
                ? (bool) $data['_is_credit']
                : $amount > 0;
            if (!$isCredit) {
                continue;
            }

            $comment = (string) ($data['_description'] ?? $this->vpbankValue($data, ['description', 'transactionDesc', 'content'], ''));
            $amount = abs($amount);
            if ($amount === 0) {
                continue;
            }

            $reference = (string) ($data['_reference'] ?? $this->vpbankValue($data, ['refNo', 'transactionId', 'id'], ''));
            $tranDate = (string) ($data['_date_key'] ?? '');
            if ($tranDate === '') {
                $dateText = (string) $this->vpbankValue($data, ['transactionDate', 'postingDate'], date('d/m/Y'));
                $tranDate = preg_replace('/\D+/', '', $dateText) ?: date('dmy');
            }
            $tranId = ($reference !== '' ? $reference : substr(md5(json_encode($data)), 0, 14)) . '.' . $tranDate;

            $user_id = $this->parse_order_id($comment, $getbank->noidungnap);
            if (!$user_id) {
                continue;
            }

            $userRow = DB::table('users')->where('id', $user_id)->first();
            if (!$userRow) {
                continue;
            }

            $checkInv = DB::table('invoices')->where('trans_id', $tranId)->first();
            if ($checkInv) {
                continue;
            }

            DB::table('invoices')->insert([
                'trans_id' => $tranId,
                'payment_method' => 'MBBank',
                'user_id' => $userRow->id,
                'description' => $comment,
                'amount' => $amount,
                'status' => 1,
                'create_time' => time(),
            ]);

            $note = "Nạp tiền tự động qua MBBank (#$tranId *Nội dung: $comment *Số tiền: $amount)";
            $this->plusCredits($userRow->id, $amount, $note);

            $txttele = "GIAO DỊCH NẠP TIỀN\n";
            $txttele .= "Người dùng: {$userRow->id}\n";
            $txttele .= "Số Tiền: " . number_format($amount) . "\n";
            $txttele .= "Mã GD: $tranId\n";
            $txttele .= "Nội dung: $comment\n";
            $txttele .= "Lúc: " . date("H:i d-m-Y");
            $this->odertele($txttele);
        }

        return "OK - Quét MBBank xong";
    }


    private function storeBankTransactions(string $bank, ?int $accountId, ?int $userId, string $accountNo, array $transactions): void
    {
        try {
            app(BankTransactionRecorder::class)->save($bank, $accountId, $userId, $accountNo, $transactions);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // -------------------------------------------------------
    // Các hàm hỗ trợ
    // -------------------------------------------------------

    private function curl_get($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    private function loginAcb($username, $password)
    {
        $url = "https://apiapp.acb.com.vn/mb/v2/auth/tokens";
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

    private function getTransactionHistoryAcb($accountNo, $token, $rows = 20)
    {
        $url = "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history?maxRows={$rows}&account={$accountNo}";
        $headers = [
            'Host: apiapp.acb.com.vn',
            'Accept: application/json, text/plain, */*',
            'User-Agent: ACB-MBA/2 CFNetwork/1474 Darwin/23.0.0',
            'Accept-Language: vi',
            "Authorization: bearer {$token}",
            'x-app-version: 3.12.4',
        ];

        return $this->callApi($url, $headers, null, 'GET', 20);
    }

    private function acbReLoginReceiver($account)
    {
        if (empty($account->phone) || empty($account->password)) {
            return false;
        }

        try {
            $login = $this->loginAcb($account->phone, $account->password);
            if (isset($login['identity']['active']) && (int) $login['identity']['active'] === 1) {
                DB::table('account_acb')->where('id', $account->id)->update([
                    'sessionId' => $login['accessToken'] ?? '',
                    'time' => time(),
                ]);

                return !empty($login['accessToken']);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return false;
    }

    private function callApi($url, $headers, $data = null, $method = 'POST', $timeout = 10)
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

    private function parse_order_id($comment, $prefix)
    {
        $re = '/'.preg_quote($prefix, '/').'\d+/im';
        preg_match_all($re, $comment, $matches, PREG_SET_ORDER, 0);
        if(count($matches) == 0) return null;
        $orderCode = $matches[0][0];
        $prefixLength = strlen($prefix);
        $orderId = intval(substr($orderCode, $prefixLength));
        return $orderId;
    }

    private function convertDateFormat($originalDate)
    {
        // "d/m/Y" => "dmy"
        $dateObject = \DateTime::createFromFormat("d/m/Y", $originalDate);
        return $dateObject->format("dmy");
    }

    private function vpbankValue(array $item, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && $item[$key] !== '') {
                return $item[$key];
            }
        }

        return $default;
    }

    private function plusCredits($user_id, $amount, $reason)
    {
        DB::transaction(function () use ($user_id, $amount, $reason) {
            $user = DB::table('users')->where('id', $user_id)->lockForUpdate()->first();
            if (!$user) {
                return;
            }

            $amount = (int) $amount;
            $before = (int) ($user->amount ?? 0);
            $after = $before + $amount;

            DB::table('users')->where('id', $user_id)->update([
                'amount' => $after,
                'total_paid' => DB::raw('COALESCE(total_paid, 0) + ' . max(0, $amount)),
            ]);

            DB::table('xlogs')->insert([
                'ip' => request()->ip(),
                'user' => $user_id,
                'log' => 'Cập nhật số dư',
                'notes' => $reason . " | Trước: {$before} | Sau: {$after}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    // Gửi Telegram
    private function odertele($text)
    {
        // token & chat_id => có thể lấy từ DB settings
        $botToken = env('TELEGRAM_BOT_TOKEN',''); 
        $chatId   = env('TELEGRAM_CHAT_ID','');

        // Hoặc cứng:
        // $botToken = "123456:ABC";
        // $chatId = "987654321";

        $text = urlencode($text);

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatId}&text={$text}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        $resp = curl_exec($ch);
        curl_close($ch);
        // Tuỳ check $resp
    }
}
