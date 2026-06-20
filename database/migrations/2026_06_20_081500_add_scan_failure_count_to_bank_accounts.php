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
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'scan_failure_count')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $column = $table->unsignedTinyInteger('scan_failure_count')->default(0);
                if (Schema::hasColumn($tableName, 'last_scan_error')) {
                    $column->after('last_scan_error');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->accountTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'scan_failure_count')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('scan_failure_count');
            });
        }
    }
};
