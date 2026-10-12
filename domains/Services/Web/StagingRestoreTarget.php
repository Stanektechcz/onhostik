<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Onhost\Domain\Dns\ServiceDnsCleanup;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Http\EgressGuard;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\Q;
use Throwable;

/**
 * The isolated copy of a verified restore (W2-4), made with the staging facility: a site of its own on the production node
 * (`StagingService::createRestoreTarget`), the backup set restored into it — files and every database dump into databases made
 * for this run — and, before anything may run it, the copy is kept from acting on the world as far as the platform can:
 *
 *  - WordPress: wp-config.php points at the copy's databases, `DISABLE_WP_CRON`, `WP_HTTP_BLOCK_EXTERNAL` (the WordPress HTTP
 *    API reaches no other host: payment gateways, webhooks, licence servers), a must-use plugin that answers `pre_wp_mail` with
 *    false (wp_mail sends nothing), the URLs rewritten to the copy's own name, search engines asked to stay away;
 *  - every site: the panel's cron jobs of production are not copied (the copy is a new site and has none).
 *
 * What it cannot do is written down as such: PHP's own mail() and direct sockets of a plugin that bypasses the WordPress API, and
 * anything an application that is not WordPress does — such an application's configuration still names the production
 * databases, so the copy is not requested over HTTP at all (it would run against live data).
 */
final class StagingRestoreTarget implements RestoreTarget
{
    /** The must-use plugin that stops wp_mail in the copy (WordPress 5.7+ `pre_wp_mail`). */
    public const MAIL_PLUGIN = 'wp-content/mu-plugins/onhost-verified-restore.php';

    private const PLUGIN_BODY = "<?php\n// ONhost verified restore: this copy sends no e-mail through wp_mail.\nadd_filter('pre_wp_mail', '__return_false', PHP_INT_MAX);\n";

    public function __construct(
        private readonly StagingService $staging,
        private readonly ServiceFeatures $features,
        private readonly FinalArchive $archives,
        private readonly HttpFactory $http,
        private readonly EgressGuard $egress,
        private readonly ServiceDnsCleanup $dns,
    ) {}

