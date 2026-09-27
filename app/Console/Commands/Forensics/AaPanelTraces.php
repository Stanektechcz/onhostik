<?php

declare(strict_types=1);

namespace App\Console\Commands\Forensics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PA-02 for `onhost:forensics:lookback` (TASK-0038): what the platform made an aaPanel node write, judged against the
 * site root. Read-only: SELECTs.
 *
 * Review round 3 (HIGH): the scan read the file actions, cron and `command.run` only, while the root/symlink escape was
 * also open to every other path that makes the node write — an archive or backup restored over the root, an import or a
 * deployment unpacked into it, an FTP account whose home the adapter hands to the panel unjailed, the `.user.ini` the
 * panel saves as root. The lists below are what the code reaches, not a choice: ForensicLookbackTest derives them from
 * the adapter (a change that reaches a site-tree endpoint of the panel, a transport write or a shell run) and from every
 * service action, operation kind and controller that calls one, and fails when they differ.
 */
final class AaPanelTraces
{
    /** The adapter's changes that write on the node (AaPanelWebProvider with AaPanelTools). */
    public const ADAPTER_WRITES = ['backup', 'createCron', 'createDirectory', 'createFtpAccount', 'createNodeProject', 'deleteFile', 'importDatabase', 'installApp', 'restore', 'restoreFromArchive',
        'runCron', 'setDirectives', 'setDocumentRoot', 'setHttp3', 'setPhpSettings', 'setSecurityRules', 'updateCron', 'writeFile'];

    /** Adapter changes that write, left out on purpose: name => why. */
    public const NOT_WRITES = [
        'ensureAgent' => 'prepares the platform\'s agent user (useradd, setfacl on the root the platform made): no customer path; the actions that call it before a command are writes of their own',
    ];

    /** Domain paths that reach a shell run only to read: Class::method => why. */
    public const READ_PATHS = [
        'ServiceFeatures::resources' => 'the listing a page shows (its `tools` part prepares the agent and runs read-only probes)',
        'WordPressService::status' => 'wp-cli reads the version and the plugin list',
    ];

    /**
     * `service.action` operations that make an aaPanel node write, by `desired.action`, with how the stored request is
     * judged: `paths` (the file keys), `command` (a shell line), `ftp` (the home the adapter passes on unjailed),
     * `directives` (the web-server paths in the content), `none` (no path the customer chose is stored).
     */
    public const SERVICE_ACTIONS = [
        'app.install' => 'none', 'archive.restore' => 'none', 'backup' => 'none', 'command.run' => 'command', 'cron.create' => 'command', 'cron.run' => 'none', 'cron.update' => 'command',
        'database.import' => 'none', 'directives.set' => 'directives', 'file.archive' => 'paths', 'file.chmod' => 'paths', 'file.copy' => 'paths', 'file.delete' => 'paths',
        'file.extract' => 'paths', 'file.mkdir' => 'paths', 'file.rename' => 'paths', 'file.save' => 'paths', 'ftp.create' => 'ftp', 'http3.set' => 'none', 'node.create' => 'paths',
        'php.settings' => 'none', 'purge' => 'none', 'reinstall' => 'none', 'restore' => 'none', 'restore.test' => 'none', 'rollback_snapshot' => 'none', 'security.set' => 'none', 'terminate' => 'none',
    ];

    /** Other operation kinds that make an aaPanel node write (ServiceService::actionWorkflowFor), judged alike; `deploy` reads the deploy source. */
    public const OPERATION_KINDS = ['service.deploy' => 'deploy', 'service.import' => 'paths', 'service.staging' => 'none', 'service.wordpress' => 'none', 'web.migrate' => 'none'];

    /** Writes past the bus, known only from their audit row (WebToolsController::upload). */
    public const AUDIT_ACTIONS = ['service.file.upload' => 'paths'];

    /** nginx statements that name a file or folder the web server reads (CustomDirectives refuses them today; history may hold them). */
    private const DIRECTIVE_PATHS = '~(?:^|[;{}\s])(?:root|alias|include|auth_basic_user_file|ssl_certificate|ssl_certificate_key)\s+["\']?([^;\s"\'{}]+)~';

    private const LIST_LIMIT = 25;

    /** @param array{0:CarbonImmutable, 1:CarbonImmutable} $window */
    public function __construct(private readonly array $window) {}

