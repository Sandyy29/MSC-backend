<?php

use App\Http\Controllers\Api\ManagerBulkEmployeeController;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$manager = User::factory()->create(['role' => 'MANAGER']);
$csv = "employee_name,username,email,phone,role,department\nJohn Doe,johndoe1,john@example.com,1234567890,EMPLOYEE,IT";

Storage::put('test.csv', $csv);
$file = new UploadedFile(Storage::path('test.csv'), 'test.csv', 'text/csv', null, true);

$request = Request::create('/api/manager/employees/bulk/validate', 'POST', [], [], ['file' => $file]);
$request->headers->set('Accept', 'application/json');
$request->setUserResolver(fn () => $manager);

$controller = app(ManagerBulkEmployeeController::class);
try {
    $response = $controller->validateBulk($request);
    echo $response->getContent();
} catch (ValidationException $e) {
    echo json_encode($e->errors());
}
