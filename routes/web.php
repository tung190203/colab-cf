<?php

use Illuminate\Support\Facades\Route;

Route::prefix('events')->group(function () {
    Route::get('/', [\App\Http\Controllers\EventLandingController::class, 'index'])->name('event.home');
    Route::get('/{slug}', [\App\Http\Controllers\EventLandingController::class, 'show'])->name('event.show');
});

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

