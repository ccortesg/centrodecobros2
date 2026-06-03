Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $repoRoot

$routeFiles = @(
    'routes\web.php',
    'routes\api.php'
)

$pathPattern = "Route::\w+\(\s*['""](?<uri>[^'""]+)['""]"
$actionPattern = "Route::\w+\(\s*['""](?<uri>[^'""]+)['""]\s*,\s*['""](?<action>[^'""]+)['""]"

$routePaths = New-Object System.Collections.Generic.HashSet[string]
$controllerIssues = New-Object System.Collections.Generic.List[object]
$frontendIssues = New-Object System.Collections.Generic.List[object]

foreach ($routeFile in $routeFiles) {
    foreach ($line in Get-Content $routeFile) {
        if ($line -match $pathPattern) {
            [void]$routePaths.Add($matches['uri'].TrimStart('/'))
        }

        if ($line -notmatch $actionPattern) {
            continue
        }

        $uri = $matches['uri']
        $action = $matches['action']
        if ($action -notmatch '^(?<controller>[\w\\]+)@(?<method>\w+)$') {
            continue
        }

        $controllerName = $matches['controller']
        $methodName = $matches['method']
        $controllerPath = Join-Path $repoRoot ('app\Http\Controllers\' + ($controllerName -replace '\\', '\') + '.php')

        if (-not (Test-Path $controllerPath)) {
            $controllerIssues.Add([pscustomobject]@{
                RouteFile = $routeFile
                Uri = $uri
                Action = $action
                Detail = 'missing controller file'
            })
            continue
        }

        $methodPattern = 'function\s+' + [regex]::Escape($methodName) + '\s*\('
        if (-not (Select-String -Path $controllerPath -Pattern $methodPattern -Quiet)) {
            $controllerIssues.Add([pscustomobject]@{
                RouteFile = $routeFile
                Uri = $uri
                Action = $action
                Detail = 'missing controller method'
            })
        }
    }
}

$endpointPatterns = @(
    'axios\.(?:get|post|put|delete)\(\s*[''"](?<uri>[^''"]+)[''"]',
    'url:\s*[''"](?<uri>[^''"]+)[''"]',
    'var\s+url\s*=\s*[''"](?<uri>[^''"]+)[''"]'
)

Get-ChildItem resources\assets\js\components -Filter *.vue | ForEach-Object {
    $componentPath = $_.FullName
    foreach ($line in Get-Content $componentPath) {
        foreach ($pattern in $endpointPatterns) {
            if ($line -notmatch $pattern) {
                continue
            }

            $rawUri = $matches['uri']
            if ($rawUri.StartsWith('http')) {
                continue
            }

            $normalizedUri = ($rawUri -split '\?')[0].TrimStart('/')
            if ([string]::IsNullOrWhiteSpace($normalizedUri)) {
                continue
            }

            if (-not $routePaths.Contains($normalizedUri)) {
                $frontendIssues.Add([pscustomobject]@{
                    File = $componentPath.Replace("$repoRoot\", '')
                    Uri = $rawUri
                })
            }
        }
    }
}

$frontendIssues = @(
    $frontendIssues |
        Sort-Object File, Uri -Unique
)

Write-Host "== Controller references =="
if ($controllerIssues.Count -eq 0) {
    Write-Host "OK"
} else {
    foreach ($issue in $controllerIssues) {
        Write-Host "$($issue.RouteFile) :: $($issue.Uri) -> $($issue.Action) :: $($issue.Detail)"
    }
}
Write-Host ""

Write-Host "== Frontend endpoints without exact route =="
if ($frontendIssues.Count -eq 0) {
    Write-Host "OK"
} else {
    foreach ($issue in $frontendIssues) {
        Write-Host "$($issue.File) :: $($issue.Uri)"
    }
}

if ($controllerIssues.Count -gt 0 -or $frontendIssues.Count -gt 0) {
    exit 1
}
