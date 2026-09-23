[CmdletBinding()]
param()

# Guards the AI orchestration layer (.ai/, .claude/agents, .claude/skills, gate/task scripts) against drift.
# Read-only: it never runs the gate, starts tasks or writes coordination state.

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

# CLAUDE.md points new sessions at the recovery path
$claude = Read-Text 'CLAUDE.md'
Assert-True ($claude -match '\.ai/PROJECT_STATE\.md' -and $claude -match 'task board') 'CLAUDE.md contains the session start protocol'

if ($failures.Count -gt 0) {
    Write-Error ("{0} orchestration test(s) failed." -f $failures.Count)
    exit 1
}
Write-Host 'All orchestration tests passed.' -ForegroundColor Green
exit 0
