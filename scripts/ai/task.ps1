[CmdletBinding()]
param(
    [Parameter(Position = 0)][ValidateSet('board', 'start', 'claim', 'release', 'finish')][string] $Action = 'board',
    [string] $Id,
    [string] $Title,
    [string] $Owner,
    [string[]] $Paths = @(),
    [ValidateSet('feat', 'fix', 'chore', 'docs', 'refactor', 'test', 'perf', 'ci')][string] $Type = 'feat',
    [ValidateSet('LOW', 'MEDIUM', 'HIGH', 'CRITICAL')][string] $Risk = 'MEDIUM',
    [ValidateSet('P0', 'P1', 'P2', 'P3')][string] $Priority = 'P2',
    [string] $Base = 'development',
    [string] $Worktree,
    [switch] $NoInstall,
    [switch] $Json
)

# Task coordination for parallel AI sessions (see .ai/DEVELOPMENT_RULES.md).
# Live coordination state (locks, id reservations, the ACTIVE_WORK board) lives in ONE place shared by every
# worktree: the main checkout's .ai/state/, which ignores itself in Git. Durable records (task file, handoff,
# gate report) live on the task branch and reach development with the integration.

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')

# powershell -File hands "a,b" over as one string: accept both forms.
$Paths = @($Paths | ForEach-Object { $_ -split ',' } | ForEach-Object { $_.Trim() } | Where-Object { $_ })
# Scopes are repository-relative; '..' or '.' segments would let a scope slip past the overlap check.
$badScope = @($Paths | Where-Object { ($_ -replace '\\', '/') -match '(^|/)\.\.?(/|$)' -or $_ -match '^([A-Za-z]:|/|\\)' })
if ($badScope.Count -gt 0) { Write-Error ("Paths must be repository-relative without '.' or '..' segments: {0}" -f ($badScope -join ', ')); exit 1 }

