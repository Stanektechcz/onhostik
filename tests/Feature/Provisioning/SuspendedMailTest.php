<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\SuspensionDepth;

/*
 * A web service can hold mailboxes now, and a suspension never touched them: an unpaid customer's mailboxes are a
 * spam relay with a bill attached, and a site quarantined for abuse went on sending the very thing it was quarantined
 * for. Suspension stops the sending and leaves the receiving, so nothing addressed to the customer is lost while they
 * are switched off — and it remembers which mailboxes it stopped, so one the customer had already stopped stays that
 * way when the service comes back.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** ISPConfig with two mailboxes: one that may send, one the customer had already stopped. */
function suspendedMailPanel(array &$updates): void
{
    Http::fake(function (Request $request) use (&$updates) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if ($function === 'mail_user_update') {
            $updates[] = ['id' => (int) ($request->data()['primary_id'] ?? 0), 'params' => (array) ($request->data()['params'] ?? [])];
        }

        $boxes = [
            11 => ['mailuser_id' => 11, 'email' => 'info@shop.cz', 'name' => 'Info', 'quota' => 2147483648, 'postfix' => 'y', 'disabledeliver' => 'n', 'disablesmtp' => 'n', 'password' => '$1$hashed', 'sys_userid' => 1],
            12 => ['mailuser_id' => 12, 'email' => 'archiv@shop.cz', 'name' => 'Archiv', 'quota' => 1073741824, 'postfix' => 'y', 'disabledeliver' => 'n', 'disablesmtp' => 'y', 'password' => '$1$hashed', 'sys_userid' => 1],
        ];
        $one = $function === 'mail_user_get' && is_numeric($request->data()['primary_id'] ?? null) ? (int) $request->data()['primary_id'] : null; // by number: one mailbox, as the panel answers

        return Http::response(match (true) {
            $function === 'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-susp'],
            $function === 'mail_user_get' => ['code' => 'ok', 'message' => '', 'response' => $one !== null ? ($boxes[$one] ?? []) : array_values($boxes)],
            $function === 'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

/** The mail domain a web service was given for its mailboxes. */
function webMailBinding(object $service): ProviderBinding
{
    return ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain',
        'remote_id' => '909', 'remote_node' => '1', 'meta' => ['domain' => 'shop.cz', 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'susp-mail:'.$service->id]);
}

it('stops a suspended site from sending mail, and leaves what it receives alone', function () {
    $updates = [];
    suspendedMailPanel($updates);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    webMailBinding($service);
    [$adapter, $ref] = [app(ServiceFeatures::class)->adapterFor($service), app(ServiceFeatures::class)->refFor($service)];

    $paused = app(SuspensionDepth::class)->pause($service->refresh(), $adapter, $ref);

    expect($paused['mail'])->toBe(['11'])                      // only the one that could send
        ->and(collect($updates)->firstWhere('id', 11)['params']['disablesmtp'])->toBe('y')
        ->and(collect($updates)->firstWhere('id', 12))->toBeNull(); // the one the customer had stopped is not touched

    // receiving is untouched, and the rest of the mailbox survives the write (ISPConfig takes an update as the whole record)
    $params = collect($updates)->firstWhere('id', 11)['params'];
    expect($params['disabledeliver'])->toBe('n')->and($params['postfix'])->toBe('y')
        ->and((int) $params['quota'])->toBe(2147483648)->and($params['name'])->toBe('Info')
        ->and($params)->not->toHaveKey('password')            // the panel returns a hash; sending it back would lock the customer out
        ->and($params)->not->toHaveKey('sys_userid');
});

it('gives back exactly what it stopped', function () {
    $updates = [];
    suspendedMailPanel($updates);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    webMailBinding($service);
    [$adapter, $ref] = [app(ServiceFeatures::class)->adapterFor($service), app(ServiceFeatures::class)->refFor($service)];
    app(SuspensionDepth::class)->pause($service->refresh(), $adapter, $ref);
    $updates = [];

    app(SuspensionDepth::class)->resume($service->refresh(), $adapter, $ref);

    expect(collect($updates)->firstWhere('id', 11)['params']['disablesmtp'])->toBe('n')
        ->and(collect($updates)->firstWhere('id', 12))->toBeNull(); // still the customer's own decision
});

it('says mail is part of what a suspension reaches', function () {
    expect(SuspensionDepth::KINDS)->toContain('mail')->toContain('cron')->toContain('ftp');
});
