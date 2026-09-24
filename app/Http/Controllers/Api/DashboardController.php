<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MisTask;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "HR Management", description: "HR Dashboard stats")]
#[OA\Tag(name: "Manager Management", description: "Manager Dashboard stats")]
#[OA\Tag(name: "Employee Management", description: "Employee Dashboard stats")]
class DashboardController extends Controller
{
    #[OA\Get(
        path: "/dashboard/hr",
        summary: "Get HR Dashboard Stats",
        security: [["bearerAuth" => []]],
        tags: ["HR Management"],
        responses: [
            new OA\Response(response: 200, description: "HR dashboard statistics")
        ]
    )]
    public function hrDashboard()
    {
        $managers = User::where('role', 'MANAGER')->with('teamMembers')->get();
        $totalManagers = $managers->count();
        $activeManagers = $managers->where('is_active', true)->count();
        
        $allTasks = MisTask::all();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->where('status', 'Completed')->count();
        $overallCompletion = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        
        $monthlyMis = MisTask::whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count();
        
        $managersData = $managers->map(function($manager) use ($allTasks) {
            $teamIds = $manager->teamMembers->pluck('id')->toArray();
            $managerTasks = $allTasks->whereIn('employee_id', array_merge([$manager->id], $teamIds));
            
            return [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'teamSize' => count($teamIds),
                'tasksDone' => $managerTasks->where('status', 'Completed')->count(),
                'totalTasks' => $managerTasks->count(),
                'isActive' => (bool) $manager->is_active,
            ];
        });
        
        return response()->json([
            'totalManagers' => $totalManagers,
            'activeManagers' => $activeManagers,
            'totalTasks' => $totalTasks,
            'overallCompletion' => $overallCompletion,
            'monthlyMis' => $monthlyMis,
            'managers' => $managersData
        ]);
    }

    #[OA\Get(
        path: "/manager/dashboard",
        summary: "Get Manager Dashboard Stats",
        security: [["bearerAuth" => []]],
        tags: ["Manager Management"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Manager dashboard statistics",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "employees",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "total", type: "integer", example: 5),
                                        new OA\Property(property: "active", type: "integer", example: 4),
                                        new OA\Property(property: "inactive", type: "integer", example: 1)
                                    ]
                                ),
                                new OA\Property(
                                    property: "tasks",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "total", type: "integer", example: 10),
                                        new OA\Property(property: "pending", type: "integer", example: 2),
                                        new OA\Property(property: "in_progress", type: "integer", example: 5),
                                        new OA\Property(property: "completed", type: "integer", example: 3)
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized access")
        ]
    )]
    public function managerDashboard(Request $request)
    {
        $manager = $request->user();

        // Employee stats
        $employeeQuery = User::where('manager_id', $manager->id)->where('role', 'EMPLOYEE');
        $employeeTotal = (clone $employeeQuery)->count();
        $employeeActive = (clone $employeeQuery)->where('is_active', true)->count();
        $employeeInactive = $employeeTotal - $employeeActive;

        // Task stats
        $taskQuery = MisTask::where('manager_id', $manager->id);
        $taskTotal = (clone $taskQuery)->count();
        $taskPending = (clone $taskQuery)->where('status', 'Pending')->count();
        $taskInProgress = (clone $taskQuery)->where('status', 'In Progress')->count();
        $taskCompleted = (clone $taskQuery)->where('status', 'Completed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'employees' => [
                    'total' => $employeeTotal,
                    'active' => $employeeActive,
                    'inactive' => $employeeInactive
                ],
                'tasks' => [
                    'total' => $taskTotal,
                    'pending' => $taskPending,
                    'in_progress' => $taskInProgress,
                    'completed' => $taskCompleted
                ]
            ]
        ]);
    }

    #[OA\Get(
        path: "/employee/dashboard",
        summary: "Get Employee Dashboard Stats",
        security: [["bearerAuth" => []]],
        tags: ["Employee Management"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Employee dashboard data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Employee dashboard data retrieved successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "employee",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 1),
                                        new OA\Property(property: "name", type: "string", example: "Employee Name"),
                                        new OA\Property(property: "username", type: "string", example: "employee1"),
                                        new OA\Property(property: "email", type: "string", example: "employee@example.com"),
                                        new OA\Property(property: "department_role", type: "string", example: "Software Developer")
                                    ]
                                ),
                                new OA\Property(
                                    property: "statistics",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "total_tasks", type: "integer", example: 5),
                                        new OA\Property(property: "pending_tasks", type: "integer", example: 2),
                                        new OA\Property(property: "in_progress_tasks", type: "integer", example: 2),
                                        new OA\Property(property: "completed_tasks", type: "integer", example: 1),
                                        new OA\Property(property: "overdue_tasks", type: "integer", example: 0)
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized access")
        ]
    )]
    public function employeeDashboard(Request $request)
    {
        $employee = $request->user();

        // Task stats
        $taskQuery = MisTask::where('employee_id', $employee->id);
        $tasks = $taskQuery->get();
        
        $totalTasks = $tasks->count();
        $pendingTasks = $tasks->where('status', 'Pending')->count();
        $inProgressTasks = $tasks->where('status', 'In Progress')->count();
        $completedTasks = $tasks->where('status', 'Completed')->count();
        
        // Overdue calculation (Pending or In Progress, and due_date < today)
        $overdueTasks = $tasks->filter(function ($task) {
            return in_array($task->status, ['Pending', 'In Progress']) && 
                   $task->due_date && 
                   \Carbon\Carbon::parse($task->due_date)->isPast();
        })->count();

        return response()->json([
            'message' => 'Employee dashboard data retrieved successfully',
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'username' => $employee->username,
                    'email' => $employee->email,
                    'department_role' => $employee->department_role
                ],
                'statistics' => [
                    'total_tasks' => $totalTasks,
                    'pending_tasks' => $pendingTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'completed_tasks' => $completedTasks,
                    'overdue_tasks' => $overdueTasks
                ]
            ]
        ]);
    }
}
