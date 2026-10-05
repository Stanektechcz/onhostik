<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\VerifyEmailNotification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;

/*
 * Shared support for the end-to-end flows (E1 sign-up to web hosting, and the E-flows after it).
 *
 * Required by the flow files (not autoloaded): `require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';`.
 * Every function name starts with `e2e` — Pest files share one global function namespace.
 *
 *   e2eSeedPlatform()              catalogue, tax rules, legal entity, DNS templates (a flow starts from an empty database)
 *   e2eWebInfrastructure()         region cz1 + an active ISPConfig instance with one web node (no credentials of a real panel)
 *   e2eIspPanel(array &$panel)     a STATEFUL ISPConfig: clients, web domains and the job queue live in $panel; a function it does
 *                                  not know is a fault, so a call the platform should not make fails the flow out loud
 *   e2eComgateEnvironment()        merchant + secret for the Comgate adapter, callback allow-list opened (undone by Pest.php afterEach)
 *   e2eComgateFake(array &$gate)   Comgate create + status endpoints; $gate['status'] decides what the status call answers
 *   e2eComgateCallback($test,...)  the gateway's callback, posted to the REAL webhook route with the shared secret
 *   e2eConsents()                  the consent set the checkout asks a web order for
 *   e2eVerificationToken(...)      the single-use token out of the (faked) verification mail
 *   e2eSignUp($test, $email)      register + verify over the real routes; [user, organization, password]
 *   e2eStepUp($test, $password)   a fresh step-up through POST /v1/auth/step-up, with the session cookie carried on
 *   e2eTopUp($test, $gate, $czk)   credit the wallet: POST /v1/wallet/topup + the gateway callback
 *   e2eHeaders()                   headers of a browser session talking to /v1 (stateful cookie auth + a fresh Idempotency-Key)
 */

const E2E_ISP = 'shared01.mgmt.test:8080/remote/json.php';

/** Catalogue, tax rules, legal entity and DNS templates. */
function e2eSeedPlatform(): void
{
    test()->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
}

/** Region cz1, one active ISPConfig instance with a qualifying web node. @return array{0:ProviderInstance,1:Node} */
function e2eWebInfrastructure(): array
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $isp = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true, 'mail.create' => true], 'options' => ['server_id' => 1, 'verify_tls' => false], 'adapter_version' => '1.0.0']);
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $isp->id, 'name' => 'shared01'], ['region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => ['cpu_pct' => 20, 'ram_used_mb' => 20000, 'disk_used_gb' => 200], 'tags' => ['public_ipv4' => '192.0.2.10']]);

    return [$isp, $node];
}

/**
 * A stateful ISPConfig remote API. State keys it keeps: `clients` (id => row), `sites` (id => row), `calls` (function names in
 * order), `queue` (job-queue count, drains by itself), `refuse` (functions that always fault). Nothing of the reply is canned:
 * a site the platform adds is the site it reads back, a client added once is found the second time.
 *
 * @param  array<string,mixed>  $panel
 */
