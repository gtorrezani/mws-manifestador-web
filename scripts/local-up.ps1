param(
    [switch] $SkipMigrate
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker CLI was not found. Install Docker Desktop or add docker.exe to PATH before running local-up.ps1.'
}

docker compose version | Out-Null
docker compose -f docker-compose.local.yml up -d

if (-not $SkipMigrate) {
    composer install --no-interaction --prefer-dist --no-progress
    $appKey = (Select-String -Path .env -Pattern '^APP_KEY=(.*)$' -ErrorAction SilentlyContinue).Matches.Groups[1].Value
    if ([string]::IsNullOrWhiteSpace($appKey)) {
        php artisan key:generate --force
    }

    php artisan migrate:fresh --seed
}

Write-Host 'Local infrastructure is ready:'
Write-Host '  Laravel: http://127.0.0.1:8020'
Write-Host '  Vite:    http://127.0.0.1:8021'
Write-Host '  MySQL:   127.0.0.1:3307 -> container 3306'
Write-Host '  Redis:   127.0.0.1:6380 -> container 6379'
