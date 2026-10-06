<?php

declare(strict_types=1);

use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Penpot\PenpotDockerProvider;

/*
 * TASK-0123 test doubles of a Penpot node (shared by tests/Feature/Penpot and tests/Feature/E2E/PenpotFlowTest.php): the SSH shell
 * answers what the adapter asks a node with Docker and Caddy, the SFTP transport keeps the written files in memory. No server.
 */
/** A Penpot node: compose projects with their containers, backups by stamp, every command recorded. */
final class PenpotNodeDouble implements NodeShell
{
    /** @var list<string> */
    public array $commands = [];

    /** @var array<string,string> project => running|stopped */
    public array $stacks = [];

    /** @var array<string,list<string>> project => backup stamps */
    public array $backups = [];

    /** @var array<string,bool> project directories made on the node */
    public array $dirs = [];

    public bool $upFails = false;

    public bool $createProfileFails = false;

    public int $http = 200;

    public function run(string $command, array $options = []): ShellResult
    {
        $this->commands[] = $command;
        $project = preg_match("/ -p '(penpot-[a-z0-9]+)'/", $command, $m) === 1 ? $m[1] : null;
        if (preg_match("/^install -d -m 0750 '\/srv\/onhost-penpot\/(penpot-[a-z0-9]+)'$/", $command, $d) === 1) {
            $this->dirs[$d[1]] = true;

            return new ShellResult(0, '');
        }
        if (str_contains($command, 'docker version')) {
            return new ShellResult(0, "28.5.1\n2.40.0\n");
        }
        if (str_contains($command, 'test -f') && str_contains($command, 'docker-compose.yaml') && str_contains($command, '.port')) {
            return new ShellResult(0, '');
        }
        if (str_contains($command, '/*/.port')) {
            return new ShellResult(0, "19001\n");
        }
        if ($project !== null && str_contains($command, ' up -d --remove-orphans')) {
            if ($this->upFails) {
                return new ShellResult(1, '', 'Error response from daemon: pull access denied');
            }
            $this->stacks[$project] = 'running';

            return new ShellResult(0, '');
        }
        if ($project !== null && str_contains($command, 'ps --all --format json')) {
            if (! isset($this->stacks[$project])) {
                return new ShellResult(0, "--onhost-dir:\n".(isset($this->dirs[$project]) ? 'yes' : 'no')."\n");
            }
            $state = $this->stacks[$project];
            $lines = implode("\n", array_map(fn ($s) => json_encode(['Project' => $project, 'Service' => $s, 'State' => $state]), ['penpot-frontend', 'penpot-backend', 'penpot-exporter', 'penpot-postgres', 'penpot-valkey']));

            return new ShellResult(0, $lines."\n--onhost-dir:\nyes\n");
        }
        if ($project !== null && str_contains($command, 'pg_dump')) {
            preg_match("/B='[^']+\\/([0-9]{8}-[0-9]{6}-[a-z0-9]{4})'/", $command, $s);
            $this->backups[$project][] = $s[1];

            return new ShellResult(0, "524288\n");
        }
        if (str_starts_with($command, 'for d in ')) {
            preg_match('/(penpot-[a-z0-9]+)/', $command, $p);
            $rows = array_map(fn (string $stamp) => $stamp.' 524288 2026-10-06T10:00:00Z', $this->backups[$p[1]] ?? []);

            return new ShellResult(0, implode("\n", $rows));
        }
        if ($project !== null && str_contains($command, 'create-profile')) {
            return $this->createProfileFails ? new ShellResult(1, '', 'profile already exists') : new ShellResult(0, '');
        }
        if ($project !== null && str_ends_with(trim($command), ' stop')) {
            $this->stacks[$project] = 'stopped';

            return new ShellResult(0, '');
        }
        if ($project !== null && str_ends_with(trim($command), ' up -d')) {
            $this->stacks[$project] = 'running';

            return new ShellResult(0, '');
        }
        if (str_contains($command, 'rm -rf --') && preg_match('/(penpot-[a-z0-9]+)/', $command, $r) === 1) {
            unset($this->dirs[$r[1]]);
        }
        if ($project !== null && str_contains($command, 'down --volumes')) {
            unset($this->stacks[$project]);

            return new ShellResult(0, '');
        }
        if (str_contains($command, 'curl ')) {
            return new ShellResult(0, (string) $this->http);
        }

        return new ShellResult(0, '');
    }

