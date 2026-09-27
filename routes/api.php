<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ManagerEmployeeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Forgot Password Routes
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\ManagerBulkEmployeeController;

Route::middleware('throttle:6,1')->group(function () {
    Route::post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
    Route::post('/forgot-password/resend-otp', [ForgotPasswordController::class, 'resendOtp']);
});
Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);

    Route::middleware('role:hr')->group(function () {
        Route::get('/dashboard/hr', [DashboardController::class, 'hrDashboard']);
        Route::put('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::get('/reports/hr-summary', [ReportController::class, 'hrSummary']);
    });

    Route::middleware('role:hr,manager')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
    });

    Route::middleware('role:manager,hr')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index']);
    });

    Route::middleware('role:employee')->group(function () {
        Route::get('/employee/dashboard', [DashboardController::class, 'employeeDashboard']);
        Route::get('/employee/tasks', [TaskController::class, 'employeeTasks']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
    });

    Route::middleware('role:manager')->group(function () {
        Route::get('/manager/reports', [ReportController::class, 'managerReports']);
        Route::get('/manager/performance', [DashboardController::class, 'managerPerformance']);
        Route::get('/manager/dashboard', [DashboardController::class, 'managerDashboard']);
        Route::get('/manager/employees/bulk/template', [ManagerBulkEmployeeController::class, 'downloadTemplate']);
        Route::post('/manager/employees/bulk/validate', [ManagerBulkEmployeeController::class, 'validateBulk']);
        Route::post('/manager/employees/bulk', [ManagerBulkEmployeeController::class, 'storeBulk']);

        Route::get('/manager/employees', [ManagerEmployeeController::class, 'index']);
        Route::post('/manager/employees', [ManagerEmployeeController::class, 'store']);
        Route::get('/manager/employees/{id}', [ManagerEmployeeController::class, 'show']);
        Route::put('/manager/employees/{id}', [ManagerEmployeeController::class, 'update']);
        Route::delete('/manager/employees/{id}', [ManagerEmployeeController::class, 'destroy']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/manager/tasks/{id}/remarks', [TaskController::class, 'updateRemarks']);
    });

    Route::middleware('role:hr,manager')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
});
