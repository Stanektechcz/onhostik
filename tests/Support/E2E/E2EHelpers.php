<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
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
