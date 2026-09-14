Set-StrictMode -Version 2.0

function Get-OnhostRepoRoot {
    return (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}

function Get-OnhostPhp {
    $candidates = @(
        (Get-Command php -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source),
        'C:\Users\medion\php83\php.exe'
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_ -PathType Leaf) }

    return $candidates | Select-Object -First 1
}

function Get-OnhostInstalledExecutable {
    param([Parameter(Mandatory = $true)][string] $Name)

    $command = Get-Command $Name -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -ne $command) {
        if ($command.Source) { return $command.Source }
        return $command.Name
    }

    $fileName = if ($Name.EndsWith('.exe')) { $Name } else { "$Name.exe" }
    $direct = @(@(
        (Join-Path $env:USERPROFILE ".local\bin\$fileName"),
        (Join-Path $env:LOCALAPPDATA "Microsoft\WindowsApps\$fileName"),
        (Join-Path $env:LOCALAPPDATA "Programs\$Name\$fileName")
    ) | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf })
    if ($direct.Count -gt 0) { return $direct | Select-Object -First 1 }

    $packages = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path -LiteralPath $packages) {
        $found = Get-ChildItem -LiteralPath $packages -Filter $fileName -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
        if ($found) { return $found }
    }

    return $null
}

function Get-OnhostCommand {
    param([Parameter(Mandatory = $true)][string] $Name)
    return Get-OnhostInstalledExecutable $Name
}

function Get-OnhostComposerPhar {
    $command = Get-OnhostInstalledExecutable 'composer'
    $candidates = @()
    if ($command) { $candidates += Join-Path (Split-Path -Parent $command) 'composer.phar' }
    $candidates += Join-Path $env:LOCALAPPDATA 'ComposerSetup\bin\composer.phar'
    $candidates += 'C:\ProgramData\ComposerSetup\bin\composer.phar'
    return @($candidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf }) | Select-Object -First 1
}

function Invoke-OnhostGit {
    param(
        [Parameter(Mandatory = $true)][string] $RepoRoot,
        [Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments
    )

    $repoPath = $RepoRoot.Replace('\', '/')
    $output = & git -c "safe.directory=$repoPath" -c "core.excludesFile=$repoPath/.git/info/exclude" -C $RepoRoot @Arguments 2>&1
    return @($output | ForEach-Object { $_.ToString() })
}

function Write-OnhostText {
    param(
        [Parameter(Mandatory = $true)][string] $Path,
        [Parameter(Mandatory = $true)][string] $Content
    )

    $normalized = ($Content -replace "`r`n", "`n").TrimEnd() + "`n"
    $current = if (Test-Path -LiteralPath $Path) { [IO.File]::ReadAllText($Path) -replace "`r`n", "`n" } else { $null }
    if ($current -eq $normalized) { return $false }

    $parent = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $parent)) {
        New-Item -ItemType Directory -Force -Path $parent | Out-Null
    }

    [IO.File]::WriteAllText($Path, $normalized, [Text.UTF8Encoding]::new($false))
    return $true
}

function Get-OnhostToolVersion {
    param(
        [Parameter(Mandatory = $true)][string] $Name,
        [string[]] $Arguments = @('--version'),
        [string] $Executable
    )

    $path = if ($Executable) { $Executable } else { Get-OnhostCommand $Name }
    if (-not $path) {
        return [ordered]@{ available = $false; version = $null }
    }

    try {
        $previousPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $lines = & $path @Arguments 2>&1
        $ErrorActionPreference = $previousPreference
        $first = @($lines | ForEach-Object { $_.ToString().Trim() } | Where-Object { $_ }) | Select-Object -First 1
        return [ordered]@{ available = $true; version = $first }
    } catch {
        $ErrorActionPreference = $previousPreference
        return [ordered]@{ available = $true; version = 'version unavailable' }
    }
}

function ConvertTo-OnhostMarkdownTable {
    param([array] $Rows)

    $lines = @('| Check | Result | Detail |', '| --- | --- | --- |')
    foreach ($row in $Rows) {
        $detail = ($row.detail -replace '\|', '\|' -replace "`r|`n", ' ').Trim()
        $lines += "| $($row.name) | $($row.result) | $detail |"
    }
    return $lines -join "`n"
}
