$ErrorActionPreference = "Stop"

$BaseUrl = if ($env:GYMTRACK_BASE_URL) {
    $env:GYMTRACK_BASE_URL.TrimEnd("/")
} else {
    "http://localhost:8082"
}

$Namespace = "gymtrack/v1"
$script:Passed = 0
$script:Failed = 0

# HttpClient funciona tanto en Windows PowerShell 5.1 como en PowerShell 7,
# y no depende de curl.exe (que no está disponible en todos los runners).
Add-Type -AssemblyName System.Net.Http

$script:HttpHandler = [System.Net.Http.HttpClientHandler]::new()
$script:HttpHandler.AllowAutoRedirect = $false
$script:HttpClient = [System.Net.Http.HttpClient]::new($script:HttpHandler)
$script:HttpClient.Timeout = [TimeSpan]::FromSeconds(30)

function Get-HttpResponse($url) {
    $response = $script:HttpClient.GetAsync($url).GetAwaiter().GetResult()

    $location = $null
    if ($null -ne $response.Headers.Location) {
        $location = $response.Headers.Location.ToString()
        if ($response.Headers.Location.IsAbsoluteUri -eq $false) {
            $location = [System.Uri]::new([System.Uri]$url, $response.Headers.Location).ToString()
        }
    }

    $result = [PSCustomObject]@{
        StatusCode = [int]$response.StatusCode
        Location   = $location
    }

    $response.Dispose()
    return $result
}

function Test-Http($name, $url, [int[]]$expected = @(200)) {
    Write-Host "`nTEST: $name"
    try {
        $response = Get-HttpResponse $url
        if ($expected -contains $response.StatusCode) {
            Write-Host "  OK - HTTP $($response.StatusCode)"
            $script:Passed++
        } else {
            Write-Host "  ERROR - HTTP recibido: $($response.StatusCode). Esperado: $($expected -join ', ')"
            $script:Failed++
        }
    } catch {
        Write-Host "  ERROR - $($_.Exception.Message)"
        $script:Failed++
    }
}

function Test-PrivateRedirect($name, $url) {
    Write-Host "`nTEST: $name"
    try {
        $response = Get-HttpResponse $url
        if ($response.StatusCode -eq 302 -and $response.Location -match '/wp-login\.php') {
            Write-Host "  OK - HTTP 302 hacia login"
            $script:Passed++
        } else {
            Write-Host "  ERROR - HTTP recibido: $($response.StatusCode)"
            if ($response.Location) {
                Write-Host "  Location: $($response.Location)"
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

try {
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
        $restResponse = Invoke-RestMethod -Uri "$BaseUrl/wp-json/" -Method Get -ErrorAction Stop
        $rest = $restResponse
        Write-Host "  OK - HTTP 200"
        $script:Passed++
    } catch {
        Write-Host "  ERROR - $($_.Exception.Message)"
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
            $status = $null
            try {
                if ($null -ne $_.Exception.Response.StatusCode) {
                    $status = [int]$_.Exception.Response.StatusCode
                }
            } catch {}

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
}
finally {
    if ($null -ne $script:HttpClient) {
        $script:HttpClient.Dispose()
    }
    if ($null -ne $script:HttpHandler) {
        $script:HttpHandler.Dispose()
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
