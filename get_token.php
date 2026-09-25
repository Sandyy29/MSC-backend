<?php
$hr = \App\Models\User::where('role', 'HR')->first();
if (!$hr) {
    echo "No HR found\n"; exit;
}
$token = $hr->createToken('test')->plainTextToken;
echo "Token: " . $token . "\n";
$manager = \App\Models\User::where('role', 'MANAGER')->first();
echo "Manager ID: " . $manager->id . "\n";
echo "Manager Role: " . $manager->role . "\n";
