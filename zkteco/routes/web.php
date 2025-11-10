<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;

Route::get('/', [DashboardController::class, 'index']);
// Use AttendanceController::data for DataTables server-side processing (returns table-shaped data)
Route::get('/api/attendance-summary', [AttendanceController::class, 'data']);
// compatibility: frontend expects /api/check-latest — map it here
Route::get('/api/check-latest', [AttendanceController::class, 'latest']);
// legacy/alternate route
Route::get('/api/latest-attendance', [AttendanceController::class, 'latest']);