    public function prepare(Service $production, VerifiedRestoreRun $run, CommandContext $context): array
    {
        try {
            $target = $this->staging->createRestoreTarget($production, $run->id, $context);
        } catch (Throwable $e) {
            return ['service_id' => null, 'domain' => null, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }

        return ['service_id' => $target->id, 'domain' => (string) $target->hostname, 'error' => null];
    }

    public function ready(VerifiedRestoreRun $run): ?bool
    {
        $target = $this->target($run);
        if ($target === null) {
            return false;
        }

        return match (true) {
            $target->state === ServiceStateMachine::ACTIVE => true,
            in_array($target->state, [ServiceStateMachine::FAILED, ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true) => false,
            default => null,
        };
    }

    public function restore(VerifiedRestoreRun $run, Service $production, Backup $backup): array
    {
        $out = [
            'files' => ['status' => VerifiedRestoreRun::NOT_VERIFIED, 'detail' => 'sada neobsahuje soubory webu'],
            'databases' => ['status' => VerifiedRestoreRun::NOT_VERIFIED, 'detail' => 'sada neobsahuje žádnou databázi', 'rows' => []],
            'created' => [],
            'suppression' => ['applied' => ['Cron úlohy panelu z produkce se do kopie nepřenášejí (kopie je nový web bez nich).'], 'not_applied' => []],
            'probe_allowed' => false, 'probe_refusal' => 'kopie nebyla připravena', 'wordpress' => false,
        ];
        $target = $this->target($run);
        if ($target === null) {
            return $out;
        }
        $adapter = $this->features->adapterFor($target);
        [$tools, $ref] = $this->features->toolsFor($target);
        if (! $adapter instanceof WebHostingProvider) {
            $out['probe_refusal'] = 'panel kopie neumí databáze';

            return $out;
        }
        $parts = $this->archives->restorable($backup);
        $work = storage_path('app/onhost-verified-restore-'.Str::random(8));
        if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
            $out['files'] = ['status' => VerifiedRestoreRun::FAIL, 'detail' => 'pracovní adresář nelze vytvořit'];

            return $out;
        }
        $credentials = []; // slug => name/user/password of the copy's database — in memory for this call only
        try {
            if ($parts['databases'] !== []) {
                [$out['databases'], $out['created'], $credentials] = $this->databases($adapter, $tools, $ref, $target, $parts['databases'], $work);
            }
            if ($parts['files'] !== null) {
                $out['files'] = $this->files($tools, $ref, (string) $parts['files'], $work);
            }
            $this->suppress($out, $tools, $ref, $production, $target, $credentials, $parts['databases'] !== []);
        } finally {
            foreach (glob($work.'/*') ?: [] as $left) {
                @unlink($left);
            }
            @rmdir($work);
        }

        return $out;
    }

    public function probe(VerifiedRestoreRun $run): array
    {
        $domain = (string) data_get($run->target, 'domain', '');
        if ($domain === '') {
            return ['status' => null, 'detail' => 'kopie nemá adresu'];
        }
        $last = 'bez odpovědi';
        foreach (['https://', 'http://'] as $scheme) {
            try {
                $response = $this->follow($scheme.$domain.'/', $domain);

                return ['status' => $response->status(), 'detail' => 'GET '.$scheme.$domain.'/ → HTTP '.$response->status()];
            } catch (Throwable $e) {
                $last = $scheme.$domain.'/: '.mb_substr($e->getMessage(), 0, 120);
            }
        }

        return ['status' => null, 'detail' => $last];
    }

    public function login(VerifiedRestoreRun $run, array $identity): array
    {
        if (data_get($run->target, 'wordpress') !== true) {
            return ['status' => VerifiedRestoreRun::NOT_VERIFIED, 'detail' => 'přihlášení umíme ověřit jen u WordPressu'];
        }
        $url = 'https://'.data_get($run->target, 'domain').'/wp-login.php';
        try {
            $response = $this->http->withOptions($this->egress->options($url))->withoutVerifying()->timeout($this->timeout())->connectTimeout(5)
                ->withCookies(['wordpress_test_cookie' => 'WP Cookie check'], (string) data_get($run->target, 'domain'))
                ->asForm()->post($url, ['log' => $identity['user'], 'pwd' => $identity['password'], 'wp-submit' => 'Log In', 'testcookie' => '1']);
        } catch (Throwable $e) {
            return ['status' => VerifiedRestoreRun::FAIL, 'detail' => 'přihlášení: '.mb_substr($e->getMessage(), 0, 120)];
        }
        $loggedIn = collect($response->cookies()->toArray())->contains(fn (array $cookie) => str_starts_with((string) ($cookie['Name'] ?? ''), 'wordpress_logged_in_'));

        return $loggedIn
            ? ['status' => VerifiedRestoreRun::PASS, 'detail' => 'testovací účet se přihlásil (HTTP '.$response->status().')']
            : ['status' => VerifiedRestoreRun::FAIL, 'detail' => 'testovací účet se nepřihlásil (HTTP '.$response->status().')'];
    }

    public function cleanup(VerifiedRestoreRun $run): array
    {
        $target = $this->target($run);
        if ($target === null || $target->state === ServiceStateMachine::TERMINATED) {
            return ['requested' => true, 'detail' => 'kopie už neexistuje'];
        }
        if (Operation::query()->where('service_id', $target->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            return ['requested' => false, 'detail' => 'zakládání kopie ještě běží; úklid počká'];
        }
        if ($target->primaryBinding() === null) { // provisioning never reached the panel: nothing there to remove
            $target->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();

            return ['requested' => true, 'detail' => 'kopie se na panel nedostala; není co mazat'];
        }
        $problems = [];
        $adapter = $this->features->adapterFor($target);
        $ref = $this->features->refFor($target);
        foreach ((array) data_get($run->target, 'databases', []) as $db) {
            try {
                if ($adapter instanceof WebHostingProvider) {
                    $adapter->deleteDatabase($ref, (string) ($db['remote_id'] ?? ''));
                }
            } catch (Throwable $e) {
                $problems[] = 'databáze '.($db['name'] ?? '').': '.mb_substr($e->getMessage(), 0, 100);
            }
        }
        try {
            $result = $adapter instanceof InfrastructureProvider ? $adapter->terminate($ref) : null;
            foreach (array_keys(array_filter((array) ($result?->data['leftover'] ?? []))) as $kind) {
                $problems[] = 'na uzlu zůstalo: '.$kind;
            }
        } catch (Throwable $e) {
            $problems[] = 'web kopie: '.mb_substr($e->getMessage(), 0, 120);
        }
        try {
            $this->dns->run($target, CommandContext::system('verified restore cleanup'), 'verified restore copy removed '.$run->id);
        } catch (Throwable $e) {
            $problems[] = 'DNS kopie: '.mb_substr($e->getMessage(), 0, 100);
        }
        $target->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
        $run->mergeTarget(['cleanup_problems' => $problems]);

        return ['requested' => true, 'detail' => $problems === [] ? 'odstranění vyžádáno' : implode('; ', $problems)];
    }

    public function cleanedUp(VerifiedRestoreRun $run): ?bool
    {
        if ((array) data_get($run->target, 'cleanup_problems', []) !== []) {
            return false;
        }
        $target = $this->target($run);
        if ($target === null || $target->primaryBinding() === null) {
            return true;
        }
        $adapter = $this->features->adapterFor($target);
        if (! $adapter instanceof InfrastructureProvider) {
            return false;
        }
        try {
            return $adapter->getActualState($this->features->refFor($target))->exists ? null : true; // ISPConfig deletes through its job queue
        } catch (Throwable) {
            return null;
        }
    }

    // ── the restore ──────────────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $dumps  slug => path on the backup disk
     * @return array{0: array{status:string, detail:string, rows:list<array<string,mixed>>}, 1: list<array{remote_id:string, name:string}>, 2: array<string, array{name:string, user:string, password:string}>}
     */
    private function databases(WebHostingProvider $adapter, WebToolsProvider $tools, ResourceRef $ref, Service $target, array $dumps, string $work): array
    {
        $rows = [];
        $created = [];
        $credentials = [];
        $failed = false;
        foreach ($dumps as $slug => $path) {
            $slug = (string) $slug;
            $local = $this->pull((string) $path, $work);
            $wanted = RestoreTest::tablesIn($local);
            $name = ServiceFeatures::scopedName($target, 'rv'.Str::lower(Str::random(6)));
            $password = Str::password(20, true, true, false);
            try {
                $made = $adapter->createDatabase($ref, ['name' => $name, 'user' => $name, 'password' => $password, 'charset' => 'utf8mb4']);
                $remoteId = $made->ref instanceof ResourceRef ? (string) $made->ref->remoteId : '';
                if ($remoteId === '') {
                    throw new \RuntimeException('panel nevrátil identifikátor databáze');
                }
                $created[] = ['remote_id' => $remoteId, 'name' => $name];
                $creds = ['name' => $name, 'user' => $name, 'password' => $password];
                $tools->importDatabase($ref, $remoteId, $local, $creds);
                $back = $work.'/back-'.substr(sha1($slug), 0, 8).'.sql';
                $tools->exportDatabase($ref, $remoteId, $back, $creds);
                $got = RestoreTest::tablesIn($back);
                $missing = array_values(array_diff($wanted, $got));
                $verified = $missing === [] && count($wanted) >= RestoreTest::MIN_TABLES;
                $rows[] = ['database' => $slug, 'tables' => count($wanted), 'restored' => count($got), 'verified' => $verified];
                $credentials[$slug] = $creds;
                $failed = $failed || ! $verified;
            } catch (Throwable $e) {
                $rows[] = ['database' => $slug, 'tables' => count($wanted), 'restored' => 0, 'verified' => false, 'error' => mb_substr($e->getMessage(), 0, 120)];
                $failed = true;
            }
        }
        $detail = implode('; ', array_map(fn (array $r) => $r['database'].': '.$r['restored'].'/'.$r['tables'].' tabulek'.(isset($r['error']) ? ' ('.$r['error'].')' : ''), $rows));

        return [['status' => $failed ? VerifiedRestoreRun::FAIL : VerifiedRestoreRun::PASS, 'detail' => $detail, 'rows' => $rows], $created, $credentials];
    }

    /** @return array{status:string, detail:string} */
    private function files(WebToolsProvider $tools, ResourceRef $ref, string $path, string $work): array
    {
        try {
            $local = $this->pull($path, $work);
            $name = 'onhost-verified-'.Str::lower(Str::random(6)).(str_ends_with($path, '.zip') ? '.zip' : '.tar.gz');
            $transport = $tools->transport($ref);
            $transport->upload($name, $local);
            try {
                if (! str_ends_with($name, '.zip') && $tools->shellAvailable($ref)) {
                    // the copy keeps the panel's own .user.ini: production's names production's paths (open_basedir) and would break PHP here
                    $run = $tools->shell($ref)->run('cd '.Q::arg($transport->root()).' && tar xzf '.Q::arg($name).' --exclude=./.user.ini --exclude=.user.ini --exclude=./.onhost', ['timeout' => 900, 'user' => $tools->siteUser($ref)]);
                    if (! $run->ok()) {
                        return ['status' => VerifiedRestoreRun::FAIL, 'detail' => 'rozbalení selhalo: '.mb_substr($run->output(), 0, 160)];
                    }
                } else {
                    $transport->extract($name, '.');
                }
            } finally {
                try {
                    $transport->delete($name);
                } catch (Throwable) {
                    // the archive stays in the copy's root; the copy itself is removed by the cleanup
                }
            }

            return ['status' => VerifiedRestoreRun::PASS, 'detail' => basename($path).' ('.number_format(((int) @filesize($local)) / 1048576, 1).' MB) rozbaleno do kopie'];
        } catch (Throwable $e) {
            return ['status' => VerifiedRestoreRun::FAIL, 'detail' => 'soubory: '.mb_substr($e->getMessage(), 0, 160)];
        }
    }

    /**
     * Keep the copy from acting on the world, and decide whether it may be requested over HTTP at all.
     *
     * @param  array<string, mixed>  $out
     * @param  array<string, array{name:string, user:string, password:string}>  $credentials
     */
    private function suppress(array &$out, WebToolsProvider $tools, ResourceRef $ref, Service $production, Service $target, array $credentials, bool $hasDatabases): void
    {
        $notApplied = [
            'Odchozí spojení z uzlu nejsou pro jednotlivý web blokována: aplikace, která obchází WordPress API (vlastní SMTP klient, přímé volání cURL), může z kopie odeslat e-mail nebo požadavek.',
            'Funkci PHP mail() na sdíleném uzlu platforma pro jeden web vypnout neumí.',
        ];
        try {
            $transport = $tools->transport($ref);
            $isWordPress = $transport->exists('wp-config.php');
        } catch (Throwable $e) {
            $out['suppression']['not_applied'] = [...$notApplied, 'Kopii se nepodařilo prohlédnout: '.mb_substr($e->getMessage(), 0, 120)];
            $out['probe_refusal'] = 'kopii se nepodařilo prohlédnout';

            return;
        }
        if (! $isWordPress) {
            $out['suppression']['not_applied'] = [...$notApplied, 'Aplikace není WordPress: její e-maily, platby a webhooky platforma v kopii potlačit neumí.'];
            if ($hasDatabases) {
                $out['probe_refusal'] = 'aplikace není WordPress a její konfigurace by se připojila k databázím produkce; kopii jsme proto přes HTTP nespouštěli';
            } else {
                $out['probe_allowed'] = true; // files only: nothing in the copy can reach live data
            }

            return;
        }
        if (! $tools->shellAvailable($ref)) {
            $tools->ensureAgent($ref);
            $out['suppression']['not_applied'] = [...$notApplied, 'WP-CLI v kopii zatím není k dispozici; WordPress v kopii nebyl upraven.'];
            $out['probe_refusal'] = 'WordPress v kopii nebylo možné odpojit od produkce; kopii jsme přes HTTP nespouštěli';

            return;
        }
        $wp = $tools->wpCommand($ref).' --path='.Q::arg($transport->root()).' --skip-plugins --skip-themes';
        $run = fn (string $args, int $timeout = 300) => $tools->shell($ref)->run($wp.' '.$args, ['timeout' => $timeout, 'user' => $tools->siteUser($ref)]);
        $config = $transport->read('wp-config.php', 65536);
        $current = preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]/", $config, $m) === 1 ? $m[1] : '';
        $creds = $credentials[FinalArchive::databaseSlug($current)] ?? (count($credentials) === 1 ? reset($credentials) : null);
        if ($creds === null) {
            $out['suppression']['not_applied'] = [...$notApplied, 'wp-config.php jmenuje databázi '.$current.', která v sadě není; WordPress v kopii nebyl připojen k obnovené databázi.'];
            $out['probe_refusal'] = 'WordPress v kopii by se připojil k databázi produkce; kopii jsme přes HTTP nespouštěli';

            return;
        }
        $applied = [];
        $pointed = true;
        foreach (['DB_NAME' => $creds['name'], 'DB_USER' => $creds['user'], 'DB_PASSWORD' => $creds['password']] as $key => $value) {
            $pointed = $run('config set '.$key.' '.Q::arg($value).' --type=constant --quiet')->ok() && $pointed;
        }
        if (! $pointed) {
            $out['suppression']['not_applied'] = [...$notApplied, 'wp-config.php kopie se nepodařilo přepsat na obnovenou databázi.'];
            $out['probe_refusal'] = 'WordPress v kopii se nepodařilo odpojit od databáze produkce; kopii jsme přes HTTP nespouštěli';

            return;
        }
        $applied[] = 'wp-config.php kopie ukazuje na obnovenou databázi, ne na produkci.';
        $constants = [
            'DISABLE_WP_CRON' => 'Plánované úlohy WordPressu (WP-Cron) jsou vypnuté (DISABLE_WP_CRON).',
            'WP_HTTP_BLOCK_EXTERNAL' => 'HTTP API WordPressu nedosáhne na žádný jiný server (WP_HTTP_BLOCK_EXTERNAL): platební brány, webhooky, licenční servery.',
        ];
        foreach ($constants as $constant => $sentence) {
            if ($run('config set '.$constant.' true --raw --type=constant --quiet')->ok()) {
                $applied[] = $sentence;
            } else {
                $notApplied[] = 'Nepodařilo se nastavit '.$constant.'.';
            }
        }
        $run('config set WP_ENVIRONMENT_TYPE staging --type=constant --quiet');
        try {
            if (! $transport->exists('wp-content/mu-plugins')) {
                $transport->mkdir('wp-content/mu-plugins');
            }
            $transport->write(self::MAIL_PLUGIN, self::PLUGIN_BODY);
            $applied[] = 'wp_mail v kopii nic neodešle (must-use plugin, filtr pre_wp_mail).';
        } catch (Throwable $e) {
            $notApplied[] = 'Odesílání e-mailů přes wp_mail se nepodařilo vypnout: '.mb_substr($e->getMessage(), 0, 100);
        }
        $from = (string) $production->spec('domain', $production->hostname);
        $to = (string) $target->hostname;
        foreach (['https://', 'http://'] as $scheme) {
            $run('search-replace '.Q::arg($scheme.$from).' '.Q::arg($scheme.$to).' --all-tables --skip-columns=guid --report-changed-only --format=count', 600);
        }
        $run('option update home '.Q::arg('https://'.$to).' --quiet');
        $run('option update siteurl '.Q::arg('https://'.$to).' --quiet');
        $run('option update blog_public 0 --quiet');
        $applied[] = 'Adresy webu v databázi kopie přepsány na adresu kopie; vyhledávače požádány o neindexování.';
        $out['suppression']['applied'] = [...$out['suppression']['applied'], ...$applied];
        $out['suppression']['not_applied'] = $notApplied;
        $out['probe_allowed'] = true;
        $out['probe_refusal'] = '';
        $out['wordpress'] = true;
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────

    private function target(VerifiedRestoreRun $run): ?Service
    {
        $id = (string) data_get($run->target, 'service_id', '');

        return $id === '' ? null : Service::query()->find($id);
    }

    private function pull(string $path, string $work): string
    {
        $local = $work.'/'.basename($path);
        $stream = $this->archives->disk()->readStream($path);
        $out = is_resource($stream) ? fopen($local, 'wb') : false;
        if (! is_resource($stream) || ! is_resource($out)) {
            throw new \RuntimeException('část zálohy '.basename($path).' nelze ze záložního disku načíst');
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return $local;
    }

    /** GET with redirects followed only on the copy's own name (EgressGuard pins every hop). */
    private function follow(string $url, string $domain): Response
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $response = $this->http->withOptions($this->egress->options($url))->withoutVerifying()->timeout($this->timeout())->connectTimeout(5)
                ->withUserAgent('ONhost-VerifiedRestore/1.0')->get($url);
            $location = (string) $response->header('Location');
            if (! $response->redirect() || $location === '') {
                return $response;
            }
            $next = str_starts_with($location, '/') ? (parse_url($url, PHP_URL_SCHEME).'://'.$domain.$location) : $location;
            if (strtolower((string) parse_url($next, PHP_URL_HOST)) !== strtolower($domain)) {
                return $response; // a redirect away from the copy is its answer; nothing else is requested
            }
            $url = $next;
        }

        return $response;
    }

    private function timeout(): int
    {
        return max(5, (int) config('onhost.verified_restore.probe_timeout_seconds', 20));
    }
}
