<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_vpbank')) {
            return;
        }

        Schema::create('account_vpbank', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 64)->index();
            $table->string('password', 255);
            $table->string('account', 32)->index();
            $table->string('name', 191)->nullable();
            $table->text('token_key')->nullable();
            $table->text('csrf')->nullable();
            $table->mediumText('cookie')->nullable();
            $table->boolean('is_login')->default(false);
            $table->string('token', 191)->nullable()->index();
            $table->timestamp('create_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_vpbank');
    }
};
