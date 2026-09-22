<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\MailAddresses;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;

/*
 * An address the platform creates has to be in a domain the service itself hosts. Nothing checked it: the mail
 * actions validated that the value *looks* like an address and sent it to the panel as it came. On a shared panel
 * every customer's mail lives in one installation, so `ceo@somebody-elses-domain.cz` was a request the platform was
 * willing to make on a customer's behalf — and whether the panel would have refused it was never ours to assume.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('refuses to make an address in a domain that is not the service\'s', function () {
    Http::fake(fn () => Http::response(['code' => 'ok', 'message' => '', 'response' => []]));
    [$user, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org, 'firma.cz');
    $services = app(ServiceService::class);
    $ctx = $this->contextFor($user, $org);

    foreach ([
        ['mailbox.create', ['address' => 'ceo@konkurence.cz', 'password' => 'Correct-Horse-Battery-9']],
        ['alias.create', ['source' => 'info@konkurence.cz', 'destination' => 'me@gmail.com']],
        ['forward.create', ['source' => 'podpora@konkurence.cz', 'destination' => 'me@gmail.com']],
        ['list.create', ['name' => 'novinky', 'email' => 'novinky@konkurence.cz', 'password' => 'Correct-Horse-Battery-9']],
    ] as [$action, $params]) {
        expect(fn () => $services->requestAction($mail, $action, $ctx, 'own-'.$action, $params))
            ->toThrow(DomainError::class, 'jen v doméně této služby');
    }
});

it('makes the same address in the service\'s own domain without a word', function () {
    Http::fake(fn () => Http::response(['code' => 'ok', 'message' => '', 'response' => []]));
    [$user, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org, 'firma.cz');

    $operation = app(ServiceService::class)->requestAction($mail, 'alias.create', $this->contextFor($user, $org), 'own-ok-1', ['source' => 'Info@Firma.cz', 'destination' => 'me@gmail.com']);

    expect(data_get($operation->desired, 'source'))->toBe('info@firma.cz'); // and it is stored the way the panel wants it
});

it('lets mail leave: where an alias or a forward goes is the customer\'s business', function () {
    Http::fake(fn () => Http::response(['code' => 'ok', 'message' => '', 'response' => []]));
    [$user, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org, 'firma.cz');

    $operation = app(ServiceService::class)->requestAction($mail, 'forward.create', $this->contextFor($user, $org), 'own-ok-2', ['source' => 'podpora@firma.cz', 'destination' => 'tym@jina-firma.cz']);

    expect(data_get($operation->desired, 'destination'))->toBe('tym@jina-firma.cz');
});

it('knows which domains a service may make addresses in', function () {
    [, $org] = $this->customerWithOrganization();

    expect(MailAddresses::of(featureMailService($org, 'firma.cz')))->toContain('firma.cz')
        ->and(MailAddresses::of(featureWebService($org, 'aapanel')))->toContain('shop.cz'); // a web service: the site it is
});
