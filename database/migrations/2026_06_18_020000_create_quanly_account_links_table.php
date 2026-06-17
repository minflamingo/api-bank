<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quanly_account_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quanly_user_id')->unique();
            $table->unsignedBigInteger('quanly_tenant_id')->nullable()->index();
            $table->foreignId('apibank_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email', 191)->nullable()->index();
            $table->string('phone', 64)->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique('apibank_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quanly_account_links');
    }
};
