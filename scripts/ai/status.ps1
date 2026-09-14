[CmdletBinding()]
param([switch] $Json)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

$root = Get-OnhostRepoRoot
$php = Get-OnhostPhp
$composerPhar = Get-OnhostComposerPhar
$branch = (Invoke-OnhostGit $root rev-parse --abbrev-ref HEAD | Select-Object -First 1)
$revision = (Invoke-OnhostGit $root rev-parse --short=12 HEAD | Select-Object -First 1)
$changes = @(Invoke-OnhostGit $root status --porcelain -- . ':(exclude)docs/generated/**')
$obsidianPath = Get-OnhostCommand 'obsidian'
$obsidianVersion = if ($obsidianPath) { (Get-Item -LiteralPath $obsidianPath).VersionInfo.ProductVersion } else { $null }

$composerStatus = if ($php -and $composerPhar) {
    Get-OnhostToolVersion 'composer' @($composerPhar, '--version', '--no-ansi') $php
} else {
    Get-OnhostToolVersion 'composer'
}

$tools = [ordered]@{
    git = Get-OnhostToolVersion 'git'
    php = Get-OnhostToolVersion 'php' @('-v') $php
    composer = $composerStatus
    node = Get-OnhostToolVersion 'node'
    npm = Get-OnhostToolVersion 'npm'
    github = Get-OnhostToolVersion 'gh'
    codex = Get-OnhostToolVersion 'codex'
    claude = Get-OnhostToolVersion 'claude'
    gemini = Get-OnhostToolVersion 'gemini'
    antigravity = Get-OnhostToolVersion 'agy'
    uv = Get-OnhostToolVersion 'uv'
    serena = Get-OnhostToolVersion 'serena'
    gitleaks = Get-OnhostToolVersion 'gitleaks'
    obsidian = [ordered]@{ available = [bool]$obsidianPath; version = $obsidianVersion }
}

$requiredContext = @('PROJECT_STATE.md', 'RECENT_CHANGES.md', 'TEST_STATUS.md', 'SECURITY_STATUS.md')
$missingContext = @($requiredContext | Where-Object { -not (Test-Path -LiteralPath (Join-Path $root "docs\generated\$_")) })
$result = [ordered]@{
    project = [ordered]@{ name = 'ONHOST'; root = $root }
    git = [ordered]@{ branch = $branch; revision = $revision; workingChanges = $changes.Count; clean = ($changes.Count -eq 0) }
    tools = $tools
    context = [ordered]@{ current = ($missingContext.Count -eq 0); missing = $missingContext }
}

if ($Json) {
    $result | ConvertTo-Json -Depth 8
    exit 0
}

Write-Host "ONHOST Brain" -ForegroundColor Cyan
Write-Host "  Git       $branch @ $revision ($($changes.Count) working changes)"
Write-Host "  Context   $(if ($missingContext.Count -eq 0) { 'ready' } else { 'run: brain.ps1 update' })"
Write-Host '  Tools'
foreach ($entry in $tools.GetEnumerator()) {
    $mark = if ($entry.Value.available) { '[ok]' } else { '[--]' }
    Write-Host ("    {0,-4} {1,-10} {2}" -f $mark, $entry.Key, $entry.Value.version)
}
