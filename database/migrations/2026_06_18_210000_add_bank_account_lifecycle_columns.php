<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $accountTables = [
        'account_acb',
        'account_vietcombank',
        'account_vpbank',
        'account_techcombank',
        'account_mbbank',
    ];

    public function up(): void
    {
        foreach ($this->accountTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'is_active')) {
                    $table->boolean('is_active')->default(true)->index()->after('token');
                }
                if (!Schema::hasColumn($tableName, 'stopped_at')) {
                    $table->dateTime('stopped_at')->nullable()->after('is_active');
                }
                if (!Schema::hasColumn($tableName, 'status_note')) {
                    $table->string('status_note', 255)->nullable()->after('stopped_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->accountTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['status_note', 'stopped_at', 'is_active'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
