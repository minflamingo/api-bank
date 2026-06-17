<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateXlogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('xlogs', function (Blueprint $table) {
            // Khóa chính tự động tăng dần
            $table->bigIncrements('xkey');

            // IP thực hiện thao tác
            $table->string('ip', 50)->nullable();

            // User ID (trong trường hợp chưa có auth, ta để mặc định 1, 
            // sau này có thể thay bằng auth()->id() hoặc quan hệ tới bảng users)
            $table->unsignedBigInteger('user')->default(1);

            // Nội dung log (ví dụ: "Thu tiền mặt", "Thanh toán chuyển khoản", ...)
            $table->text('log')->nullable();

            // Notes (ghi chú thêm, nếu có)
            $table->text('notes')->nullable();

            // Tự động có cột created_at, updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('xlogs');
    }
}
