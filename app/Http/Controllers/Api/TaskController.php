<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MisTask;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Task Management", description: "Organization-wide task monitoring")]
class TaskController extends Controller
{
    #[OA\Get(
        path: "/tasks",
        summary: "List all tasks",
        description: "HR/Manager can view tasks according to backend authorization/filtering.",
        security: [["bearerAuth" => []]],
        tags: ["Task Management"],
        parameters: [
            new OA\Parameter(name: "branch", in: "query", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "List of tasks")
        ]
    )]
    public function index(Request $request)
    {
        $query = MisTask::with(['employee:id,name,department_role,branch', 'manager:id,name']);

        $user = $request->user();
        
        if ($user->role === 'MANAGER') {
            $query->where('manager_id', $user->id);
        } elseif ($user->role === 'EMPLOYEE') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->has('branch')) {
            $branch = $request->branch;
            $query->whereHas('employee', function ($q) use ($branch) {
                $q->where('branch', $branch);
            });
        }

        $tasks = $query->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'date' => $t->date,
                'day' => $t->day,
                'due_date' => $t->due_date,
                'taskActivity' => $t->task_activity,
                'category' => $t->category,
                'priority' => $t->priority,
                'employeeName' => optional($t->employee)->name,
                'departmentRole' => optional($t->employee)->department_role,
                'branch' => optional($t->employee)->branch,
                'managerName' => optional($t->manager)->name,
                'status' => $t->status,
                'target' => $t->target,
                'actualOutput' => $t->actual_output,
                'percentage' => $t->percentage,
                'progress' => $t->progress,
                'timeSpent' => $t->time_spent,
            ];
        });

        return response()->json($tasks);
    }

    #[OA\Post(
        path: "/tasks",
        summary: "Create a new task",
        description: "Manager creates and assigns a task to an employee.",
        security: [["bearerAuth" => []]],
        tags: ["Task Management"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["employee_id", "title", "description", "priority", "due_date"],
                properties: [
                    new OA\Property(property: "employee_id", type: "integer", example: 2),
                    new OA\Property(property: "title", type: "string", example: "Develop login feature"),
                    new OA\Property(property: "description", type: "string", example: "Implement JWT auth"),
                    new OA\Property(property: "priority", type: "string", enum: ["Low", "Medium", "High"]),
                    new OA\Property(property: "due_date", type: "string", format: "date")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Task created"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'description' => 'required|string',
            'priority' => 'required|in:Low,Medium,High',
            'due_date' => 'required|date'
        ]);

        $manager = $request->user();
        $employee = \App\Models\User::find($validated['employee_id']);

        if ($employee->role !== 'EMPLOYEE' || $employee->manager_id !== $manager->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task = MisTask::create([
            'employee_id' => $employee->id,
            'manager_id' => $manager->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'due_date' => $validated['due_date'],
            // Add default required fields
            'day' => now()->format('l'),
            'date' => now()->toDateString(),
            'task_activity' => $validated['title'],
        ]);

        return response()->json($task, 201);
    }

    #[OA\Get(
        path: "/employee/tasks",
        summary: "Get tasks assigned to the authenticated employee",
        security: [["bearerAuth" => []]],
        tags: ["Task Management"],
        responses: [
            new OA\Response(response: 200, description: "List of tasks"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function employeeTasks(Request $request)
    {
        $employee = $request->user();

        $tasks = MisTask::with(['manager:id,name'])
            ->where('employee_id', $employee->id)
            ->get();

        return response()->json($tasks);
    }

    #[OA\Put(
        path: "/tasks/{id}",
        summary: "Update task progress and status",
        description: "Employee updates ONLY their assigned task's status and progress.",
        security: [["bearerAuth" => []]],
        tags: ["Task Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["Pending", "In Progress", "Completed"]),
                    new OA\Property(property: "progress", type: "integer", minimum: 0, maximum: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Task updated"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $employee = $request->user();
        $task = MisTask::find($id);

        if (!$task) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($task->employee_id !== $employee->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:Pending,In Progress,Completed',
            'progress' => 'sometimes|integer|min:0|max:100',
        ]);

        // Map progress to percentage if frontend expects it or keep progress
        if (isset($validated['progress'])) {
            $task->progress = $validated['progress'];
            $task->percentage = $validated['progress']; // for backwards compatibility
        }

        if (isset($validated['status'])) {
            $task->status = $validated['status'];
        }

        $task->save();

        return response()->json($task);
    }
}