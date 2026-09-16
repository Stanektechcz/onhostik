[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$phpstan = Join-Path $root 'phpstan.neon'
$failures = [System.Collections.Generic.List[string]]::new()
. (Join-Path $root 'scripts\ai\common.ps1')

function Assert-True {
    param([bool] $Condition, [string] $Message)
    if ($Condition) { Write-Host "PASS  $Message" -ForegroundColor Green; return }
    Write-Host "FAIL  $Message" -ForegroundColor Red
    $failures.Add($Message)
}

$claude = Get-Content -LiteralPath (Join-Path $root '.claude\settings.json') -Raw | ConvertFrom-Json
$gemini = Get-Content -LiteralPath (Join-Path $root '.gemini\settings.json') -Raw | ConvertFrom-Json
$mcp = Get-Content -LiteralPath (Join-Path $root '.mcp.json') -Raw | ConvertFrom-Json
$antigravityMcp = Get-Content -LiteralPath (Join-Path $root '.agents\mcp_config.json') -Raw | ConvertFrom-Json

Assert-True ($claude.permissions.deny -contains 'Read(./.env*)') 'Claude denies environment files'
Assert-True ($claude.hooks.PreToolUse.Count -gt 0) 'Claude registers a pre-tool security hook'
Assert-True ($gemini.advanced.ignoreLocalEnv -eq $true) 'Gemini does not load the project .env'
Assert-True (($gemini.mcp.allowed -join ',') -eq 'serena,context7') 'Gemini allows only Serena and Context7 MCP'
Assert-True (($mcp.mcpServers.psobject.Properties.Name | Sort-Object) -join ',' -eq 'context7,serena') 'Shared MCP config contains only two reviewed servers'
Assert-True (($antigravityMcp.mcpServers.psobject.Properties.Name | Sort-Object) -join ',' -eq 'context7,serena') 'Antigravity uses only the two reviewed MCP servers'
Assert-True (-not ((Get-Content -LiteralPath (Join-Path $root '.agents\mcp_config.json') -Raw) -match '(?i)api[_-]?key|bearer\s+|token\s*[=:]')) 'Antigravity MCP config contains no credential material'
Assert-True (-not ((Get-Content -LiteralPath (Join-Path $root '.mcp.json') -Raw) -match '(?i)api[_-]?key|bearer\s+|token\s*[=:]')) 'MCP config contains no credential material'
Assert-True ($mcp.mcpServers.serena.env.PYTHONUTF8 -eq '1') 'Claude Serena MCP uses UTF-8 on Windows'
Assert-True ($gemini.mcpServers.serena.env.PYTHONUTF8 -eq '1') 'Gemini Serena MCP uses UTF-8 on Windows'
Assert-True ($mcp.mcpServers.serena.command -eq 'powershell.exe' -and $mcp.mcpServers.serena.args -contains 'scripts/ai/serena-mcp.ps1') 'Claude launches Serena through the portable Windows wrapper'
Assert-True ($gemini.mcpServers.serena.command -eq 'powershell.exe' -and $gemini.mcpServers.serena.args -contains 'scripts/ai/serena-mcp.ps1') 'Gemini launches Serena through the portable Windows wrapper'
Assert-True ($antigravityMcp.mcpServers.serena.command -eq 'powershell.exe' -and $antigravityMcp.mcpServers.serena.args -contains 'scripts/ai/serena-mcp.ps1') 'Antigravity launches Serena through the portable Windows wrapper'

$serena = Get-Content -LiteralPath (Join-Path $root '.serena\project.yml') -Raw
Assert-True ($serena -match '(?m)^read_only:\s*true\s*$') 'Serena is retrieval-only'
Assert-True ($serena -match '(?m)^\s*- php\s*$') 'Serena indexes PHP symbols'

$agents = @(Get-ChildItem -LiteralPath (Join-Path $root '.claude\agents') -Filter '*.md')
$skills = @(Get-ChildItem -LiteralPath (Join-Path $root '.claude\skills') -Filter 'SKILL.md' -Recurse)
Assert-True ($agents.Count -eq 6) 'Six focused Claude reviewers are configured'
Assert-True ($skills.Count -eq 7) 'Seven repeatable Claude workflows are configured'

foreach ($file in @($agents + $skills)) {
    $text = Get-Content -LiteralPath $file.FullName -Raw
    Assert-True ($text -match '(?s)^---\s*\r?\nname:\s*[^\r\n]+\r?\ndescription:\s*[^\r\n]+.*?\r?\n---') "$($file.Name) has valid minimal frontmatter"
}

$protect = Join-Path $root '.claude\hooks\protect-files.ps1'
$previous = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
'{"tool_input":{"file_path":"docs/context/PROJECT.md"}}' | & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $protect 2>$null
$safeCode = $LASTEXITCODE
'{"tool_input":{"file_path":".env"}}' | & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $protect 2>$null
$blockedCode = $LASTEXITCODE
$ErrorActionPreference = $previous
Assert-True ($safeCode -eq 0) 'Claude hook allows safe project context'
Assert-True ($blockedCode -eq 2) 'Claude hook blocks credential-bearing paths'

$uvPath = Get-OnhostInstalledExecutable 'uv'
Assert-True ($uvPath -and (Test-Path -LiteralPath $uvPath -PathType Leaf)) 'Tool discovery sees a newly installed winget executable before terminal restart'
$installer = Get-Content -LiteralPath (Join-Path $root 'scripts\ai\install-tools.ps1') -Raw
Assert-True ($installer -match 'tool install --python 3\.13 --managed-python serena-agent') 'Serena installation requires an isolated uv-managed Python'
$phpstanText = Get-Content -LiteralPath $phpstan -Raw
Assert-True ($phpstanText -match '(?m)^\s*paths:' -and $phpstanText -match '(?m)^\s*- app$' -and $phpstanText -match '(?m)^\s*- domains$') 'Larastan has explicit application paths'
$phpstanBaseline = Get-Content -LiteralPath (Join-Path $root 'phpstan-baseline.neon') -Raw
Assert-True (($phpstanBaseline | Select-String -Pattern '(?m)^\s*count:' -AllMatches).Matches.Count -eq 15) 'Larastan baseline records 16 exact existing findings'
$preCommit = Get-Content -LiteralPath (Join-Path $root '.githooks\pre-commit') -Raw
Assert-True ($preCommit -match '\.env\\\.example' -and $preCommit -match 'storage/app/private/\\\.gitignore') 'pre-commit permits tracked secret-free templates'
Assert-True ($preCommit -match 'brain\.ps1 security -Quick -Json') 'pre-commit runs the portable redacted Gitleaks gate'
$testGate = Get-Content -LiteralPath (Join-Path $root 'scripts\ai\test.ps1') -Raw
$securityGate = Get-Content -LiteralPath (Join-Path $root 'scripts\ai\security.ps1') -Raw
Assert-True ($testGate -match 'if \(-not \$Quick -or -not \(Test-Path') 'quick tests preserve the last full test report'
Assert-True ($securityGate -match 'if \(-not \$Quick -or -not \(Test-Path') 'quick security preserves the last full security report'

if ($failures.Count -gt 0) {
    Write-Error ("{0} AI configuration test(s) failed." -f $failures.Count)
    exit 1
}

Write-Host 'All AI configuration tests passed.' -ForegroundColor Green
exit 0
