[CmdletBinding()]
param(
    [Parameter(Position = 0)][string] $Command = 'status',
    [switch] $Json,
    [switch] $Quick,
    [switch] $E2E,
    [Parameter(ValueFromRemainingArguments = $true)][string[]] $Rest = @()
)

$ErrorActionPreference = 'Stop'
$scripts = Join-Path $PSScriptRoot 'scripts\ai'

# Array splatting would pass "-Title" as a value; forward the remaining arguments as positional + named splats.
function ConvertTo-OnhostSplat {
    param([string[]] $Items)
    $named = @{}
    $positional = @()
    for ($i = 0; $i -lt $Items.Count; $i++) {
        # -Name:value always binds the value, even one that looks like a flag (e.g. -Title:-urgent).
        if ($Items[$i] -match '^-([A-Za-z]+):(.*)$') { $named[$Matches[1]] = $Matches[2]; continue }
        if ($Items[$i] -match '^-([A-Za-z]+)$') {
            $name = $Matches[1]
            if ($i + 1 -lt $Items.Count -and $Items[$i + 1] -notmatch '^-[A-Za-z]+$') { $named[$name] = $Items[$i + 1]; $i++ }
            else { $named[$name] = $true }
        } else {
            $positional += $Items[$i]
        }
    }
    return @{ named = $named; positional = $positional }
}

function Show-OnhostBrainHelp {
    @'
ONHOST Brain

Usage: .\brain.ps1 <command> [-Quick] [-E2E] [-Json]

  status    One-screen project, Git, tool, and context status
  update    Regenerate compact project context
  doctor    Check the local development environment
  test      Run Pint, Larastan, Pest, and the frontend build
  security  Run tracked-file, Gitleaks, Composer, and npm checks
  context   Show the context loading order
  audit     Run doctor, full tests, and security checks
  release   Run release-readiness checks; never deploy
  gate      Quality gate vs .ai/baseline (-Quick = critical flows; -Task TASK-0000 writes .ai/reports)
  task      Parallel work: board | start | claim | release | finish (see .ai/DEVELOPMENT_RULES.md)
  help      Show this help
'@ | Write-Host
}
switch ($Command.ToLowerInvariant()) {
    'status' { & (Join-Path $scripts 'status.ps1') -Json:$Json; exit $LASTEXITCODE }
    'update' { & (Join-Path $scripts 'update-context.ps1') -Json:$Json; exit $LASTEXITCODE }
    'doctor' { & (Join-Path $scripts 'doctor.ps1') -Json:$Json; exit $LASTEXITCODE }
    'test' { & (Join-Path $scripts 'test.ps1') -Quick:$Quick -E2E:$E2E -Json:$Json; exit $LASTEXITCODE }
    'security' { & (Join-Path $scripts 'security.ps1') -Quick:$Quick -Json:$Json; exit $LASTEXITCODE }
    'context' {
        if ($Json) {
            [ordered]@{ order = @('AGENTS.md', 'docs/context/CURRENT_STATE.md', 'docs/context/DOMAIN_MAP.md', 'relevant ADR/runbook/test/source', 'docs/generated') } | ConvertTo-Json
        } else {
            Write-Host '1. AGENTS.md'
            Write-Host '2. docs/context/CURRENT_STATE.md'
            Write-Host '3. docs/context/DOMAIN_MAP.md'
            Write-Host '4. Relevant ADR, runbook, test, and source files'
            Write-Host '5. docs/generated snapshots'
        }
        exit 0
    }
    'audit' {
        & (Join-Path $scripts 'doctor.ps1'); if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & (Join-Path $scripts 'test.ps1') -E2E:$E2E; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & (Join-Path $scripts 'security.ps1'); exit $LASTEXITCODE
    }
    'release' {
        Write-Host 'Release readiness only; no deployment or production action will run.' -ForegroundColor Yellow
        & (Join-Path $scripts 'doctor.ps1'); if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & (Join-Path $scripts 'test.ps1') -E2E; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & (Join-Path $scripts 'security.ps1'); exit $LASTEXITCODE
    }
    'gate' {
        $forward = ConvertTo-OnhostSplat $Rest
        $named = $forward.named
        $positional = $forward.positional
        & (Join-Path $scripts 'gate.ps1') @positional @named -Quick:$Quick -Json:$Json; exit $LASTEXITCODE
    }
    'task' {
        $forward = ConvertTo-OnhostSplat $Rest
        $named = $forward.named
        $positional = $forward.positional
        & (Join-Path $scripts 'task.ps1') @positional @named -Json:$Json; exit $LASTEXITCODE
    }
    { $_ -in @('help', '-h', '--help') } { Show-OnhostBrainHelp; exit 0 }
    default {
        Write-Error "Unknown ONHOST Brain command: $Command"
        Show-OnhostBrainHelp
        exit 2
    }
}
