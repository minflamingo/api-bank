<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMbbank extends Model
{
    protected $table = 'account_mbbank';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username',
        'password',
        'account',
        'name',
        'session_id',
        'device_id',
        'token',
        'balance',
        'create_date',
    ];
}
