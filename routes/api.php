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
Route::post('/detail-user', [BookingController::class, 'detailUser']);