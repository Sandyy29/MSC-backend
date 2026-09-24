<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Manager Management", description: "APIs for Managers to manage employees")]
class ManagerEmployeeController extends Controller
{
    #[OA\Get(
        path: "/manager/employees",
        summary: "List all employees under the authenticated manager",
        security: [["bearerAuth" => []]],
        tags: ["Manager Management"],
        responses: [
            new OA\Response(response: 200, description: "List of employees"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $manager = $request->user();

        $employees = User::where('manager_id', $manager->id)
                         ->where('role', 'EMPLOYEE')
                         ->get(['id', 'name', 'username', 'email', 'role', 'department_role', 'branch', 'is_active', 'manager_id']);

        return response()->json($employees);
    }

    #[OA\Get(
        path: "/manager/employees/{id}",
        summary: "Get a specific employee's details",
        security: [["bearerAuth" => []]],
        tags: ["Manager Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Employee details"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized access to this employee"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function show(Request $request, $id)
    {
        $manager = $request->user();

        $employee = User::where('id', $id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if ($employee->role !== 'EMPLOYEE') {
            return response()->json(['message' => 'Not an employee'], 404);
        }

        if ($employee->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized access to this employee'], 403);
        }

        return response()->json($employee->only(['id', 'name', 'username', 'email', 'role', 'department_role', 'branch', 'is_active', 'manager_id']));
    }

    #[OA\Put(
        path: "/manager/employees/{id}",
        summary: "Update a specific employee's details",
        security: [["bearerAuth" => []]],
        tags: ["Manager Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "department_role", type: "string"),
                    new OA\Property(property: "branch", type: "string"),
                    new OA\Property(property: "is_active", type: "boolean")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Employee updated"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized access to this employee"),
            new OA\Response(response: 404, description: "Employee not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function update(Request $request, $id)
    {
        $manager = $request->user();

        $employee = User::where('id', $id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if ($employee->role !== 'EMPLOYEE') {
            return response()->json(['message' => 'Not an employee'], 404);
        }

        if ($employee->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized access to this employee'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $employee->id,
            'department_role' => 'nullable|string',
            'branch' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $employee->update($validated);

        return response()->json($employee->only(['id', 'name', 'username', 'email', 'role', 'department_role', 'branch', 'is_active', 'manager_id']));
    }

    #[OA\Delete(
        path: "/manager/employees/{id}",
        summary: "Deactivate an employee (soft delete)",
        security: [["bearerAuth" => []]],
        tags: ["Manager Management"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Employee deactivated"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Unauthorized access to this employee"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $manager = $request->user();

        $employee = User::where('id', $id)->first();

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if ($employee->role !== 'EMPLOYEE') {
            return response()->json(['message' => 'Not an employee'], 404);
        }

        if ($employee->manager_id !== $manager->id) {
            return response()->json(['message' => 'Unauthorized access to this employee'], 403);
        }

        // Soft deactivation
        $employee->is_active = false;
        $employee->save();

        return response()->json(['message' => 'Employee deactivated successfully']);
    }
}
