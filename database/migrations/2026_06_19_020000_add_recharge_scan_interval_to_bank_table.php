<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bank', 'recharge_scan_interval_seconds')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->unsignedTinyInteger('recharge_scan_interval_seconds')
                    ->nullable()
                    ->default(2)
                    ->after('receiver_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank', 'recharge_scan_interval_seconds')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->dropColumn('recharge_scan_interval_seconds');
            });
        }
    }
};
