<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTechcombank extends Model
{
    protected $table = 'account_techcombank';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username',
        'password',
        'account',
        'name',
        'auth_token',
        'refresh_token',
        'arrangement_id',
        'cookie',
        'login_url',
        'code_verifier',
        'code_challenge',
        'state',
        'nonce',
        'is_login',
        'token',
        'balance',
        'create_date',
    ];
}
