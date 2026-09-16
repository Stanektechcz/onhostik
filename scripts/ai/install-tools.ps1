[CmdletBinding()]
param([switch] $SkipObsidian, [switch] $SkipSerena)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

function Install-WingetPackage {
    param([string] $Id, [string] $CommandName)

    if ($CommandName -and (Get-OnhostCommand $CommandName)) {
        Write-Host "[ok] $Id already available" -ForegroundColor Green
        return
    }

    $listing = & winget list --id $Id --exact --accept-source-agreements 2>$null
    $isListed = $LASTEXITCODE -eq 0 -and ($listing -match [regex]::Escape($Id))
    if ($isListed -and -not $CommandName) {
        $uninstallRoots = @(
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\*',
            'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\*'
        )
        $record = Get-ItemProperty $uninstallRoots -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -eq 'Obsidian' } |
            Select-Object -First 1
        $displayIcon = if ($record) { ($record.DisplayIcon -split ',')[0] } else { $null }
        if ($displayIcon -and (Test-Path -LiteralPath $displayIcon -PathType Leaf)) {
            Write-Host "[ok] $Id already installed" -ForegroundColor Green
            return
        }
    }

    Write-Host "Installing $Id..." -ForegroundColor Cyan
    $arguments = @('install', '--id', $Id, '--exact', '--accept-package-agreements', '--accept-source-agreements', '--silent')
    if ($isListed) { $arguments += '--force' }
    & winget @arguments
    if ($LASTEXITCODE -ne 0) { throw "Installation failed: $Id" }
}

if (-not (Get-OnhostCommand 'winget')) { throw 'winget is required for managed tool installation.' }

Install-WingetPackage 'Gitleaks.Gitleaks' 'gitleaks'
Install-WingetPackage 'astral-sh.uv' 'uv'
if (-not $SkipObsidian) { Install-WingetPackage 'Obsidian.Obsidian' $null }

if (-not $SkipSerena) {
    $uv = Get-OnhostCommand 'uv'
    if (-not $uv) { throw 'uv was installed but its executable could not be located.' }

    if (Get-OnhostCommand 'serena') {
        Write-Host '[ok] Serena already available' -ForegroundColor Green
    } else {
        Write-Host 'Installing Serena...' -ForegroundColor Cyan
        & $uv tool install --python 3.13 --managed-python serena-agent
        if ($LASTEXITCODE -ne 0) { throw 'Serena installation failed.' }
    }

    & $uv tool update-shell
    if ($LASTEXITCODE -ne 0) { throw 'Serena is installed, but its command directory could not be added to PATH.' }
}

Write-Host 'ONHOST Brain tools are installed. Open a new terminal if a command is not yet on PATH.' -ForegroundColor Green
