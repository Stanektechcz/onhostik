$ErrorActionPreference = 'SilentlyContinue'
$root = if ($env:CLAUDE_PROJECT_DIR) { $env:CLAUDE_PROJECT_DIR } else { (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path }
$script = Join-Path $root 'scripts\ai\update-context.ps1'
if (Test-Path -LiteralPath $script) {
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $script *> $null
}
exit 0
