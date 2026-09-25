<?php
use App\Models\User;

$hr = User::where('role', 'HR')->first();
$mgr = User::create([
    'name' => 'Temp Manager',
    'username' => 'tempmgr_' . uniqid(),
    'email' => 'tempmgr_' . uniqid() . '@company.com',
    'role' => 'MANAGER',
    'password' => bcrypt('password'),
    'is_active' => true
]);

echo "HR:" . $hr->email . ",MGR:" . $mgr->id . "\n";
