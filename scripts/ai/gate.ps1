[CmdletBinding()]
param(
    [switch] $Quick,
    [string[]] $Tests = @(),
    [string] $Task,
    [switch] $SkipBuild,
    [switch] $Json
)

# Quality gate for AI-driven tasks. Runs the project's own tools (the same ones CI runs) and compares
# every failing test with .ai/baseline/baseline.json, so a failure that existed before the task is reported as
# PREEXISTING and never blamed on the task. Full mode: Pint, Larastan, Pest, frontend build.
# Quick mode: Pint + the critical-flow tests from the baseline + any -Tests paths.

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

# powershell -File hands "a,b" over as one string: accept both forms.
$Tests = @($Tests | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })

$root = Get-OnhostRepoRoot
$php = Get-OnhostPhp
if (-not $php) { Write-Error 'PHP 8.3 was not found.'; exit 1 }
if ($Task -and $Task -notmatch '^TASK-\d{4}$') { Write-Error "Task id must look like TASK-0001, got '$Task'."; exit 1 }

$baselinePath = Join-Path $root '.ai\baseline\baseline.json'
if (-not (Test-Path -LiteralPath $baselinePath)) { Write-Error 'Missing .ai/baseline/baseline.json.'; exit 1 }
$baseline = Get-Content -LiteralPath $baselinePath -Raw | ConvertFrom-Json

$stateDir = Join-Path $root '.ai\state'
New-Item -ItemType Directory -Force -Path $stateDir | Out-Null
$junit = Join-Path $stateDir 'pest-junit.xml'
if (Test-Path -LiteralPath $junit) { Remove-Item -LiteralPath $junit -Force }

function ConvertTo-TestKey {
    param([string] $Value)
    return ($Value -replace '\\', '/').Trim()
}

$known = @($baseline.known_failing_tests | ForEach-Object { ConvertTo-TestKey $_ })
$steps = [System.Collections.Generic.List[object]]::new()

function Invoke-GateStep {
    param([string] $Name, [string] $File, [string[]] $Arguments)

    Write-Host "`n== $Name ==" -ForegroundColor Cyan
    $started = Get-Date
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & $File @Arguments | Out-Host
    $code = $LASTEXITCODE
    $ErrorActionPreference = $previous
    [void]$steps.Add([ordered]@{
        name = $Name
        result = $(if ($code -eq 0) { 'PASS' } else { 'FAIL' })
        detail = ('exit {0}; {1:n1}s' -f $code, ((Get-Date) - $started).TotalSeconds)
    })
    return $code
}

Push-Location $root
try {
    [void](Invoke-GateStep 'Pint' $php @('vendor/bin/pint', '--test'))
    if (-not $Quick) {
        [void](Invoke-GateStep 'Larastan' $php @('vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=2G'))
    }

    $pestArgs = @('artisan', 'test', '--compact', "--log-junit=$junit")
    if ($Quick) {
        $paths = @($baseline.critical_flows | ForEach-Object { $_.tests }) + $Tests
        $missing = @($paths | Where-Object { -not (Test-Path -LiteralPath (Join-Path $root $_)) })
        if ($missing.Count -gt 0) { Write-Error ("Critical-flow test(s) no longer exist: {0}. Update .ai/baseline/baseline.json deliberately." -f ($missing -join ', ')) }
        $pestArgs += @($paths | Select-Object -Unique)
    }
    $pestCode = Invoke-GateStep $(if ($Quick) { 'Pest (critical flows)' } else { 'Pest' }) $php $pestArgs

    if (-not $Quick -and -not $SkipBuild) {
        $npm = Get-OnhostCommand 'npm'
        if (-not $npm) { Write-Error 'npm was not found.' }
        [void](Invoke-GateStep 'Frontend build' $npm @('run', 'build'))
    }
} finally {
    Pop-Location
}

