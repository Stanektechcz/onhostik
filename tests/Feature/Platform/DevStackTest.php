<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * The development stack stays reproducible and bounded (Brain card H190): a new developer gets the same versions as
 * everybody else, no container can take the whole laptop, and the file carries nothing that is a production secret.
 */

it('pins every image, bounds every container and keeps production secrets out of the development stack', function () {
    $path = base_path('infra/docker-compose.yml');
    $raw = (string) file_get_contents($path);
    $services = Yaml::parse($raw)['services'];
    expect($services)->not->toBe([]);

    foreach ($services as $name => $service) {
        expect(isset($service['mem_limit'], $service['cpus']))->toBeTrue("service {$name} has no memory or CPU ceiling");
        if (isset($service['image'])) {
            $image = (string) $service['image'];
            expect(str_contains($image, ':') && ! str_ends_with($image, ':latest'))->toBeTrue("service {$name} uses a floating image tag ({$image})");
        } else {
            expect($service)->toHaveKey('build'); // built from this repository: the Dockerfile pins its own base image
        }
    }

    // local throw-away defaults only: no key material, no vendor tokens, no value that looks like a real credential
    expect($raw)->not->toMatch('/(sk_live_|ptla_|ptlc_|AKIA[0-9A-Z]{16}|-----BEGIN [A-Z ]*PRIVATE KEY-----|base64:[A-Za-z0-9+\/]{40,})/');
    foreach ($services as $name => $service) {
        foreach ((array) ($service['environment'] ?? []) as $key => $value) {
            if (is_string($key) && preg_match('/(PASSWORD|SECRET|TOKEN|API_KEY)$/', $key) === 1) {
                expect(in_array((string) $value, ['onhost', 'false', ''], true))->toBeTrue("{$name}.{$key} must stay a local default, not a real secret");
            }
        }
    }
});
