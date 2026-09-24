<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ManagerEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_manager_can_list_own_employees()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id]);
        User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id]);
        
        $manager2 = User::factory()->create(['role' => 'MANAGER']);
        User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager2->id]);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/manager/employees');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_manager_cannot_see_other_manager_employees()
    {
        $manager1 = User::factory()->create(['role' => 'MANAGER']);
        $manager2 = User::factory()->create(['role' => 'MANAGER']);
        $employee2 = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager2->id]);

        $response = $this->actingAs($manager1, 'sanctum')->getJson('/api/manager/employees/' . $employee2->id);

        $response->assertStatus(403);
    }

    public function test_manager_can_view_own_employee()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/manager/employees/' . $employee->id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_manager_can_update_own_employee()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager, 'sanctum')->putJson('/api/manager/employees/' . $employee->id, [
            'name' => 'Updated Name',
            'department_role' => 'New Role'
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name', 'department_role' => 'New Role']);
    }

    public function test_manager_cannot_change_employee_role_or_manager_id()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id]);

        // Trying to update manager_id or role should be ignored by the controller since they aren't validated
        $response = $this->actingAs($manager, 'sanctum')->putJson('/api/manager/employees/' . $employee->id, [
            'manager_id' => 999,
            'role' => 'MANAGER'
        ]);

        $response->assertStatus(200);
        $this->assertEquals($manager->id, $employee->fresh()->manager_id);
        $this->assertEquals('EMPLOYEE', $employee->fresh()->role);
    }

    public function test_employee_cannot_access_manager_apis()
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $response = $this->actingAs($employee, 'sanctum')->getJson('/api/manager/employees');

        $response->assertStatus(403); // Assuming role middleware returns 403
    }
    
    public function test_hr_cannot_access_manager_apis()
    {
        $hr = User::factory()->create(['role' => 'HR']);

        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/manager/employees');

        $response->assertStatus(403);
    }
    
    public function test_unauthenticated_request()
    {
        $response = $this->getJson('/api/manager/employees');
        $response->assertStatus(401);
    }
    
    public function test_manager_can_deactivate_own_employee()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'manager_id' => $manager->id, 'is_active' => true]);

        $response = $this->actingAs($manager, 'sanctum')->deleteJson('/api/manager/employees/' . $employee->id);

        $response->assertStatus(200);
        $this->assertFalse((bool)$employee->fresh()->is_active);
    }
}
