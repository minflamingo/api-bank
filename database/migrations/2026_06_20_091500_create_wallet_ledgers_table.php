<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_ledgers')) {
            return;
        }

        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('type', 64)->index();
            $table->string('direction', 16)->index();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->string('reference', 191)->unique();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'wallet_ledgers_user_created_idx');
            $table->index(['type', 'created_at'], 'wallet_ledgers_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledgers');
    }
};
