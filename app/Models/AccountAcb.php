<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountAcb extends Model
{
    protected $table = 'account_acb'; // Tên bảng trong MySQL
    public $timestamps = false;       // Nếu không dùng cột created_at/updated_at

    protected $fillable = [
        'user_id',
        'phone',
        'stk',
        'name',
        'password',
        'sessionId',
        'deviceId',
        'token',
        'time'
    ];
}
