<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Onhost\Domain\Services\Freshness;
use Onhost\Domain\Services\Mail\MailAuthWatch;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Platform\Dns\RecordResolver;

/*
 * Mail authentication as public DNS shows it (W2-3): one SPF record that admits us within ten lookups, the DKIM key we
 * publish, a DMARC policy with a report address. Public DNS only; no message is sent. A domain that answers nothing is
 * "could not be read", never "missing".
 */

beforeEach(function () {
    Http::preventStrayRequests();
    config(['onhost.dns.spf_include' => '_spf.onhost.cz', 'onhost.dns.mail_host' => 'mail.onhost.cz']);
});

const MAILAUTH_KEY = 'AAAAfaketestkeyAAAAfaketestkeyAAAA';

/** Public DNS answers keyed `<name>|<type>`; a value that is a Throwable is thrown. */
function mailAuthDns(array $answers): void
{
    app()->bind(RecordResolver::class, fn () => new class($answers) implements RecordResolver
    {
        public function __construct(private readonly array $answers) {}

        public function records(string $name, string $type): array
        {
            $a = $this->answers[mb_strtolower($name).'|'.$type] ?? [];
            if ($a instanceof Throwable) {
                throw $a;
            }

            return (array) $a;
        }
    });
}

function mailAuthTxt(string ...$values): array
{
    return array_map(fn (string $v) => ['txt' => $v], $values);
}

/** DNS of a healthy domain; pass overrides to break one record. */
function mailAuthGood(array $override = []): array
{
    return array_merge([
        'shop.cz|MX' => [['target' => 'mail.onhost.cz', 'pri' => 10]],
        'shop.cz|TXT' => mailAuthTxt('v=spf1 mx include:_spf.onhost.cz -all'),
        '_spf.onhost.cz|TXT' => mailAuthTxt('v=spf1 ip4:203.0.113.5 -all'),
        'onh1._domainkey.shop.cz|TXT' => mailAuthTxt('v=DKIM1; k=rsa; p='.MAILAUTH_KEY),
        '_dmarc.shop.cz|TXT' => mailAuthTxt('v=DMARC1; p=quarantine; rua=mailto:dmarc@shop.cz'),
    ], $override);
}

function mailAuthService(object $org): Service
{
    $service = featureWebService($org, 'ispconfig');
    MailDomain::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'remote_id' => 909, 'remote_node' => '1', 'state' => 'active', 'sending_enabled' => true,
        'dkim_selector' => 'onh1', 'dkim_public' => "-----BEGIN PUBLIC KEY-----\n".MAILAUTH_KEY."\n-----END PUBLIC KEY-----"]);

    return $service->refresh();
}

function mailAuthFinding(Service $service): ?array
{
    return collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'mail_auth');
}

it('says ok when SPF, DKIM and DMARC all check out', function () {
    mailAuthDns(mailAuthGood());
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);

    $result = app(MailAuthWatch::class)->run();

    $finding = mailAuthFinding($service);
    expect($result)->toMatchArray(['checked' => 1, 'bad' => 0, 'warn' => 0, 'errors' => 0])
        ->and($finding['level'])->toBe('ok')->and($finding['state'])->toBe(Freshness::CURRENT)->and($finding['observed_at'])->not->toBeNull();
});

it('warns about a missing DMARC with the record to add', function () {
    mailAuthDns(mailAuthGood(['_dmarc.shop.cz|TXT' => []]));
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);

    app(MailAuthWatch::class)->run();

    $finding = mailAuthFinding($service);
    expect($finding['level'])->toBe('warn')
        ->and($finding['cs'])->toContain('chybí DMARC')->toContain('v=DMARC1; p=quarantine; rua=mailto:dmarc@shop.cz')
        ->and($finding['en'])->toContain('DMARC is missing');
});

it('warns about a DMARC with policy none or without a report address', function () {
    mailAuthDns(mailAuthGood(['_dmarc.shop.cz|TXT' => mailAuthTxt('v=DMARC1; p=none; rua=mailto:d@shop.cz')]));
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['level'])->toBe('warn')->and(mailAuthFinding($service)['en'])->toContain('policy is "none"');

    mailAuthDns(mailAuthGood(['_dmarc.shop.cz|TXT' => mailAuthTxt('v=DMARC1; p=reject')]));
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['en'])->toContain('no report address');
});

