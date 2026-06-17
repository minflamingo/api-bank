<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_mbbank')) {
            return;
        }

        Schema::create('account_mbbank', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 64)->index();
            $table->string('password', 255);
            $table->string('account', 32)->index();
            $table->string('name', 191)->nullable();
            $table->mediumText('session_id')->nullable();
            $table->string('device_id', 128)->nullable();
            $table->string('token', 191)->nullable()->index();
            $table->bigInteger('balance')->nullable();
            $table->timestamp('create_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mbbank');
    }
};