function e2eIspPanel(array &$panel): void
{
    $panel += ['clients' => [], 'sites' => [], 'calls' => [], 'queue' => 0, 'refuse' => []];
    $envelope = fn (mixed $response, string $code = 'ok', string $message = ''): array => ['code' => $code, 'message' => $message, 'response' => $response];
    Http::fake(function (Request $request) use (&$panel, $envelope) {
        if (! str_contains($request->url(), E2E_ISP)) {
            return null; // not ours: another fake answers, or preventStrayRequests() fails the call
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $data = $request->data();
        $panel['calls'][] = $function;
        if (in_array($function, $panel['refuse'], true)) {
            return Http::response($envelope(false, 'remote_fault', 'the panel refused this'));
        }
        $key = $data['primary_id'] ?? null;
        $params = (array) ($data['params'] ?? []);

        return Http::response(match ($function) {
            'login' => $envelope('sess-e2e'),
            'client_get_by_username' => (function () use (&$panel, $data, $envelope) {
                foreach ($panel['clients'] as $client) {
                    if ($client['username'] === ($data['username'] ?? null)) {
                        return $envelope($client);
                    }
                }

                return $envelope(false, 'remote_fault', 'No user account found.'); // ISPConfig 3.2 answers an unknown name with a fault
            })(),
            'client_add' => (function () use (&$panel, $params, $envelope) {
                $id = count($panel['clients']) + 12;
                $panel['clients'][$id] = ['client_id' => $id, 'sys_groupid' => $id] + $params;

                return $envelope($id);
            })(),
            'client_update' => (function () use (&$panel, $data, $params, $envelope) {
                $id = (int) ($data['client_id'] ?? 0);
                $panel['clients'][$id] = array_merge($panel['clients'][$id] ?? [], $params);

                return $envelope(1);
            })(),
            'sites_web_domain_get' => (function () use (&$panel, $key, $envelope) {
                if (is_array($key)) { // a lookup by column (the platform asks "is there already a site with this name?")
                    return $envelope(array_values(array_filter($panel['sites'], fn (array $site) => collect($key)->every(fn ($value, $column) => (string) ($site[$column] ?? '') === (string) $value))));
                }

                return $envelope($panel['sites'][(int) $key] ?? false);
            })(),
            'sites_web_domain_add' => (function () use (&$panel, $data, $params, $envelope) {
                $id = count($panel['sites']) + 77;
                $client = (int) ($data['client_id'] ?? 0);
                $panel['sites'][$id] = ['domain_id' => $id, 'sys_groupid' => $client, 'system_user' => "web{$id}", 'system_group' => "client{$client}", 'document_root' => "/var/www/clients/client{$client}/web{$id}"] + $params;
                $panel['queue'] = 2; // the panel's own job queue works the new vhost off

                return $envelope($id);
            })(),
            'sites_web_domain_update' => (function () use (&$panel, $key, $params, $envelope) {
                $panel['sites'][(int) $key] = array_merge($panel['sites'][(int) $key] ?? [], $params);

                return $envelope(1);
            })(),
            'sites_web_domain_delete' => (function () use (&$panel, $key, $envelope) {
                unset($panel['sites'][(int) $key]);

                return $envelope(1);
            })(),
            'monitor_jobqueue_count' => (function () use (&$panel, $envelope) {
                $now = $panel['queue'];
                $panel['queue'] = max(0, $panel['queue'] - 1);

                return $envelope($now);
            })(),
            // listings that answer "nothing here yet" for a fresh site
            'sites_database_get', 'sites_ftp_user_get', 'sites_shell_user_get', 'sites_cron_get', 'sites_web_aliasdomain_get', 'sites_web_subdomain_get', 'sites_web_folder_get', 'sites_web_folder_user_get',
            'sites_database_user_get', 'mail_domain_get', 'mail_user_get', 'sites_web_domain_backup_list' => $envelope([]),
            default => $envelope(false, 'remote_fault', "nothing here answers {$function}"),
        });
    });
}

/** Merchant + secret for the Comgate adapter; the callback allow-list is opened for the test. Pest.php removes the environment afterwards. */
function e2eComgateEnvironment(): void
{
    putenv('COMGATE_MERCHANT=123456');
    putenv('COMGATE_SECRET=topsecret');
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'topsecret';
    config(['onhost.payments.comgate.callback_allowlist' => []]);
}

/**
 * The two Comgate endpoints: create a payment and read its status. `$gate['status']` (default PAID) is read on every status
 * call, so a test flips it by reference; `$gate['total']` is what the status call says was paid; `$gate['trans_id']` the id.
 *
 * @param  array<string,mixed>  $gate
 */
function e2eComgateFake(array &$gate): void
{
    $gate += ['status' => 'PAID', 'trans_id' => 'E2E1-AAAA-0001', 'total' => 0, 'created' => 0, 'status_calls' => 0];
    Http::fake(function (Request $request) use (&$gate) {
        $url = $request->url();
        if (str_contains($url, 'payments.comgate.cz/v2.0/payment/transId/')) {
            $gate['status_calls']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => $gate['trans_id'], 'status' => $gate['status'], 'price' => $gate['total'], 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'x']);
        }
        if (str_ends_with($url, 'payments.comgate.cz/v2.0/payment')) {
            $gate['created']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => $gate['trans_id'], 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id='.$gate['trans_id']]);
        }

        return null; // other fakes (the panel) get their turn: the first one that answers wins
    });
}

/** The gateway's callback to the REAL webhook route (it carries the shared secret, then asks the gateway for the truth). */
function e2eComgateCallback(object $test, string $transId, int $total, string $status = 'PAID'): TestResponse
{
    return $test->postJson('/v1/webhooks/payments/comgate', ['transId' => $transId, 'status' => $status, 'price' => $total, 'curr' => 'CZK', 'secret' => 'topsecret']);
}

/** The consent set a web order needs at checkout. */
function e2eConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
}

/** The single-use token out of the verification mail (Notification::fake() must be active). */
function e2eVerificationToken(object $user): string
{
    $token = null;
    Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return (string) $token;
}

/** Browser-session headers for /v1 (the cookie session is stateful for this Referer) plus a fresh Idempotency-Key for the next write. */
function e2eHeaders(string $label): array
{
    return ['Referer' => 'http://localhost', 'Idempotency-Key' => 'e2e-'.$label.'-'.bin2hex(random_bytes(6))];
}

/**
 * A mail node on the same ISPConfig instance as the web node (a mail plan is placed on a node with the `mail` role).
 *
 * @return array{0:ProviderInstance,1:Node}
 */
function e2eMailInfrastructure(): array
{
    [$isp] = e2eWebInfrastructure();
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $isp->id, 'name' => 'mail01'], ['region_code' => 'cz1', 'role' => 'mail', 'state' => 'active', 'capacity' => ['mailboxes' => 5000], 'usage' => []]);

    return [$isp, $node];
}

/**
 * The mail half of the stateful ISPConfig. Register it BEFORE `e2eIspPanel()` on the same `$panel`: it answers the mail functions
 * and passes (null) on everything else, so the login, the client and the job queue stay with `e2eIspPanel`.
 *
 * State it keeps: `mail_domains`, `mail_users`, `mail_aliases` (id => row; forwards live in `mail_aliases` with type `forward`),
 * `mail_updates` (every mail_user_update: id + params, so a test can see what a password change really sent); `calls` is kept by e2eIspPanel.
 * Rows put into the state by a test before the flow starts are the panel's HISTORICAL mail: nothing the platform made.
 * An array lookup by `email`/`source` with a leading `%` is a suffix match, as ISPConfig's LIKE is.
 *
 * @param  array<string,mixed>  $panel
 */
