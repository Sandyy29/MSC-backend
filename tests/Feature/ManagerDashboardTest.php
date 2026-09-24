<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\MisTask;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_get_dashboard()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        
        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/manager/dashboard');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'employees' => ['total', 'active', 'inactive'],
                         'tasks' => ['total', 'pending', 'in_progress', 'completed']
                     ]
                 ]);
    }

    public function test_manager_gets_only_their_employee_count()
    {
        $manager1 = User::factory()->create(['role' => 'MANAGER']);
        $manager2 = User::factory()->create(['role' => 'MANAGER']);

        // M1 Employees: 2 active, 1 inactive -> total 3
        User::factory()->count(2)->create(['role' => 'EMPLOYEE', 'manager_id' => $manager1->id, 'is_active' => true]);
        User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager1->id, 'is_active' => false]);

        // M2 Employees: 4 active
        User::factory()->count(4)->create(['role' => 'EMPLOYEE', 'manager_id' => $manager2->id, 'is_active' => true]);

        $response = $this->actingAs($manager1, 'sanctum')->getJson('/api/manager/dashboard');

        $response->assertStatus(200);
        $data = $response->json('data.employees');

        $this->assertEquals(3, $data['total']);
        $this->assertEquals(2, $data['active']);
        $this->assertEquals(1, $data['inactive']);
    }

    public function test_manager_gets_only_their_task_count()
    {
        $manager1 = User::factory()->create(['role' => 'MANAGER']);
        $manager2 = User::factory()->create(['role' => 'MANAGER']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager1->id]);

        // M1 tasks
        MisTask::factory()->create(['manager_id' => $manager1->id, 'employee_id' => $employee->id, 'status' => 'Pending']);
        MisTask::factory()->create(['manager_id' => $manager1->id, 'employee_id' => $employee->id, 'status' => 'In Progress']);
        MisTask::factory()->create(['manager_id' => $manager1->id, 'employee_id' => $employee->id, 'status' => 'Completed']);
        MisTask::factory()->create(['manager_id' => $manager1->id, 'employee_id' => $employee->id, 'status' => 'Completed']);

        // M2 tasks
        MisTask::factory()->count(5)->create(['manager_id' => $manager2->id, 'employee_id' => $employee->id, 'status' => 'Pending']);

        $response = $this->actingAs($manager1, 'sanctum')->getJson('/api/manager/dashboard');
        
        $response->assertStatus(200);
        $data = $response->json('data.tasks');

        $this->assertEquals(4, $data['total']);
        $this->assertEquals(1, $data['pending']);
        $this->assertEquals(1, $data['in_progress']);
        $this->assertEquals(2, $data['completed']);
    }

    public function test_manager_2_cannot_see_manager_1_data()
    {
        $manager1 = User::factory()->create(['role' => 'MANAGER']);
        $manager2 = User::factory()->create(['role' => 'MANAGER']);

        User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager1->id]);
        
        $response = $this->actingAs($manager2, 'sanctum')->getJson('/api/manager/dashboard');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.employees.total'));
    }

    public function test_employee_gets_403()
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        $response = $this->actingAs($employee, 'sanctum')->getJson('/api/manager/dashboard');
        $response->assertStatus(403);
    }

    public function test_hr_gets_403()
    {
        $hr = User::factory()->create(['role' => 'HR']);
        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/manager/dashboard');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_gets_401()
    {
        $response = $this->getJson('/api/manager/dashboard');
        $response->assertStatus(401);
    }

    public function test_empty_manager_data_returns_zero_counts()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/manager/dashboard');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(0, $data['employees']['total']);
        $this->assertEquals(0, $data['employees']['active']);
        $this->assertEquals(0, $data['employees']['inactive']);
        $this->assertEquals(0, $data['tasks']['total']);
        $this->assertEquals(0, $data['tasks']['pending']);
        $this->assertEquals(0, $data['tasks']['in_progress']);
        $this->assertEquals(0, $data['tasks']['completed']);
    }
}
