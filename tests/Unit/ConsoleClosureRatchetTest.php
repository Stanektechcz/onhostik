<?php

declare(strict_types=1);

// W3-7 / N19-B: closure console commands in routes/*.php may only shrink (106 measured at 2f09c6a).
it('does not add closure console commands to routes', function () {
    $total = 0;
    foreach (glob(dirname(__DIR__, 2).'/routes/*.php') ?: [] as $file) {
        $total += preg_match_all('/Artisan::command\(/', (string) file_get_contents($file));
    }

    expect($total)->toBeLessThanOrEqual(106, 'new console commands are classes in app/Console/Commands (N19-B)');
});
