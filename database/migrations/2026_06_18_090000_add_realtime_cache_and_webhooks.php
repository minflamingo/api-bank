<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_transactions')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $this->addColumn($table, 'string', 'bank_code', ['length' => 32, 'nullable' => true, 'after' => 'account_id']);
                $this->addColumn($table, 'string', 'ref_id', ['length' => 191, 'nullable' => true, 'after' => 'transaction_id']);
                $this->addColumn($table, 'unsignedBigInteger', 'active_ms', ['nullable' => true, 'after' => 'ref_id']);
                $this->addColumn($table, 'dateTime', 'happened_at', ['nullable' => true, 'after' => 'posted_at']);
                $this->addColumn($table, 'json', 'raw', ['nullable' => true, 'after' => 'counterparty_bank']);
                $this->addColumn($table, 'char', 'transaction_uid', ['length' => 64, 'nullable' => true, 'after' => 'transaction_hash']);
                $this->addColumn($table, 'dateTime', 'synced_at', ['nullable' => true, 'after' => 'raw']);
            });

            $this->addIndex('bank_transactions', ['bank_code', 'account_no', 'happened_at'], 'bank_transactions_code_account_happened_idx');
            $this->addIndex('bank_transactions', ['account_id', 'happened_at'], 'bank_transactions_account_happened_idx');
            $this->addIndex('bank_transactions', ['transaction_uid'], 'bank_transactions_uid_idx');

            if (Schema::hasColumn('bank_transactions', 'bank') && Schema::hasColumn('bank_transactions', 'bank_code')) {
                try {
                    \Illuminate\Support\Facades\DB::table('bank_transactions')
                        ->whereNull('bank_code')
                        ->update(['bank_code' => \Illuminate\Support\Facades\DB::raw('bank')]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        foreach (['account_acb', 'account_vietcombank', 'account_vpbank', 'account_techcombank', 'account_mbbank'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $this->addColumn($table, 'dateTime', 'last_synced_at', ['nullable' => true]);
                $this->addColumn($table, 'dateTime', 'next_scan_at', ['nullable' => true]);
                $this->addColumn($table, 'unsignedInteger', 'scan_interval_seconds', ['nullable' => false, 'default' => 60]);
                $this->addColumn($table, 'bigInteger', 'last_balance', ['nullable' => true]);
                $this->addColumn($table, 'dateTime', 'last_balance_at', ['nullable' => true]);
                $this->addColumn($table, 'string', 'last_scan_status', ['length' => 32, 'nullable' => true]);
                $this->addColumn($table, 'text', 'last_scan_error', ['nullable' => true]);
            });

            $this->addIndex($tableName, ['next_scan_at'], $tableName . '_next_scan_at_idx');
            $this->addIndex($tableName, ['last_synced_at'], $tableName . '_last_synced_at_idx');
        }

        if (!Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 120);
                $table->string('url', 2048);
                $table->json('events');
                $table->string('secret', 191);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event_id', 80)->unique();
                $table->string('event', 80)->index();
                $table->string('target_url', 2048);
                $table->string('secret', 191);
                $table->json('payload');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(8);
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamp('delivered_at')->nullable()->index();
                $table->timestamp('failed_at')->nullable()->index();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['event', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }

    private function addColumn(Blueprint $table, string $type, string $name, array $options = []): void
    {
        $schema = Schema::getConnection()->getSchemaBuilder();
        $tableName = $table->getTable();
        if ($schema->hasColumn($tableName, $name)) {
            return;
        }

        $column = match ($type) {
            'string' => $table->string($name, $options['length'] ?? 255),
            'char' => $table->char($name, $options['length'] ?? 255),
            'text' => $table->text($name),
            'json' => $table->json($name),
            'dateTime' => $table->dateTime($name),
            'unsignedBigInteger' => $table->unsignedBigInteger($name),
            'unsignedInteger' => $table->unsignedInteger($name),
            'bigInteger' => $table->bigInteger($name),
            default => throw new InvalidArgumentException('Unsupported column type: ' . $type),
        };

        if (($options['nullable'] ?? false) === true) {
            $column->nullable();
        }
        if (array_key_exists('default', $options)) {
            $column->default($options['default']);
        }
        if (!empty($options['after'])) {
            $column->after($options['after']);
        }
    }

    private function addIndex(string $tableName, array $columns, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            // MySQL throws when an index already exists. Keeping migrations idempotent
            // matters more here because several VPS databases are already mid-migration.
        }
    }
};
