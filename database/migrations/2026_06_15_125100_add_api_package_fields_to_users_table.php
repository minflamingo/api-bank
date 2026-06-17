<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'api_plan')) {
                $table->string('api_plan', 40)->nullable()->after('time_end');
            }
            if (!Schema::hasColumn('users', 'api_account_limit')) {
                $table->unsignedSmallInteger('api_account_limit')->default(0)->after('api_plan');
            }
            if (!Schema::hasColumn('users', 'api_extra_slots')) {
                $table->unsignedSmallInteger('api_extra_slots')->default(0)->after('api_account_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'api_extra_slots')) {
                $table->dropColumn('api_extra_slots');
            }
            if (Schema::hasColumn('users', 'api_account_limit')) {
                $table->dropColumn('api_account_limit');
            }
            if (Schema::hasColumn('users', 'api_plan')) {
                $table->dropColumn('api_plan');
            }
        });
    }
};
