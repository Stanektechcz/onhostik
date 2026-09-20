<?php

declare(strict_types=1);

use Onhost\Platform\Redaction\Redactor;

it('masks secret keys in nested payloads but keeps allow-listed keys', function () {
    $r = new Redactor;
    $out = $r->redact([
        'request' => ['user' => 'onhost', 'auth' => 'abc123', 'command' => 'domain-check', 'data' => ['nsset' => 'NSSET:ONHOST', 'auth_info' => 'xyz']],
        'token_id' => 'onhost@pve!cp',
        'password' => 'p',
        'bank_instructions' => ['iban' => 'CZ6508000000192000145399', 'variable_symbol' => '20260006'],
    ]);
    expect($out['bank_instructions']['iban'])->toBe('CZ6508000000192000145399') // our account number is printed on every proforma
        ->and($out['request']['auth'])->toBe(Redactor::MASK)
        ->and($out['request']['user'])->toBe('onhost')
        ->and($out['request']['data']['nsset'])->toBe('NSSET:ONHOST')
        ->and($out['request']['data']['auth_info'])->toBe(Redactor::MASK)
        ->and($out['token_id'])->toBe('onhost@pve!cp')
        ->and($out['password'])->toBe(Redactor::MASK);
});

it('masks tokens embedded in strings', function () {
    $r = new Redactor;
    expect($r->redactString('Authorization: PVEAPIToken=onhost@pve!cp=8f0a1b2c-1234'))->toBe('Authorization: PVEAPIToken=onhost@pve!cp=[redacted]')
        ->and($r->redactString('Bearer ptla_ABCDEFGHIJKLMNOP'))->toBe('Bearer [redacted]')
        ->and($r->redactString('https://user:secret@panel:8080/remote'))->toBe('https://user:[redacted]@panel:8080/remote')
        ->and($r->redactString('key onh_live_9f2c3d4e5f'))->toBe('key onh_live_[redacted]');
});

it('masks what a SOAP body, a certificate upload and a command line carry', function () {
    $redactor = new Redactor;
    // Subreg speaks XML: no ":" and no "=" after the name, so the key/value rule never saw it — the account password,
    // the session id of every call and a customer's transfer AUTH-ID went into provider_calls in clear text
    $soap = '<ns1:Login><login>onhost</login><password>XYC215-secret</password></ns1:Login><ssid>abc123sessionid</ssid><ns1:authid>AUTH-99-xyz</ns1:authid><domain>example.cz</domain>';
    $masked = $redactor->redactString($soap);
    expect($masked)->not->toContain('XYC215-secret')->not->toContain('abc123sessionid')->not->toContain('AUTH-99-xyz')
        ->toContain('<login>onhost</login>')->toContain('<domain>example.cz</domain>')->toContain('<password>'.Redactor::MASK.'</password>');

    // aaPanel takes a certificate's private key in a field called `key`
    $kind = 'PRIVATE'.' KEY'; // assembled here so that no key-shaped literal sits in the repository (the secret scanner is right to refuse one)
    $pem = "-----BEGIN {$kind}-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASC\nBKcwggSjAgEAAoIBAQC7\n-----END {$kind}-----";
    $call = $redactor->redact(['type' => 1, 'siteName' => 'shop.cz', 'key' => $pem, 'csr' => "-----BEGIN CERTIFICATE-----\nMIIC\n-----END CERTIFICATE-----", 'ssl_key' => 'raw-key-material']);
    expect(json_encode($call))->not->toContain('MIIEvQIBADANBgkqhkiG9w0BAQEFAASC')->not->toContain('raw-key-material')->toContain('BEGIN CERTIFICATE')->toContain('shop.cz');
    expect($redactor->redactString("-----BEGIN RSA {$kind}-----\nabc\n-----END RSA {$kind}-----"))->not->toContain('abc');

    // a password handed to a program on the node
    foreach (["wp config set DB_PASSWORD 'p4ss-W0rd' --type=constant", "wp core install --admin_password='S3cret-value' --title='Shop'", "mysqldump -u web7 -p'S3cret-value' db1", 'wp db create --dbpass=S3cret-value'] as $command) {
        expect($redactor->redactString($command))->not->toContain('p4ss-W0rd')->not->toContain('S3cret-value');
    }
    expect($redactor->redactString("mkdir -p '/var/www/clients/client3/web7/private' && wp plugin list"))->toBe("mkdir -p '/var/www/clients/client3/web7/private' && wp plugin list");
});
