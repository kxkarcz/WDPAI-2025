# Testy integracyjne endpointów MindGarden (PowerShell)
# Uruchom: .\tests\integration_test.ps1 -BaseUrl "http://localhost:8080"

param(
    [string]$BaseUrl = "http://localhost:8080"
)

$Passed = 0
$Failed = 0

function Test-Endpoint {
    param(
        [string]$Method,
        [string]$Endpoint,
        [int]$ExpectedCode,
        [string]$Description
    )
    
    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl$Endpoint" -Method $Method -MaximumRedirection 0 -ErrorAction SilentlyContinue -SkipHttpErrorCheck
        $statusCode = $response.StatusCode
    }
    catch {
        if ($_.Exception.Response) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        } else {
            $statusCode = 0
        }
    }
    
    if ($statusCode -eq $ExpectedCode) {
        Write-Host "✓ PASS: $Description (HTTP $statusCode)" -ForegroundColor Green
        $script:Passed++
    } else {
        Write-Host "✗ FAIL: $Description (Expected $ExpectedCode, got $statusCode)" -ForegroundColor Red
        $script:Failed++
    }
}

Write-Host "=============================================="
Write-Host "Testy integracyjne MindGarden"
Write-Host "URL bazowy: $BaseUrl"
Write-Host "=============================================="
Write-Host ""

# Testy publicznych endpointów
Write-Host "--- Endpointy publiczne ---"
Test-Endpoint -Method "GET" -Endpoint "/" -ExpectedCode 200 -Description "Strona główna (login)"
Test-Endpoint -Method "GET" -Endpoint "/login" -ExpectedCode 200 -Description "Strona logowania"
Test-Endpoint -Method "GET" -Endpoint "/register" -ExpectedCode 200 -Description "Strona rejestracji"

# Testy błędów
Write-Host ""
Write-Host "--- Obsługa błędów ---"
Test-Endpoint -Method "GET" -Endpoint "/nieistniejaca-strona" -ExpectedCode 404 -Description "Strona 404"
Test-Endpoint -Method "GET" -Endpoint "/admin/dashboard" -ExpectedCode 302 -Description "Panel admina bez autoryzacji (redirect)"
Test-Endpoint -Method "GET" -Endpoint "/patient/dashboard" -ExpectedCode 302 -Description "Panel pacjenta bez autoryzacji (redirect)"
Test-Endpoint -Method "GET" -Endpoint "/psychologist/dashboard" -ExpectedCode 302 -Description "Panel psychologa bez autoryzacji (redirect)"

# Test zabezpieczeń
Write-Host ""
Write-Host "--- Zabezpieczenia ---"
Test-Endpoint -Method "POST" -Endpoint "/login" -ExpectedCode 403 -Description "Login bez CSRF tokena"
Test-Endpoint -Method "POST" -Endpoint "/register" -ExpectedCode 403 -Description "Rejestracja bez CSRF tokena"

# Test API bez autoryzacji
Write-Host ""
Write-Host "--- API endpoints ---"
Test-Endpoint -Method "GET" -Endpoint "/api/patient/moods" -ExpectedCode 302 -Description "API moods bez autoryzacji"
Test-Endpoint -Method "GET" -Endpoint "/api/chat/messages" -ExpectedCode 302 -Description "API chat bez autoryzacji"

Write-Host ""
Write-Host "=============================================="
Write-Host "Podsumowanie: $Passed przeszło, $Failed nie przeszło"
Write-Host "=============================================="

if ($Failed -gt 0) {
    exit 1
}
exit 0
