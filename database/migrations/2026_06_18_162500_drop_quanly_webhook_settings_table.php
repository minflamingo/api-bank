<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quanly_webhook_settings');
    }

    public function down(): void
    {
        // Intentionally left blank. Webhook destinations are user-created
        // rows in webhook_endpoints, not system-created Quanly settings.
    }
};