function e2eIspMail(array &$panel): void
{
    $panel += ['mail_domains' => [], 'mail_users' => [], 'mail_aliases' => [], 'mail_updates' => [], 'calls' => [], 'queue' => 0, 'clients' => []];
    $envelope = fn (mixed $response): array => ['code' => 'ok', 'message' => '', 'response' => $response];
    $match = function (array $rows, mixed $key): array {
        if (! is_array($key)) {
            return [];
        }

        return array_values(array_filter($rows, function (array $row) use ($key) {
            foreach ($key as $column => $wanted) {
                $have = mb_strtolower((string) ($row[$column] ?? ''));
                $wanted = mb_strtolower((string) $wanted);
                if (str_starts_with($wanted, '%') ? ! str_ends_with($have, substr($wanted, 1)) : $have !== $wanted) {
                    return false;
                }
            }

            return true;
        }));
    };
    Http::fake(function (Request $request) use (&$panel, $envelope, $match) {
        if (! str_contains($request->url(), E2E_ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (! str_starts_with($function, 'mail') && $function !== 'mailquota_get_by_user') {
            return null;
        }
        $data = $request->data(); // (the call itself is logged by e2eIspPanel, which sees every request of the panel too)
        $key = $data['primary_id'] ?? null;
        $params = (array) ($data['params'] ?? []);
        $client = (int) ($data['client_id'] ?? 0);
        $group = (int) ($panel['clients'][$client]['sys_groupid'] ?? $client);
        $add = function (string $table, string $idColumn, int $base, array $row) use (&$panel, $group): int {
            $id = max([$base, ...array_map('intval', array_keys($panel[$table]))]) + 1; // never a number a deleted row had left to a living one
            $panel[$table][$id] = [$idColumn => $id, 'sys_groupid' => $group] + $row;
            $panel['queue'] = 2; // the panel's job queue works the change off

            return $id;
        };

        return Http::response($envelope(match ($function) {
            'mail_domain_get' => is_array($key) ? $match($panel['mail_domains'], $key) : ($panel['mail_domains'][(int) $key] ?? false),
            'mail_domain_add' => $add('mail_domains', 'domain_id', 900, $params),
            'mail_domain_delete' => (function () use (&$panel, $key) {
                unset($panel['mail_domains'][(int) $key]);

                return 1;
            })(),
            'mail_user_get' => is_array($key) ? $match($panel['mail_users'], $key) : ($panel['mail_users'][(int) $key] ?? false),
            'mail_user_add' => $add('mail_users', 'mailuser_id', 5000, $params),
            'mail_user_update' => (function () use (&$panel, $key, $params) {
                $panel['mail_updates'][] = ['id' => (int) $key, 'params' => $params];
                $panel['mail_users'][(int) $key] = array_merge($panel['mail_users'][(int) $key] ?? [], $params);
                $panel['queue'] = 2;

                return 1;
            })(),
            'mail_user_delete' => (function () use (&$panel, $key) {
                unset($panel['mail_users'][(int) $key]);
                $panel['queue'] = 2;

                return 1;
            })(),
            'mail_alias_get', 'mail_forward_get' => $match(array_filter($panel['mail_aliases'], fn (array $row) => $row['type'] === ($function === 'mail_alias_get' ? 'alias' : 'forward')), $key),
            'mail_alias_add', 'mail_forward_add' => $add('mail_aliases', 'forwarding_id', 300, $params),
            'mail_alias_delete', 'mail_forward_delete' => (function () use (&$panel, $key) {
                unset($panel['mail_aliases'][(int) $key]);
                $panel['queue'] = 2;

                return 1;
            })(),
            default => [], // the other mail listings (catch-all, filters, lists, policies, quota) have nothing yet
        }));
    });
}

/**
 * A PowerDNS that accepts whatever the platform publishes and remembers it: `$dns['calls']` = "METHOD path" in order. The zone the
 * platform keeps is its own record of truth; this is only the node it is pushed to.
 *
 * @param  array<string,mixed>  $dns
 */
function e2ePowerDnsFake(array &$dns): void
{
    $dns += ['calls' => []];
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    Http::fake(function (Request $request) use (&$dns) {
        if (! str_contains($request->url(), 'pdns.mgmt.test')) {
            return null;
        }
        $dns['calls'][] = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        return Http::response(['name' => 'zone.', 'serial' => 2, 'rrsets' => []]);
    });
}

/**
 * Sign up and verify an account over the real routes (Notification::fake() must be active). Leaves the session signed in as the
 * new owner. `$org` is the organization the registration created.
 *
 * @return array{0:User,1:Organization,2:string} user, organization, password
 */
function e2eSignUp(object $test, string $email, string $organization = 'Domeny e2e s.r.o.'): array
{
    $password = 'Correct-Horse-Battery-9-Staple';
    $test->withHeaders(e2eHeaders('signup-'.$email))->postJson('/v1/auth/register', [
        'name' => 'Jana Nováková', 'email' => $email, 'password' => $password, 'organization' => $organization, 'type' => 'company', 'ico' => '12345678', 'country' => 'CZ', 'terms' => true,
    ])->assertCreated();
    $user = User::query()->where('email', $email)->firstOrFail();
    $test->withHeaders(e2eHeaders('verify-'.$email))->postJson('/v1/auth/verify-email', ['token' => e2eVerificationToken($user)])->assertOk();

    return [$user->refresh(), Organization::query()->where('owner_user_id', $user->id)->firstOrFail(), $password];
}

/** A fresh step-up for the signed-in session, by password (the customer has no authenticator): POST /v1/auth/step-up. */
function e2eStepUp(object $test, string $password): void
{
    $answer = $test->withHeaders(e2eHeaders('stepup'))->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => $password])->assertOk();
    // the grant belongs to THIS browser session: carry its cookies into the next requests, as a browser does (the test client keeps no jar)
    $test->withCredentials();
    foreach ($answer->headers->getCookies() as $cookie) {
        $test->withUnencryptedCookie($cookie->getName(), (string) $cookie->getValue());
    }
}