# Classify every failing test against the baseline.
$failed = @()
$report = $null
if ((Test-Path -LiteralPath $junit) -and (Get-Item -LiteralPath $junit).Length -gt 0) {
    try { [xml] $report = Get-Content -LiteralPath $junit -Raw } catch { $report = $null }
}
if ($null -ne $report -and $null -ne $report.DocumentElement) {
    $failed = @($report.SelectNodes('//testcase[failure or error]') | ForEach-Object { ConvertTo-TestKey $_.GetAttribute('file') })
} else {
    # No report means no evidence, whatever the exit code said: never let that pass.
    $failed = @("<pest produced no JUnit report (exit $pestCode); no test evidence - read the Pest output above>")
}
if ($pestCode -ne 0 -and $failed.Count -eq 0) {
    # Pest failed without naming a failing test (bootstrap error, no tests matched, crash after the report started).
    $failed = @("<pest exited $pestCode but the report names no failing test; read the Pest output above>")
}
$preexisting = @($failed | Where-Object { $known -contains $_ })
$regressions = @($failed | Where-Object { $known -notcontains $_ })

$toolFailures = @($steps | Where-Object { $_.result -eq 'FAIL' -and $_.name -notlike 'Pest*' })
$verdict = 'PASS'
if ($regressions.Count -gt 0 -or $toolFailures.Count -gt 0) {
    $verdict = 'FAIL'
} elseif ($preexisting.Count -gt 0) {
    $verdict = 'PASS_WITH_PREEXISTING_FAILURES'
}

$branch = (Invoke-OnhostGit $root rev-parse --abbrev-ref HEAD | Select-Object -First 1)
$revision = (Invoke-OnhostGit $root rev-parse --short=12 HEAD | Select-Object -First 1)
$dirty = @(Invoke-OnhostGit $root status --porcelain).Count
$result = [ordered]@{
    verdict = $verdict
    mode = $(if ($Quick) { 'quick' } else { 'full' })
    task = $Task
    branch = $branch
    revision = $revision
    uncommitted_paths = $dirty
    baseline_revision = $baseline.revision.commit
    measured_at = (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    checks = @($steps)
    regressions = $regressions
    preexisting_failures = $preexisting
}
[void](Write-OnhostText (Join-Path $stateDir 'last-gate.json') ($result | ConvertTo-Json -Depth 6))

if ($Task) {
    $lines = @(
        "# Gate report: $Task",
        '',
        "> Generated by ``brain.ps1 gate``. Evidence for review and integration; rerun after every change.",
        '',
        "- **Verdict:** $verdict ($($result.mode))",
        "- **Revision:** ``$branch`` @ ``$revision`` ($dirty uncommitted paths at run time)",
        "- **Baseline:** ``$($baseline.revision.commit)``",
        "- **Measured:** $($result.measured_at)",
        '',
        (ConvertTo-OnhostMarkdownTable $steps),
        '',
        "**Regressions (new failures):** $(if ($regressions.Count) { '' } else { 'none' })"
    )
    $lines += @($regressions | ForEach-Object { "- ``$_``" })
    $lines += @('', "**Pre-existing failures (in baseline):** $(if ($preexisting.Count) { '' } else { 'none' })")
    $lines += @($preexisting | ForEach-Object { "- ``$_``" })
    [void](Write-OnhostText (Join-Path $root ".ai\reports\$Task-gate.md") ($lines -join "`n"))
}

if ($Json) {
    $result | ConvertTo-Json -Depth 6
} else {
    Write-Host "`nGate verdict: $verdict" -ForegroundColor $(if ($verdict -eq 'FAIL') { 'Red' } else { 'Green' })
    foreach ($r in $regressions) { Write-Host "  REGRESSION   $r" -ForegroundColor Red }
    foreach ($p in $preexisting) { Write-Host "  PREEXISTING  $p" -ForegroundColor Yellow }
}

if ($verdict -eq 'FAIL') { exit 1 }
exit 0
