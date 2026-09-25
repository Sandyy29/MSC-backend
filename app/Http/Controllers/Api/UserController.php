<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "User Management", description: "Endpoints for listing and creating users")]
class UserController extends Controller
{
    #[OA\Get(
        path: "/users",
        summary: "List users (employees/managers) with filters",
        description: "Accessible according to existing backend authorization.",
        security: [["bearerAuth" => []]],
        tags: ["User Management"],
        parameters: [
            new OA\Parameter(name: "role", in: "query", schema: new OA\Schema(type: "string", enum: ["HR","MANAGER","EMPLOYEE"])),
            new OA\Parameter(name: "departmentRole", in: "query", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "branch", in: "query", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "List of users")
        ]
    )]
    public function index(Request $request)
    {
        $query = User::query();

        $user = $request->user();
        
        if (strtoupper($user->role) === 'EMPLOYEE') {
            $query->where('id', $user->id);
        } elseif (strtoupper($user->role) === 'MANAGER') {
            $query->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhere('id', $user->id);
            });
        }
        // HR can see all

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('departmentRole')) {
            $query->where('department_role', $request->departmentRole);
        }

        if ($request->has('branch')) {
            $query->where('branch', $request->branch);
        }

        $users = $query->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'role' => $u->role,
                'departmentRole' => $u->department_role,
                'managerId' => $u->manager_id,
                'managerName' => optional(User::find($u->manager_id))->name,
                'branch' => $u->branch,
                'isActive' => $u->is_active,
                'teamSize' => User::where('manager_id', $u->id)->count(),
            ];
        });

        return response()->json($users);
    }

    #[OA\Post(
        path: "/users",
        summary: "Create a new Manager or Employee",
        description: "- HR can create MANAGER users.\n- Manager can create EMPLOYEE users.\n- Employee cannot create users.",
        security: [["bearerAuth" => []]],
        tags: ["User Management"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name","username","email","role"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Karthik Manager"),
                    new OA\Property(property: "username", type: "string", example: "karthik"),
                    new OA\Property(property: "email", type: "string", example: "karthik@company.com"),
                    new OA\Property(property: "role", type: "string", enum: ["MANAGER","EMPLOYEE"]),
                    new OA\Property(property: "departmentRole", type: "string", example: "Development"),
                    new OA\Property(property: "managerId", type: "integer", example: 1),
                    new OA\Property(property: "branch", type: "string", example: "Madurai")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "User created"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();

        // Enforce role-based creation rules
        if (strtoupper($user->role) === 'HR' && strtoupper($request->role) !== 'MANAGER') {
            return response()->json(['message' => 'HR can only create Managers.'], 403);
        }
        
        if (strtoupper($user->role) === 'MANAGER' && strtoupper($request->role) !== 'EMPLOYEE') {
            return response()->json(['message' => 'Managers can only create Employees.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:MANAGER,EMPLOYEE',
            'departmentRole' => 'nullable|string',
            'branch' => 'nullable|string',
            'password' => 'required_if:role,MANAGER|string|min:6',
        ]);

        // Automatically assign manager_id if created by a Manager
        $managerId = null;
        if (strtoupper($user->role) === 'MANAGER') {
            $managerId = $user->id;
        }

        $newUser = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'department_role' => $validated['departmentRole'] ?? null,
            'manager_id' => $managerId,
            'branch' => $validated['branch'] ?? null,
            'is_active' => true,
            'password' => $request->has('password') ? Hash::make($validated['password']) : Hash::make(Str::random(12)),
        ]);

        return response()->json([
            'message' => ucfirst(strtolower($validated['role'])) . ' created successfully',
            'data' => $newUser,
        ], 201);
    }

    #[OA\Put(
        path: "/users/{id}/toggle-status",
        summary: "Activate or deactivate a user",
        security: [["bearerAuth" => []]],
        tags: ["HR Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Status toggled"),
            new OA\Response(response: 404, description: "User not found")
        ]
    )]
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $user,
        ]);
    }

    #[OA\Delete(
        path: "/users/{id}",
        summary: "Delete a manager",
        security: [["bearerAuth" => []]],
        tags: ["HR Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Manager deleted successfully"),
            new OA\Response(response: 400, description: "Cannot delete manager due to existing relationships"),
            new OA\Response(response: 403, description: "Cannot delete this user"),
            new OA\Response(response: 404, description: "User not found")
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $user = User::where('id', $id)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (strtoupper($user->role) !== 'MANAGER') {
            return response()->json(['message' => 'Cannot delete non-manager users'], 403);
        }

        // Check for related employees (team members)
        if ($user->teamMembers()->exists()) {
            return response()->json([
                'message' => 'Cannot delete manager because they have employees assigned to them.'
            ], 400);
        }

        // Check for related tasks created by this manager
        $hasTasks = \App\Models\MisTask::where('manager_id', $user->id)->exists();
        if ($hasTasks) {
            return response()->json([
                'message' => 'Cannot delete manager because they have associated tasks.'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'Manager deleted successfully'
        ], 200);
    }
}
