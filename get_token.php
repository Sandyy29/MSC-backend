<?php

use App\Models\User;

$hr = User::where('role', 'HR')->first();
if (! $hr) {
    echo "No HR found\n";
    exit;
}
$token = $hr->createToken('test')->plainTextToken;
echo 'Token: '.$token."\n";
$manager = User::where('role', 'MANAGER')->first();
echo 'Manager ID: '.$manager->id."\n";
echo 'Manager Role: '.$manager->role."\n";
