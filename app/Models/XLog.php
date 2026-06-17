<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XLog extends Model
{
    use HasFactory;

    protected $table = 'xlogs'; // Giả sử bạn có bảng xlogs
    public $timestamps = false; // Nếu không dùng cột created_at/updated_at

    protected $fillable = [
        'ip',
        'user',
        'log',
        'notes'
    ];
}