/** Credit the organization's wallet over the real routes: POST /v1/wallet/topup, then the gateway's callback. */
function e2eTopUp(object $test, array &$gate, int $amountCzk): void
{
    $gate['trans_id'] = 'E2E-TOPUP-'.bin2hex(random_bytes(3));
    $gate['status'] = 'PAID';
    $gate['total'] = $amountCzk * 100;
    $test->withHeaders(e2eHeaders('topup'))->postJson('/v1/wallet/topup', ['amount' => $amountCzk, 'currency' => 'CZK', 'provider' => 'comgate', 'method' => 'card'])->assertCreated();
    e2eComgateCallback($test, $gate['trans_id'], $gate['total'])->assertOk();
}

/**
 * Cron jobs and FTP accounts on the stateful ISPConfig double of `e2eIspPanel`, kept in `$panel['crons']` and `$panel['ftps']`
 * (id => row, `active` y|n). Register it BEFORE `e2eIspPanel`: the first fake that answers wins, and the plain panel only knows
 * these listings as empty. A suspension switches both off and a resume switches them back on, so a flow reads the rows to see it.
 *
 * @param  array<string,mixed>  $panel
 */
function e2eIspSiteExtras(array &$panel): void
{
    $panel += ['crons' => [], 'ftps' => [], 'calls' => []];
    $envelope = fn (mixed $response): array => ['code' => 'ok', 'message' => '', 'response' => $response];
    Http::fake(function (Request $request) use (&$panel, $envelope) {
        if (! str_contains($request->url(), E2E_ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (! in_array($function, ['sites_cron_get', 'sites_cron_update', 'sites_ftp_user_get', 'sites_ftp_user_update'], true)) {
            return null;
        }
        $panel['calls'][] = $function;
        $data = $request->data();
        $rows = str_starts_with($function, 'sites_cron') ? 'crons' : 'ftps';
        if (str_ends_with($function, '_get')) {
            $parent = (string) (((array) ($data['primary_id'] ?? []))['parent_domain_id'] ?? '');

            return Http::response($envelope(array_values(array_filter($panel[$rows], fn (array $row) => (string) ($row['parent_domain_id'] ?? '') === $parent))));
        }
        $id = (int) ($data['primary_id'] ?? 0);
        $panel[$rows][$id] = array_merge($panel[$rows][$id] ?? [], (array) ($data['params'] ?? []));

        return Http::response($envelope(1));
    });
}

/**
 * A STATEFUL Proxmox cluster (one node `prg1-n2`, the lab instance of pveLab()). Every guest is a row in `$pve['guests']` (config,
 * power status, snapshots, firewall), the golden template 9001 is there from the start, and a clone really creates the next guest:
 * what the platform writes is what it reads back, so a power action, a snapshot, a rollback, a reinstall or a rescue boot changes
 * the cluster and the next read shows it. A path it does not know answers 501 and lands in `$pve['unknown']` (a test asserts it stays
 * empty), so a call the platform should not make fails the flow out loud.
 *
 * State keys: `guests` (vmid => [name, description, tags, status, template, config, snapshots, firewall_rules, firewall_options,
 * rolled_back_to]), `backups` (vmids that still have backups on the backup server), `isos` (volume ids the node offers), `writes`
 * (every non-GET call as "METHOD /path", in order), `clones` (vmids cloned into, in order), `vnc_tickets` (issued), `unknown`,
 * `floor` (the lowest number Proxmox hands out).
 *
 * @param  array<string,mixed>  $pve
 */
function e2ePveCluster(array &$pve): void
{
    $pve += ['guests' => [], 'backups' => [], 'isos' => ['local:iso/systemrescue-11.iso', 'local:iso/debian-13-netinst.iso'], 'writes' => [], 'clones' => [], 'vnc_tickets' => [], 'unknown' => [], 'floor' => 1040];
    $pve['guests'][9001] ??= ['name' => 'debian-13-golden', 'description' => '', 'tags' => '', 'status' => 'stopped', 'template' => 1, 'config' => ['scsi0' => 'local-zfs:base-9001-disk-0,size=4G', 'cores' => 1, 'memory' => 2048], 'snapshots' => [], 'firewall_rules' => [], 'firewall_options' => []];
    $base = 'https://pve.mgmt.test:8006/api2/json';
    Http::fake(function (Request $request) use (&$pve, $base) {
        if (! str_starts_with($request->url(), $base)) {
            return null; // the gateway and the other panels get their turn
        }
        $path = substr(rawurldecode((string) parse_url($request->url(), PHP_URL_PATH)), strlen('/api2/json'));
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $method = $request->method();
        $data = $request->data();
        if ($method !== 'GET') {
            $pve['writes'][] = $method.' '.$path;
        }
        $ok = fn (mixed $payload = null) => Http::response(['data' => $payload]);
        $task = fn (string $what, int|string $vmid) => Http::response(['data' => "UPID:prg1-n2:000A1B2C:0004E1F5:66F0AA11:{$what}:{$vmid}:onhost@pve!cp:"]);
        $fail = fn (int $status, string $reason) => Create::promiseFor(new Response($status, ['Content-Type' => 'application/json'], '{"data":null}', '1.1', $reason));

        if ($path === '/cluster/resources') {
            return $ok(array_values(array_map(fn (int $id, array $g) => ['type' => 'qemu', 'vmid' => $id, 'node' => 'prg1-n2', 'name' => $g['name'], 'tags' => $g['tags'], 'status' => $g['status'], 'template' => $g['template']], array_keys($pve['guests']), $pve['guests'])));
        }
        if ($path === '/cluster/nextid') {
            if (isset($query['vmid'])) {
                return isset($pve['guests'][(int) $query['vmid']]) ? Http::response(['errors' => ['vmid' => "VM {$query['vmid']} already exists"], 'data' => null], 400) : $ok((string) (int) $query['vmid']);
            }
            for ($id = (int) $pve['floor']; isset($pve['guests'][$id]); $id++);

            return $ok((string) $id);
        }
        if ($path === '/nodes/prg1-n2/storage/pbs-cz1/content') {
            return $ok(array_map(fn (int $id) => ['volid' => "pbs-cz1:backup/vm/{$id}/2026-07-01T02:00:00Z", 'vmid' => $id, 'format' => 'pbs-vm', 'content' => 'backup', 'size' => 21474836480, 'protected' => 1], $pve['backups']));
        }
        if ($path === '/nodes/prg1-n2/storage/local/content') {
            return $ok(array_map(fn (string $volume) => ['volid' => $volume, 'size' => 900 * 1024 * 1024, 'content' => 'iso'], $pve['isos']));
        }
        if (preg_match('~^/nodes/[^/]+/tasks/~', $path) === 1) {
            return $ok(['status' => 'stopped', 'exitstatus' => 'OK']);
        }
        if ($path === '/nodes/prg1-n2/qemu/9001/clone' && $method === 'POST') {
            $id = (int) $data['newid'];
            $pve['clones'][] = $id;
            if (isset($pve['guests'][$id])) {
                return $fail(500, "unable to create VM {$id}: config file already exists");
            }
            $template = $pve['guests'][9001];
            $pve['guests'][$id] = ['name' => (string) $data['name'], 'description' => (string) ($data['description'] ?? ''), 'tags' => '', 'status' => 'stopped', 'template' => 0,
                'config' => ['scsi0' => "local-zfs:vm-{$id}-disk-0,size=4G", 'cores' => 1, 'memory' => 2048, 'agent' => '1', 'onboot' => 1, 'boot' => 'order=scsi0;net0'] + $template['config'], 'snapshots' => [], 'firewall_rules' => [], 'firewall_options' => []];

            return $task('qmclone', 9001);
        }
        if (preg_match('~^/nodes/prg1-n2/qemu/(\d+)/(.+)$~', $path, $m) !== 1) {
            $pve['unknown'][] = $method.' '.$path;

            return Http::response(['data' => null], 501);
        }
        $id = (int) $m[1];
        $what = $m[2];
        if (! isset($pve['guests'][$id])) {
            return $fail(500, "Configuration file 'nodes/prg1-n2/qemu-server/{$id}.conf' does not exist");
        }
        $guest = &$pve['guests'][$id];

        if ($what === 'config' && $method === 'GET') {
            return $ok(['name' => $guest['name'], 'description' => $guest['description'], 'tags' => $guest['tags']] + $guest['config'] + ($guest['template'] ? ['template' => 1] : []));
        }
        if ($what === 'config' && in_array($method, ['PUT', 'POST'], true)) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'description', 'tags'], true)) {
                    $guest[$key] = (string) $value;
                } elseif ($key === 'scsi0' && str_contains((string) $value, 'import-from=')) { // a reinstall: the old disk is detached, a fresh one stands in its place
                    $guest['config']['unused0'] = explode(',', (string) $guest['config']['scsi0'])[0];
                    $guest['config']['scsi0'] = "local-zfs:vm-{$id}-disk-1,size=4G";
                    $guest['config']['import'] = (string) $value;
                } else {
                    $guest['config'][$key] = $value;
                }
            }

            return $method === 'POST' ? $task('qmconfig', $id) : $ok();
        }
        if ($what === 'resize' && $method === 'PUT') {
            preg_match('~size=(\d+)G~', (string) $guest['config']['scsi0'], $current);
            $wanted = (string) $data['size'];
            $gb = str_starts_with($wanted, '+') ? (int) ($current[1] ?? 0) + (int) ltrim($wanted, '+') : (int) $wanted;
            $guest['config']['scsi0'] = preg_replace('~size=\d+G~', "size={$gb}G", (string) $guest['config']['scsi0']);

            return $task('resize', $id);
        }
        if ($what === 'cloudinit' && $method === 'PUT') {
            return $ok();
        }
        if ($what === 'status/current') {
            return $ok(['status' => $guest['status'], 'uptime' => $guest['status'] === 'running' ? 7 : 0, 'cpu' => 0.02, 'mem' => 536870912, 'maxmem' => 8589934592, 'netin' => 1000, 'netout' => 2000]);
        }
        if (preg_match('~^status/(start|stop|shutdown|reboot|reset)$~', $what, $s) === 1 && $method === 'POST') {
            $guest['status'] = in_array($s[1], ['stop', 'shutdown'], true) ? 'stopped' : 'running';

            return $task('qm'.$s[1], $id);
        }
        if ($what === 'snapshot' && $method === 'POST') {
            $guest['snapshots'][(string) $data['snapname']] = ['name' => (string) $data['snapname'], 'description' => (string) ($data['description'] ?? ''), 'snaptime' => 1_760_000_000 + count($guest['snapshots'])];

            return $task('qmsnapshot', $id);
        }
        if ($what === 'snapshot' && $method === 'GET') {
            return $ok(array_merge(array_values($guest['snapshots']), [['name' => 'current']]));
        }
        if (preg_match('~^snapshot/([^/]+)/rollback$~', $what, $s) === 1 && $method === 'POST') {
            if (! isset($guest['snapshots'][$s[1]])) {
                return $fail(500, "snapshot '{$s[1]}' does not exist");
            }
            $guest['rolled_back_to'] = $s[1];
            $guest['status'] = ! empty($data['start']) ? 'running' : 'stopped';

            return $task('qmrollback', $id);
        }
        if (preg_match('~^snapshot/([^/]+)$~', $what, $s) === 1 && $method === 'DELETE') {
            unset($guest['snapshots'][$s[1]]);

            return $task('qmdelsnapshot', $id);
        }
        if ($what === 'firewall/rules' && $method === 'GET') {
            return $ok(array_map(fn (int $pos, array $rule) => $rule + ['pos' => $pos], array_keys($guest['firewall_rules']), $guest['firewall_rules']));
        }
        if ($what === 'firewall/rules' && $method === 'POST') {
            $rule = $data;
            $position = (int) ($rule['pos'] ?? 0);
            unset($rule['pos']);
            array_splice($guest['firewall_rules'], $position, 0, [$rule]);

            return $ok();
        }
        if (preg_match('~^firewall/rules/(\d+)$~', $what, $s) === 1 && $method === 'DELETE') {
            array_splice($guest['firewall_rules'], (int) $s[1], 1);

            return $ok();
        }
        if ($what === 'firewall/options') {
            if ($method === 'PUT') {
                $guest['firewall_options'] = $data + $guest['firewall_options'];

                return $ok();
            }

            return $ok($guest['firewall_options']);
        }
        if ($what === 'vncproxy' && $method === 'POST') {
            $pve['vnc_tickets'][] = 'PVEVNC:e2e-secret-ticket-'.$id;

            return $ok(['port' => 5900, 'ticket' => 'PVEVNC:e2e-secret-ticket-'.$id, 'user' => 'onhost@pve!cp', 'password' => 'one-time-vnc-secret']);
        }
        $pve['unknown'][] = $method.' '.$path;

        return Http::response(['data' => null], 501);
    });
}

