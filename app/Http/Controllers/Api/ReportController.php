<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MisTask;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "HR Management", description: "Organization-wide reports & analytics")]
class ReportController extends Controller
{
    #[OA\Get(
        path: "/reports/hr-summary",
        summary: "Get HR reports and analytics summary",
        security: [["bearerAuth" => []]],
        tags: ["HR Management"],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Summary stats")
        ]
    )]
    public function hrSummary(\Illuminate\Http\Request $request)
    {
        $employees = User::where('role', 'EMPLOYEE')->get();
        $managers = User::where('role', 'MANAGER')->get();
        
        $taskQuery = MisTask::with(['employee:id,name,department_role', 'manager:id,name']);
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $taskQuery->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        
        $tasks = $taskQuery->get();

        $completedTasksCount = $tasks->whereIn('status', ['Completed', 'Approved by Manager', 'Closed'])->count();

        $statusCounts = [
            'Completed' => $completedTasksCount,
            'In Progress' => $tasks->where('status', 'In Progress')->count(),
            'Pending' => $tasks->where('status', 'Pending')->count(),
            'Blocked' => $tasks->where('status', 'Blocked')->count(),
        ];

        $deptData = $tasks->groupBy(function ($t) {
            return optional($t->employee)->department_role ?? 'Unassigned';
        })->map(function ($group) {
            $completed = $group->where('percentage', 100)->count();
            return $group->count() ? round(($completed / $group->count()) * 100) : 0;
        });

        $managerPerformance = $tasks->groupBy('manager_id')->map(function ($group) {
            $manager = $group->first()->manager;
            $completed = $group->whereIn('status', ['Completed', 'Approved by Manager', 'Closed'])->count();
            return [
                'id' => optional($manager)->id,
                'name' => optional($manager)->name ?? 'Unknown',
                'totalTasks' => $group->count(),
                'completedTasks' => $completed,
                'completionPercentage' => $group->count() ? round(($completed / $group->count()) * 100) : 0,
            ];
        })->values();

        $employeePerformance = $tasks->groupBy('employee_id')->map(function ($group) {
            $employee = $group->first()->employee;
            $completed = $group->whereIn('status', ['Completed', 'Approved by Manager', 'Closed'])->count();
            return [
                'id' => optional($employee)->id,
                'name' => optional($employee)->name ?? 'Unknown',
                'departmentRole' => optional($employee)->department_role,
                'totalTasks' => $group->count(),
                'completedTasks' => $completed,
                'completionPercentage' => $group->count() ? round(($completed / $group->count()) * 100) : 0,
            ];
        })->values();

        return response()->json([
            'totalManagers' => $managers->count(),
            'totalEmployees' => $employees->count(),
            'activeManagers' => $managers->where('is_active', true)->count(),
            'activeEmployees' => $employees->where('is_active', true)->count(),
            'inactiveManagers' => $managers->where('is_active', false)->count(),
            'inactiveEmployees' => $employees->where('is_active', false)->count(),
            'totalWorkforce' => $employees->count() + $managers->count(),
            'totalTasks' => $tasks->count(),
            'pendingTasks' => $statusCounts['Pending'],
            'inProgressTasks' => $statusCounts['In Progress'],
            'completedTasks' => $completedTasksCount,
            'avgTaskCompletion' => $tasks->count() ? round(($completedTasksCount / $tasks->count()) * 100) : 0,
            'todaysAttendance' => 95,
            'taskStatusCounts' => $statusCounts,
            'departmentPerformance' => $deptData,
            'managerPerformance' => $managerPerformance,
            'employeePerformance' => $employeePerformance,
        ]);
    }

    public function managerReports(\Illuminate\Http\Request $request)
    {
        $manager = $request->user();
        
        $employees = \App\Models\User::where('manager_id', $manager->id)
            ->where('role', 'EMPLOYEE')
            ->get();
            
        $tasks = \App\Models\MisTask::where('manager_id', $manager->id)->get();
        
        $totalEmployees = $employees->count();
        $totalTasks = $tasks->count();
        $pendingTasks = $tasks->where('status', 'Pending')->count();
        $inProgressTasks = $tasks->where('status', 'In Progress')->count();
        $completedTasks = $tasks->where('status', 'Completed')->count();
        
        $overallCompletionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        
        $employeeData = [];
        
        foreach ($employees as $employee) {
            $empTasks = $tasks->where('employee_id', $employee->id);
            
            $empTotalTasks = $empTasks->count();
            $empPendingTasks = $empTasks->where('status', 'Pending')->count();
            $empInProgressTasks = $empTasks->where('status', 'In Progress')->count();
            $empCompletedTasks = $empTasks->where('status', 'Completed')->count();
            
            $empCompletionPercentage = $empTotalTasks > 0 ? round(($empCompletedTasks / $empTotalTasks) * 100) : 0;
            
            $employeeData[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'departmentRole' => $employee->department_role,
                'totalTasks' => $empTotalTasks,
                'pendingTasks' => $empPendingTasks,
                'inProgressTasks' => $empInProgressTasks,
                'completedTasks' => $empCompletedTasks,
                'completionPercentage' => $empCompletionPercentage
            ];
        }
        
        // Also provide recent activity for the UI
        $recentTasks = $tasks->sortByDesc('updated_at')->take(5)->map(function($t) use ($employees) {
            $emp = $employees->where('id', $t->employee_id)->first();
            return [
                'id' => $t->id,
                'employeeName' => $emp ? $emp->name : 'Unknown',
                'taskActivity' => $t->task_activity,
                'status' => $t->status,
                'percentage' => $t->percentage
            ];
        })->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'totalEmployees' => $totalEmployees,
                    'totalTasks' => $totalTasks,
                    'pendingTasks' => $pendingTasks,
                    'inProgressTasks' => $inProgressTasks,
                    'completedTasks' => $completedTasks,
                    'completionPercentage' => $overallCompletionPercentage
                ],
                'employees' => $employeeData,
                'recentActivity' => $recentTasks
            ]
        ]);
    }
}