param(
    [Parameter(Mandatory = $true)]
    [string] $InstallerPath,

    [string] $FileName = 'MWS-Manifestador-Agent-Setup.msi'
)

$ErrorActionPreference = 'Stop'
$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$resolvedInstaller = Resolve-Path $InstallerPath
$extension = [System.IO.Path]::GetExtension($resolvedInstaller.Path).ToLowerInvariant()

if ($extension -notin @('.msi', '.exe')) {
    throw 'Only .msi and .exe installers can be published as the primary Agent installer.'
}

$targetDirectory = Join-Path $repoRoot 'storage\app\private\installers'
$targetPath = Join-Path $targetDirectory $FileName
New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
Copy-Item -LiteralPath $resolvedInstaller.Path -Destination $targetPath -Force

$hash = Get-FileHash -Algorithm SHA256 -LiteralPath $targetPath

Write-Host "Installer copied to: $targetPath"
Write-Host "SHA-256: $($hash.Hash.ToLowerInvariant())"
Write-Host ''
Write-Host 'Configure the Web .env with:'
Write-Host 'MWS_AGENT_INSTALLER_LOCAL_DISK=local'
Write-Host "MWS_AGENT_INSTALLER_LOCAL_PATH=installers/$FileName"
Write-Host "MWS_AGENT_INSTALLER_FILE_NAME=$FileName"
Write-Host 'MWS_AGENT_INSTALLER_VERSION=1.0.0'
Write-Host "MWS_AGENT_INSTALLER_SHA256=$($hash.Hash.ToLowerInvariant())"
