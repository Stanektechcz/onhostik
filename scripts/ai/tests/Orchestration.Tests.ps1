[CmdletBinding()]
param()

# Guards the AI orchestration layer (.ai/, .claude/agents, .claude/skills, gate/task scripts) against drift.
# Read-only for this checkout: it never runs the gate, starts tasks or writes coordination state; the one behavioural
# check builds a throw-away Git repository under %TEMP% and deletes it again.

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$failures = [System.Collections.Generic.List[string]]::new()

function Assert-True {
    param([bool] $Condition, [string] $Message)
    if ($Condition) { Write-Host "PASS  $Message" -ForegroundColor Green; return }
    Write-Host "FAIL  $Message" -ForegroundColor Red
    $failures.Add($Message)
}

function Read-Text { param([string] $Relative) return [IO.File]::ReadAllText((Join-Path $root $Relative)) }

# Knowledge layer
$required = @('PROJECT_STATE.md', 'ARCHITECTURE.md', 'DOMAIN_MAP.md', 'DEPENDENCY_MAP.md', 'TECH_STACK.md', 'TESTING.md',
    'DEVELOPMENT_RULES.md', 'SECURITY_RULES.md', 'INTEGRATION_RULES.md', 'ACTIVE_WORK.md', 'DECISIONS.md',
    'baseline\baseline.json', 'baseline\baseline.md', 'tasks\TEMPLATE.md', 'state\.gitignore')
foreach ($file in $required) { Assert-True (Test-Path -LiteralPath (Join-Path $root ".ai\$file")) ".ai/$file exists" }
Assert-True ((Read-Text '.ai\state\.gitignore').Trim() -eq '*') '.ai/state ignores its own contents'

# Baseline
$baseline = (Read-Text '.ai\baseline\baseline.json') | ConvertFrom-Json
Assert-True ($baseline.revision.commit -match '^[0-9a-f]{7,40}$') 'baseline names the measured commit'
$flowTests = @($baseline.critical_flows | ForEach-Object { $_.tests })
Assert-True ($flowTests.Count -gt 0) 'baseline lists critical-flow tests'
$missing = @($flowTests | Where-Object { -not (Test-Path -LiteralPath (Join-Path $root $_)) })
Assert-True ($missing.Count -eq 0) ("every critical-flow test exists{0}" -f $(if ($missing) { ': missing ' + ($missing -join ', ') } else { '' }))
foreach ($known in @($baseline.known_failing_tests)) {
    $file = ($known -split '::')[0]
    Assert-True ($null -ne $baseline.known_failing_notes.$file) "known failure $file is explained in known_failing_notes"
}

# Task template must match what task.ps1 fills and parses
$template = Read-Text '.ai\tasks\TEMPLATE.md'
foreach ($placeholder in @('{{ID}}', '{{TITLE}}', '{{PRIORITY}}', '{{RISK}}', '{{OWNER}}', '{{BRANCH}}', '{{WORKTREE}}', '{{DATE}}', '{{PATHS}}')) {
    Assert-True ($template.Contains($placeholder)) "task template contains $placeholder"
}
Assert-True ($template -match '(?m)^- \*\*Status:\*\*\s*PLANNED\s*$') 'task template status line matches the task.ps1 parser'

# Agents
$agents = @(Get-ChildItem -LiteralPath (Join-Path $root '.claude\agents') -Filter '*.md')
Assert-True ($agents.Count -ge 10) "project agents are defined ($($agents.Count))"
foreach ($agent in $agents) {
    $text = [IO.File]::ReadAllText($agent.FullName)
    $ok = $text -match '(?s)^---\r?\nname:\s*([^\r\n]+)\r?\ndescription:\s*[^\r\n]+\r?\ntools:\s*[^\r\n]+\r?\nmodel:\s*[^\r\n]+\r?\n---'
    Assert-True $ok "$($agent.Name) has name/description/tools/model frontmatter"
    if ($ok) { Assert-True ($Matches[1].Trim() -eq $agent.BaseName) "$($agent.Name) name matches its file name" }
    Assert-True ($text -notmatch '(?i)model:\s*claude-') "$($agent.Name) does not hardcode a model id"
}
$mapped = @([regex]::Matches((Read-Text '.ai\DOMAIN_MAP.md'), '\bonhost-[a-z]+\b') | ForEach-Object { $_.Value } | Select-Object -Unique)
foreach ($name in $mapped) { Assert-True (Test-Path -LiteralPath (Join-Path $root ".claude\agents\$name.md")) "agent $name named in DOMAIN_MAP exists" }
$readOnly = @('onhost-architect', 'onhost-security', 'onhost-reviewer', 'onhost-performance', 'onhost-release', 'onhost-researcher')
foreach ($name in $readOnly) {
    $tools = ([regex]::Match((Read-Text ".claude\agents\$name.md"), '(?m)^tools:\s*(.+)$')).Groups[1].Value
    Assert-True ($tools -notmatch '\b(Edit|Write)\b') "$name cannot edit files"
}

