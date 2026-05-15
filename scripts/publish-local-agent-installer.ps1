[CmdletBinding(SupportsShouldProcess = $true)]
param(
    [Parameter(Mandatory = $true)]
    [string] $InstallerPath,

    [string] $FileName = 'MWS-Manifestador-Agent-Setup.msi',

    [string] $InstallerVersion = '1.0.1'
)

$ErrorActionPreference = 'Stop'
$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$resolvedInstaller = Resolve-Path $InstallerPath
$extension = [System.IO.Path]::GetExtension($resolvedInstaller.Path).ToLowerInvariant()
$safeFileName = [System.IO.Path]::GetFileName($FileName)

if ($extension -notin @('.msi', '.exe')) {
    throw 'Only .msi and .exe installers can be published as the primary Agent installer.'
}

if ($safeFileName -ne $FileName -or [string]::IsNullOrWhiteSpace($safeFileName)) {
    throw 'FileName must be a plain file name without directory segments.'
}

$targetDirectory = Join-Path $repoRoot 'storage\app\private\installers'
$targetPath = Join-Path $targetDirectory $safeFileName
New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
if (-not $PSCmdlet.ShouldProcess($targetPath, "Copy Agent installer from $($resolvedInstaller.Path)")) {
    return
}

Copy-Item -LiteralPath $resolvedInstaller.Path -Destination $targetPath -Force
$hash = Get-FileHash -Algorithm SHA256 -LiteralPath $targetPath

Write-Host "Installer copied to: $targetPath"
Write-Host "SHA-256: $($hash.Hash.ToLowerInvariant())"
Write-Host ''
Write-Host 'Configure the Web .env with:'
Write-Host 'MWS_AGENT_INSTALLER_LOCAL_DISK=local'
Write-Host "MWS_AGENT_INSTALLER_LOCAL_PATH=installers/$safeFileName"
Write-Host "MWS_AGENT_INSTALLER_FILE_NAME=$safeFileName"
Write-Host "MWS_AGENT_INSTALLER_VERSION=$InstallerVersion"
Write-Host "MWS_AGENT_INSTALLER_SHA256=$($hash.Hash.ToLowerInvariant())"
