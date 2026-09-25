<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'hr@msc.com'],
            [
                'username' => 'hr_ragul',
                'name' => 'Ragul HR',
                'password' => Hash::make('hr@123'),
                'role' => 'hr',
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@msc.com'],
            [
                'username' => 'manager1',
                'name' => 'Manager One',
                'password' => Hash::make('manager@123'),
                'role' => 'manager',
            ]
        );

        User::updateOrCreate(
            ['email' => 'employee@msc.com'],
            [
                'username' => 'employee1',
                'name' => 'Employee One',
                'password' => Hash::make('employee@123'),
                'role' => 'employee',
            ]
        );
    }
}