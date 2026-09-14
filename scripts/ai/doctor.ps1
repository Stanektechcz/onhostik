[CmdletBinding()]
param([switch] $Json)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-OnhostRepoRoot
$php = Get-OnhostPhp
$composerPhar = Get-OnhostComposerPhar
$checks = @(
    [ordered]@{ name = 'Git'; required = $true; ok = [bool](Get-OnhostCommand 'git'); detail = 'source control' },
    [ordered]@{ name = 'PHP 8.3'; required = $true; ok = [bool]$php; detail = $(if ($php) { $php } else { 'not found' }) },
    [ordered]@{ name = 'Composer'; required = $true; ok = [bool]($php -and $composerPhar); detail = 'PHP dependencies' },
    [ordered]@{ name = 'Node'; required = $true; ok = [bool](Get-OnhostCommand 'node'); detail = 'frontend build' },
    [ordered]@{ name = 'npm'; required = $true; ok = [bool](Get-OnhostCommand 'npm'); detail = 'frontend dependencies' },
    [ordered]@{ name = 'vendor'; required = $true; ok = (Test-Path -LiteralPath (Join-Path $root 'vendor\autoload.php')); detail = 'run composer install if missing' },
    [ordered]@{ name = 'node_modules'; required = $true; ok = (Test-Path -LiteralPath (Join-Path $root 'node_modules')); detail = 'run npm ci if missing' },
    [ordered]@{ name = 'Gitleaks'; required = $false; ok = [bool](Get-OnhostCommand 'gitleaks'); detail = 'recommended secret scanning' },
    [ordered]@{ name = 'Serena'; required = $false; ok = [bool](Get-OnhostCommand 'serena'); detail = 'optional semantic retrieval' },
    [ordered]@{ name = 'Antigravity'; required = $false; ok = [bool](Get-OnhostCommand 'agy'); detail = 'Gemini agent client for personal accounts' },
    [ordered]@{ name = 'Obsidian'; required = $false; ok = [bool](Get-OnhostCommand 'obsidian'); detail = 'optional docs UI' }
)

$failedRequired = @($checks | Where-Object { $_.required -and -not $_.ok })
$result = [ordered]@{ ok = ($failedRequired.Count -eq 0); checks = $checks }

if ($Json) {
    $result | ConvertTo-Json -Depth 6
} else {
    Write-Host 'ONHOST Brain doctor' -ForegroundColor Cyan
    foreach ($check in $checks) {
        $mark = if ($check.ok) { '[ok]' } elseif ($check.required) { '[!!]' } else { '[--]' }
        Write-Host ("  {0,-4} {1,-14} {2}" -f $mark, $check.name, $check.detail)
    }
}

if ($failedRequired.Count -gt 0) { exit 1 }
exit 0
