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
        responses: [
            new OA\Response(response: 200, description: "Summary stats")
        ]
    )]
    public function hrSummary()
    {
        $employees = User::where('role', 'EMPLOYEE')->get();
        $managers = User::where('role', 'MANAGER')->get();
        $tasks = MisTask::with('employee:id,department_role')->get();

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

        return response()->json([
            'totalWorkforce' => $employees->count() + $managers->count(),
            'avgTaskCompletion' => $tasks->count() ? round(($completedTasksCount / $tasks->count()) * 100) : 0,
            'todaysAttendance' => 95,
            'taskStatusCounts' => $statusCounts,
            'departmentPerformance' => $deptData,
        ]);
    }
}