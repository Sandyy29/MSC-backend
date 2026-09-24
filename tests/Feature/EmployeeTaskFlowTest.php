<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MisTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class EmployeeTaskFlowTest extends TestCase
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

        $this->task1 = MisTask::create([
            'employee_id' => $this->employeeUser1->id,
            'manager_id' => $this->managerUser1->id,
            'title' => 'Task 1',
            'description' => 'Desc 1',
            'priority' => 'High',
            'due_date' => '2024-12-31',
            'day' => 'Monday',
            'date' => '2024-01-01',
            'task_activity' => 'Task 1',
            'status' => 'Pending',
            'progress' => 0
        ]);

        $this->task2 = MisTask::create([
            'employee_id' => $this->employeeUser2->id,
            'manager_id' => $this->managerUser2->id,
            'title' => 'Task 2',
            'description' => 'Desc 2',
            'priority' => 'Medium',
            'due_date' => '2024-12-31',
            'day' => 'Monday',
            'date' => '2024-01-01',
            'task_activity' => 'Task 2',
            'status' => 'In Progress',
            'progress' => 50
        ]);
    }

    // 1. Employee dashboard → 200
    public function test_employee_dashboard_returns_200()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->getJson('/api/employee/dashboard');
        $response->assertStatus(200);
        $response->assertJsonPath('data.statistics.total_tasks', 1);
    }

    // 2. Manager accessing Employee dashboard → 403
    public function test_manager_accessing_employee_dashboard_returns_403()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->getJson('/api/employee/dashboard');
        $response->assertStatus(403);
    }

    // 3. HR accessing Employee dashboard → 403
    public function test_hr_accessing_employee_dashboard_returns_403()
    {
        Sanctum::actingAs($this->hrUser);
        $response = $this->getJson('/api/employee/dashboard');
        $response->assertStatus(403);
    }

    // 4. Unauthenticated Employee dashboard → 401
    public function test_unauthenticated_employee_dashboard_returns_401()
    {
        $response = $this->getJson('/api/employee/dashboard');
        $response->assertStatus(401);
    }

    // 5. Employee gets own tasks → 200
    public function test_employee_gets_own_tasks()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->getJson('/api/employee/tasks');
        $response->assertStatus(200);
    }

    // 6. Employee receives only own tasks
    public function test_employee_receives_only_own_tasks()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->getJson('/api/employee/tasks');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $this->task1->id);
    }

    // 7. Employee updates own task status → 200
    public function test_employee_updates_own_task_status()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'status' => 'In Progress'
        ]);
        $response->assertStatus(200);
        $this->assertEquals('In Progress', $this->task1->fresh()->status);
    }

    // 8. Employee updates own task progress → 200
    public function test_employee_updates_own_task_progress()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'progress' => 30
        ]);
        $response->assertStatus(200);
        $this->assertEquals(30, $this->task1->fresh()->progress);
    }

    // 9. Employee updates status + progress together → 200
    public function test_employee_updates_status_and_progress_together()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'status' => 'Completed',
            'progress' => 100
        ]);
        $response->assertStatus(200);
        $task = $this->task1->fresh();
        $this->assertEquals('Completed', $task->status);
        $this->assertEquals(100, $task->progress);
    }

    // 10. Employee cannot update another employee's task → 403/404
    public function test_employee_cannot_update_another_employees_task()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task2->id, [
            'status' => 'Completed',
            'progress' => 100
        ]);
        $response->assertStatus(403);
    }

    // 11. Employee cannot change employee_id
    public function test_employee_cannot_change_employee_id()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'employee_id' => $this->employeeUser2->id
        ]);
        $response->assertStatus(200);
        $this->assertEquals($this->employeeUser1->id, $this->task1->fresh()->employee_id);
    }

    // 12. Employee cannot change manager_id
    public function test_employee_cannot_change_manager_id()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'manager_id' => $this->managerUser2->id
        ]);
        $response->assertStatus(200);
        $this->assertEquals($this->managerUser1->id, $this->task1->fresh()->manager_id);
    }

    // 13. Employee cannot create task → 403
    public function test_employee_cannot_create_task()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser1->id,
            'title' => 'New Task',
            'description' => 'Desc',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(403);
    }

    // 14. Manager creates task for own employee → 201
    public function test_manager_creates_task_for_own_employee()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser1->id,
            'title' => 'Task 3',
            'description' => 'Desc 3',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(201);
    }

    // 15. Manager cannot assign task to another manager's employee → 403
    public function test_manager_cannot_assign_task_to_another_managers_employee()
    {
        Sanctum::actingAs($this->managerUser1);
        $response = $this->postJson('/api/tasks', [
            'employee_id' => $this->employeeUser2->id,
            'title' => 'Task 4',
            'description' => 'Desc 4',
            'priority' => 'High',
            'due_date' => '2024-12-31',
        ]);
        $response->assertStatus(403);
    }

    // 16. Manager GET /api/tasks shows Employee's updated status
    public function test_manager_sees_employee_updated_status()
    {
        $this->task1->update(['status' => 'In Progress']);

        Sanctum::actingAs($this->managerUser1);
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);
        $response->assertJsonPath('0.status', 'In Progress');
    }

    // 17. Manager GET /api/tasks shows Employee's updated progress
    public function test_manager_sees_employee_updated_progress()
    {
        $this->task1->update(['progress' => 50]);

        Sanctum::actingAs($this->managerUser1);
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);
        $response->assertJsonPath('0.progress', 50);
    }

    // 18. Invalid progress below 0 → 422
    public function test_invalid_progress_below_zero_returns_422()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'progress' => -10
        ]);
        $response->assertStatus(422);
    }

    // 19. Invalid progress above 100 → 422
    public function test_invalid_progress_above_100_returns_422()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'progress' => 110
        ]);
        $response->assertStatus(422);
    }

    // 20. Invalid status → 422
    public function test_invalid_status_returns_422()
    {
        Sanctum::actingAs($this->employeeUser1);
        $response = $this->putJson('/api/tasks/' . $this->task1->id, [
            'status' => 'INVALID_STATUS'
        ]);
        $response->assertStatus(422);
    }
}
