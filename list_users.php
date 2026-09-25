<?php
$users = \App\Models\User::all(['id', 'name', 'role']);
foreach($users as $u) {
    echo $u->id . ' | ' . $u->name . ' | ' . $u->role . "\n";
}