# Skills
$skills = @(Get-ChildItem -LiteralPath (Join-Path $root '.claude\skills') -Filter 'SKILL.md' -Recurse)
foreach ($skill in $skills) {
    $text = [IO.File]::ReadAllText($skill.FullName)
    $ok = $text -match '(?s)^---\r?\nname:\s*([^\r\n]+)\r?\ndescription:\s*[^\r\n]+'
    Assert-True $ok "$($skill.Directory.Name) has name/description frontmatter"
    if ($ok) { Assert-True ($Matches[1].Trim() -eq $skill.Directory.Name) "$($skill.Directory.Name) name matches its directory" }
}
foreach ($name in @('ai-orchestrate', 'ai-status', 'ai-task', 'ai-review', 'ai-integrate', 'ai-release-check')) {
    Assert-True (Test-Path -LiteralPath (Join-Path $root ".claude\skills\$name\SKILL.md")) "skill $name exists"
}

# Settings: secrets unreadable, prototype read-only, no imposed permission mode
$settings = (Read-Text '.claude\settings.json') | ConvertFrom-Json
$deny = @($settings.permissions.deny)
Assert-True ($deny -contains 'Read(./.env)') 'Claude cannot read .env'
Assert-True ($deny -contains 'Read(./storage/app/private/**)') 'Claude cannot read private storage'
Assert-True ($deny -contains 'Edit(./apps/surfaces/*.dc.html)' -and $deny -contains 'Write(./apps/surfaces/*.dc.html)') 'prototype surfaces are read-only'
Assert-True ($null -eq $settings.permissions.defaultMode) 'project settings do not change the permission mode'
$ask = @($settings.permissions.ask)
Assert-True ($ask -contains 'Bash(git push:*)' -and $ask -contains 'Bash(gh pr merge:*)') 'pushes and merges always ask the human'
$gate = Read-Text 'scripts\ai\gate.ps1'
Assert-True ($gate -match '\$pestCode -ne 0 -and \$failed\.Count -eq 0' -and $gate -match 'produced no JUnit report \(exit') 'gate never passes without test evidence'
Assert-True ((Read-Text 'scripts\ai\task.ps1') -match 'FileMode\]::CreateNew') 'task ids are reserved atomically'

# Scripts parse and are wired into brain.ps1
foreach ($script in @('scripts\ai\gate.ps1', 'scripts\ai\task.ps1', 'brain.ps1')) {
    $tokens = $null; $errors = $null
    [void][System.Management.Automation.Language.Parser]::ParseFile((Join-Path $root $script), [ref]$tokens, [ref]$errors)
    Assert-True ($errors.Count -eq 0) "$script parses without errors"
}
$brain = Read-Text 'brain.ps1'
Assert-True ($brain -match "'gate'\s*\{" -and $brain -match "'task'\s*\{") 'brain.ps1 exposes gate and task'
$task = Read-Text 'scripts\ai\task.ps1'
Assert-True ($task -match 'composer\.phar' -and $task -notmatch 'mklink /J "\$tree\\vendor"') 'task worktrees install vendor for real (no vendor junction)'
Assert-True ($task -match "branch --merged") 'task finish refuses unmerged branches'
Assert-True ($task -match 'cherry \$upstream \$Branch' -and $task -match 'refs/remotes/origin/\$Base') 'task finish recognises rebase merges against origin/<base>'
Assert-True ($task -match 'fetch --quiet origin \$Base' -and $task -match 'Test-BranchIntegrated \$here') 'task finish fetches the base before judging'

