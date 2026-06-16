<?php

use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

// Equipment API routes - NO AUTH FOR NOW (for testing)
Route::prefix('equipment')->group(function () {
    Route::get('/', [EquipmentController::class, 'getAll']);
    Route::get('/stats', [EquipmentController::class, 'getEquipmentStats']);
    Route::get('/usage', [EquipmentController::class, 'getEquipmentUsage']);
    Route::post('/', [EquipmentController::class, 'store']);
    Route::put('/{id}', [EquipmentController::class, 'update']);
    Route::delete('/{id}', [EquipmentController::class, 'destroy']);
    Route::post('/{id}/transfer', [EquipmentController::class, 'transfer']);
});


Route::prefix('v1')->group(function () {
    Route::get('/room/list', [RoomController::class, 'apiIndex']);
    Route::get('/room/list/{id}', [RoomController::class, 'apiShow']);
});

