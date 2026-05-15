<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HR\AdminController;
use App\Http\Controllers\HR\StaffController;
use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

// ─── Public Booking Routes ─────────────────────────────────────────────────────
Route::post('/booking', [BookingController::class, 'store']);
Route::get('/extras', [BookingController::class, 'extras']);
Route::get('/packages', [BookingController::class, 'packages']);
Route::get('/tables', [BookingController::class, 'tables']);
Route::get('/booking/{booking}/vietqr', [BookingController::class, 'getVietQR']);
Route::post('/booking/upload-proof', [BookingController::class, 'uploadProof']);
Route::post('/momo/callback', [BookingController::class, 'handleMomoCallback']);
Route::post('/detail-user-by-card', [BookingController::class, 'findUserByCard']);
Route::post('/detail-user-by-phone', [BookingController::class, 'findUserByPhone']);
Route::get('/list-booking', [BookingController::class, 'getListBookings']);
Route::get('/all-bookings', [BookingController::class, 'getAllBookings']);
Route::post('/add-member', [BookingController::class, 'addMember']);
Route::post('/check-table', [BookingController::class, 'checkTableAvailability']);
Route::post('/booking/mark-as-served', [BookingController::class, 'markAsServed']);
Route::get('/list-members', [BookingController::class, 'getListMembers']);
Route::delete('/member/{member}', [BookingController::class, 'deleteMember']);
Route::put('/member/{member}', [BookingController::class, 'editMember']);

// ─── Admin / Staff Auth ────────────────────────────────────────────────────────
Route::post('/admin/login', [AdminController::class, 'login']);

// ─── Protected: requires Sanctum token ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/logout', [AdminController::class, 'logout']);
    Route::get('/admin/me', [AdminController::class, 'me']);
    Route::get('/admin/stats', [AdminController::class, 'getStats']);

    // Admin & Shift Leader
    Route::middleware('role:admin,shift_leader')->group(function () {
        Route::post('/admin/shifts', [AdminController::class, 'saveShifts']);
        Route::post('/admin/staff', [AdminController::class, 'addStaff']);
        Route::put('/admin/staff/{id}', [AdminController::class, 'updateStaff']);
        Route::delete('/admin/staff/{id}', [AdminController::class, 'deleteStaff']);

        Route::get('/admin/payroll', [AdminController::class, 'getPayroll']);
        Route::post('/admin/payroll', [AdminController::class, 'savePayroll']);

        // Menu Management
        Route::get('/admin/menu', [MenuController::class, 'index']);
        Route::post('/admin/menu', [MenuController::class, 'store']);
        Route::post('/admin/menu/upload-image', [MenuController::class, 'uploadImage']);
        Route::put('/admin/menu/{id}', [MenuController::class, 'update']);
        Route::delete('/admin/menu/{id}', [MenuController::class, 'destroy']);

        // Booking setup management
        Route::get('/admin/packages', [BookingController::class, 'adminPackages']);
        Route::post('/admin/packages', [BookingController::class, 'storePackage']);
        Route::put('/admin/packages/{package}', [BookingController::class, 'updatePackage']);
        Route::delete('/admin/packages/{package}', [BookingController::class, 'destroyPackage']);
        Route::get('/admin/tables', [BookingController::class, 'adminTables']);
        Route::post('/admin/tables', [BookingController::class, 'storeTable']);
        Route::put('/admin/tables/{table}', [BookingController::class, 'updateTable']);
        Route::delete('/admin/tables/{table}', [BookingController::class, 'destroyTable']);

        Route::post('/admin/schedule', [AdminController::class, 'saveSchedule']);
        Route::delete('/admin/schedule/{id}', [AdminController::class, 'deleteSchedule']);
    });

    // Staff & Admin
    Route::get('/admin/staff', [AdminController::class, 'getStaffList']);
    Route::get('/admin/schedule', [AdminController::class, 'getSchedule']);
    Route::get('/shifts', [AdminController::class, 'getShifts']);
    Route::post('/staff/check-in', [StaffController::class, 'checkIn']);
    Route::post('/staff/check-out', [StaffController::class, 'checkOut']);
    Route::post('/staff/smart-check-in', [StaffController::class, 'smartCheckIn']);
    Route::get('/staff/attendance', [StaffController::class, 'getMyAttendance']);
    Route::get('/staff/schedule', [StaffController::class, 'getMySchedule']);
    Route::get('/staff/payroll', [StaffController::class, 'getMyPayroll']);
    Route::get('/staff/payroll/history', [StaffController::class, 'getMyPayrollHistory']);
});
