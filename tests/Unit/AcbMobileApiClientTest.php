<?php

namespace Tests\Unit;

use App\Services\AcbMobileApiClient;
use PHPUnit\Framework\TestCase;

class AcbMobileApiClientTest extends TestCase
{
    public function test_it_normalizes_only_notifications_for_the_requested_account(): void
    {
        $client = new AcbMobileApiClient('5dfc1654-8630-4a98-a1c4-5392de6734d8');
        $payload = [
            'codeStatus' => 200,
            'messageStatus' => 'success',
            'data' => [
                [
                    'id' => 'notification-1',
                    'createdAt' => 1784818800000,
                    'message' => 'ACB: TK 203888888 (VND) + 1,250,000 GD: KHACH HANG THANH TOAN',
                ],
                [
                    'id' => 'notification-2',
                    'createdAt' => 1784818860000,
                    'message' => 'ACB: TK 38148977 (VND) - 50,000 GD: CHUYEN TIEN',
                ],
                [
                    'id' => 'notification-3',
                    'createdAt' => 1784818920000,
                    'message' => 'Thong bao khuyen mai ACB',
                ],
            ],
        ];

        $result = $client->normalizeNotifications($payload, '203888888');

        $this->assertSame(200, $result['codeStatus']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('notification-1', $result['data'][0]['transactionNumber']);
        $this->assertSame(1250000, $result['data'][0]['amount']);
        $this->assertSame('IN', $result['data'][0]['type']);
        $this->assertSame('KHACH HANG THANH TOAN', $result['data'][0]['description']);
    }

    public function test_it_matches_account_numbers_when_acb_omits_leading_zeroes(): void
    {
        $client = new AcbMobileApiClient('5dfc1654-8630-4a98-a1c4-5392de6734d8');
        $result = $client->normalizeNotifications([
            'codeStatus' => 200,
            'data' => [[
                'id' => 'notification-leading-zero',
                'createdAt' => 1784818800000,
                'message' => 'ACB: TK 451000252337 (VND) + 10,000 GD: TEST',
            ]],
        ], '0451000252337');

        $this->assertCount(1, $result['data']);
    }

    public function test_it_preserves_acb_error_messages_for_scanner_diagnostics(): void
    {
        $client = new AcbMobileApiClient('5dfc1654-8630-4a98-a1c4-5392de6734d8');
        $result = $client->normalizeNotifications([
            'codeStatus' => 403,
            'message' => 'Forbidden: requests are not allowed',
        ], '203888888');

        $this->assertSame(403, $result['codeStatus']);
        $this->assertSame('Forbidden: requests are not allowed', $result['message']);
        $this->assertSame([], $result['data']);
    }
}
