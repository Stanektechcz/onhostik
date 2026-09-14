$ErrorActionPreference = 'Stop'
$payload = [Console]::In.ReadToEnd()
$normalized = $payload.Replace('/', '\').ToLowerInvariant()

$blocked = @(
    '(?:^|[\\"''])\.env(?:\.|[\\"''\s]|$)',
    'auth\.json',
    'storage\\app\\private',
    'storage\\logs',
    'database\\[^\\"'']+\.sqlite',
    'infra\\edge\\[^\\"'']+\.generated',
    '(?:id_rsa|id_ed25519|\.pfx|\.p12|private[_-]?key)'
)

foreach ($pattern in $blocked) {
    if ($normalized -match $pattern) {
        [Console]::Error.WriteLine('Blocked: ONHOST private or credential-bearing path. Use a sanitized fixture or documented schema instead.')
        exit 2
    }
}

exit 0
