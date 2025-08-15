<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Nơi bạn đăng ký các channel cho ứng dụng.
| Các channel này sẽ dùng để client (Vue/Pusher) subscribe.
|
*/

// Channel công khai
Broadcast::channel('bookings', function () {
    return true; // Cho phép mọi người join, hoặc return điều kiện
});

// Channel private có kiểm tra user
Broadcast::channel('private-bookings.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