    /** @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>, notes:list<string>} */
    public function outsideRoot(): array
    {
        $instances = DB::table('provider_instances')->where('provider', 'aapanel')->pluck('id')->all();
        $services = DB::table('services')->whereIn('provider_instance_id', $instances)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $roots = DB::table('provider_bindings')->where('remote_type', 'site')->whereIn('provider_instance_id', $instances)->get(['service_id', 'meta'])
            ->mapWithKeys(fn ($b) => [(string) $b->service_id => (string) (self::json($b->meta)['path'] ?? '')])->filter();
        $operations = DB::table('operations')
            ->where(fn ($q) => $q->where(fn ($a) => $a->where('kind', 'service.action')->whereIn('desired->action', array_keys(self::SERVICE_ACTIONS)))->orWhereIn('kind', array_keys(self::OPERATION_KINDS)))
            ->whereBetween('created_at', $this->window)->where(fn ($q) => $q->whereIn('provider_instance_id', $instances)->orWhereIn('service_id', $services))
            ->orderBy('created_at')->get(['id', 'organization_id', 'service_id', 'kind', 'state', 'actor_id', 'desired', 'created_at']);
        $sources = DB::table('deploy_sources')->whereIn('service_id', $operations->where('kind', 'service.deploy')->pluck('service_id')->unique()->all())->get()->keyBy('service_id');
        $hits = [];
        $unjudged = [];
        $counts = ['extractions' => 0, 'no_root' => 0, 'ftp_relative' => 0];
        foreach ($operations as $operation) {
            $desired = self::json($operation->desired);
            $action = (string) ($desired['action'] ?? $operation->kind);
            $judge = $operation->kind === 'service.action' ? self::SERVICE_ACTIONS[$action] : self::OPERATION_KINDS[$operation->kind];
            $root = $roots[$operation->service_id] ?? null;
            $base = ['operation_id' => $operation->id, 'service_id' => $operation->service_id, 'organization_id' => $operation->organization_id, 'action' => $action,
                'state' => $operation->state, 'actor_id' => $operation->actor_id, 'at' => self::iso($operation->created_at)];
            if ($judge === 'none' || $judge === 'deploy') { // the node acted as root or as the site agent on a path nobody stored
                $label = $operation->kind === 'service.action' ? $action : $operation->kind;
                $unjudged[$label] = ($unjudged[$label] ?? 0) + 1;
            }
            $hit = match ($judge) {
                'command' => $this->command($desired, $root, $action === 'command.run' ? 'command_reaches_outside_root' : 'cron_reaches_outside_root', $counts),
                'ftp' => $this->ftpHome((string) ($desired['path'] ?? ''), $root, $counts),
                'directives' => $this->directives((string) ($desired['content'] ?? ''), $root),
                'deploy' => $this->deploySource($sources[$operation->service_id] ?? null, $root),
                'paths' => $this->paths($desired, $root, $operation->state),
                default => null,
            };
            if ($action === 'file.extract' && $operation->state === 'SUCCEEDED') {
                $counts['extractions']++;
            }
            if ($hit !== null) {
                $hits[] = ['kind' => $hit['kind'], 'confidence' => $hit['confidence']] + $base + array_diff_key($hit, ['kind' => 1, 'confidence' => 1]);
            }
        }
        [$uploadHits, $uploads] = $this->uploads($services, $roots->all());

        return ['checked' => ['operations' => $operations->count(), 'audit_events' => $uploads, 'provider_bindings' => $roots->count(), 'deploy_sources' => $sources->count()],
            'hits' => [...$hits, ...$uploadHits],
            'unknowns' => $this->unknowns($counts, $unjudged, $services, $operations->pluck('service_id')->map(fn ($id) => (string) $id)->unique()->values()->all()),
            'notes' => ['Read: service actions '.implode(', ', array_keys(self::SERVICE_ACTIONS)).'; operation kinds '.implode(', ', array_keys(self::OPERATION_KINDS)).'; audited '.implode(', ', array_keys(self::AUDIT_ACTIONS)).' — derived from the code (ForensicLookbackTest).']];
    }

