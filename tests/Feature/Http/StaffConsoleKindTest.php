<?php

declare(strict_types=1);

// The Pterodactyl console-token answer carries kind "wings"; the staff console used to wait for the relay-internal
// value "wings_ws", so the live console never connected.

function staffConsoleGuard(): string
{
    $view = (string) file_get_contents(resource_path('views/staff-console.blade.php'));
    preg_match('/if \((.*?)\) \{ say\(\'Tato konzole je/', $view, $g);

    return $g[1] ?? '';
}

it('accepts the console kind the Pterodactyl provider returns in the staff console script', function () {
    $provider = (string) file_get_contents(base_path('providers/Pterodactyl/PterodactylGameProvider.php'));
    expect(preg_match('/return \[\'kind\' => \'([a-z_]+)\', \'url\' => \(string\) \$ws/', $provider, $m))->toBe(1);

    expect(staffConsoleGuard())->toContain("'{$m[1]}'");
});

it('still accepts the legacy wings_ws kind and rejects the VNC kind', function () {
    expect(staffConsoleGuard())->toContain("'wings_ws'")->and(staffConsoleGuard())->not->toContain('novnc');
});
