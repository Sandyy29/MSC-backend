<?php

namespace Database\Factories;

use App\Models\MisTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MisTask>
 */
class MisTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => 1,
            'manager_id' => 1,
            'day' => 'Monday',
            'date' => now()->toDateString(),
            'task_activity' => $this->faker->sentence,
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'status' => 'Pending',
            'priority' => 'Medium',
            'due_date' => now()->addDays(7)->toDateString(),
            'progress' => 0,
        ];
    }
}
