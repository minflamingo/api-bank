<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    /**
     * Tên bảng trong CSDL
     *
     * @var string
     */
    protected $table = 'bank';

    /**
     * Cột khóa chính (nếu không phải là "id" hoặc có cấu hình khác).
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Bảng không có 2 cột timestamps (created_at, updated_at)
     * Nếu bảng có 2 cột timestamps, bạn để true hoặc bỏ dòng này cũng được.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Các cột được phép thao tác mass assignment.
     *
     * @var array
     */
    protected $fillable = [
        'short_name',
        'image',
        'accountNumber',
        'accountName',
        'codebank',
        'noidungnap',
        'vietqr_template',
        'min_amount',
        'quick_amounts',
        'instructions',
        'receiver_bank_type',
        'receiver_account_id',
    ];
}
