<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Domain\Services\Penpot\PenpotParents;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Penpot\PenpotDockerProvider;

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * TASK-0150: a Penpot node whose deploy user runs rootless Docker (instance option `docker_host`), and the doctor in front of a
 * Penpot node that only sandbox tenants may use.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

/** Records what the adapter sends and hands the command, without the Docker host prefix, to the node double. */
final class PenpotRootlessShellDouble implements NodeShell
{
    /** @var list<string> */
    public array $sent = [];

    public function __construct(private readonly PenpotNodeDouble $node, private readonly string $prefix) {}

    public function run(string $command, array $options = []): ShellResult
    {
        $this->sent[] = $command;

        return $this->node->run(str_starts_with($command, $this->prefix) ? substr($command, strlen($this->prefix)) : $command, $options);
    }

    public function available(): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'rootless penpot node double';
    }
}

function penpotRootlessInstance(?string $dockerHost): ProviderInstance
{
    $instance = ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail();
    $options = (array) $instance->options;
    if ($dockerHost === null) {
        unset($options['docker_host']);
    } else {
        $options['docker_host'] = $dockerHost;
    }
    $instance->forceFill(['options' => $options])->save();
    app(ProviderRegistry::class)->forget($instance);

    return $instance->refresh();
}

it('talks to a rootless Docker of the deploy user through the docker_host option, on every command', function () {
    $node = penpotLab();
    $prefix = "DOCKER_HOST='unix:///run/user/1001/docker.sock'; export DOCKER_HOST; ";
    $shell = new PenpotRootlessShellDouble($node, $prefix);
    PenpotDockerProvider::$shellFactory = fn () => $shell;
    penpotRootlessInstance('unix:///run/user/1001/docker.sock');
    $adapter = app(ProviderRegistry::class)->forKey('penpot-cz1');

    $health = $adapter->health();
    $ref = new ResourceRef('stack', 'penpot-abcdef1234', 'penpot01', ['port' => 19005]);
    $adapter->suspend($ref);
    $adapter->resume($ref);
    $adapter->probe($ref);

    expect($health->healthy)->toBeTrue()->and($shell->sent)->not->toBe([]);
    foreach ($shell->sent as $command) {
        expect($command)->toStartWith($prefix);
    }
});

it('sends no Docker host prefix when the node runs Docker as root, and refuses a docker_host that is not a unix socket path', function () {
    $node = penpotLab();
    penpotRootlessInstance(null);
    app(ProviderRegistry::class)->forKey('penpot-cz1')->health();
    expect($node->commands)->not->toBe([]);
    foreach ($node->commands as $command) {
        expect($command)->not->toContain('DOCKER_HOST');
    }

    foreach (['tcp://203.0.113.9:2375', "unix:///run/user/1001/docker.sock'; rm -rf /; '", 'unix://relative.sock', 'ssh://root@203.0.113.9'] as $bad) {
        $node->commands = [];
        penpotRootlessInstance($bad);
        $health = app(ProviderRegistry::class)->forKey('penpot-cz1')->health();
        expect($health->healthy)->toBeFalse()->and((string) $health->error)->toContain('docker_host')->and($node->commands)->toBe([]);
    }
});

it('does not count a sandbox-only Penpot node as one that runs the Penpot customers order', function () {
    penpotLab();
    $instance = ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail();
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['sandbox' => true])])->save();
    app(ProviderRegistry::class)->forget($instance);

    // what the cart asks for an ordinary customer: no node can run it
    expect(PenpotParents::deliverable(null))->toBeFalse();

    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    $sold = $rows['Penpot is sold only with a Penpot node to run it'];
    expect($sold['ok'])->toBeFalse()
        ->and($sold['blocking'])->toBeFalse()
        ->and($sold['detail'])->toContain('sandbox')
        ->and($sold['remedy'])->not->toBe('');

    // the same node without the sandbox flag is the production node the row asks for
    $instance->forceFill(['options' => array_diff_key((array) $instance->options, ['sandbox' => true])])->save();
    app(ProviderRegistry::class)->forget($instance);
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['Penpot is sold only with a Penpot node to run it']['ok'])->toBeTrue()
        ->and($rows['Penpot is sold only with a Penpot node to run it']['detail'])->toContain('usable Penpot nodes: 1');
    expect(Node::query()->where('name', 'penpot01')->value('state'))->toBe('active');
});
