<?php

declare(strict_types=1);

use Onhost\Domain\Identity\StepUp\Totp;

it('matches the RFC 6238 SHA-1 test vectors', function () {
    // RFC 6238 appendix B uses the 20-byte ASCII secret "12345678901234567890" and 8 digits.
    $secret = Totp::base32Encode('12345678901234567890');
    expect(Totp::code($secret, 59, 30, 8))->toBe('94287082')
        ->and(Totp::code($secret, 1111111109, 30, 8))->toBe('07081804')
        ->and(Totp::code($secret, 1234567890, 30, 8))->toBe('89005924');
});

it('round-trips base32', function () {
    $raw = random_bytes(20);
    expect(Totp::base32Decode(Totp::base32Encode($raw)))->toBe($raw);
});

it('verifies within one window of drift and rejects garbage', function () {
    $secret = Totp::generateSecret();
    $now = 1_700_000_000;
    $code = Totp::code($secret, $now);
    expect(Totp::verify($secret, $code, $now + 29))->toBeTrue()
        ->and(Totp::verify($secret, $code, $now + 61))->toBeFalse()
        ->and(Totp::verify($secret, 'abc', $now))->toBeFalse();
});