/*
 * E5 — game server flow.
 *
 *   e2eGameInfrastructure()        region cz1 + an active Pterodactyl instance (egg minecraft-paper mapped) + one game node with a panel id
 *   e2eGamePanel(array &$panel)    a STATEFUL Pterodactyl (application + client API): users, servers, allocations, power, variables, backups.
 *                                  `$panel['refuse']` lists "METHOD path-regex" rules that answer 500; an endpoint it does not know is a 404
 *                                  the platform must cope with (or the flow fails out loud). `calls` is the ordered "METHOD path" log.
 */

const E2E_PTERO = 'https://games01.mgmt.test';

/** @return array{0:ProviderInstance,1:Node} */
function e2eGameInfrastructure(): array
{
    $_ENV['PTERODACTYL_GAMES01_APPLICATION_KEY'] = 'ptla_E2EAPPLICATIONKEY1234567890';
    $_ENV['PTERODACTYL_GAMES01_CLIENT_KEY'] = 'ptlc_E2ECLIENTKEY1234567890';
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'pterodactyl-games01'], ['provider' => 'pterodactyl', 'name' => 'Game panel games01', 'region_code' => 'cz1', 'base_url' => E2E_PTERO, 'secret_ref' => 'env://PTERODACTYL_GAMES01', 'state' => 'active', 'capabilities' => ['game.create' => true, 'console' => true], 'options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 5]]], 'adapter_version' => '1.0.0']);
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'games01'], ['region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '2']);

    return [$instance, $node];
}

