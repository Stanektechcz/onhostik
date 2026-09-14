<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\QueueScaler;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Platform\Commands\CommandContext;

/*
 * Helper workers on the backlog gauge (audit §5i-3): the desired count follows the stale operations, `--apply`
 * starts the missing helpers as short-lived drain workers, a cool-down keeps them from doubling.
 */

afterEach(fn () => QueueScaler::$launcher = null);

it('advises helper workers from the backlog and starts the missing ones once per cool-down', function () {
    config()->set('onhost.provisioning.backlog.threshold', 2);
    config()->set('onhost.provisioning.autoscale.max_helpers', 3);
    $launched = [];
    QueueScaler::$launcher = function (array $command) use (&$launched) {
        $launched[] = $command;
    };
    $scaler = app(QueueScaler::class);
    expect($scaler->advise())->toMatchArray(['stale' => 0, 'desired' => 0, 'running' => 0, 'max' => 3]);
    $this->artisan('onhost:queue:scale --apply')->assertSuccessful();
    expect($launched)->toBe([]);

    $operations = app(OperationService::class);
    foreach (['sc-1', 'sc-2', 'sc-3'] as $key) {
        $operations->start(GameMigrationWorkflow::class, $key, ['target_node_id' => null], CommandContext::system('test'), null, null, null, null, null, false)->forceFill(['next_run_at' => now()->subMinutes(10)])->save();
    }
    expect($scaler->advise())->toMatchArray(['stale' => 3, 'threshold' => 2, 'desired' => 2, 'running' => 0]);
    $this->artisan('onhost:queue:scale')->assertSuccessful()->expectsOutputToContain('2'); // advice only
    expect($launched)->toBe([]);
    $this->artisan('onhost:queue:scale --apply')->assertSuccessful();
    expect($launched)->toHaveCount(2)->and($launched[0])->toContain('queue:work')->toContain('--stop-when-empty')->toContain('--queue='.QueueScaler::QUEUES);
    expect($scaler->advise()['running'])->toBe(2);
    $this->artisan('onhost:queue:scale --apply')->assertSuccessful();
    expect($launched)->toHaveCount(2); // within the cool-down nothing doubles
    expect($scaler->apply(1))->toBe(0); // a lower cap never starts more
    Cache::forget(QueueScaler::HELPERS_KEY);
    expect($scaler->apply())->toBe(2)->and($launched)->toHaveCount(4);
});