$here = Get-OnhostRepoRoot
$mainLine = @(Invoke-OnhostGit $here worktree list --porcelain) | Where-Object { $_ -like 'worktree *' } | Select-Object -First 1
$main = ($mainLine -replace '^worktree ', '').Replace('/', '\')
$state = Join-Path $main '.ai\state'
$locksDir = Join-Path $state 'coordination\locks'
$idsDir = Join-Path $state 'coordination\ids'
New-Item -ItemType Directory -Force -Path $locksDir, $idsDir | Out-Null
$selfIgnore = Join-Path $state '.gitignore'
if (-not (Test-Path -LiteralPath $selfIgnore)) { [void](Write-OnhostText $selfIgnore '*') }

function Get-Now { (Get-Date).ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ') }

# git worktree add/remove report progress on stderr; under 'Stop' PowerShell 5.1 would treat that as a failure.
function Invoke-GitChecked {
    param([string] $Repo, [Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)
    $ErrorActionPreference = 'Continue'
    $output = @(Invoke-OnhostGit $Repo @Arguments)
    return [pscustomobject]@{ ok = ($LASTEXITCODE -eq 0); output = $output }
}

function ConvertTo-ScopePath {
    param([string] $Value)
    return ($Value.Trim() -replace '\\', '/' -replace '^\./', '' -replace '/+$', '').ToLowerInvariant()
}

function Get-Locks {
    return @(Get-ChildItem -LiteralPath $locksDir -Filter 'TASK-*.json' | ForEach-Object {
        $lock = Get-Content -LiteralPath $_.FullName -Raw | ConvertFrom-Json
        $lock.paths = @($lock.paths)
        $lock
    })
}

function Find-Overlaps {
    param([string[]] $Wanted, [string] $ExceptId)
    $hits = @()
    foreach ($lock in (Get-Locks | Where-Object { $_.id -ne $ExceptId })) {
        foreach ($held in $lock.paths) {
            $h = ConvertTo-ScopePath $held
            foreach ($want in $Wanted) {
                $w = ConvertTo-ScopePath $want
                if ($w -eq $h -or $w.StartsWith("$h/") -or $h.StartsWith("$w/")) {
                    $hits += [pscustomobject]@{ task = $lock.id; owner = $lock.owner; held = $held; wanted = $want }
                }
            }
        }
    }
    return $hits
}

function Assert-NoOverlap {
    param([string[]] $Wanted, [string] $ExceptId)
    $hits = @(Find-Overlaps $Wanted $ExceptId)
    if ($hits.Count -eq 0) { return }
    foreach ($hit in $hits) { Write-Host ("  {0} ({1}) holds '{2}' - overlaps '{3}'" -f $hit.task, $hit.owner, $hit.held, $hit.wanted) -ForegroundColor Red }
    Write-Host 'Scope overlap. The orchestrator decides: serialize, change boundaries, merge ownership, or agree an interface contract (.ai/DEVELOPMENT_RULES.md).' -ForegroundColor Red
    exit 2
}

function Get-NextId {
    $numbers = @()
    $numbers += @(Get-ChildItem -LiteralPath $idsDir -Filter 'TASK-*' | ForEach-Object { [int]($_.Name -replace '\D', '') })
    $numbers += @(Invoke-OnhostGit $here for-each-ref --format='%(refname:short)' refs/heads refs/remotes | ForEach-Object {
        if ($_ -match 'TASK-(\d{4})') { [int]$Matches[1] }
    })
    $numbers += @(Get-ChildItem -LiteralPath (Join-Path $here '.ai\tasks') -Filter 'TASK-*.md' -ErrorAction SilentlyContinue | ForEach-Object {
        if ($_.Name -match 'TASK-(\d{4})') { [int]$Matches[1] }
    })
    $max = ($numbers + 0 | Measure-Object -Maximum).Maximum
    return 'TASK-{0:d4}' -f ([int]$max + 1)
}

function ConvertTo-Slug {
    param([string] $Text)
    $plain = -join ($Text.Normalize([Text.NormalizationForm]::FormD).ToCharArray() | Where-Object {
        [Globalization.CharUnicodeInfo]::GetUnicodeCategory($_) -ne [Globalization.UnicodeCategory]::NonSpacingMark
    })
    $slug = ($plain.ToLowerInvariant() -replace '[^a-z0-9]+', '-').Trim('-')
    if ($slug.Length -gt 40) { $slug = $slug.Substring(0, 40).Trim('-') }
    if (-not $slug) { $slug = 'task' }
    return $slug
}

function Save-Lock {
    param($Lock)
    [void](Write-OnhostText (Join-Path $locksDir "$($Lock.id).json") ($Lock | ConvertTo-Json -Depth 4))
}

function Get-TaskStatus {
    param($Lock)
    if (-not $Lock.worktree) { return '?' }
    $file = Join-Path $Lock.worktree ".ai\tasks\$($Lock.id).md"
    if (-not (Test-Path -LiteralPath $file)) { return '(no task file)' }
    $match = Select-String -LiteralPath $file -Pattern '^- \*\*Status:\*\*\s*(\S+)' | Select-Object -First 1
    if ($match) { return $match.Matches[0].Groups[1].Value }
    return '?'
}

function Update-Board {
    $rows = @()
    foreach ($lock in (Get-Locks | Sort-Object id)) {
        $exists = $lock.worktree -and (Test-Path -LiteralPath $lock.worktree)
        $dirty = if ($exists) { @(Invoke-OnhostGit $lock.worktree status --porcelain).Count } else { '-' }
        $rows += [pscustomobject]@{
            id = $lock.id; title = $lock.title; owner = $lock.owner; risk = $lock.risk
            status = $(if ($lock.worktree -and -not $exists) { 'WORKTREE_MISSING' } else { Get-TaskStatus $lock })
            branch = $lock.branch; worktree = $lock.worktree; dirty = $dirty
            paths = ($lock.paths -join ', '); since = $lock.since
        }
    }
    $lockedTrees = @((Get-Locks) | ForEach-Object { if ($_.worktree) { $_.worktree.ToLowerInvariant().TrimEnd('\') } })
    $orphans = @(Invoke-OnhostGit $here worktree list --porcelain | Where-Object { $_ -like 'worktree *' } | ForEach-Object {
        ($_ -replace '^worktree ', '').Replace('/', '\')
    } | Where-Object { $_.ToLowerInvariant().TrimEnd('\') -ne $main.ToLowerInvariant().TrimEnd('\') -and $lockedTrees -notcontains $_.ToLowerInvariant().TrimEnd('\') })

    $lines = @(
        '# Active work (live board)',
        '',
        "> Generated by ``brain.ps1 task board`` at $(Get-Now) from the locks in this directory. Do not edit by hand;",
        '> use `brain.ps1 task claim|release`. Shared by every worktree of this clone; never committed.',
        '',
        '| Task | Title | Owner | Risk | Status | Branch | Dirty | Owned paths | Since |',
        '| --- | --- | --- | --- | --- | --- | --- | --- | --- |'
    )
    foreach ($r in $rows) { $lines += "| $($r.id) | $($r.title) | $($r.owner) | $($r.risk) | $($r.status) | $($r.branch) | $($r.dirty) | $($r.paths) | $($r.since) |" }
    if ($rows.Count -eq 0) { $lines += '| - | no active work | | | | | | | |' }
    $lines += @('', '## Worktrees without a lock', '')
    if ($orphans.Count -eq 0) { $lines += 'none' } else { $lines += @($orphans | ForEach-Object { "- ``$_``" }) }
    [void](Write-OnhostText (Join-Path $state 'ACTIVE_WORK.md') ($lines -join "`n"))
    return [pscustomobject]@{ tasks = $rows; unlocked_worktrees = $orphans; board = (Join-Path $state 'ACTIVE_WORK.md') }
}

switch ($Action) {
    'board' {
        $board = Update-Board
        if ($Json) { $board | ConvertTo-Json -Depth 5 } else { Get-Content -LiteralPath $board.board | Out-Host }
        exit 0
    }

    'claim' {
        if ($Id -notmatch '^TASK-\d{4}$' -or $Paths.Count -eq 0 -or -not $Owner) { Write-Error 'claim needs -Id TASK-0000 -Owner <agent> -Paths a,b [-Title]'; exit 1 }
        Assert-NoOverlap $Paths $Id
        $file = Join-Path $locksDir "$Id.json"
        $lock = if (Test-Path -LiteralPath $file) { Get-Content -LiteralPath $file -Raw | ConvertFrom-Json } else { $null }
        $merged = @(@($(if ($lock) { $lock.paths } else { @() })) + $Paths | Select-Object -Unique)
        # -Worktree records work that happens elsewhere (e.g. uncommitted work in the main checkout); default: here.
        $where = if ($Worktree) { (Resolve-Path -LiteralPath $Worktree).Path } elseif ($lock) { $lock.worktree } else { $here }
        # One task worktree belongs to one lock; the main checkout may carry several claims (it is never removed by finish).
        $isMain = $where.TrimEnd('\').ToLowerInvariant() -eq $main.TrimEnd('\').ToLowerInvariant()
        $sharing = @(Get-Locks | Where-Object { $_.id -ne $Id -and $_.worktree -and $_.worktree.TrimEnd('\').ToLowerInvariant() -eq $where.TrimEnd('\').ToLowerInvariant() })
        if (-not $isMain -and $sharing.Count -gt 0) {
            Write-Host ("Worktree {0} is already held by {1}; claim paths under that task instead." -f $where, (($sharing | ForEach-Object { $_.id }) -join ', ')) -ForegroundColor Red
            exit 2
        }
        Save-Lock ([ordered]@{
            id = $Id
            title = $(if ($Title) { $Title } elseif ($lock) { $lock.title } else { '' })
            owner = $Owner
            risk = $Risk
            branch = (Invoke-OnhostGit $where rev-parse --abbrev-ref HEAD | Select-Object -First 1)
            worktree = $where
            paths = $merged
            since = $(if ($lock) { $lock.since } else { Get-Now })
        })
        [void](New-Item -ItemType File -Force -Path (Join-Path $idsDir $Id))
        [void](Update-Board)
        Write-Host "$Id holds: $($merged -join ', ')" -ForegroundColor Green
        exit 0
    }

    'release' {
        if ($Id -notmatch '^TASK-\d{4}$') { Write-Error 'release needs -Id TASK-0000'; exit 1 }
        $file = Join-Path $locksDir "$Id.json"
        if (Test-Path -LiteralPath $file) { Remove-Item -LiteralPath $file }
        [void](Update-Board)
        Write-Host "$Id released." -ForegroundColor Green
        exit 0
    }

    'start' {
        if (-not $Title -or -not $Owner -or $Paths.Count -eq 0) { Write-Error 'start needs -Title "..." -Owner <agent> -Paths a,b [-Type feat|fix|...] [-Risk] [-Priority]'; exit 1 }
        Assert-NoOverlap $Paths ''
        # Reserve the id atomically: creating the reservation file fails if a concurrent start took the same id.
        $newId = $null
        for ($attempt = 0; $attempt -lt 20 -and -not $newId; $attempt++) {
            $candidate = Get-NextId
            try {
                [void][IO.File]::Open((Join-Path $idsDir $candidate), [IO.FileMode]::CreateNew).Dispose()
                $newId = $candidate
            } catch [IO.IOException] {
                Start-Sleep -Milliseconds (50 * ($attempt + 1))
            }
        }
        if (-not $newId) { Write-Error 'Could not reserve a task id after 20 attempts.'; exit 1 }
        $slug = ConvertTo-Slug $Title
        $branch = "$Type/$newId-$slug"
        $tree = Join-Path (Split-Path -Parent $main) "onhost-worktrees\$newId-$slug"
        if (@(Invoke-OnhostGit $here branch --list $branch).Count -gt 0) { Write-Error "Branch $branch already exists."; exit 1 }
        if (Test-Path -LiteralPath $tree) { Write-Error "Worktree path $tree already exists."; exit 1 }

        $add = Invoke-GitChecked $here worktree add -b $branch $tree $Base
        if (-not $add.ok) { $add.output | Out-Host; Write-Error 'git worktree add failed.'; exit 1 }

        # Dependencies: PHP needs a real vendor/ (a junction makes Composer resolve App\ into the main checkout);
        # node_modules is only read by the build tools, so a junction to the main checkout is enough.
        if (-not (Test-Path -LiteralPath (Join-Path $tree '.env'))) { [void](New-Item -ItemType File -Path (Join-Path $tree '.env')) }
        $mainModules = Join-Path $main 'node_modules'
        if (Test-Path -LiteralPath $mainModules) { & cmd.exe /c mklink /J "$tree\node_modules" "$mainModules" | Out-Null }
        if (-not $NoInstall) {
            $php = Get-OnhostPhp
            $phar = @(@((Get-OnhostComposerPhar), (Join-Path (Split-Path -Parent $php) 'composer.phar')) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }) | Select-Object -First 1
            if (-not $phar) { Write-Warning 'composer.phar not found; run composer install in the worktree before testing.' }
            else {
                Push-Location $tree
                try { & $php $phar install --no-interaction --no-progress | Out-Host } finally { Pop-Location }
                if ($LASTEXITCODE -ne 0) { Write-Warning "composer install failed (exit $LASTEXITCODE) in $tree; rerun it there before testing - the task is started but its vendor/ is incomplete." }
            }
        }

        $template = Join-Path $tree '.ai\tasks\TEMPLATE.md'
        $taskFile = Join-Path $tree ".ai\tasks\$newId.md"
        $body = if (Test-Path -LiteralPath $template) { [IO.File]::ReadAllText($template) } else { "# {{ID}}: {{TITLE}}`n`n- **Status:** PLANNED`n" }
        $pathList = ($Paths | ForEach-Object { "- ``$_``" }) -join "`n"
        $body = $body.Replace('{{ID}}', $newId).Replace('{{TITLE}}', $Title).Replace('{{PRIORITY}}', $Priority).Replace('{{RISK}}', $Risk).Replace('{{OWNER}}', $Owner).Replace('{{BRANCH}}', $branch).Replace('{{WORKTREE}}', $tree).Replace('{{DATE}}', (Get-Now)).Replace('{{PATHS}}', $pathList)
        [void](Write-OnhostText $taskFile $body)
        # The task record is the branch's first commit: durable at once, and the worktree starts clean.
        $staged = Invoke-GitChecked $tree add -- ".ai/tasks/$newId.md"
        $opened = Invoke-GitChecked $tree commit -m "chore(ai): open $newId $Title" -- ".ai/tasks/$newId.md"
        if (-not ($staged.ok -and $opened.ok)) { $opened.output | Out-Host; Write-Warning "Could not commit the task file; commit $taskFile by hand." }

        Save-Lock ([ordered]@{ id = $newId; title = $Title; owner = $Owner; risk = $Risk; branch = $branch; worktree = $tree; paths = @($Paths); since = (Get-Now) })
        [void](Update-Board)
        $result = [ordered]@{ id = $newId; branch = $branch; worktree = $tree; task_file = $taskFile }
        if ($Json) { $result | ConvertTo-Json } else {
            Write-Host "$newId started." -ForegroundColor Green
            Write-Host "  Branch    $branch"
            Write-Host "  Worktree  $tree"
            Write-Host "  Task      $taskFile"
            Write-Host "Open a new Claude Code session in the worktree and give it the task file."
        }
        exit 0
    }

    'finish' {
        if ($Id -notmatch '^TASK-\d{4}$') { Write-Error 'finish needs -Id TASK-0000'; exit 1 }
        $file = Join-Path $locksDir "$Id.json"
        if (-not (Test-Path -LiteralPath $file)) { Write-Error "No lock for $Id."; exit 1 }
        $lock = Get-Content -LiteralPath $file -Raw | ConvertFrom-Json
        $tree = $lock.worktree
        if ($tree -and $tree.TrimEnd('\').ToLowerInvariant() -eq $main.TrimEnd('\').ToLowerInvariant()) { Write-Error 'This task ran in the main checkout; use release instead.'; exit 1 }
        if ($tree -and (Test-Path -LiteralPath $tree)) {
            $dirty = @(Invoke-OnhostGit $tree status --porcelain)
            if ($dirty.Count -gt 0) { $dirty | Out-Host; Write-Error "Worktree $tree has uncommitted changes; commit or hand them off first. Nothing was removed."; exit 1 }
            $merged = @(Invoke-OnhostGit $here branch --merged $Base --list $lock.branch)
            if ($merged.Count -eq 0) { Write-Error "Branch $($lock.branch) is not merged into $Base; integrate it first (ai-integrate). Nothing was removed."; exit 1 }
            $modules = Join-Path $tree 'node_modules'
            if ((Test-Path -LiteralPath $modules) -and ((Get-Item -LiteralPath $modules -Force).Attributes -band [IO.FileAttributes]::ReparsePoint)) {
                & cmd.exe /c rmdir "$modules" | Out-Null   # removes the junction only, never the main checkout's node_modules
            }
            $remove = Invoke-GitChecked $here worktree remove $tree
            if (-not $remove.ok) { $remove.output | Out-Host; Write-Error 'git worktree remove refused; inspect the worktree by hand.'; exit 1 }
        }
        Remove-Item -LiteralPath $file
        [void](Update-Board)
        Write-Host "$Id finished: worktree removed, lock released. Branch $($lock.branch) is kept; delete it yourself when you no longer need it." -ForegroundColor Green
        exit 0
    }
}