/**
 * A stateful Pterodactyl 1.11. Keys it keeps: `users` (id => attributes), `servers` (id => attributes), `allocations` (list), `power`
 * (server id => running|offline), `variables`, `name`, `backups` (uuid => row), `commands` (sent to the console), `calls`, `refuse`,
 * `install_reads` (GETs of a new server before its install is over), `daemon` (the node's fqdn, the only host a transfer link may name),
 * `download_host` (what a backup download link names; defaults to the daemon), `created` (bodies of every server create).
 *
 * @param  array<string,mixed>  $panel
 */
function e2eGamePanel(array &$panel): void
{
    $panel += ['users' => [], 'servers' => [], 'allocations' => [['id' => 11, 'ip' => '89.187.160.10', 'port' => 25565, 'alias' => null, 'assigned' => false], ['id' => 12, 'ip' => '89.187.160.10', 'port' => 25566, 'alias' => null, 'assigned' => false]],
        'power' => [], 'variables' => [], 'name' => [], 'backups' => [], 'commands' => [], 'calls' => [], 'refuse' => [], 'install_reads' => 2, 'reads' => [], 'daemon' => 'wings.mgmt.test', 'download_host' => null, 'created' => []];
    Http::fake(function (Request $request) use (&$panel) {
        if (! str_starts_with($request->url(), E2E_PTERO)) {
            return null; // not the panel: the gateway fake (or a stray-request failure) answers
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();
        $panel['calls'][] = $m.' '.$path;
        foreach ($panel['refuse'] as $rule) {
            [$ruleMethod, $rulePath] = explode(' ', $rule, 2);
            if ($ruleMethod === $m && preg_match('~^'.$rulePath.'$~', $path) === 1) {
                return Http::response(['errors' => [['code' => 'HttpException', 'status' => '500', 'detail' => 'The panel refused this.']]], 500);
            }
        }
        $notFound = Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$m} {$path}"]]], 404);
        $one = fn (string $object, array $attributes, int $status = 200) => Http::response(['object' => $object, 'attributes' => $attributes], $status);
        $list = fn (string $object, array $rows) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], array_values($rows)), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $serverView = function (array $server) use (&$panel): array {
            $reads = ($panel['reads'][$server['id']] = ($panel['reads'][$server['id']] ?? 0) + 1);
            if ($server['status'] === 'installing' && $reads >= $panel['install_reads']) { // the daemon finished the install
                $panel['servers'][$server['id']]['status'] = null;
                $panel['servers'][$server['id']]['container']['installed'] = 1;
                $panel['power'][$server['id']] = 'running'; // start_on_completion
            }

            return $panel['servers'][$server['id']];
        };

        // ── application API ──
        if ($path === '/api/application/nodes' && $m === 'GET') {
            return $list('node', [['id' => 2, 'name' => 'games01', 'fqdn' => $panel['daemon'], 'memory' => 131072, 'disk' => 2000000, 'maintenance_mode' => false, 'allocated_resources' => ['memory' => 0, 'disk' => 0]]]);
        }
        if ($path === '/api/application/nodes/2/allocations' && $m === 'GET') {
            return $list('allocation', $panel['allocations']);
        }
        if (preg_match('~^/api/application/users/external/(.+)$~', $path, $x) === 1 && $m === 'GET') {
            foreach ($panel['users'] as $user) {
                if (($user['external_id'] ?? null) !== null && $user['external_id'] === rawurldecode($x[1])) {
                    return $one('user', $user);
                }
            }

            return $notFound;
        }
        if ($path === '/api/application/users' && $m === 'POST') {
            $id = max(array_keys($panel['users']) ?: [100]) + 1;
            $panel['users'][$id] = ['id' => $id, 'external_id' => $request['external_id'], 'email' => $request['email'], 'username' => $request['username'], 'first_name' => $request['first_name'], 'last_name' => $request['last_name'], 'language' => 'en', 'root_admin' => (bool) $request['root_admin']];

            return $one('user', $panel['users'][$id], 201);
        }
        if ($path === '/api/application/users' && $m === 'GET') {
            return $list('user', $panel['users']); // an e-mail lookup must never be how the platform finds a customer's panel user: the flow asserts it never asks
        }
        if (preg_match('~^/api/application/users/(\d+)$~', $path, $x) === 1 && $m === 'GET') {
            return isset($panel['users'][(int) $x[1]]) ? $one('user', $panel['users'][(int) $x[1]]) : $notFound;
        }
        if (preg_match('~^/api/application/servers/external/(.+)$~', $path, $x) === 1 && $m === 'GET') {
            foreach ($panel['servers'] as $server) {
                if ($server['external_id'] === rawurldecode($x[1])) {
                    return $one('server', $server);
                }
            }

            return $notFound;
        }
        if ($path === '/api/application/servers' && $m === 'POST') {
            $id = 101 + count($panel['servers']);
            $identifier = substr(md5('e2e-server-'.$id), 0, 8);
            $allocationId = (int) $request['allocation']['default'];
            foreach ($panel['allocations'] as $i => $allocation) {
                if ($allocation['id'] === $allocationId) {
                    $panel['allocations'][$i]['assigned'] = true;
                }
            }
            $panel['created'][] = $request->data();
            $panel['servers'][$id] = ['id' => $id, 'external_id' => $request['external_id'], 'uuid' => $identifier.'-e2e-uuid', 'identifier' => $identifier, 'name' => $request['name'], 'user' => (int) $request['user'], 'node' => 2, 'allocation' => $allocationId, 'egg' => (int) $request['egg'],
                'status' => 'installing', 'suspended' => false, 'container' => ['installed' => 0], 'limits' => $request['limits'], 'feature_limits' => $request['feature_limits'], 'environment' => $request['environment']];
            $panel['variables'][$id] = (array) $request['environment'];
            $panel['name'][$id] = $request['name'];

            return $one('server', $panel['servers'][$id], 201);
        }
        if (preg_match('~^/api/application/servers/(\d+)$~', $path, $x) === 1 && $m === 'GET') {
            return isset($panel['servers'][(int) $x[1]]) ? $one('server', $serverView($panel['servers'][(int) $x[1]])) : $notFound;
        }
        if (preg_match('~^/api/application/servers/(\d+)/(suspend|unsuspend)$~', $path, $x) === 1 && $m === 'POST') {
            if (! isset($panel['servers'][(int) $x[1]])) {
                return $notFound;
            }
            $panel['servers'][(int) $x[1]]['suspended'] = $x[2] === 'suspend';
            if ($x[2] === 'suspend') {
                $panel['power'][(int) $x[1]] = 'offline'; // the panel stops a suspended server
            }

            return Http::response('', 204);
        }
        if ($path === '/api/application/nests' && $m === 'GET') {
            return $list('nest', [['id' => 1, 'name' => 'Minecraft']]);
        }
        if ($path === '/api/application/nests/1/eggs' && $m === 'GET') {
            return $list('egg', [['id' => 5, 'name' => 'Paper', 'docker_image' => 'ghcr.io/games/java:21', 'docker_images' => [], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]]]]);
        }
        if ($path === '/api/application/nests/1/eggs/5' && $m === 'GET') {
            $variable = fn (string $env, string $default) => ['object' => 'egg_variable', 'attributes' => ['env_variable' => $env, 'default_value' => $default, 'rules' => 'required|string', 'user_editable' => true]];

            return $one('egg', ['id' => 5, 'name' => 'Paper', 'docker_image' => 'ghcr.io/games/java:21', 'docker_images' => [], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]],
                'relationships' => ['variables' => ['data' => [$variable('MINECRAFT_VERSION', 'latest'), $variable('SERVER_JARFILE', 'server.jar'), $variable('MOTD', 'A Minecraft Server')]]]]);
        }

        // ── client API (one server, by its short identifier) ──
        if (preg_match('~^/api/client/servers/([0-9a-f]{8})(/.*)?$~', $path, $x) !== 1) {
            return $notFound;
        }
        $server = collect($panel['servers'])->firstWhere('identifier', $x[1]);
        if ($server === null) {
            return $notFound;
        }
        $id = $server['id'];
        $rest = $x[2] ?? '';
        if ($server['suspended'] && $rest !== '' && $rest !== '/resources') {
            return Http::response(['errors' => [['code' => 'ServerSuspendedException', 'status' => '403', 'detail' => 'This server is suspended.']]], 403);
        }
        switch (true) {
            case $rest === '' && $m === 'GET':
                return $one('server', ['identifier' => $server['identifier'], 'name' => $panel['name'][$id], 'sftp_details' => ['ip' => $panel['daemon'], 'port' => 2022], 'limits' => $server['limits'], 'is_installing' => $server['status'] === 'installing', 'is_suspended' => $server['suspended'], 'egg_features' => ['eula'], 'docker_image' => 'ghcr.io/games/java:21', 'invocation' => 'java -jar server.jar',
                    'relationships' => ['allocations' => ['data' => array_values(array_map(fn (array $a) => ['attributes' => ['id' => $a['id'], 'ip' => $a['ip'], 'ip_alias' => null, 'port' => $a['port'], 'is_default' => $a['id'] === $server['allocation']]], array_filter($panel['allocations'], fn (array $a) => $a['id'] === $server['allocation'])))]]]);
            case $rest === '/resources':
                return $one('stats', ['current_state' => $server['suspended'] ? 'offline' : ($panel['power'][$id] ?? 'offline'), 'is_suspended' => $server['suspended'], 'resources' => ['memory_bytes' => 2 * 1024 ** 3, 'cpu_absolute' => 12.0, 'disk_bytes' => 1024 ** 3, 'network_rx_bytes' => 10, 'network_tx_bytes' => 20, 'uptime' => 5000]]);
            case $rest === '/power' && $m === 'POST':
                $panel['power'][$id] = match ((string) $request['signal']) {
                    'start', 'restart' => 'running', 'stop', 'kill' => 'offline', default => $panel['power'][$id] ?? 'offline',
                };

                return Http::response('', 204);
            case $rest === '/command' && $m === 'POST':
                $panel['commands'][] = (string) $request['command'];

                return Http::response('', 204);
            case $rest === '/websocket' && $m === 'GET':
                return Http::response(['data' => ['token' => 'wings-e2e-session-token', 'socket' => 'wss://'.$panel['daemon'].':8080/api/servers/'.$server['uuid'].'/ws']]);
            case $rest === '/startup' && $m === 'GET':
                return Http::response(['object' => 'list', 'data' => array_map(fn ($k, $v) => ['object' => 'egg_variable', 'attributes' => ['name' => $k, 'description' => '', 'env_variable' => $k, 'default_value' => 'x', 'server_value' => $v, 'is_editable' => true, 'rules' => 'required|string']], array_keys($panel['variables'][$id]), $panel['variables'][$id]),
                    'meta' => ['startup_command' => 'java -jar server.jar', 'raw_startup_command' => 'java -jar {{SERVER_JARFILE}}', 'docker_image' => 'ghcr.io/games/java:21', 'docker_images' => ['Java 21' => 'ghcr.io/games/java:21']]]);
            case $rest === '/startup/variable' && $m === 'PUT':
                $panel['variables'][$id][(string) $request['key']] = (string) $request['value'];

                return $one('egg_variable', ['env_variable' => $request['key'], 'server_value' => $request['value']]);
            case $rest === '/settings/rename' && $m === 'POST':
                $panel['name'][$id] = (string) $request['name'];

                return Http::response('', 204);
            case $rest === '/backups' && $m === 'POST':
                $uuid = 'bk-e2e-'.(count($panel['backups']) + 1);
                $panel['backups'][$uuid] = ['uuid' => $uuid, 'name' => (string) $request['name'], 'is_successful' => true, 'is_locked' => false, 'bytes' => 4096, 'completed_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String()];

                return $one('backup', $panel['backups'][$uuid]);
            case $rest === '/backups' && $m === 'GET':
                return $list('backup', $panel['backups']);
            case preg_match('~^/backups/([\w-]+)/download$~', $rest) === 1:
                return Http::response(['object' => 'signed_url', 'attributes' => ['url' => 'https://'.($panel['download_host'] ?? $panel['daemon']).':8080/download/backup?token=e2e-signed']]);
            case preg_match('~^/backups/([\w-]+)$~', $rest, $y) === 1 && $m === 'GET':
                return isset($panel['backups'][$y[1]]) ? $one('backup', $panel['backups'][$y[1]]) : $notFound;
        }

        return $notFound;
    });
}
