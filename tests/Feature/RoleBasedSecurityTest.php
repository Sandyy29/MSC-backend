<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MisTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class RoleBasedSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hrUser = User::factory()->create([
            'role' => 'HR',
            'password' => bcrypt('password'),
        ]);

        $this->managerUser1 = User::factory()->create([
            'role' => 'MANAGER',
            'password' => bcrypt('password'),
        ]);

        $this->managerUser2 = User::factory()->create([
            'role' => 'MANAGER',
            'password' => bcrypt('password'),
        ]);

        $this->employeeUser1 = User::factory()->create([
            'role' => 'EMPLOYEE',
            'manager_id' => $this->managerUser1->id,
            'password' => bcrypt('password'),
        ]);

        $this->employeeUser2 = User::factory()->create([
            'role' => 'EMPLOYEE',
            'manager_id' => $this->managerUser2->id,
            'password' => bcrypt('password'),
        ]);
    }

    // 1. Unauthenticated HR dashboard → 401
    public function test_unauthenticated_hr_dashboard_returns_401()
    {
        $response = $this->getJson('/api/dashboard/hr');
        $response->assertStatus(401);
    }

    // 2. HR dashboard with HR → 200
    public function test_hr_dashboard_with_hr_returns_200()
    {
        Sanctum::actingAs($this->hrUser);
        $response = $this->getJson('/api/dashboard/hr');
        $response->assertStatus(200);
    }

    // 3. Manager dashboard with HR → 403
    public function test_manager_dashboard_with_hr_returns_403()
    {
        Sanctum::actingAs($this->hrUser);
        $response = $this->getJson('/api/manager/dashboard');
        $response->assertStatus(403);
    }

    // 4. HR creates Manager → 201
    public function test_hr_creates_manager_returns_201()
    {
        Sanctum::actingAs($this->hrUser);
        $response = $this->postJson('/api/users', [
            'name' => 'New Manager',
            'username' => 'newmanager',
            'email' => 'newmanager@test.com',
            'role' => 'MANAGER',
        ]);
        $response->assertStatus(201);
    }

    // 5. HR creates Employee → 403
    public function test_hr_creates_employee_returns_403()
    {
        Sanctum::actingAs($this->hrUser);
        $response = $this->postJson('/api/users', [
            'name' => 'New Employee',
            'username' => 'newemployee',
            'email' => 'newemployee@test.com',
            'role' => 'EMPLOYEE',
        ]);
        $response->assertStatus(403);
    }

    // 6. Manager creates Employee → 201
    public function test_manager_creates_employee_returns_201()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/users', [
            'name' => 'New Employee 2',
            'username' => 'newemployee2',
            'email' => 'newemployee2@test.com',
            'role' => 'EMPLOYEE',
        ]);
        $response->assertStatus(201);
    }

    // 7. Manager creates Manager → 403
    public function test_manager_creates_manager_returns_403()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/users', [
            'name' => 'New Manager 2',
            'username' => 'newmanager2',
            'email' => 'newmanager2@test.com',
            'role' => 'MANAGER',
        ]);
        $response->assertStatus(403);
    }

    // 8. Employee creates user → 403
    public function test_employee_creates_user_returns_403()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->postJson('/api/users', [
            'name' => 'Should Fail',
            'username' => 'shouldfail',
            'email' => 'fail@test.com',
            'role' => 'EMPLOYEE',
        ]);
        $response->assertStatus(403);
    }

    // 9. Manager sees own employees
    public function test_manager_sees_own_employees()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->getJson('/api/manager/employees');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $this->employeeUser1->id);
    }

    // 10. Manager cannot see another manager's employee
    public function test_manager_cannot_see_another_managers_employee()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->getJson('/api/manager/employees/' . $this->employeeUser2->id);
        $response->assertStatus(403);
    }

    // 11. Manager assigns task to own employee → 201
    public function test_manager_assigns_task_to_own_employee()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser1->id,
            'title' => 'Task 1',
            'description' => 'Desc 1',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(201);
    }

    // 12. Manager cannot assign task to another manager's employee → 403
    public function test_manager_cannot_assign_task_to_another_managers_employee()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser2->id,
            'title' => 'Task 1',
            'description' => 'Desc 1',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(403);
    }

    // 13. Employee sees own tasks
    public function test_employee_sees_own_tasks()
    {
        $task = MisTask::create([
            'employee_id' => $this->employeeUser1->id,
            'manager_id' => $this->managerUser1->id,
            'title' => 'Task 1',
            'description' => 'Desc 1',
            'priority' => 'High',
            'due_date' => '2024-12-31',
            'day' => 'Monday',
            'date' => '2024-01-01',
            'task_activity' => 'Task 1',
        ]);

        Sanctum::actingAs($this->employeeUser1);
        $response = $this->getJson('/api/employee/tasks');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $task->id);
    }

    // 14. Employee cannot access another employee's task
    public function test_employee_cannot_access_another_employees_task()
    {
        $task2 = MisTask::create([
            'employee_id' => $this->employeeUser2->id,
            'manager_id' => $this->managerUser2->id,
            'title' => 'Task 2',
            'description' => 'Desc 2',
            'priority' => 'High',
            'due_date' => '2024-12-31',
            'day' => 'Monday',
            'date' => '2024-01-01',
            'task_activity' => 'Task 2',
        ]);

        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $task2->id, [
            'status' => 'Completed',
        ]);
        $response->assertStatus(403);
    }

    // 15. Employee cannot create task → 403
    public function test_employee_cannot_create_task()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser1->id,
            'title' => 'Task 1',
            'description' => 'Desc 1',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(403);
    }

    // 16. Employee cannot access manager employee APIs → 403
    public function test_employee_cannot_access_manager_employee_apis()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->getJson('/api/manager/employees');
        $response->assertStatus(403);
    }

    // 17. Missing token → 401
    public function test_missing_token_returns_401()
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);
    }

    // 18. Invalid token → 401
    public function test_invalid_token_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalidtoken123',
        ])->getJson('/api/me');
        $response->assertStatus(401);
    }
}
