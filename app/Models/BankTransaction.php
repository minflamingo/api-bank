<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $table = 'bank_transactions';

    protected $fillable = [
        'user_id',
        'account_id',
        'bank',
        'bank_code',
        'account_no',
        'transaction_id',
        'ref_id',
        'transaction_hash',
        'transaction_uid',
        'posted_at',
        'happened_at',
        'direction',
        'amount',
        'currency',
        'description',
        'counterparty_name',
        'counterparty_account',
        'counterparty_bank',
        'raw',
        'synced_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'account_id' => 'integer',
        'amount' => 'integer',
        'raw' => 'array',
        'posted_at' => 'datetime',
        'happened_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
