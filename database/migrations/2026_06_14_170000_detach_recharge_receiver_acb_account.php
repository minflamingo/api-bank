<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bank = DB::table('bank')
            ->where('codebank', '970416')
            ->where('receiver_bank_type', 'ACB')
            ->whereNotNull('receiver_account_id')
            ->first();

        if (!$bank) {
            return;
        }

        DB::table('account_acb')
            ->where('id', (int) $bank->receiver_account_id)
            ->update(['user_id' => null]);
    }

    public function down(): void
    {
        //
    }
};
