$ErrorActionPreference = 'Stop'

$Root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $Root

$vendorAutoload = Join-Path $Root 'vendor/autoload.php'
$composerLock = Join-Path $Root 'composer.lock'
$runComposerInstall = -not (Test-Path $vendorAutoload)

if (-not $runComposerInstall -and (Test-Path $composerLock)) {
    $runComposerInstall = (Get-Item $composerLock).LastWriteTimeUtc -gt (Get-Item $vendorAutoload).LastWriteTimeUtc
}

if ($runComposerInstall) {
    composer install
}
else {
    Write-Host 'composer install skipped: vendor is present and current.'
}

$nodeModules = Join-Path $Root 'node_modules'
$npmInstallState = Join-Path $nodeModules '.package-lock.json'
$packageLock = Join-Path $Root 'package-lock.json'
$runNpmCi = -not (Test-Path $nodeModules)

if (-not $runNpmCi -and (Test-Path $packageLock)) {
    if (-not (Test-Path $npmInstallState)) {
        $runNpmCi = $true
    }
    else {
        $runNpmCi = (Get-Item $packageLock).LastWriteTimeUtc -gt (Get-Item $npmInstallState).LastWriteTimeUtc
    }
}

$npm = Get-Command npm.cmd -ErrorAction SilentlyContinue
if (-not $npm) {
    $npm = Get-Command npm -ErrorAction Stop
}

if ($runNpmCi) {
    & $npm.Source ci
}
else {
    Write-Host 'npm ci skipped: node_modules is present and current.'
}

composer quality
& $npm.Source run quality
