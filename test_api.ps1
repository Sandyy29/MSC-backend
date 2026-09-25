$loginBody = @{
    username = 'hr_admin'
    password = 'password'
} | ConvertTo-Json

$tokenResponse = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method Post -Body $loginBody -ContentType 'application/json' -Headers @{ Accept = 'application/json' }
$token = $tokenResponse.token

$headers = @{ Authorization = "Bearer $token"; Accept = 'application/json' }

Write-Host "GET Managers before:"
$managers = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users?role=MANAGER' -Method Get -Headers $headers
$managers | Select-Object id, username

# Run PHP to find the temp manager ID
$tempId = php artisan tinker --execute "echo App\Models\User::where('email', 'like', 'tempmgr_%')->first()->id;"
$tempId = $tempId.Trim()
Write-Host "Temp Manager ID is: $tempId"

Write-Host "DELETE Manager ${tempId}:"
try {
    $deleteResponse = Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/users/$tempId" -Method Delete -Headers $headers
    Write-Host $deleteResponse.StatusCode
    Write-Host $deleteResponse.Content
} catch {
    Write-Host $_.Exception.Response.StatusCode.value__
    Write-Host (new-object System.IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd()
}

Write-Host "GET Managers after:"
$managersAfter = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/users?role=MANAGER' -Method Get -Headers $headers
$managersAfter | Select-Object id, username

Write-Host "Check DB:"
php artisan tinker --execute "echo App\Models\User::where('id', $tempId)->exists() ? 'Exists' : 'Deleted from DB';"
