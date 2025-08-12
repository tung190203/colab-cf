<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingSystemTables extends Migration
{
    public function up()
    {
        // Bảng gói dịch vụ
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price'); // giá tiền (VND)
            $table->integer('duration'); // thời gian phút
            $table->string('duration_label')->nullable();
            $table->timestamps();
        });

        // Bảng bàn
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // mã bàn, ví dụ A01
            $table->enum('status', ['free', 'occupied'])->default('free');
            $table->timestamps();
        });

        // Bảng đặt bàn
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->integer('total_price'); // tổng tiền
            $table->enum('payment_method', ['momo', 'card', 'none'])->default('none');
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        // Bảng các dịch vụ thêm (đồ uống, in ấn, phòng họp)
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // drinks, prints, rooms
            $table->string('name');
            $table->integer('price');
            $table->timestamps();
        });

        // Bảng liên kết booking và extras (dùng nhiều extras cho 1 booking)
        Schema::create('booking_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained('extras')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('booking_extras');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('packages');
    }
}