    /**
     * @param  array<string,mixed>  $desired
     * @param  array{extractions:int, no_root:int, ftp_relative:int}  $counts
     * @return array<string,mixed>|null
     */
    private function command(array $desired, ?string $root, string $kind, array &$counts): ?array
    {
        // review round 1: `command.run` runs a shell as the site agent (ServiceActionWorkflow) — the same `ln -s` and `..` as a cron
        $command = (string) ($desired['command'] ?? '');
        if ($root === null && str_contains($command, '/')) {
            $counts['no_root']++;
        }
        $trace = SitePaths::commandTrace($command, (string) ($desired['cwd'] ?? ''), $root);

        return $trace['outside'] !== [] || $trace['climbs'] || $trace['symlink'] // only the offending paths leave the report: a command may carry keys in a URL
            ? ['kind' => $kind, 'confidence' => 'possible', 'paths' => array_map(fn ($p) => self::clean($p), $trace['outside']), 'symlink' => $trace['symlink'], 'climbs' => $trace['climbs']]
            : null;
    }

    /**
     * An FTP account's home went to the panel as the customer wrote it (ServiceService strips the leading slash only;
     * AaPanelWebProvider::createFtpAccount passes it unjailed). A `..`, or a path that starts where the root starts
     * (`www/…` beside `/www/wwwroot/site`) and is not under it, is a way out; a plain relative one lands wherever the
     * panel resolves it — only the node shows that.
     *
     * @param  array{extractions:int, no_root:int, ftp_relative:int}  $counts
     * @return array<string,mixed>|null
     */
    private function ftpHome(string $path, ?string $root, array &$counts): ?array
    {
        if ($path === '') {
            return null;
        }
        $absolute = '/'.ltrim($path, '/');
        $top = '/'.explode('/', trim((string) $root, '/'))[0].'/';
        $outside = SitePaths::outsideRoot($path, $root) || ($root !== null && str_starts_with($absolute.'/', $top) && SitePaths::outsideRoot($absolute, $root));
        if (! $outside) {
            $counts['ftp_relative']++;

            return null;
        }

        return ['kind' => 'ftp_home_outside_root', 'confidence' => 'possible', 'paths' => [self::clean($path)]];
    }

    /** @return array<string,mixed>|null */
    private function directives(string $content, ?string $root): ?array
    {
        preg_match_all(self::DIRECTIVE_PATHS, $content, $found);
        $outside = array_values(array_filter($found[1], fn (string $p) => ! str_starts_with($p, '$') && (str_starts_with($p, '/') ? SitePaths::outsideRoot($p, $root) : SitePaths::outsideRoot($p, null))));

        return $outside === [] ? null : ['kind' => 'directive_reaches_outside_root', 'confidence' => 'possible', 'paths' => array_map(fn ($p) => self::clean($p), $outside)];
    }

    /**
     * A deployment runs the source's build command and hooks as the site agent and serves `deploy_path` of the clone.
     * The source keeps only today's values (a standing state), and the repository's own symlinks are not stored.
     *
     * @return array<string,mixed>|null
     */
    private function deploySource(?object $source, ?string $root): ?array
    {
        if ($source === null) {
            return null;
        }
        $paths = [];
        $symlink = false;
        $climbs = (string) ($source->deploy_path ?? '') !== '' && SitePaths::outsideRoot((string) $source->deploy_path, null);
        foreach ([(string) ($source->build_command ?? ''), ...array_map('strval', array_filter((array) self::json($source->hooks), 'is_string'))] as $command) {
            $trace = SitePaths::commandTrace($command, '', $root);
            [$paths, $symlink, $climbs] = [[...$paths, ...$trace['outside']], $symlink || $trace['symlink'], $climbs || $trace['climbs']];
        }

        return $paths !== [] || $symlink || $climbs
            ? ['kind' => 'deploy_reaches_outside_root', 'confidence' => 'possible', 'paths' => array_map(fn ($p) => self::clean($p), array_values(array_unique($paths))), 'symlink' => $symlink, 'climbs' => $climbs, 'deploy_source_now' => true]
            : null;
    }

    /**
     * @param  array<string,mixed>  $desired
     * @return array<string,mixed>|null
     */
    private function paths(array $desired, ?string $root, string $state): ?array
    {
        $outside = array_values(array_filter(SitePaths::filePaths($desired), fn (string $p) => SitePaths::outsideRoot($p, $root)));

        return $outside === [] ? null : ['kind' => 'path_outside_root', 'confidence' => $state === 'SUCCEEDED' ? 'confirmed' : 'attempt', 'paths' => array_map(fn ($p) => self::clean($p), $outside)];
    }

