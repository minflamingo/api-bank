<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_transactions')) {
            return;
        }

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('bank', 32);
            $table->string('account_no', 64);
            $table->string('transaction_id', 191)->nullable();
            $table->char('transaction_hash', 64)->unique();
            $table->dateTime('posted_at')->nullable()->index();
            $table->enum('direction', ['in', 'out', 'unknown'])->default('unknown')->index();
            $table->bigInteger('amount')->default(0)->index();
            $table->string('currency', 8)->default('VND');
            $table->text('description')->nullable();
            $table->string('counterparty_name', 255)->nullable();
            $table->string('counterparty_account', 64)->nullable();
            $table->string('counterparty_bank', 191)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'posted_at'], 'bank_transactions_user_posted_idx');
            $table->index(['bank', 'account_no', 'posted_at'], 'bank_transactions_bank_account_posted_idx');
            $table->index(['bank', 'account_no', 'transaction_id'], 'bank_transactions_bank_account_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
