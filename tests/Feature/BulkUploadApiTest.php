<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkUploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_bulk()
    {
        $manager = User::factory()->create(['role' => 'MANAGER']);
        $csv = "employee_name,username,email,phone,role,department\n,johndoe1,not-an-email,,EMPLOYEE,IT";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->actingAs($manager)->postJson('/api/manager/employees/bulk/validate', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                '*' => ['row', 'errors'],
            ],
        ]);
    }
}
