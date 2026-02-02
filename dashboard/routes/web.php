<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Attendance2Controller;

// auth-free endpoints
Route::get('/login', [DashboardController::class, 'showLogin']);
Route::post('/login', [DashboardController::class, 'login']);

// logout
Route::post('/logout', [DashboardController::class, 'logout']);

// protected dashboard
Route::get('/', [DashboardController::class, 'index']);
// Attendance2 routes
Route::get('/attendance2', [Attendance2Controller::class, 'index']);
Route::get('/api/attendance2-summary', [Attendance2Controller::class, 'data']);
// Use AttendanceController::data for DataTables server-side processing (returns table-shaped data)
Route::get('/api/attendance-summary', [AttendanceController::class, 'data']);
Route::post('/api/attendance/update', [AttendanceController::class, 'update']);
Route::post('/api/attendance/delete', [AttendanceController::class, 'delete']);
// Add manual attendance entry from dashboard UI
Route::post('/api/attendance/add', [AttendanceController::class, 'add']);
// compatibility: frontend expects /api/check-latest — map it here
Route::get('/api/check-latest', [AttendanceController::class, 'latest']);
// users endpoint for populating user dropdowns
Route::get('/api/users', [AttendanceController::class, 'users']);
// legacy/alternate route
Route::get('/api/latest-attendance', [AttendanceController::class, 'latest']);
// User CRUD API (used by dashboard frontend)
Route::get('/api/users', [\App\Http\Controllers\UserController::class, 'index']);
Route::post('/api/users', [\App\Http\Controllers\UserController::class, 'store']);
Route::put('/api/users/{id}', [\App\Http\Controllers\UserController::class, 'update']);
Route::delete('/api/users/{id}', [\App\Http\Controllers\UserController::class, 'destroy']);
// User page removed: user UI is embedded in dashboard
Route::get('/api-test', function () {
    try {
        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer test12345',
            'Accept' => 'application/json'
        ])->get('http://182.160.120.92:8080/');

        if (!$response->successful()) {
            return 'API Error: ' . $response->status();
        }

        $data = $response->json();

        // Reorder each object
        $orderedData = array_map(function($item) {
            return [
                'user_id' => $item['user_id'] ?? null,
                'date'    => $item['date'] ?? null,
                'in'      => $item['in'] ?? null,
                'out'     => $item['out'] ?? null,
                'status'  => $item['status'] ?? null,
                'punch'    => $item['punch'] ?? null,
                'message' => $item['message'] ?? null,
            ];
        }, $data);

        return '<pre>' . json_encode($orderedData, JSON_PRETTY_PRINT) . '</pre>';

    } catch (\Exception $e) {
        return 'Connection Error: ' . $e->getMessage();
    }
});
