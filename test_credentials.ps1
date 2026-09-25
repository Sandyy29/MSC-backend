$users = @(
    @('hr_ragul', 'hr@123'),
    @('manager1', 'manager@123'),
    @('employee1', 'employee@123')
)

foreach ($u in $users) {
    $username = $u[0]
    $password = $u[1]
    Write-Host "--- Testing $username ---"
    
    # 10. Verify DB
    $dbCheck = php artisan tinker --execute "echo App\Models\User::where('username', '$username')->exists() ? 'DB: EXISTS' : 'DB: NOT FOUND';"
    Write-Host $dbCheck.Trim()
    
    # 11. Verify Hash
    $hashCheck = php artisan tinker --execute "echo Illuminate\Support\Facades\Hash::check('$password', App\Models\User::where('username', '$username')->first()->password) ? 'HASH: OK' : 'HASH: FAILED';"
    Write-Host $hashCheck.Trim()
    
    # 12. Verify Login Endpoint
    $loginBody = @{
        username = $username
        password = $password
    } | ConvertTo-Json
    
    try {
        $loginResponse = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method Post -Body $loginBody -ContentType 'application/json' -Headers @{ Accept = 'application/json' }
        if ($loginResponse.token) {
            Write-Host "API: Login successful (HTTP 200)"
        } else {
            Write-Host "API: Login returned 200 but no token"
        }
    } catch {
        Write-Host "API: Login Failed - " $_.Exception.Message
        Write-Host $_.Exception.Response.StatusCode.value__
    }
}
