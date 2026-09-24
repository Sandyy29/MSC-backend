<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\MisTask;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manager1 = User::factory()->create(['role' => 'MANAGER']);
        $this->manager2 = User::factory()->create(['role' => 'MANAGER']);
        
        $this->employee1 = User::factory()->create([
            'role' => 'EMPLOYEE',
            'manager_id' => $this->manager1->id,
        ]);
        
        $this->employee2 = User::factory()->create([
            'role' => 'EMPLOYEE',
            'manager_id' => $this->manager2->id,
        ]);
    }

    public function test_manager1_creates_task_for_manager1_employee_returns_201()
    {
        $response = $this->actingAs($this->manager1)->postJson('/api/tasks', [
            'employee_id' => $this->employee1->id,
            'title' => 'Test Task',
            'description' => 'Test task description',
            'priority' => 'High',
            'due_date' => '2026-09-30'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mis_tasks', [
            'manager_id' => $this->manager1->id,
            'employee_id' => $this->employee1->id,
            'title' => 'Test Task'
        ]);
    }

    public function test_manager1_creates_task_for_manager2_employee_returns_403()
    {
        $response = $this->actingAs($this->manager1)->postJson('/api/tasks', [
            'employee_id' => $this->employee2->id,
            'title' => 'Test Task',
            'description' => 'Test task description',
            'priority' => 'High',
            'due_date' => '2026-09-30'
        ]);

        $response->assertStatus(403);
    }

    public function test_manager1_get_tasks_returns_only_manager1_tasks()
    {
        MisTask::factory()->create(['manager_id' => $this->manager1->id, 'employee_id' => $this->employee1->id]);
        MisTask::factory()->create(['manager_id' => $this->manager2->id, 'employee_id' => $this->employee2->id]);

        $response = $this->actingAs($this->manager1)->getJson('/api/tasks');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.managerName', $this->manager1->name);
    }

    public function test_employee1_get_tasks_returns_only_employee1_tasks()
    {
        MisTask::factory()->create(['manager_id' => $this->manager1->id, 'employee_id' => $this->employee1->id]);
        MisTask::factory()->create(['manager_id' => $this->manager2->id, 'employee_id' => $this->employee2->id]);

        $response = $this->actingAs($this->employee1)->getJson('/api/employee/tasks');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.employee_id', $this->employee1->id);
    }

    public function test_employee1_updates_own_task_returns_200()
    {
        $task = MisTask::factory()->create(['manager_id' => $this->manager1->id, 'employee_id' => $this->employee1->id]);

        $response = $this->actingAs($this->employee1)->putJson("/api/tasks/{$task->id}", [
            'status' => 'In Progress',
            'progress' => 50
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mis_tasks', [
            'id' => $task->id,
            'status' => 'In Progress',
            'progress' => 50
        ]);
    }

    public function test_employee1_updates_another_employees_task_returns_403()
    {
        $task = MisTask::factory()->create(['manager_id' => $this->manager2->id, 'employee_id' => $this->employee2->id]);

        $response = $this->actingAs($this->employee1)->putJson("/api/tasks/{$task->id}", [
            'status' => 'In Progress',
            'progress' => 50
        ]);

        $response->assertStatus(403);
    }

    public function test_employee_sets_progress_150_returns_422()
    {
        $task = MisTask::factory()->create(['manager_id' => $this->manager1->id, 'employee_id' => $this->employee1->id]);

        $response = $this->actingAs($this->employee1)->putJson("/api/tasks/{$task->id}", [
            'progress' => 150
        ]);

        $response->assertStatus(422);
    }

    public function test_employee_attempts_to_update_task_title()
    {
        $task = MisTask::factory()->create([
            'manager_id' => $this->manager1->id, 
            'employee_id' => $this->employee1->id,
            'title' => 'Original Title'
        ]);

        $response = $this->actingAs($this->employee1)->putJson("/api/tasks/{$task->id}", [
            'title' => 'Hacked Title'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mis_tasks', [
            'id' => $task->id,
            'title' => 'Original Title'
        ]);
    }
}
