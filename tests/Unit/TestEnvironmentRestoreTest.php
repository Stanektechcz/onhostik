<?php

it('undoes the environment keys a test added, changed or removed', function () {
    putenv('ONHOST_T18_KEPT=before');
    $_ENV['ONHOST_T18_KEPT'] = 'before';
    $baseline = ['env' => $_ENV, 'process' => getenv()];

    putenv('ONHOST_T18_ADDED=secret');
    $_ENV['ONHOST_T18_ADDED'] = 'secret';
    putenv('ONHOST_T18_KEPT=after');
    $_ENV['ONHOST_T18_KEPT'] = 'after';
    restoreEnvironment($baseline);

    expect(getenv('ONHOST_T18_ADDED'))->toBeFalse()
        ->and($_ENV)->not->toHaveKey('ONHOST_T18_ADDED')
        ->and(getenv('ONHOST_T18_KEPT'))->toBe('before')
        ->and($_ENV['ONHOST_T18_KEPT'])->toBe('before');

    putenv('ONHOST_T18_KEPT');
    unset($_ENV['ONHOST_T18_KEPT']);
    restoreEnvironment($baseline);

    expect(getenv('ONHOST_T18_KEPT'))->toBe('before')
        ->and($_ENV['ONHOST_T18_KEPT'])->toBe('before');

    putenv('ONHOST_T18_KEPT');
    unset($_ENV['ONHOST_T18_KEPT']);
});

it('handles environment names made only of digits, which PHP turns into integer array keys', function () {
    $baseline = ['env' => $_ENV, 'process' => getenv()];

    putenv('70018=added');
    $_ENV['70018'] = 'added';
    expect(array_key_exists(70018, getenv()))->toBeTrue(); // the integer key that made getenv($key) fatal under strict_types

    restoreEnvironment($baseline);

    expect(getenv('70018'))->toBeFalse()
        ->and($_ENV)->not->toHaveKey('70018');

    // a digits-only name that existed before the test comes back when the test removed it
    putenv('70019=kept');
    $_ENV['70019'] = 'kept';
    $withKept = ['env' => $_ENV, 'process' => getenv()];
    putenv('70019');
    unset($_ENV['70019']);
    restoreEnvironment($withKept);

    expect(getenv('70019'))->toBe('kept')
        ->and($_ENV['70019'])->toBe('kept');

    putenv('70019');
    unset($_ENV['70019']);
});
