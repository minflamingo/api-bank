<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank')) {
            return;
        }

        if (!Schema::hasColumn('bank', 'vietqr_template')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->string('vietqr_template', 32)->nullable()->default('IRuAFR6')->after('noidungnap');
            });
        }

        if (!Schema::hasColumn('bank', 'min_amount')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->unsignedInteger('min_amount')->nullable()->default(10000)->after('vietqr_template');
            });
        }

        if (!Schema::hasColumn('bank', 'quick_amounts')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->string('quick_amounts', 255)->nullable()->default('50000,100000,200000,500000,1000000')->after('min_amount');
            });
        }

        if (!Schema::hasColumn('bank', 'instructions')) {
            Schema::table('bank', function (Blueprint $table) {
                $table->text('instructions')->nullable()->after('quick_amounts');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('bank')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('bank', 'instructions') ? 'instructions' : null,
            Schema::hasColumn('bank', 'quick_amounts') ? 'quick_amounts' : null,
            Schema::hasColumn('bank', 'min_amount') ? 'min_amount' : null,
            Schema::hasColumn('bank', 'vietqr_template') ? 'vietqr_template' : null,
        ]));

        if (!$columns) {
            return;
        }

        Schema::table('bank', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
