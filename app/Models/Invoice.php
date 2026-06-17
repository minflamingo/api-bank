<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /**
     * Tên bảng tương ứng trong CSDL
     *
     * @var string
     */
    protected $table = 'invoices';

    /**
     * Khóa chính của bảng (nếu là cột "id" thì có thể bỏ qua).
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Tắt/bật tự động quản lý timestamps (created_at, updated_at).
     * Bảng `invoices` của bạn không có 2 cột này, nên để false.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Các cột được phép gán giá trị hàng loạt (mass assignment).
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'trans_id',
        'payment_method',
        'amount',
        'description',
        'status',
        'create_time'
    ];

    /**
     * (Tùy chọn) Nếu bạn muốn cast/trả về kiểu dữ liệu cho cột cụ thể.
     * Ví dụ: 'amount' trả về integer, v.v.
     */
    protected $casts = [
        'amount' => 'integer',
        'status' => 'integer',
        'create_time' => 'integer',
    ];

    // Nếu cần quan hệ (relationship) với bảng users
    // public function user() {
    //     return $this->belongsTo(User::class, 'user_id', 'id');
    // }
}

