<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\ServiceService;

/*
 * A mailbox is named by an id, and the platform checked only its shape.
 *
 * `MailAddressOwnershipTest` already keeps a customer from CREATING an address in a domain they do not host. Changing
 * or deleting one goes the other way round — by `remote_id` — and the saga built the ResourceRef straight from that
 * parameter (`new ResourceRef('mailbox', (string) $p('remote_id'), …)`). ISPConfig is reached through one
 * administrator session per instance and never asks whose `mailuser_id` it was handed, so on a shared mail server,
 * where the ids are consecutive integers, `mailbox.update` with the neighbour's id set THEIR password (read their
 * mail, send as them) and `mailbox.delete` deleted their mailbox.
 *
 * Every other resource of a service is resolved against its own listing before the panel is touched; mail is now too.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** ISPConfig where our mail domain (id 5) holds exactly one mailbox, id 31; 32 belongs to the neighbour. */
function mailOwnFake(array &$calls): void
{
    Http::fake(function (Request $request) use (&$calls) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;
        $answer = match ($function) {
            'login' => 'sess-mail-own',
            'mail_domain_get' => ['domain_id' => 5, 'domain' => 'shop.cz', 'active' => 'y'],
            'mail_user_get' => [['mailuser_id' => 31, 'email' => 'info@shop.cz', 'name' => 'Info', 'quota' => 2147483648, 'disablesmtp' => 'n']],
            default => true,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

it('refuses to change a mailbox that belongs to somebody else', function () {
    $calls = [];
    mailOwnFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.update', $this->contextFor($user, $org), 'own-mbx-1', ['remote_id' => '32', 'password' => 'Zvolene-Heslo-2026!']));

    expect($operation->state)->not->toBe(Operation::SUCCEEDED)
        ->and((string) data_get($operation->error, 'message', ''))->toContain('nepatří')
        ->and($calls)->not->toContain('mail_user_update'); // the panel is never asked
});

it('refuses to delete a mailbox that belongs to somebody else', function () {
    $calls = [];
    mailOwnFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.delete', $this->contextFor($user, $org), 'own-mbx-2', ['remote_id' => '32']));

    expect($operation->state)->not->toBe(Operation::SUCCEEDED)
        ->and($calls)->not->toContain('mail_user_delete');
});

it('still changes the mailbox the service really has', function () {
    $calls = [];
    mailOwnFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.update', $this->contextFor($user, $org), 'own-mbx-3', ['remote_id' => '31', 'quota_mb' => 4096]));

    // ISPConfig answers with a job for its queue, so the operation waits for the panel — what matters here is that
    // it was asked at all, and that nothing refused it
    expect($operation->state)->not->toBe(Operation::FAILED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and($calls)->toContain('mail_user_update');
});
