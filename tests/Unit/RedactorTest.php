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
