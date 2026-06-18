<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quanly_webhook_settings')) {
            Schema::create('quanly_webhook_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('url', 2048)->nullable();
                $table->string('secret', 191)->nullable();
                $table->json('events');
                $table->boolean('is_active')->default(false)->index();
                $table->timestamps();
            });
        }

        $this->backfillLinkedQuanlyUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('quanly_webhook_settings');
    }

    private function backfillLinkedQuanlyUsers(): void
    {
        if (!Schema::hasTable('quanly_account_links') || !Schema::hasTable('quanly_webhook_settings')) {
            return;
        }

        $url = trim((string) config('services.quanly_webhook.url', ''));
        $secret = trim((string) config('services.quanly_webhook.secret', ''));
        if ($url === '' || $secret === '') {
            return;
        }

        $events = $this->configuredEvents();
        $now = now();

        DB::table('quanly_account_links')
            ->select('apibank_user_id')
            ->whereNotNull('apibank_user_id')
            ->distinct()
            ->orderBy('apibank_user_id')
            ->chunk(200, function ($links) use ($url, $secret, $events, $now) {
                foreach ($links as $link) {
                    DB::table('quanly_webhook_settings')->updateOrInsert(
                        ['user_id' => (int) $link->apibank_user_id],
                        [
                            'url' => $url,
                            'secret' => $secret,
                            'events' => json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'is_active' => true,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            });
    }

    private function configuredEvents(): array
    {
        $events = array_filter(array_map('trim', explode(',', (string) config(
            'services.quanly_webhook.events',
            'transaction.created,transaction.updated,balance.updated,account.session_expired'
        ))));

        return array_values(array_intersect($events, [
            'transaction.created',
            'transaction.updated',
            'balance.updated',
            'account.session_expired',
            'recharge.matched',
            'recharge.failed',
        ])) ?: [
            'transaction.created',
            'transaction.updated',
            'balance.updated',
            'account.session_expired',
        ];
    }
};
