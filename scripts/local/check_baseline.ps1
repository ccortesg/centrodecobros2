param(
    [switch]$RunBuild
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $repoRoot

Write-Host "Centro de Cobros - Baseline local check"
Write-Host "Timestamp: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')"
Write-Host "Workspace: $repoRoot"
Write-Host ""

Write-Host "== Runtime =="
php -v | Select-Object -First 1
composer --version
node -v
npm -v
Write-Host ""

Write-Host "== Legacy Node hint =="
$nodeSassVendor = Get-ChildItem node_modules\node-sass\vendor -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name
if ($nodeSassVendor) {
    $nodeSassVendor | ForEach-Object { Write-Host $_ }
} else {
    Write-Host "node-sass vendor binding not found."
}
Write-Host ""

Write-Host "== Laravel =="
php artisan --version
Write-Host ""

Write-Host "== Database =="
$dbCheck = php artisan tinker --execute='try { \DB::connection()->getPdo(); echo ''DB_OK''; } catch (Throwable $e) { echo ''DB_ERROR''; }' 2>&1
$dbCheck | ForEach-Object { Write-Host $_ }
if (($dbCheck | Out-String).Contains('DB_OK')) {
    php artisan tinker --execute='foreach ([''users'',''roles'',''personas'',''clientes'',''transacciones'',''respuestas'',''tmp_personas_merge''] as $table) { echo sprintf(''%s=%s'' . PHP_EOL, $table, \DB::table($table)->count()); }'
}
Write-Host ""

Write-Host "== Artisan commands =="
Write-Host "-- route:list"
$routeList = php artisan route:list 2>&1
$routeExit = $LASTEXITCODE
if ($routeExit -eq 0) {
    Write-Host "route:list: OK"
} else {
    Write-Host "route:list: FAIL ($routeExit)"
}
$routeList | ForEach-Object { Write-Host $_ }
Write-Host ""

Write-Host "-- schedule:list"
$scheduleList = php artisan schedule:list 2>&1
$scheduleExit = $LASTEXITCODE
if ($scheduleExit -eq 0) {
    Write-Host "schedule:list: OK"
} else {
    Write-Host "schedule:list: FAIL ($scheduleExit)"
}
$scheduleList | ForEach-Object { Write-Host $_ }
Write-Host ""

Write-Host "== Assets =="
Get-Item public\js\app.js, public\js\plantilla.js, public\css\plantilla.css |
    Select-Object Name, Length, LastWriteTime |
    Format-Table -AutoSize
Write-Host ""

if ($RunBuild) {
    Write-Host "== Frontend build =="
    cmd /c npm run dev
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
} else {
    Write-Host "== Frontend build =="
    Write-Host "Skipped. Use -RunBuild to execute 'npm run dev' with the pinned legacy Node lane."
}
