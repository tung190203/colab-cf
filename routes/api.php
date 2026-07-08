<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HR\AdminController;
use App\Http\Controllers\HR\StaffController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PosOrderController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ShiftHandoverController;
use App\Http\Controllers\StockController;
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
Route::get('/booking/{booking}/stock-check', [BookingController::class, 'checkBookingStock']);
Route::post('/booking/mark-as-served', [BookingController::class, 'markAsServed']);
Route::post('/booking/cancel', [BookingController::class, 'cancelBooking']);
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
        Route::get('/admin/penalty-rules', [AdminController::class, 'getPenaltyRules']);
        Route::post('/admin/penalty-rules', [AdminController::class, 'storePenaltyRule'])->middleware('role:admin');
        Route::put('/admin/penalty-rules/{penaltyRule}', [AdminController::class, 'updatePenaltyRule'])->middleware('role:admin');
        Route::delete('/admin/penalty-rules/{penaltyRule}', [AdminController::class, 'destroyPenaltyRule'])->middleware('role:admin');
        Route::get('/admin/attendance', [AdminController::class, 'getAttendance']);
        Route::post('/admin/attendance', [AdminController::class, 'saveAttendance']);
        Route::get('/admin/audit-logs', [AdminController::class, 'getAuditLogs'])->middleware('role:admin');

        // Event Management
        Route::post('/admin/events/upload-zip', [\App\Http\Controllers\HR\EventController::class, 'uploadZip'])->middleware('role:admin');
        Route::get('/admin/events', [\App\Http\Controllers\HR\EventController::class, 'index'])->middleware('role:admin');
        Route::post('/admin/events', [\App\Http\Controllers\HR\EventController::class, 'store'])->middleware('role:admin');
        Route::get('/admin/events/{id}', [\App\Http\Controllers\HR\EventController::class, 'show'])->middleware('role:admin');
        Route::put('/admin/events/{id}', [\App\Http\Controllers\HR\EventController::class, 'update'])->middleware('role:admin');
        Route::delete('/admin/events/{id}', [\App\Http\Controllers\HR\EventController::class, 'destroy'])->middleware('role:admin');

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

        Route::get('/admin/customer-stats', [AdminController::class, 'getCustomerStats']);

        // Material Management
        Route::get('/materials', [MaterialController::class, 'index']);
        Route::post('/materials', [MaterialController::class, 'store']);
        Route::patch('/materials/{material}', [MaterialController::class, 'update']);
        Route::delete('/materials/{material}', [MaterialController::class, 'destroy']);

        Route::post('/stock/import', [StockController::class, 'import']);
        Route::post('/stock/manual-deduct', [StockController::class, 'manualDeduct']);
        Route::post('/stock/deduct-by-order', [StockController::class, 'deductByOrder']);
        Route::get('/stock/logs', [StockController::class, 'logs']);

        Route::get('/recipes', [RecipeController::class, 'index']);
        Route::get('/recipes/options', [RecipeController::class, 'options']);
        Route::post('/recipes', [RecipeController::class, 'store']);
        Route::get('/recipes/{recipe}/logs', [RecipeController::class, 'logs']);
    });

    // Staff & Admin
    Route::get('/shift-handover/prepare', [ShiftHandoverController::class, 'prepare']);
    Route::post('/shift-handover', [ShiftHandoverController::class, 'store']);
    Route::get('/shift-handover', [ShiftHandoverController::class, 'index']);
    Route::get('/shift-handover/export/{month}', [ShiftHandoverController::class, 'export']);
    Route::get('/shift-handover/{shiftHandover}', [ShiftHandoverController::class, 'show']);
    Route::post('/shift-handover/{shiftHandover}/confirm', [ShiftHandoverController::class, 'confirm']);
    Route::post('/shift-handover/{shiftHandover}/dispute', [ShiftHandoverController::class, 'dispute']);
    Route::get('/pos-orders/options', [PosOrderController::class, 'options']);
    Route::get('/pos-orders', [PosOrderController::class, 'index']);
    Route::post('/pos-orders', [PosOrderController::class, 'store']);
    Route::get('/admin/staff', [AdminController::class, 'getStaffList']);
    Route::get('/stock/alerts', [StockController::class, 'alerts']);
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