it('is bad when the domain publishes two SPF records', function () {
    mailAuthDns(mailAuthGood(['shop.cz|TXT' => mailAuthTxt('v=spf1 mx include:_spf.onhost.cz -all', 'v=spf1 include:other.example -all')]));
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);

    app(MailAuthWatch::class)->run();

    $finding = mailAuthFinding($service);
    expect($finding['level'])->toBe('bad')->and($finding['cs'])->toContain('více než jeden záznam')->and($finding['en'])->toContain('more than one SPF record');
});

it('is bad when SPF leaves our sending hosts out or needs more than ten lookups', function () {
    mailAuthDns(mailAuthGood(['shop.cz|TXT' => mailAuthTxt('v=spf1 include:other.example -all'), 'shop.cz|MX' => [['target' => 'mx.elsewhere.cz', 'pri' => 10]]]));
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['level'])->toBe('bad')->and(mailAuthFinding($service)['en'])->toContain('does not admit our sending servers')->toContain('include:_spf.onhost.cz');

    $many = 'v=spf1 include:_spf.onhost.cz '.implode(' ', array_map(fn ($i) => "include:s{$i}.example", range(1, 10))).' -all';
    mailAuthDns(mailAuthGood(['shop.cz|TXT' => mailAuthTxt($many)]));
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['level'])->toBe('bad')->and(mailAuthFinding($service)['en'])->toContain('more than 10 DNS lookups');
});

it('counts lookups through nested includes', function () {
    $answers = mailAuthGood(['_spf.onhost.cz|TXT' => mailAuthTxt('v=spf1 '.implode(' ', array_map(fn ($i) => "include:n{$i}.example", range(1, 6))).' -all')]);
    foreach (range(1, 6) as $i) {
        $answers["n{$i}.example|TXT"] = mailAuthTxt('v=spf1 a mx -all'); // 2 more each => 1 + 6 + 12 lookups
    }
    mailAuthDns($answers);
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);

    app(MailAuthWatch::class)->run();

    expect(mailAuthFinding($service)['level'])->toBe('bad')->and($service->refresh()->tags['mail_auth']['domains'][0]['spf']['state'])->toBe('too_many_lookups');
});

it('is bad when the DKIM key in DNS is not the key we sign with, or is missing', function () {
    mailAuthDns(mailAuthGood(['onh1._domainkey.shop.cz|TXT' => mailAuthTxt('v=DKIM1; k=rsa; p=AAAAanotherfaketestkeyAAAA')]));
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);
    app(MailAuthWatch::class)->run();
    $finding = mailAuthFinding($service);
    expect($finding['level'])->toBe('bad')->and($finding['en'])->toContain('different key')->and($finding['cs'])->toContain('onh1._domainkey');

    mailAuthDns(mailAuthGood(['onh1._domainkey.shop.cz|TXT' => []]));
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['en'])->toContain('is missing in DNS');
});

it('reports a DNS failure as collection_error, never as missing records', function () {
    mailAuthDns(['shop.cz|TXT' => new RuntimeException('resolver down')]);
    [, $org] = $this->customerWithOrganization();
    $service = mailAuthService($org);

    $result = app(MailAuthWatch::class)->run();

    $finding = mailAuthFinding($service);
    expect($result['errors'])->toBe(1)
        ->and($finding['state'])->toBe(Freshness::COLLECTION_ERROR)->and($finding['level'])->toBe('warn')
        ->and($finding['cs'])->toContain('nepodařilo')->not->toContain('chybí');

    // a domain that answers nothing at all is not judged either (a failed lookup looks exactly like it)
    mailAuthDns([]);
    app(MailAuthWatch::class)->run();
    expect(mailAuthFinding($service)['state'])->toBe(Freshness::COLLECTION_ERROR);
});

it('says nothing for a site without a mail domain and nothing before the first nightly look', function () {
    mailAuthDns(mailAuthGood());
    [, $org] = $this->customerWithOrganization();
    $plain = featureWebService($org, 'aapanel');
    expect(app(MailAuthWatch::class)->run()['checked'])->toBe(0)->and(mailAuthFinding($plain))->toBeNull();

    $mail = mailAuthService($this->customerWithOrganization()[1]);
    expect(mailAuthFinding($mail))->toBeNull(); // mail domain, never measured: the posture reports it as not measured
});

it('is a registered collector with a freshness limit', function () {
    expect(CollectorHeartbeat::EXPECTED)->toHaveKey('mail.auth')->and(Freshness::COLLECTORS['mail_auth'])->toBe('mail.auth')->and(Freshness::limitMinutes('mail_auth'))->toBe(2880);
});
