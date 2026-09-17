<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'HR Admin',
            'username' => 'hradmin',
            'email' => 'hr@company.com',
            'password' => Hash::make('hr@123'),
            'role' => 'hr',
        ]);

        User::create([
            'name' => 'Manager One',
            'username' => 'manager1',
            'email' => 'manager1@company.com',
            'password' => Hash::make('manager@123'),
            'role' => 'manager',
        ]);

        User::create([
            'name' => 'Ravi',
            'username' => 'ravi',
            'email' => 'ravi@company.com',
            'password' => Hash::make('ravi@123'),
            'role' => 'employee',
        ]);
    }
}