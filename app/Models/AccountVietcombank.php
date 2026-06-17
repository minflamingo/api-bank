<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountVietcombank extends Model
{
    use HasFactory;

    protected $table = 'account_vietcombank'; // tên bảng
    public $timestamps = false; // tắt nếu bảng không có cột created_at, updated_at
    
    protected $fillable = [
        'user_id',
        'name',
        'username',
        'password',
        'account',
        'session_id',
        'access_key',
        'cif',
        'mobile_id',
        'client_id',
        'tranId',
        'browserToken',
        'token',
        'create_date'
    ];
}
