param(
    [ValidateSet('serve', 'admin', 'test', 'migrate', 'status')]
    [string] $Action = 'serve'
)

$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$phpBinary = Join-Path $projectRoot '.tools\php\php.exe'
$backendFolder = Join-Path $projectRoot 'backend'

if (-not (Test-Path -LiteralPath $phpBinary)) {
    $installedPhp = Get-Command php -ErrorAction SilentlyContinue
    if (-not $installedPhp) { throw 'PHP 8.3+ is required. See backend/PHASE-1.md.' }
    $phpBinary = $installedPhp.Source
}
if (-not (Test-Path -LiteralPath (Join-Path $backendFolder 'vendor\autoload.php'))) {
    throw 'Composer dependencies are missing. See backend/PHASE-1.md.'
}

Push-Location -LiteralPath $backendFolder
try {
    switch ($Action) {
        'serve' { & $phpBinary artisan serve --host=127.0.0.1 --port=8000 }
        'admin' { & $phpBinary artisan portfolio:admin }
        'test' { & $phpBinary artisan test }
        'migrate' { & $phpBinary artisan migrate }
        'status' { & $phpBinary artisan about }
    }
    $commandExit = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $commandExit
