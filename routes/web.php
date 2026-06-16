<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MainDashboardController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\CollegeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleNotificationController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\DashboardController;

function authCheck(Request $request)
{
    return $request->session()->has('user');
}

// Login Routes
Route::get('/login', function () {
    if (authCheck(request())) {
        return redirect('/MainDashboard');
    }
    return Inertia::render('Login');
})->name('login');

Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth.session'])->group(function () {
    // Main Dashboard (with pagination and search)
    Route::get('/', [MainDashboardController::class, 'index'])->name('dashboard');
    Route::get('/MainDashboard', [MainDashboardController::class, 'index'])->name('main.dashboard');

    // API endpoints for frontend
    Route::get('/api/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/api/dashboard/rooms', [DashboardController::class, 'getRooms']);
    Route::get('/api/dashboard/search', [DashboardController::class, 'search']);

    // Building Management
    Route::resource('BuildingDashboard', BuildingController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['BuildingDashboard' => 'building']);

    // College Management
    Route::resource('CollegeDashboard', CollegeController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['CollegeDashboard' => 'college']);

    // Department Management
    Route::resource('Departments', DepartmentController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['Departments' => 'department']);

    // Room Types
    Route::resource('RoomTypes', RoomTypeController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['RoomTypes' => 'roomtype']);

    // Rooms Management
    Route::resource('Rooms', RoomController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['Rooms' => 'room']);

    // Equipment Management Routes
    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');

    // Equipment API Routes
    Route::prefix('/api/equipment')->group(function () {
        Route::get('/', [EquipmentController::class, 'getAll']);
        Route::get('/suggestions', [EquipmentController::class, 'suggestions']);
        Route::post('/validate-names', [EquipmentController::class, 'validateNames']);
        Route::get('/stats', [EquipmentController::class, 'getStats']);
        Route::get('/usage', [EquipmentController::class, 'getEquipmentUsage']);
        Route::post('/', [EquipmentController::class, 'store']);
        Route::put('/{equipment}', [EquipmentController::class, 'update']);
        Route::post('/{equipment}/transfer', [EquipmentController::class, 'transfer']);
        Route::delete('/{equipment}', [EquipmentController::class, 'destroy']);
    });
    // Schedule Management
    Route::get('/Schedule', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/Schedule/room-equipment', [ScheduleController::class, 'roomEquipment'])->name('schedules.room-equipment');
    Route::post('/Schedule', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::put('/Schedule/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::patch('/Schedule/{schedule}/status', [ScheduleController::class, 'updateStatus'])->name('schedules.update-status');
    Route::delete('/Schedule/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
    Route::get('/api/schedule-notifications', [ScheduleNotificationController::class, 'index'])->name('schedule-notifications.index');
    Route::patch('/api/schedule-notifications/read-all', [ScheduleNotificationController::class, 'markAllRead'])->name('schedule-notifications.read-all');
    Route::patch('/api/schedule-notifications/unread-all', [ScheduleNotificationController::class, 'markAllUnread'])->name('schedule-notifications.unread-all');
    Route::delete('/api/schedule-notifications/clear-all', [ScheduleNotificationController::class, 'clearAll'])->name('schedule-notifications.clear-all');
    Route::patch('/api/schedule-notifications/{notification}/read', [ScheduleNotificationController::class, 'markRead'])->name('schedule-notifications.read');

    // Terms Management
    Route::get('/Terms', [TermController::class, 'index'])->name('terms.index');

    // User Account Management

    Route::get('/UserAccountPage', [UserAccountController::class, 'index'])->name('user-accounts.index');
    Route::post('/user-accounts', [UserAccountController::class, 'store'])->name('user-accounts.store');
    Route::put('/user-accounts/{userAccount}', [UserAccountController::class, 'update'])->name('user-accounts.update');
    Route::delete('/user-accounts/{userAccount}', [UserAccountController::class, 'destroy'])->name('user-accounts.destroy');
    Route::post('/user-accounts/{userAccount}/change-status', [UserAccountController::class, 'changeStatus'])->name('user-accounts.change-status');
    Route::post('/user-accounts/bulk-actions', [UserAccountController::class, 'bulkActions'])->name('user-accounts.bulk-actions');
    // Report Generation
    Route::prefix('/api/reports')->group(function () {
        Route::get('/room-utilization', function (Request $request) {
            return app(\App\Services\ReportService::class)->generateRoomUtilizationReport(
                $request->query('start_date', now()->subDays(30)->format('Y-m-d')),
                $request->query('end_date', now()->format('Y-m-d'))
            );
        });

        Route::get('/equipment-status', function () {
            return app(\App\Services\ReportService::class)->generateEquipmentStatusReport();
        });

        Route::get('/user-activity', function (Request $request) {
            return app(\App\Services\ReportService::class)->generateUserActivityReport(
                $request->query('start_date', now()->subDays(30)->format('Y-m-d')),
                $request->query('end_date', now()->format('Y-m-d'))
            );
        });

        Route::get('/schedule-report', function (Request $request) {
            return app(\App\Services\ReportService::class)->generateScheduleReport(
                $request->query('start_date', now()->subDays(30)->format('Y-m-d')),
                $request->query('end_date', now()->format('Y-m-d'))
            );
        });

        Route::get('/building-report', function () {
            return app(\App\Services\ReportService::class)->generateBuildingReport();
        });
    });
});

Route::get('/{any}', function () {
    return Inertia::render('NotFound');
})->where('any', '.*');
