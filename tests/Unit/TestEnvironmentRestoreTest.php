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
