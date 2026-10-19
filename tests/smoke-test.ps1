$ErrorActionPreference = "Stop"

$baseUrl = "http://localhost:8082"

$testsPassed = 0
$testsFailed = 0

function Test-Url {
    param (
        [string]$Name,
        [string]$Url,
        [int]$ExpectedStatus = 200
    )

    Write-Host ""
    Write-Host "TEST: $Name"

    try {
        $response = Invoke-WebRequest `
            -Uri $Url `
            -Method GET `
            -UseBasicParsing `
            -TimeoutSec 10

        if ([int]$response.StatusCode -eq $ExpectedStatus) {
            Write-Host "  OK - HTTP $($response.StatusCode)" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - Esperado HTTP $ExpectedStatus, recibido HTTP $($response.StatusCode)" -ForegroundColor Red
            $script:testsFailed++
        }
    }
    catch {
        $statusCode = $null

        if ($null -ne $_.Exception.Response) {
            try {
                $statusCode = [int]$_.Exception.Response.StatusCode
            }
            catch {
                $statusCode = $null
            }
        }

        if ($null -ne $statusCode -and $statusCode -eq $ExpectedStatus) {
            Write-Host "  OK - HTTP $statusCode" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - $($_.Exception.Message)" -ForegroundColor Red

            if ($null -ne $statusCode) {
                Write-Host "  HTTP recibido: $statusCode" -ForegroundColor Red
            }

            $script:testsFailed++
        }
    }
}

function Test-RestNamespace {
    param (
        [string]$Name,
        [string]$Url,
        [string]$ExpectedNamespace
    )

    Write-Host ""
    Write-Host "TEST: $Name"

    try {
        $response = Invoke-WebRequest `
            -Uri $Url `
            -Method GET `
            -UseBasicParsing `
            -TimeoutSec 10

        $json = $response.Content | ConvertFrom-Json

        if ($null -ne $json.namespaces -and
            $json.namespaces -contains $ExpectedNamespace) {

            Write-Host "  OK - Namespace encontrado: $ExpectedNamespace" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - Namespace no encontrado: $ExpectedNamespace" -ForegroundColor Red
            $script:testsFailed++
        }
    }
    catch {
        Write-Host "  ERROR - $($_.Exception.Message)" -ForegroundColor Red
        $script:testsFailed++
    }
}

function Test-ProtectedEndpoint {
    param (
        [string]$Name,
        [string]$Url
    )

    Write-Host ""
    Write-Host "TEST: $Name"

    try {
        $response = Invoke-WebRequest `
            -Uri $Url `
            -Method GET `
            -UseBasicParsing `
            -TimeoutSec 10

        if ([int]$response.StatusCode -eq 401) {
            Write-Host "  OK - Endpoint responde sin acceso no autorizado" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - Se esperaba HTTP 401, recibido HTTP $($response.StatusCode)" -ForegroundColor Red
            $script:testsFailed++
        }
    }
    catch {
        $statusCode = $null

        if ($null -ne $_.Exception.Response) {
            try {
                $statusCode = [int]$_.Exception.Response.StatusCode
            }
            catch {
                $statusCode = $null
            }
        }

        if ($null -ne $statusCode -and $statusCode -eq 401) {
            Write-Host "  OK - Endpoint existe y esta protegido (HTTP 401)" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - Respuesta inesperada" -ForegroundColor Red

            if ($null -ne $statusCode) {
                Write-Host "  HTTP recibido: $statusCode" -ForegroundColor Red
            }
            else {
                Write-Host "  $($_.Exception.Message)" -ForegroundColor Red
            }

            $script:testsFailed++
        }
    }
}

function Test-DockerContainer {
    param (
        [string]$Name
    )

    Write-Host ""
    Write-Host "TEST: Contenedor Docker $Name"

    try {
        $status = docker inspect -f "{{.State.Status}}" $Name 2>$null

        if ($status -eq "running") {
            Write-Host "  OK - Contenedor en ejecucion" -ForegroundColor Green
            $script:testsPassed++
        }
        else {
            Write-Host "  ERROR - Estado: $status" -ForegroundColor Red
            $script:testsFailed++
        }
    }
    catch {
        Write-Host "  ERROR - $($_.Exception.Message)" -ForegroundColor Red
        $script:testsFailed++
    }
}

Write-Host "========================================="
Write-Host " GymTrack - Smoke Tests"
Write-Host "========================================="

# --------------------------------------------------
# WordPress
# --------------------------------------------------

Test-Url `
    -Name "WordPress responde" `
    -Url "$baseUrl/"

# --------------------------------------------------
# Frontend GymTrack
# --------------------------------------------------

Test-Url `
    -Name "Dashboard GymTrack responde" `
    -Url "$baseUrl/gymtrack/"

# --------------------------------------------------
# WordPress REST API
# --------------------------------------------------

Test-Url `
    -Name "WordPress REST API responde" `
    -Url "$baseUrl/wp-json/"

# --------------------------------------------------
# Namespace GymTrack
# --------------------------------------------------

Test-RestNamespace `
    -Name "REST API contiene namespace GymTrack" `
    -Url "$baseUrl/wp-json/" `
    -ExpectedNamespace "gymtrack/v1"

# --------------------------------------------------
# Endpoints protegidos
# --------------------------------------------------

Test-ProtectedEndpoint `
    -Name "Endpoint exercises existe y esta protegido" `
    -Url "$baseUrl/wp-json/gymtrack/v1/exercises"

Test-ProtectedEndpoint `
    -Name "Endpoint routines existe y esta protegido" `
    -Url "$baseUrl/wp-json/gymtrack/v1/routines"

Test-ProtectedEndpoint `
    -Name "Endpoint workouts existe y esta protegido" `
    -Url "$baseUrl/wp-json/gymtrack/v1/workouts"

Test-ProtectedEndpoint `
    -Name "Endpoint progress existe y esta protegido" `
    -Url "$baseUrl/wp-json/gymtrack/v1/progress"

# --------------------------------------------------
# Docker
# --------------------------------------------------

Test-DockerContainer `
    -Name "gymtrack-wordpress"

Test-DockerContainer `
    -Name "gymtrack-db"

# --------------------------------------------------
# Resultado
# --------------------------------------------------

Write-Host ""
Write-Host "========================================="
Write-Host " Resultado"
Write-Host "========================================="

Write-Host "Pruebas correctas: $testsPassed" -ForegroundColor Green
Write-Host "Pruebas fallidas:  $testsFailed" -ForegroundColor Red

if ($testsFailed -gt 0) {
    Write-Host ""
    Write-Host "TESTS FALLIDOS" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "TODAS LAS PRUEBAS HAN PASADO" -ForegroundColor Green
exit 0