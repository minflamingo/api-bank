<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_techcombank')) {
            return;
        }

        Schema::create('account_techcombank', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 64)->index();
            $table->string('password', 255);
            $table->string('account', 32)->index();
            $table->string('name', 191)->nullable();
            $table->mediumText('auth_token')->nullable();
            $table->mediumText('refresh_token')->nullable();
            $table->string('arrangement_id', 191)->nullable()->index();
            $table->mediumText('cookie')->nullable();
            $table->mediumText('login_url')->nullable();
            $table->text('code_verifier')->nullable();
            $table->string('code_challenge', 191)->nullable();
            $table->string('state', 64)->nullable();
            $table->string('nonce', 64)->nullable();
            $table->boolean('is_login')->default(false);
            $table->string('token', 191)->nullable()->index();
            $table->bigInteger('balance')->nullable();
            $table->timestamp('create_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_techcombank');
    }
};
