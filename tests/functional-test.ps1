$ErrorActionPreference = "Stop"

$baseUrl = "http://localhost:8082"
$restUrl = "$baseUrl/wp-json/gymtrack/v1"

$testsOk = 0
$testsFail = 0

function Test-Http200 {
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

        if ($statusCode -eq 200) {
            Write-Host "  OK - HTTP 200"
            $script:testsOk++
        }
        else {
            Write-Host "  ERROR - Se esperaba HTTP 200 y se recibio HTTP $statusCode"
            $script:testsFail++
        }
    }
    catch {
        Write-Host "  ERROR - No se pudo acceder al recurso"
        $script:testsFail++
    }
}

function Test-DockerContainer {
    param(
        [string]$ContainerName
    )

    Write-Host ""
    Write-Host "TEST: Contenedor Docker $ContainerName"

    try {
        $status = docker inspect -f "{{.State.Status}}" $ContainerName 2>$null

        if ($status -eq "running") {
            Write-Host "  OK - Contenedor en ejecucion"
            $script:testsOk++
        }
        else {
            Write-Host "  ERROR - Estado del contenedor: $status"
            $script:testsFail++
        }
    }
    catch {
        Write-Host "  ERROR - No se pudo consultar el contenedor"
        $script:testsFail++
    }
}

Write-Host "========================================="
Write-Host " GymTrack - Regression Tests"
Write-Host "========================================="

Test-Http200 `
    -Name "WordPress responde" `
    -Url $baseUrl

Test-Http200 `
    -Name "Dashboard GymTrack responde" `
    -Url "$baseUrl/gymtrack/"

Test-Http200 `
    -Name "WordPress REST API responde" `
    -Url "$baseUrl/wp-json"

Test-Http200 `
    -Name "Namespace REST GymTrack responde" `
    -Url $restUrl

Test-DockerContainer `
    -ContainerName "gymtrack-wordpress"

Test-DockerContainer `
    -ContainerName "gymtrack-db"

Write-Host ""
Write-Host "========================================="
Write-Host " Resultado"
Write-Host "========================================="
Write-Host "Pruebas correctas: $testsOk"
Write-Host "Pruebas fallidas:  $testsFail"
Write-Host ""

if ($testsFail -eq 0) {
    Write-Host "TODAS LAS PRUEBAS DE REGRESION HAN PASADO"
    exit 0
}
else {
    Write-Host "HAY PRUEBAS DE REGRESION FALLIDAS"
    exit 1
}