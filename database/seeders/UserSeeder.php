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
            ['username' => 'hr_admin'],
            [
                'name' => 'HR Admin',
                'email' => 'hr@msc.com',
                'password' => Hash::make('hr@123'),
                'role' => 'hr',
            ]
        );

        User::updateOrCreate(
            ['username' => 'manager1'],
            [
                'name' => 'Manager One',
                'email' => 'manager@msc.com',
                'password' => Hash::make('manager@123'),
                'role' => 'manager',
            ]
        );

        User::updateOrCreate(
            ['username' => 'employee1'],
            [
                'name' => 'Employee One',
                'email' => 'employee@msc.com',
                'password' => Hash::make('employee@123'),
                'role' => 'employee',
            ]
        );
    }
}