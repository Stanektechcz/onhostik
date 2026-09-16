[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-OnhostRepoRoot
$serena = Get-OnhostInstalledExecutable 'serena'
if (-not $serena) {
    Write-Error 'Serena is not installed. Run scripts/ai/install-tools.ps1.'
    exit 1
}

Set-Location $root
& $serena start-mcp-server --project-from-cwd --context ide-assistant --enable-web-dashboard false
exit $LASTEXITCODE