# Test-BranchIntegrated for real, in a throw-away repository under %TEMP% (never this checkout)
$taskAst = [System.Management.Automation.Language.Parser]::ParseFile((Join-Path $root 'scripts\ai\task.ps1'), [ref]$null, [ref]$null)
$integratedAst = $taskAst.Find({ param($n) $n -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -eq 'Test-BranchIntegrated' }, $true)
Assert-True ($null -ne $integratedAst) 'task.ps1 defines Test-BranchIntegrated'
if ($integratedAst) {
    . (Join-Path $root 'scripts\ai\common.ps1')
    . ([scriptblock]::Create($integratedAst.Extent.Text))
    $scratch = Join-Path ([IO.Path]::GetTempPath()) ("onhost-orch-" + [guid]::NewGuid().ToString('N').Substring(0, 8))
    function Invoke-ScratchGit {
        $ErrorActionPreference = 'Continue'
        $out = & git -c user.name=orch-test -c user.email=orch-test@invalid -c commit.gpgsign=false -C $scratch @args 2>&1
        if ($LASTEXITCODE -ne 0) { throw "git $($args -join ' ') failed: $out" }
    }
    function Add-ScratchCommit { param([string] $File, [string] $Text)
        [IO.File]::WriteAllText((Join-Path $scratch $File), $Text); Invoke-ScratchGit add -- $File; Invoke-ScratchGit commit -q -m "$File $Text"
    }
    try {
        [void](New-Item -ItemType Directory -Path $scratch)
        Invoke-ScratchGit init -q -b base
        Add-ScratchCommit 'a.txt' 'one'
        Invoke-ScratchGit checkout -q -b topic
        Add-ScratchCommit 'b.txt' 'two'
        Add-ScratchCommit 'c.txt' 'three'
        Invoke-ScratchGit checkout -q base
        Add-ScratchCommit 'd.txt' 'other'
        Assert-True (-not (Test-BranchIntegrated $scratch 'topic' 'base').merged) 'unmerged branch is not integrated'
        Invoke-ScratchGit cherry-pick topic~1
        $half = Test-BranchIntegrated $scratch 'topic' 'base'
        Assert-True ((-not $half.merged) -and $half.pending.Count -eq 1) 'half cherry-picked branch is not integrated and names the missing commit'
        Invoke-ScratchGit cherry-pick topic
        $rebased = Test-BranchIntegrated $scratch 'topic' 'base'
        Assert-True ($rebased.merged -and $rebased.upstream -eq 'base' -and $rebased.how -like '*patch-equivalent*') 'rebase-merged branch (new SHAs) is integrated'
        # origin/base carries the rebase merge, the local base lags behind it (nobody pulled yet)
        Invoke-ScratchGit update-ref refs/remotes/origin/base base
        Invoke-ScratchGit reset -q --hard HEAD~2
        $remote = Test-BranchIntegrated $scratch 'topic' 'base'
        Assert-True ($remote.merged -and $remote.upstream -eq 'origin/base') 'integration is judged against origin/<base> when the local base lags'
        Invoke-ScratchGit merge -q --no-ff --no-edit topic
        $ancestor = Test-BranchIntegrated $scratch 'topic' 'base'
        Assert-True ($ancestor.merged -and $ancestor.how -like 'ancestor*') 'merge-committed branch is integrated as an ancestor'
        Assert-True (-not (Test-BranchIntegrated $scratch 'no-such-branch' 'base').merged) 'a missing branch is never integrated'
    } catch {
        Assert-True $false "Test-BranchIntegrated scratch repository: $_"
    } finally {
        if (Test-Path -LiteralPath $scratch) { Remove-Item -LiteralPath $scratch -Recurse -Force }
    }
}

# CLAUDE.md points new sessions at the recovery path
$claude = Read-Text 'CLAUDE.md'
Assert-True ($claude -match '\.ai/PROJECT_STATE\.md' -and $claude -match 'task board') 'CLAUDE.md contains the session start protocol'

if ($failures.Count -gt 0) {
    Write-Error ("{0} orchestration test(s) failed." -f $failures.Count)
    exit 1
}
Write-Host 'All orchestration tests passed.' -ForegroundColor Green
exit 0