    /**
     * @param  list<string>  $services
     * @param  array<string,string>  $roots
     * @return array{0: list<array<string,mixed>>, 1: int}
     */
    private function uploads(array $services, array $roots): array
    {
        $hits = [];
        $uploads = 0;
        if ($services === []) {
            return [[], 0];
        }
        foreach (DB::table('audit_events')->whereIn('action', array_keys(self::AUDIT_ACTIONS))->whereBetween('created_at', $this->window)->lazyById(1000, 'id') as $row) {
            if (! in_array((string) $row->resource_id, $services, true)) {
                continue;
            }
            $uploads++;
            $path = (string) (self::json($row->detail)['path'] ?? '');
            if ($path !== '' && SitePaths::outsideRoot($path, $roots[$row->resource_id] ?? null)) {
                $hits[] = ['kind' => 'path_outside_root', 'confidence' => 'confirmed', 'audit_event_id' => $row->id, 'service_id' => $row->resource_id, 'organization_id' => $row->organization_id,
                    'action' => 'file.upload', 'actor_id' => $row->actor_id, 'at' => self::iso($row->created_at), 'paths' => [self::clean($path)]];
            }
        }

        return [$hits, $uploads];
    }

    /**
     * @param  array{extractions:int, no_root:int, ftp_relative:int}  $counts
     * @param  array<string,int>  $unjudged
     * @param  list<string>  $services
     * @param  list<string>  $active
     * @return list<string>
     */
    private function unknowns(array $counts, array $unjudged, array $services, array $active): array
    {
        $unknowns = [];
        if ($counts['extractions'] > 0) {
            $unknowns[] = "{$counts['extractions']} archive extraction".($counts['extractions'] === 1 ? '' : 's').' ran on aaPanel: what the archive held (a symlink, an absolute or ../ entry) is not stored — only the node shows it.';
        }
        if ($unjudged !== []) {
            $unknowns[] = array_sum($unjudged).' operation(s) made an aaPanel node write where the platform stored no path to judge — it acted as root or as the site agent, and a symlink inside the root decides where that landed: '
                .implode(', ', array_map(fn (string $label, int $n) => "{$label} ×{$n}", array_keys($unjudged), $unjudged)).'.';
        }
        if ($counts['ftp_relative'] > 0) {
            $unknowns[] = "{$counts['ftp_relative']} FTP account".($counts['ftp_relative'] === 1 ? ' was' : 's were').' made on aaPanel with a relative home path the adapter passed to the panel unjailed: where the panel put it is only on the node.';
        }
        if ($counts['no_root'] > 0) {
            $unknowns[] = "{$counts['no_root']} terminal or scheduled command(s) belong to a service whose site root is not stored: their absolute paths cannot be judged.";
        }
        if ($services !== []) { // review round 1: never CLEAN while aaPanel sites exist — a symlink made by SFTP, SSH or the site's own code leaves no row
            $unknowns[] = count($services).' aaPanel service(s): a symlink or a path outside the root made through SFTP, SSH, the site\'s own code or an archive leaves no row here, only on the node — the aaPanel tenancy dry-run of TASK-0034 (operator:aapanel:tenancy, read-only) looks there. Those with writes in the window first: '
                .self::ids(array_values(array_unique([...$active, ...$services]))).'.';
        }

        return $unknowns;
    }

    /** @return array<string,mixed> */
    private static function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private static function iso(mixed $moment): ?string
    {
        return $moment === null ? null : CarbonImmutable::parse((string) $moment)->toIso8601String();
    }

    /** @param list<string> $ids */
    private static function ids(array $ids): string
    {
        return implode(', ', array_slice($ids, 0, self::LIST_LIMIT)).(count($ids) > self::LIST_LIMIT ? ' … (+'.(count($ids) - self::LIST_LIMIT).')' : '');
    }

    /** Customer text in the report loses control and bidi characters and stays short: it cannot drive the operator's terminal. */
    private static function clean(string $text): string
    {
        return mb_substr((string) (preg_replace('/[\p{Cc}\p{Cf}]/u', '?', $text) ?? '?'), 0, 200);
    }
}
