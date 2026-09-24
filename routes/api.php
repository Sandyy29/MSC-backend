<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:hr')->group(function () {
        Route::get('/dashboard/hr', [DashboardController::class, 'hrDashboard']);
        Route::put('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
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
        Route::get('/manager/dashboard', [DashboardController::class, 'managerDashboard']);
        Route::get('/manager/employees', [\App\Http\Controllers\Api\ManagerEmployeeController::class, 'index']);
        Route::get('/manager/employees/{id}', [\App\Http\Controllers\Api\ManagerEmployeeController::class, 'show']);
        Route::put('/manager/employees/{id}', [\App\Http\Controllers\Api\ManagerEmployeeController::class, 'update']);
        Route::delete('/manager/employees/{id}', [\App\Http\Controllers\Api\ManagerEmployeeController::class, 'destroy']);
        Route::post('/tasks', [TaskController::class, 'store']);
    });

    Route::middleware('role:hr,manager')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
});