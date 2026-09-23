<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;

/*
 * „5 schránek“, „50 schránek“, „500 schránek“ — every web hosting plan states a number of mailboxes, the price list
 * prints it and a paid add-on raises it. The web service offered **no mailbox action at all**: the feature list
 * carried `mail` (the number, as a label) and the actions hang off `mailboxes`, which the web branch never set. And
 * even switched on there was nowhere to put a mailbox: ISPConfig keeps mailboxes inside a mail domain, and the site
 * saga never made one — `createMailDomain()` was called only by the separate mail service's own saga.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The ISPConfig of a web service that is about to get its first mailbox. */
function webMailPanel(array &$calls): void
{
    Http::fake(function (Request $request) use (&$calls) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;

        return Http::response(match (true) {
            $function === 'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-mail'],
            $function === 'mail_domain_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            $function === 'mail_domain_add' => ['code' => 'ok', 'message' => '', 'response' => 909],
            $function === 'client_get_by_username' => ['code' => 'ok', 'message' => '', 'response' => ['client_id' => 3, 'username' => 'onh_1']],
            $function === 'mail_user_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            $function === 'mail_user_add' => ['code' => 'ok', 'message' => '', 'response' => 5001],
            $function === 'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

it('offers the mailboxes its plan sells, and makes the mail domain at the first one', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig'); // the fixture's plan sells ten mailboxes

    expect(app(ServiceFeatures::class)->features($service)['mailboxes'])->toMatchArray(['enabled' => true, 'limit' => 10]);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-1', ['address' => 'info@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);
    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.' '.$operation->state.': '.(string) data_get($operation->error, 'message', ''));

    // the mail domain was made for the site's own domain, and the mailbox went into it
    expect(in_array('mail_domain_add', $calls, true))->toBeTrue()->and(in_array('mail_user_add', $calls, true))->toBeTrue()
        ->and(MailDomain::query()->where('service_id', $service->id)->value('domain'))->toBe('shop.cz')
        ->and(ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->exists())->toBeTrue();

    // the records mail needs are named, whether or not the zone is ours to publish into
    $types = array_column((array) data_get($operation->fresh()->context, 'dns_records_required', []), 'type');
    expect($types)->toContain('MX')->toContain('TXT');
});

it('makes the mail domain once, not with every mailbox', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $services = app(ServiceService::class);

    driveOperation($services->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-2a', ['address' => 'a@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);
    $first = count(array_keys($calls, 'mail_domain_add', true));
    driveOperation($services->requestAction($service->fresh(), 'mailbox.create', $this->contextFor($user, $org), 'wm-2b', ['address' => 'b@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);

    expect($first)->toBe(1)->and(count(array_keys($calls, 'mail_domain_add', true)))->toBe(1);
});

it('still refuses an address in a domain the service does not host', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    expect(fn () => app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-3', ['address' => 'info@konkurence.cz', 'password' => 'Correct-Horse-Battery-9']))
        ->toThrow(DomainError::class, 'jen v doméně této služby');
    expect($calls)->toBe([]); // nothing was asked of the panel
});

it('does not offer mailboxes where the panel has no mail', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();

    expect(app(ServiceFeatures::class)->features(featureWebService($org, 'aapanel'))['mailboxes']['enabled'] ?? false)->toBeFalse();
});
