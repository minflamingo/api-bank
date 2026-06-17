<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasReceiverBankType = Schema::hasColumn('bank', 'receiver_bank_type');
        $hasReceiverAccountId = Schema::hasColumn('bank', 'receiver_account_id');

        if (!$hasReceiverBankType || !$hasReceiverAccountId) {
            Schema::table('bank', function (Blueprint $table) use ($hasReceiverBankType, $hasReceiverAccountId) {
                if (!$hasReceiverBankType) {
                    $table->string('receiver_bank_type', 16)->nullable()->after('instructions');
                }

                if (!$hasReceiverAccountId) {
                    $table->unsignedBigInteger('receiver_account_id')->nullable()->after('receiver_bank_type');
                    $table->index('receiver_account_id', 'bank_receiver_account_id_idx');
                }
            });
        }

        $acbBank = DB::table('bank')->where('codebank', '970416')->first();
        if ($acbBank && empty($acbBank->receiver_account_id)) {
            $receiver = DB::table('account_acb')
                ->where('stk', trim((string) $acbBank->accountNumber))
                ->whereNotNull('token')
                ->where('token', '<>', '')
                ->orderByDesc('id')
                ->first();

            if ($receiver) {
                DB::table('bank')->where('id', $acbBank->id)->update([
                    'receiver_bank_type' => 'ACB',
                    'receiver_account_id' => $receiver->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $hasReceiverAccountId = Schema::hasColumn('bank', 'receiver_account_id');
        $hasReceiverBankType = Schema::hasColumn('bank', 'receiver_bank_type');

        if ($hasReceiverAccountId || $hasReceiverBankType) {
            Schema::table('bank', function (Blueprint $table) use ($hasReceiverAccountId, $hasReceiverBankType) {
                if ($hasReceiverAccountId) {
                    $table->dropIndex('bank_receiver_account_id_idx');
                    $table->dropColumn('receiver_account_id');
                }

                if ($hasReceiverBankType) {
                    $table->dropColumn('receiver_bank_type');
                }
            });
        }
    }
};
