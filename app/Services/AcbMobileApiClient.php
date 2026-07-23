<?php

namespace App\Services;

class AcbMobileApiClient
{
    private const API_HOST = 'apiapp.acb.com.vn';

    private const CLIENT_ID = 'iuSuHYVufIUuNIREV0FB9EoLn9kHsDbm';

    private const API_KEY = 'CQk6S5usauGmMgMYLGqCuDtgtqIM8FI1';

    private const APP_VERSION = '3.52.0';

    private const USER_AGENT = 'ACB-MBA/3 CFNetwork/3826.600.41 Darwin/24.6.0';

    private const LOGIN_URL = 'https://apiapp.acb.com.vn/mb/v2/auth/tokens';

    private const BALANCE_URL = 'https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/list/account-payment';

    private const NOTIFICATIONS_URL = 'https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/v2/notifications';

    public function __construct(private readonly ?string $fixedDeviceId = null) {}

    public function login(string $username, string $password): ?array
    {
        $response = $this->request(
            self::LOGIN_URL,
            'POST',
            $this->headers(),
            json_encode([
                'clientId' => self::CLIENT_ID,
                'username' => $username,
                'password' => $password,
                'deviceId' => $this->deviceId(),
            ], JSON_UNESCAPED_SLASHES)
        );

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function balance(string $token): string
    {
        return $this->request(
            self::BALANCE_URL,
            'GET',
            $this->headers($token)
        );
    }

    public function transactions(string $accountNo, string $token, int $rows = 20): string
    {
        $url = self::NOTIFICATIONS_URL.'?'.http_build_query([
            'page' => 0,
            'size' => max(1, min(100, $rows)),
            'language' => 'vi',
        ]);
        $response = $this->request(
            $url,
            'GET',
            $this->headers($token),
            null,
            preferHttp2: true
        );
        $decoded = json_decode($response, true);
        $normalized = $this->normalizeNotifications(is_array($decoded) ? $decoded : [], $accountNo);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '{"codeStatus":500,"message":"Không chuẩn hóa được dữ liệu ACB","data":[]}';
    }

    /**
     * @return array{codeStatus:int,messageStatus:string,message:string,description:string,data:array<int,array<string,mixed>>}
     */
    public function normalizeNotifications(array $payload, string $accountNo): array
    {
        $status = (int) ($payload['codeStatus'] ?? $payload['errorCode'] ?? 0);
        $items = $payload['data'] ?? null;
        if ($status !== 200 || ! is_array($items)) {
            $message = trim((string) (
                $payload['message']
                ?? $payload['description']
                ?? $payload['error']
                ?? 'Không lấy được giao dịch ACB'
            ));

            return [
                'codeStatus' => $status > 0 ? $status : 500,
                'messageStatus' => (string) ($payload['messageStatus'] ?? 'error'),
                'message' => $message,
                'description' => $message,
                'data' => [],
            ];
        }

        $transactions = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $message = trim((string) ($item['message'] ?? ''));
            if (! $this->messageBelongsToAccount($message, $accountNo)) {
                continue;
            }
            if (! preg_match('/\(VND\)\s*([+-])\s*([\d.,]+)/u', $message, $amountMatch)) {
                continue;
            }

            $descriptionParts = preg_split('/\bGD:\s*/u', $message, 2);
            $description = trim((string) ($descriptionParts[1] ?? $message));
            $amountSource = isset($item['amount']) && (string) $item['amount'] !== ''
                ? (string) $item['amount']
                : (string) $amountMatch[2];
            $amount = (int) (preg_replace('/\D+/', '', $amountSource) ?: 0);
            $createdAt = (int) ($item['createdAt'] ?? $item['activeDatetime'] ?? 0);
            $reference = (string) ($item['id'] ?? $item['notificationId'] ?? '');
            if ($reference === '') {
                $reference = hash('sha256', implode('|', [$accountNo, $createdAt, $amount, $message]));
            }

            $transactions[] = [
                'transactionID' => $reference,
                'transactionNumber' => $reference,
                'amount' => $amount,
                'description' => $description,
                'transactionDate' => $createdAt > 0
                    ? date('d/m/Y H:i:s', (int) floor($createdAt / 1000))
                    : '',
                'postingDate' => $createdAt,
                'activeDatetime' => $createdAt,
                'type' => $amountMatch[1] === '+' ? 'IN' : 'OUT',
                '_notification' => $item,
            ];
        }

        return [
            'codeStatus' => 200,
            'messageStatus' => (string) ($payload['messageStatus'] ?? 'success'),
            'message' => (string) ($payload['description'] ?? 'success'),
            'description' => (string) ($payload['description'] ?? 'success'),
            'data' => $transactions,
        ];
    }

    private function headers(?string $token = null): array
    {
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Host: '.self::API_HOST,
            'Accept-Language: vi',
            'Cache-Control: no-cache',
            'x-conversation-id: '.$this->uuid().'-1-',
            'x-request-id: '.$this->uuid(),
            'apikey: '.self::API_KEY,
            'User-Agent: '.self::USER_AGENT,
            'x-device-id: '.$this->deviceId(),
            'x-app-version: '.self::APP_VERSION,
            'Connection: keep-alive',
            'Accept: application/json, text/plain, */*',
        ];

        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: bearer '.$token;
        }

        return $headers;
    }

    private function request(
        string $url,
        string $method,
        array $headers,
        ?string $body = null,
        bool $preferHttp2 = false
    ): string {
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTP_VERSION => $preferHttp2 && defined('CURL_HTTP_VERSION_2TLS')
                ? CURL_HTTP_VERSION_2TLS
                : CURL_HTTP_VERSION_1_1,
        ];
        if ($method === 'POST' && $body !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            return json_encode([
                'codeStatus' => 503,
                'messageStatus' => 'error',
                'message' => 'Lỗi kết nối ACB: '.$curlError,
                'data' => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        $decoded = json_decode((string) $response, true);
        if ($httpStatus >= 400 && is_array($decoded) && ! isset($decoded['codeStatus'])) {
            $decoded['codeStatus'] = $httpStatus;

            return json_encode(
                $decoded,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ) ?: (string) $response;
        }

        return (string) $response;
    }

    private function messageBelongsToAccount(string $message, string $accountNo): bool
    {
        if ($message === '' || ! preg_match('/ACB:\s*TK\s*([0-9]+)/iu', $message, $accountMatch)) {
            return false;
        }

        return ltrim((string) $accountMatch[1], '0') === ltrim(preg_replace('/\D+/', '', $accountNo) ?: '', '0');
    }

    private function deviceId(): string
    {
        if ($this->fixedDeviceId !== null && $this->fixedDeviceId !== '') {
            return $this->fixedDeviceId;
        }

        $appKey = function_exists('config') ? (string) config('app.key', '') : '';
        $hex = substr(hash('sha256', 'apibank-acb-device|'.$appKey), 0, 32);

        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            dechex((hexdec($hex[16]) & 0x3) | 0x8),
            substr($hex, 17, 3),
            substr($hex, 20, 12)
        );
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
