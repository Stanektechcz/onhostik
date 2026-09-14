[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$brain = Join-Path $repoRoot 'brain.ps1'
$failures = [System.Collections.Generic.List[string]]::new()

function Assert-True {
    param([bool] $Condition, [string] $Message)

    if ($Condition) {
        Write-Host "PASS  $Message" -ForegroundColor Green
        return
    }

    Write-Host "FAIL  $Message" -ForegroundColor Red
    $failures.Add($Message)
}

function Invoke-Brain {
    param([string[]] $Arguments)

    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $output = & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $brain @Arguments 2>&1
    $code = $LASTEXITCODE
    $ErrorActionPreference = $previousPreference
    [pscustomobject]@{
        ExitCode = $code
        Output = ($output -join [Environment]::NewLine)
    }
}

Assert-True (Test-Path -LiteralPath $brain -PathType Leaf) 'brain.ps1 exists'

$testRunner = Get-Content -LiteralPath (Join-Path $repoRoot 'scripts\ai\test.ps1') -Raw
Assert-True ($testRunner -match '\[void\]\$steps\.Add' -and $testRunner -match '\| Out-Host') 'test runner returns only the aggregate boolean'

if (Test-Path -LiteralPath $brain -PathType Leaf) {
    $status = Invoke-Brain @('status', '-Json')
    Assert-True ($status.ExitCode -eq 0) 'status exits successfully'

    try {
        $statusJson = $status.Output | ConvertFrom-Json
        Assert-True ($null -ne $statusJson.project) 'status reports project'
        Assert-True ($null -ne $statusJson.git) 'status reports Git state'
        Assert-True ($null -ne $statusJson.tools) 'status reports tool state'
        Assert-True ($null -ne $statusJson.context) 'status reports context freshness'
        Assert-True ($statusJson.tools.composer.available -and $statusJson.tools.composer.version -match '^Composer version') 'status reads the Composer version through PHP'
        Assert-True ($statusJson.tools.obsidian.available) 'status detects the installed Obsidian application'
    } catch {
        Assert-True $false 'status emits valid JSON'
    }

    $invalid = Invoke-Brain @('not-a-command')
    Assert-True ($invalid.ExitCode -ne 0) 'unknown command fails closed'

    $security = Invoke-Brain @('security', '-Quick', '-Json')
    Assert-True ($security.ExitCode -eq 0) 'quick security gate accepts only safe tracked templates'

    $fixture = Join-Path $repoRoot '.env.brain-test'
    try {
        Set-Content -LiteralPath $fixture -Value 'BRAIN_TEST_SECRET=must-never-appear' -Encoding UTF8
        $first = Invoke-Brain @('update', '-Json')
        Assert-True ($first.ExitCode -eq 0) 'update exits successfully'

        $generated = @(
            'docs/generated/PROJECT_STATE.md'
            'docs/generated/RECENT_CHANGES.md'
            'docs/generated/TEST_STATUS.md'
            'docs/generated/SECURITY_STATUS.md'
        ) | ForEach-Object { Join-Path $repoRoot $_ }

        Assert-True (($generated | Where-Object { -not (Test-Path -LiteralPath $_) }).Count -eq 0) 'update creates every generated snapshot'
        $before = $generated | ForEach-Object { (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash }

        $second = Invoke-Brain @('update', '-Json')
        $after = $generated | ForEach-Object { (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash }
        Assert-True ($second.ExitCode -eq 0 -and (($before -join '') -eq ($after -join ''))) 'update is deterministic for unchanged input'

        $allGenerated = $generated | ForEach-Object { Get-Content -LiteralPath $_ -Raw }
        Assert-True (-not (($allGenerated -join '') -match 'must-never-appear')) 'generated context excludes env secrets'
    } finally {
        if (Test-Path -LiteralPath $fixture) {
            Remove-Item -LiteralPath $fixture -Force
        }
    }
}

if ($failures.Count -gt 0) {
    Write-Error ("{0} Brain contract test(s) failed." -f $failures.Count)
    exit 1
}

Write-Host 'All Brain contract tests passed.' -ForegroundColor Green
exit 0
