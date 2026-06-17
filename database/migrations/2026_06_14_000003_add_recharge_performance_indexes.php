<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('invoices', 'invoices_user_create_time_idx', ['user_id', 'create_time']);
        $this->addIndexIfMissing('invoices', 'invoices_trans_id_idx', ['trans_id']);
        $this->addIndexIfMissing('bank', 'bank_codebank_idx', ['codebank']);
        $this->addIndexIfMissing('account_acb', 'account_acb_stk_idx', ['stk']);
        $this->addIndexIfMissing('account_acb', 'account_acb_token_idx', ['token']);
    }

    public function down(): void
    {
        foreach ([
            ['account_acb', 'account_acb_token_idx'],
            ['account_acb', 'account_acb_stk_idx'],
            ['bank', 'bank_codebank_idx'],
            ['invoices', 'invoices_trans_id_idx'],
            ['invoices', 'invoices_user_create_time_idx'],
        ] as [$table, $index]) {
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $rows = DB::select('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?', [$index]);

        return count($rows) > 0;
    }
};
