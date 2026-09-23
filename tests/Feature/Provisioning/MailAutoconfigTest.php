<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;

/*
 * The platform could make a mailbox and hand over its password, and then said nothing at all about where to put it:
 * no server name, no port, no encryption — the one thing every customer asks support for on the day they set up
 * their phone. Mail clients will ask for it themselves if we answer: Thunderbird fetches
 * `autoconfig.<domain>/mail/config-v1.1.xml`, Outlook posts to `autodiscover.<domain>/autodiscover/autodiscover.xml`.
 * Both are public by nature, so an answer is given only for a domain the platform really hosts mail for.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A hosted mail domain, as `ensureMailDomainStep` or the mail saga leaves it. */
function hostedMailDomain(string $serviceId, string $organizationId, string $domain): MailDomain
{
    return MailDomain::query()->create(['service_id' => $serviceId, 'domain' => $domain, 'remote_id' => 909, 'remote_node' => '1', 'state' => 'active', 'sending_enabled' => true]);
}

it('sets a mail client up from the address alone', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    hostedMailDomain($service->id, $org->id, 'shop.cz');

    $xml = $this->get('/mail/config-v1.1.xml?emailaddress=info@shop.cz')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();

    expect($xml)->toContain('<domain>shop.cz</domain>')
        ->toContain('<incomingServer type="imap">')->toContain('<port>993</port>')->toContain('<socketType>SSL</socketType>')
        ->toContain('<outgoingServer type="smtp">')->toContain('<port>587</port>')->toContain('<socketType>STARTTLS</socketType>')
        ->toContain('<username>%EMAILADDRESS%</username>')                       // the whole address, never the part before the @
        ->toContain('<hostname>'.MailSettings::host().'</hostname>');            // the same name the MX points at
});

it('answers Outlook too, reading the address out of its request', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    hostedMailDomain($service->id, $org->id, 'shop.cz');
    $body = '<?xml version="1.0"?><Autodiscover><Request><EMailAddress>info@shop.cz</EMailAddress></Request></Autodiscover>';

    $xml = $this->call('POST', '/autodiscover/autodiscover.xml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $body)->assertOk()->getContent();

    expect($xml)->toContain('<Type>IMAP</Type>')->toContain('<Port>993</Port>')->toContain('<SSL>on</SSL>')
        ->toContain('<Type>SMTP</Type>')->toContain('<Port>587</Port>')->toContain('<SSL>TLS</SSL>')
        ->toContain('<AuthRequired>on</AuthRequired>');
});

it('says nothing about a domain the platform does not host mail for', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    hostedMailDomain($service->id, $org->id, 'shop.cz');

    $this->get('/mail/config-v1.1.xml?emailaddress=ceo@konkurence.cz')->assertNotFound();
    $this->get('/mail/config-v1.1.xml?emailaddress=not-an-address')->assertNotFound();
    $this->call('POST', '/autodiscover/autodiscover.xml', [], [], [], ['CONTENT_TYPE' => 'text/xml'], '<Autodiscover><Request><EMailAddress>x@konkurence.cz</EMailAddress></Request></Autodiscover>')->assertNotFound();
});

it('stops answering for a domain whose service is gone', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    hostedMailDomain($service->id, $org->id, 'shop.cz');
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();

    $this->get('/mail/config-v1.1.xml?emailaddress=info@shop.cz')->assertNotFound();
});

it('tells the customer in the panel where their mailbox lives', function () {
    Http::fake(fn () => Http::response(['code' => 'ok', 'message' => '', 'response' => []]));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    $access = app(ServiceFeatures::class)->resources($service, 'mail_access');

    expect($access['imap'])->toMatchArray(['port' => 993, 'security' => 'SSL/TLS'])
        ->and($access['smtp'])->toMatchArray(['port' => 587, 'security' => 'STARTTLS', 'auth' => true])
        ->and($access['host'])->toBe(MailSettings::host())
        ->and($access['username'])->toBe('address');
});

it('publishes the records a mail client looks for before it asks anybody', function () {
    $records = MailSettings::autoconfigRecords('shop.cz');

    expect(array_column($records, 'type'))->toBe(['CNAME', 'SRV'])
        ->and($records[0]['name'])->toBe('autoconfig')
        ->and($records[1]['name'])->toBe('_autodiscover._tcp')
        ->and($records[1]['content'])->toContain('443');
});