    public function available(): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'penpot node double';
    }

    /** @return list<string> */
    public function matching(string $needle): array
    {
        return array_values(array_filter($this->commands, fn (string $c) => str_contains($c, $needle)));
    }
}

/** SFTP double: files kept in memory per root; downloads write the stored bytes (or a stand-in) to the local file. */
final class PenpotFilesDouble implements FileTransport
{
    /** @var array<string,string> absolute path => content */
    public static array $files = [];

    /** @var array<string,int> absolute path => mode */
    public static array $modes = [];

    public function __construct(private readonly string $root) {}

    private function abs(string $path): string
    {
        return rtrim($this->root, '/').'/'.ltrim($path, '/');
    }

    public function list(string $path): array
    {
        return ['path' => $path, 'entries' => []];
    }

    public function read(string $path, int $maxBytes = 20971520): string
    {
        return self::$files[$this->abs($path)] ?? '';
    }

    public function write(string $path, string $content): void
    {
        self::$files[$this->abs($path)] = $content;
    }

    public function upload(string $path, string $localFile): void
    {
        self::$files[$this->abs($path)] = (string) file_get_contents($localFile);
    }

    public function download(string $path, string $localFile): void
    {
        file_put_contents($localFile, self::$files[$this->abs($path)] ?? str_repeat('penpot-backup-bytes ', 8));
    }

    public function delete(string $path, bool $directory = false): void
    {
        unset(self::$files[$this->abs($path)]);
    }

    public function mkdir(string $path): void {}

    public function rename(string $from, string $to): void {}

    public function copy(string $from, string $to): void {}

    public function chmod(string $path, int $mode): void
    {
        self::$modes[$this->abs($path)] = $mode;
    }

    public function archive(array $paths, string $target): void {}

    public function extract(string $archive, string $targetDir): void {}

    public function exists(string $path): bool
    {
        return isset(self::$files[$this->abs($path)]);
    }

    public function root(): string
    {
        return $this->root;
    }
}

/** The Penpot node, its instance, and the product on sale with a price (what the owner's --apply and staff pricing leave). */
function penpotLab(): PenpotNodeDouble
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $_ENV['PENPOT_CZ1_SSH_PRIVATE_KEY'] = 'test-key-not-a-real-one';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'penpot-cz1'], ['provider' => 'penpot', 'name' => 'Penpot node cz1', 'region_code' => 'cz1', 'base_url' => 'ssh://198.51.100.20', 'secret_ref' => 'env://PENPOT_CZ1', 'state' => 'active', 'capabilities' => ['penpot.stack' => true], 'options' => ['ssh_host' => '198.51.100.20', 'public_ipv4' => '198.51.100.20']]);
    Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'penpot01'], ['region_code' => 'cz1', 'role' => 'penpot', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => [], 'tags' => ['public_ipv4' => '198.51.100.20']]);
    if (! Product::query()->where('key', 'penpot')->exists()) {
        CatalogRevisions::createDefined('penpot');
    }
    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    Price::query()->whereIn('plan_version_id', PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('product_id', $product->id))->pluck('id'))->update(['amount_minor' => 34900, 'renewal_amount_minor' => 34900]);
    $product->forceFill(['state' => 'active'])->save();
    $node = new PenpotNodeDouble;
    PenpotDockerProvider::$shellFactory = fn () => $node;
    PenpotDockerProvider::$transportFactory = fn ($instance, string $root) => new PenpotFilesDouble($root);
    PenpotFilesDouble::$files = [];
    PenpotFilesDouble::$modes = [];

    return $node;
}

/** A Penpot ordered by `$org` and driven to the end of its provisioning. */
function penpotProvisioned(Organization $org, CommandContext $ctx): Service
{
    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('product_id', $product->id)->where('key', 'penpot-team'))->firstOrFail();
    $service = app(ServiceService::class)->create($org, $product, $version, [], $ctx);
    driveOperation(Operation::query()->where('service_id', $service->id)->where('kind', 'provision.penpot')->firstOrFail());

    return $service->refresh();
}
