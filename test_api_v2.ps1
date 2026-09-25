$id = php artisan tinker create_mgr.php
$id = $id.Trim()
Write-Host "Created Manager ID: ${id}"

$loginBody = @{
    username = 'hr_admin'
    password = 'password'
} | ConvertTo-Json

$tokenResponse = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method Post -Body $loginBody -ContentType 'application/json' -Headers @{ Accept = 'application/json' }
$token = $tokenResponse.token

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }

Write-Host "DELETE Manager ${id}:"
try {
    $deleteResponse = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/users/${id}" -Method Delete -Headers $headers
    Write-Host $deleteResponse.message
} catch {
    Write-Host $_.Exception.Response.StatusCode.value__
    Write-Host (new-object System.IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd()
}

Write-Host "Check DB:"
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class); \$kernel->bootstrap(); echo App\Models\User::where('id', ${id})->exists() ? 'Exists' : 'Deleted from DB';"
