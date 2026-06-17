<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountVpbank extends Model
{
    protected $table = 'account_vpbank';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username',
        'password',
        'account',
        'name',
        'token_key',
        'csrf',
        'cookie',
        'is_login',
        'token',
        'create_date',
    ];
}
