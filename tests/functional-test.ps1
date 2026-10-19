$ErrorActionPreference = "Stop"

$baseUrl = "http://localhost:8082"
$restUrl = "$baseUrl/wp-json/gymtrack/v1"

$testsOk = 0
$testsFail = 0

function Test-Unauthorized {
    param(
        [string]$Name,
        [string]$Url
    )

    Write-Host ""
    Write-Host "TEST: $Name"

    try {
        $response = Invoke-WebRequest `
            -Uri $Url `
            -Method Get `
            -UseBasicParsing `
            -ErrorAction Stop

        $statusCode = [int]$response.StatusCode

        Write-Host "  ERROR - Se esperaba HTTP 401 y se recibio HTTP $statusCode"
        $script:testsFail++
    }
    catch {
        $statusCode = $null

        if ($_.Exception.Response) {
            try {
                $statusCode = [int]$_.Exception.Response.StatusCode
            }
            catch {
                $statusCode = $null
            }
        }

        if ($statusCode -eq 401) {
            Write-Host "  OK - Endpoint protegido (HTTP 401)"
            $script:testsOk++
        }
        else {
            Write-Host "  ERROR - Se esperaba HTTP 401 y se recibio HTTP $statusCode"
            $script:testsFail++
        }
    }
}

Write-Host "========================================="
Write-Host " GymTrack - Functional Tests"
Write-Host "========================================="

Test-Unauthorized `
    -Name "Endpoint exercises rechaza peticiones no autenticadas" `
    -Url "$restUrl/exercises"

Test-Unauthorized `
    -Name "Endpoint routines rechaza peticiones no autenticadas" `
    -Url "$restUrl/routines"

Test-Unauthorized `
    -Name "Endpoint workouts rechaza peticiones no autenticadas" `
    -Url "$restUrl/workouts"

Test-Unauthorized `
    -Name "Endpoint progress rechaza peticiones no autenticadas" `
    -Url "$restUrl/progress"

Write-Host ""
Write-Host "========================================="
Write-Host " Resultado"
Write-Host "========================================="
Write-Host "Pruebas correctas: $testsOk"
Write-Host "Pruebas fallidas:  $testsFail"
Write-Host ""

if ($testsFail -eq 0) {
    Write-Host "TODAS LAS PRUEBAS FUNCIONALES HAN PASADO"
    exit 0
}
else {
    Write-Host "HAY PRUEBAS FUNCIONALES FALLIDAS"
    exit 1
}