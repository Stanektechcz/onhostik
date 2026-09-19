<?php

declare(strict_types=1);

use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;

/*
 * Event payloads are redacted by key name before they are stored (no secret in the outbox). The redactor matches
 * fragments — `auth`, `token`, `ticket`, `signature` … — so an innocent key that merely contains one is masked too,
 * and whoever reads it gets "[redacted]". That is how `author_type` silently sent every reply of support to the
 * staff inbox instead of the customer. A payload key has to survive the outbox, or must not be read from it.
 */

/** @return array<string, list<string>> masked key → the events published with it */
function outboxMaskedPayloadKeys(): array
{
    $redactor = new Redactor;
    $found = [];
    foreach (['domains', 'platform', 'app', 'providers'] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/GenericEvent::of\('([a-z0-9_.]+)'.*$/m", (string) file_get_contents($file->getPathname()), $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                preg_match_all("/'([a-z_0-9]+)' =>/", $call[0], $keys);
                foreach (array_unique($keys[1]) as $key) {
                    if ($redactor->isSecretKey($key)) {
                        $found[$key][] = $call[1];
                    }
                }
            }
        }
    }

    return $found;
}

it('publishes no event with a payload key the outbox would mask', function () {
    expect(outboxMaskedPayloadKeys())->toBe([]);
});

it('keeps who wrote a message readable and still masks what is a credential', function () {
    $outbox = app(OutboxPublisher::class);
    $outbox->publish(GenericEvent::of('test.payload_keys', 'test', 't1', ['author_type' => 'staff', 'author_name' => 'Podpora', 'malware' => 'Eicar-Test-Signature', 'auth_info' => 'Secret-Auth-1', 'token' => 'tok_123456', 'signature' => 'sha256=abcdef']));
    $payload = OutboxMessage::query()->where('name', 'test.payload_keys')->sole()->payload;
    expect($payload)->toMatchArray(['author_type' => 'staff', 'author_name' => 'Podpora', 'malware' => 'Eicar-Test-Signature'])
        ->and($payload['auth_info'])->toBe(Redactor::MASK)->and($payload['token'])->toBe(Redactor::MASK)->and($payload['signature'])->toBe(Redactor::MASK);
});
