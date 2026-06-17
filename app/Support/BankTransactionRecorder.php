<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BankTransactionRecorder
{
    public function save(string $bank, ?int $accountId, ?int $userId, string $accountNo, array $transactions): int
    {
        $bank = strtolower(trim($bank));
        $accountNo = trim($accountNo);
        if ($bank === '' || $accountNo === '' || empty($transactions)) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }

            $row = $this->rowFromTransaction($bank, $accountId, $userId, $accountNo, $transaction, $now);
            if ($row !== null) {
                $rows[$row['transaction_hash']] = $row;
            }
        }

        if (!$rows) {
            return 0;
        }

        $inserted = 0;
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            $inserted += (int) DB::table('bank_transactions')->insertOrIgnore($chunk);
        }

        return $inserted;
    }

    private function rowFromTransaction(string $bank, ?int $accountId, ?int $userId, string $accountNo, array $item, $now): ?array
    {
        $description = $this->cleanText($this->firstValue($item, [
            '_description', 'Description', 'description', 'TransactionDescription', 'transactionDesc',
            'Narrative', 'Remark', 'Content', 'note', 'remittanceInformation',
        ]));

        $amount = $this->transactionAmount($bank, $item);
        $isCredit = $this->isCredit($bank, $item, $amount);
        $amount = $isCredit === false ? -abs($amount) : abs($amount);
        $direction = $isCredit === true ? 'in' : ($isCredit === false ? 'out' : 'unknown');

        $reference = $this->cleanText($this->firstValue($item, [
            '_reference', 'Reference', 'ReferenceNumber', 'TransactionId', 'transactionId', 'Id', 'id',
            'TransactionNumber', 'transactionNumber', 'SeqNo', 'refNo', 'transId', 'trans_id',
        ]));

        $dateText = $this->firstValue($item, [
            '_date_text', 'TransactionDate', 'transactionDate', 'postingDate', 'PostingDate',
            'BookingDate', 'bookingDate', 'ValueDate', 'valueDate', 'Date', 'creationTime', 'createdAt',
        ]);
        $postedAt = $this->parseDate($dateText);
        $dateKey = $postedAt ? $postedAt->format('YmdHis') : ($this->digits($dateText) ?: $this->digits($this->firstValue($item, ['_date_key'])));

        if ($bank === 'acb' && $reference === '') {
            $reference = $this->cleanText((string) ($item['transactionNumber'] ?? ''));
        }
        if ($bank === 'vcb' && $reference === '') {
            $reference = $this->cleanText((string) ($item['SeqNo'] ?? ''));
        }

        $transactionId = $reference !== ''
            ? mb_substr($reference . ($dateKey !== '' ? '.' . $dateKey : ''), 0, 191)
            : '';

        $raw = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($raw === false) {
            $raw = '{}';
        }

        if ($transactionId === '' && $description === '' && $amount === 0) {
            return null;
        }

        $party = $this->partyInfo($item, $bank, $accountNo, $isCredit === true);
        $rawFingerprint = $transactionId === '' ? sha1($raw) : '';
        $hashSeed = implode('|', [
            $bank,
            $accountNo,
            $transactionId,
            $dateKey,
            (string) $amount,
            $description,
            $rawFingerprint,
        ]);

        return [
            'user_id' => $userId,
            'account_id' => $accountId,
            'bank' => $bank,
            'account_no' => mb_substr($accountNo, 0, 64),
            'transaction_id' => $transactionId !== '' ? $transactionId : null,
            'transaction_hash' => hash('sha256', $hashSeed),
            'posted_at' => $postedAt ? $postedAt->format('Y-m-d H:i:s') : null,
            'direction' => $direction,
            'amount' => $amount,
            'currency' => 'VND',
            'description' => $description !== '' ? $description : null,
            'counterparty_name' => $party['name'] !== '' ? mb_substr($party['name'], 0, 255) : null,
            'counterparty_account' => $party['account'] !== '' ? mb_substr($party['account'], 0, 64) : null,
            'counterparty_bank' => $party['bank'] !== '' ? mb_substr($party['bank'], 0, 191) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function transactionAmount(string $bank, array $item): int
    {
        if (isset($item['_amount'])) {
            return (int) $item['_amount'];
        }

        if ($bank === 'vcb') {
            $amount = abs($this->moneyValue($item['Amount'] ?? 0));
            return (($item['CD'] ?? '') === '+') ? $amount : -$amount;
        }

        if ($bank === 'acb') {
            $amount = abs($this->moneyValue($item['amount'] ?? 0));
            return (($item['type'] ?? '') === 'IN') ? $amount : -$amount;
        }

        $credit = $this->moneyValue($this->firstValue($item, ['creditAmount', 'CreditAmount']));
        if ($credit > 0) {
            return abs($credit);
        }

        $debit = $this->moneyValue($this->firstValue($item, ['debitAmount', 'DebitAmount']));
        if ($debit > 0) {
            return -abs($debit);
        }

        $nestedAmount = $item['transactionAmountCurrency']['amount'] ?? null;
        if ($nestedAmount !== null) {
            $amount = $this->moneyValue($nestedAmount);
            $direction = strtoupper((string) $this->firstValue($item, ['creditDebitIndicator', 'type']));
            return in_array($direction, ['DBIT', 'DEBIT', 'D'], true) ? -abs($amount) : abs($amount);
        }

        return $this->moneyValue($this->firstValue($item, ['amount', 'Amount', 'transactionAmount', 'TransactionAmount']));
    }

    private function isCredit(string $bank, array $item, int $amount): ?bool
    {
        if (array_key_exists('_is_credit', $item)) {
            return (bool) $item['_is_credit'];
        }
        if ($bank === 'vcb') {
            return (($item['CD'] ?? '') === '+');
        }
        if ($bank === 'acb') {
            return (($item['type'] ?? '') === 'IN');
        }
        if ($this->moneyValue($this->firstValue($item, ['creditAmount', 'CreditAmount'])) > 0) {
            return true;
        }
        if ($this->moneyValue($this->firstValue($item, ['debitAmount', 'DebitAmount'])) > 0) {
            return false;
        }
        if ($amount > 0) {
            return true;
        }
        if ($amount < 0) {
            return false;
        }

        $direction = strtoupper((string) $this->firstValue($item, [
            'creditDebitIndicator', 'CreditDebitIndicator', 'DebitCreditIndicator', 'TransactionType', 'type', 'Type', 'CD',
        ]));
        if (in_array($direction, ['+', 'C', 'CR', 'CRDT', 'CREDIT', 'IN'], true)) {
            return true;
        }
        if (in_array($direction, ['-', 'D', 'DR', 'DBIT', 'DEBIT', 'OUT'], true)) {
            return false;
        }

        return null;
    }

    private function partyInfo(array $item, string $bank, string $accountNo, bool $isCredit): array
    {
        $party = is_array($item['_party_info'] ?? null) ? $item['_party_info'] : [];
        $name = $this->cleanText((string) ($party['name'] ?? ''));
        $account = $this->cleanText((string) ($party['account'] ?? ''));
        $bankName = $this->cleanText((string) ($party['bank'] ?? ''));

        if ($bank === 'techcombank') {
            $additions = is_array($item['additions'] ?? null) ? $item['additions'] : [];
            $creditNo = (string) ($additions['creditAcctNo'] ?? '');
            $debitNo = (string) ($additions['debitAcctNo'] ?? '');
            $useDebit = $isCredit || ($creditNo !== '' && $creditNo === $accountNo);
            $name = $name ?: $this->cleanText((string) ($useDebit ? ($additions['debitAcctName'] ?? '') : ($additions['creditAcctName'] ?? '')));
            $account = $account ?: $this->cleanText((string) ($useDebit ? $debitNo : $creditNo));
            $bankName = $bankName ?: $this->cleanText((string) ($useDebit ? ($additions['debitBankName'] ?? '') : ($additions['creditBankName'] ?? '')));
        }

        if ($name === '') {
            $name = $this->cleanText($this->firstValue($item, $isCredit ? [
                'senderName', 'fromName', 'remitterName', 'payerName', 'debitAcctName', 'debitAccountName', 'sourceAccountName', 'fromAccountName',
            ] : [
                'receiverName', 'toName', 'beneficiaryName', 'beneficiaryAccountName', 'benAccountName', 'creditAcctName', 'creditAccountName', 'destinationAccountName', 'toAccountName',
            ]));
        }

        if ($account === '') {
            $account = $this->cleanText($this->firstValue($item, $isCredit ? [
                'senderAccount', 'fromAccount', 'remitterAccount', 'payerAccount', 'debitAcctNo', 'debitAccountNo', 'sourceAccount', 'fromAccountNumber',
            ] : [
                'receiverAccount', 'toAccount', 'beneficiaryAccount', 'beneficiaryAccountNo', 'benAccountNo', 'creditAcctNo', 'creditAccountNo', 'destinationAccount', 'toAccountNumber',
            ]));
        }

        return ['name' => $name, 'account' => $account, 'bank' => $bankName];
    }

    private function firstValue(array $item, array $keys): string
    {
        $wanted = array_map(static fn ($key) => strtolower((string) $key), $keys);
        $stack = [$item];
        while ($stack) {
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

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/Date\((\d{10,13})\)/', $value, $match)) {
            return Carbon::createFromTimestamp(((int) $match[1]) > 9999999999 ? (int) floor(((int) $match[1]) / 1000) : (int) $match[1]);
        }

        if (ctype_digit($value)) {
            $number = (int) $value;
            if ($number > 9999999999) {
                return Carbon::createFromTimestamp((int) floor($number / 1000));
            }
            if ($number > 999999999) {
                return Carbon::createFromTimestamp($number);
            }
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function moneyValue($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $raw = trim((string) $value);
        $negative = str_contains($raw, '-') || (str_starts_with($raw, '(') && str_ends_with($raw, ')'));
        $normalised = str_replace([',', ' ', 'VND', 'vnd', 'VNĐ', 'vnđ', 'đ'], '', $raw);
        if (substr_count($normalised, '.') > 1) {
            $normalised = str_replace('.', '', $normalised);
        }
        $normalised = preg_replace('/[^0-9.\-]/', '', $normalised) ?: '0';
        $amount = (int) round((float) $normalised);

        return $negative ? -abs($amount) : $amount;
    }

    private function cleanText(string $text): string
    {
        $text = trim(strip_tags($text));
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        return trim($text, " \t\n\r\0\x0B-:;,.|/");
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }
}
