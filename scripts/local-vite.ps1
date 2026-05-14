$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

npm.cmd run dev -- --host 127.0.0.1 --port 8021 --strictPort
