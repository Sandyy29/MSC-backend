<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// 1. Get HR user
$hr = User::where('role', 'HR')->first();
if (! $hr) {
    $hr = User::factory()->create(['role' => 'HR']);
}

// 2. Create token
$token = $hr->createToken('test')->plainTextToken;
echo "Token: $token\n";

// 3. Test /api/me
$request = Request::create('/api/me', 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $app->handle($request);
echo '/api/me Response: '.$response->getStatusCode()."\n";
echo $response->getContent()."\n\n";

// 4. Test /api/dashboard/hr
$request = Request::create('/api/dashboard/hr', 'GET');
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $app->handle($request);
echo '/api/dashboard/hr Response: '.$response->getStatusCode()."\n";
echo $response->getContent()."\n";
