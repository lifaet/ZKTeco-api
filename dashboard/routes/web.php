<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;

// auth-free endpoints
Route::get('/login', [DashboardController::class, 'showLogin']);
Route::post('/login', [DashboardController::class, 'login']);

// logout
Route::post('/logout', [DashboardController::class, 'logout']);

// protected dashboard
Route::get('/', [DashboardController::class, 'index']);
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
// Staff CRUD API (used by dashboard frontend)
Route::get('/api/staff', [\App\Http\Controllers\StaffController::class, 'index']);
Route::post('/api/staff', [\App\Http\Controllers\StaffController::class, 'store']);
Route::put('/api/staff/{id}', [\App\Http\Controllers\StaffController::class, 'update']);
Route::delete('/api/staff/{id}', [\App\Http\Controllers\StaffController::class, 'destroy']);
// Staff page removed: staff UI is embedded in dashboard
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
