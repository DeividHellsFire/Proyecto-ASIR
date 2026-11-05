$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:GYMTRACK_BASE_URL) {
    $env:GYMTRACK_BASE_URL.TrimEnd("/")
} else {
    "http://localhost:8082"
}

$Namespace = "gymtrack/v1"
$script:Passed = 0
$script:Failed = 0

function Get-Status($err) {
    try {
        if ($null -ne $err.Exception.Response) {
            return [int]$err.Exception.Response.StatusCode
        }
    } catch {}

    # Compatibilidad adicional con respuestas HTTP de PowerShell 7.
    try {
        if ($null -ne $err.Exception.Response.StatusCode) {
            return [int]$err.Exception.Response.StatusCode
        }
    } catch {}

    return $null
}

# curl.exe permite leer de forma consistente el código HTTP y la cabecera
# Location tanto en Windows PowerShell 5.1 como en PowerShell 7.
function Get-CurlResponse($url) {
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if (-not $curl) {
        throw "No se encuentra curl.exe. Es necesario para comprobar las redirecciones."
    }

    $output = & curl.exe -sS -D - -o NUL --max-redirs 0 -w "`n__HTTP_STATUS__:%{http_code}" $url 2>&1
    $curlExitCode = $LASTEXITCODE
    $raw = ($output | Out-String)

    if ($curlExitCode -ne 0) {
        throw "curl.exe terminó con código $curlExitCode. Respuesta: $raw"
    }

    $status = $null
    if ($raw -match '(?m)__HTTP_STATUS__:(\d{3})') {
        $status = [int]$Matches[1]
    }

    $location = $null
    if ($raw -match '(?im)^Location:\s*(.+?)\s*$') {
        $location = $Matches[1].Trim()
    }

    return [PSCustomObject]@{
        StatusCode = $status
        Location   = $location
        Raw        = $raw
    }
}

function Test-Http($name, $url, [int[]]$expected = @(200)) {
    Write-Host "`nTEST: $name"
    try {
        $response = Get-CurlResponse $url
        $status = $response.StatusCode

        if ($null -ne $status -and $expected -contains $status) {
            Write-Host "  OK - HTTP $status"
            $script:Passed++
            return
        }

        Write-Host "  ERROR - HTTP recibido: $status. Esperado: $($expected -join ', ')"
        $script:Failed++
    } catch {
        Write-Host "  ERROR - $($_.Exception.Message)"
        $script:Failed++
    }
}

function Test-PrivateRedirect($name, $url) {
    Write-Host "`nTEST: $name"
    try {
        $response = Get-CurlResponse $url
        $status = $response.StatusCode
        $location = $response.Location

        if ($status -eq 302 -and $location -match '/wp-login\.php') {
            Write-Host "  OK - HTTP 302 hacia login"
            $script:Passed++
        } else {
            Write-Host "  ERROR - HTTP recibido: $status"
            if ($location) {
                Write-Host "  Location: $location"
            }
            $script:Failed++
        }
    } catch {
        Write-Host "  ERROR - $($_.Exception.Message)"
        $script:Failed++
    }
}

Write-Host "========================================="
Write-Host " GymTrack - Smoke Tests"
Write-Host " Base URL: $BaseUrl"
Write-Host "========================================="

Test-Http "WordPress responde" "$BaseUrl/wp-login.php" @(200)
Test-PrivateRedirect "Dashboard GymTrack redirige al login" "$BaseUrl/gymtrack/"
Test-PrivateRedirect "Dashboard WordPress redirige al login" "$BaseUrl/wp-admin/"

$assets = @(
    "gymtrack-home.jpg",
    "gymtrack-registra.jpg",
    "gymtrack-analiza.jpg",
    "gymtrack-superate.jpg",
    "gymtrack-buscar.jpg"
)

foreach ($asset in $assets) {
    Test-Http "Imagen $asset" "$BaseUrl/wp-content/plugins/gymtrack/assets/$asset" @(200)
}

Write-Host "`nTEST: WordPress REST API responde"
$rest = $null
try {
    $rest = Invoke-RestMethod -Uri "$BaseUrl/wp-json/" -Method Get -ErrorAction Stop
    Write-Host "  OK - HTTP 200"
    $script:Passed++
} catch {
    $status = Get-Status $_
    if ($null -ne $status) {
        Write-Host "  ERROR - HTTP $status"
    } else {
        Write-Host "  ERROR - $($_.Exception.Message)"
    }
    $script:Failed++
}

if ($null -ne $rest) {
    Write-Host "`nTEST: REST API contiene namespace $Namespace"
    if (@($rest.namespaces) -contains $Namespace) {
        Write-Host "  OK - Namespace encontrado"
        $script:Passed++
    } else {
        Write-Host "  ERROR - Namespace no encontrado"
        $script:Failed++
    }
}

foreach ($endpoint in @("exercises", "routines", "workouts", "progress")) {
    Write-Host "`nTEST: Endpoint $endpoint existe y está protegido"
    try {
        Invoke-RestMethod -Uri "$BaseUrl/wp-json/$Namespace/$endpoint" -Method Get -ErrorAction Stop | Out-Null
        Write-Host "  ERROR - Acceso anónimo permitido"
        $script:Failed++
    } catch {
        $status = Get-Status $_
        if ($status -in @(401, 403)) {
            Write-Host "  OK - HTTP $status"
            $script:Passed++
        } else {
            if ($null -eq $status) {
                Write-Host "  ERROR - No se pudo determinar el HTTP: $($_.Exception.Message)"
            } else {
                Write-Host "  ERROR - HTTP recibido: $status"
            }
            $script:Failed++
        }
    }
}

if (Get-Command docker -ErrorAction SilentlyContinue) {
    foreach ($name in @("gymtrack-wordpress", "gymtrack-db")) {
        Write-Host "`nTEST: Contenedor Docker $name"
        $state = docker inspect -f '{{.State.Running}}' $name 2>$null
        if ($LASTEXITCODE -eq 0 -and $state -eq "true") {
            Write-Host "  OK - En ejecución"
            $script:Passed++
        } else {
            Write-Host "  ERROR - No encontrado o detenido"
            $script:Failed++
        }
    }
}

Write-Host "`n========================================="
Write-Host " Resultado"
Write-Host "========================================="
Write-Host "Pruebas correctas: $script:Passed"
Write-Host "Pruebas fallidas:  $script:Failed"

if ($script:Failed -gt 0) {
    Write-Host "`nSMOKE TESTS FAILED"
    exit 1
}

Write-Host "`nSMOKE TESTS OK"
exit 0
