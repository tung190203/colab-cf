<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

Route::post('/booking', [BookingController::class, 'store']);
Route::get('/extras',[BookingController::class, 'extras']);
Route::get('/packages',[BookingController::class, 'packages']);
Route::get('/tables',[BookingController::class, 'tables']);
Route::get('/booking/{booking}/vietqr', [BookingController::class, 'getVietQR']);
Route::post('/booking/upload-proof', [BookingController::class, 'uploadProof']);
Route::post('/momo/callback', [BookingController::class, 'handleMomoCallback']);
Route::post('/detail-user-by-card', [BookingController::class, 'findUserByCard']);
Route::post('/detail-user-by-phone', [BookingController::class, 'findUserByPhone']);
Route::get('/list-booking', [BookingController::class, 'getListBookings']);
Route::post('/add-member', [BookingController::class, 'addMember']);
Route::post('/check-table', [BookingController::class, 'checkTableAvailability']);
Route::post('/booking/mark-as-served', [BookingController::class, 'markAsServed']);
Route::get('/list-members', [BookingController::class, 'getListMembers']);
Route::delete('/member/{member}', [BookingController::class, 'deleteMember']);
Route::put('/member/{member}', [BookingController::class, 'editMember']);
