<?php
$mgr = App\Models\User::create(['name' => 'Temp Manager', 'username' => 'tempmgr_' . uniqid(), 'email' => 'tempmgr_' . uniqid() . '@company.com', 'role' => 'MANAGER', 'password' => bcrypt('password'), 'is_active' => true]);
echo $mgr->id . "\n";
