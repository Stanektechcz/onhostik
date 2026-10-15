<?php

declare(strict_types=1);

use Tests\TestCase;

// F1: ONHOST_TEST_CLOCK accepts a bare date (noon UTC, as before) and a full ISO time (nightly-clock.yml, 23:30Z / 00:30Z).
function testClockTargetF1(string $target): string
{
    $method = new ReflectionMethod(TestCase::class, 'timeBombSweepTarget');

    return $method->invoke(null, $target)->format('Y-m-d\TH:i:sP');
}

it('keeps a bare date at noon UTC', function () {
    expect(testClockTargetF1('2027-04-01'))->toBe('2027-04-01T12:00:00+00:00');
});

it('takes an ISO time as given, in UTC', function () {
    expect(testClockTargetF1('2026-10-15T23:30:00Z'))->toBe('2026-10-15T23:30:00+00:00')
        ->and(testClockTargetF1('2026-10-16T00:30:00'))->toBe('2026-10-16T00:30:00+00:00')
        ->and(testClockTargetF1('2026-10-16T02:30:00+02:00'))->toBe('2026-10-16T00:30:00+00:00');
});
