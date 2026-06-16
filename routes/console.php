<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\MainDashboardController;

Route::get('/', function () {
    return redirect()->route('main_dashboard');
});

Route::get('/main_dashboard', [MainDashboardController::class, 'index'])->name('main_dashboard');
Route::get('/user_account', [UserAccountController::class, 'index'])->name('user_account.index');
Route::get('/building_dashboard', [BuildingController::class, 'index'])->name('building_dashboard');

// CRUD rooms
Route::resource('rooms', RoomController::class);