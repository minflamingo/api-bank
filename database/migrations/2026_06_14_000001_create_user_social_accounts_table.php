<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_social_accounts')) {
            return;
        }

        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 50);
            $table->string('provider_id', 191);
            $table->string('email', 191)->nullable()->index();
            $table->string('display_name', 191)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('raw_profile')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id'], 'usa_provider_provider_id_unique');
            $table->index(['user_id', 'provider'], 'usa_user_provider_index');
            $table->foreign('user_id', 'usa_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
