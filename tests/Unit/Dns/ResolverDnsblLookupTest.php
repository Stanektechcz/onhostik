<?php

declare(strict_types=1);

use Onhost\Platform\Dns\Dnsbl\ResolverDnsblLookup;

/*
 * Where the blocklist questions go. Blocklists refuse (or answer wrongly) through public resolvers, and `dns_get_record()`
 * cannot tell NXDOMAIN (not listed) from SERVFAIL or a timeout — so the system resolver is asked over the same UDP client,
 * and a host without one never reads "no answer" as "not listed".
 */

function dnsblResolvConf(string $content): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'resolv');
    file_put_contents($path, $content);

    return $path;
}

/** @return array{0:ResolverDnsblLookup, 1:object} */
function dnsblLookupSpy(?string $resolvConf, ?Closure $answer = null): array
{
    $spy = new class
    {
        /** @var list<array{host:string, port:int, timeout:float}> */
        public array $sent = [];
    };
    $lookup = new ResolverDnsblLookup($resolvConf, function (string $host, string $packet, int $port, float $timeout) use ($spy, $answer): ?string {
        $spy->sent[] = ['host' => $host, 'port' => $port, 'timeout' => $timeout];
        if ($answer === null) {
            return null;
        }
        $id = unpack('n', substr($packet, 0, 2))[1];

        return $answer($id, substr($packet, 12));
    });

    return [$lookup, $spy];
}

it('counts only a named resolver that is not a well-known public one as own', function (string $resolver, bool $own) {
    config()->set('onhost.antispam.dnsbl.resolver', $resolver);

    expect((new ResolverDnsblLookup('/nonexistent/resolv.conf'))->isOwnResolver())->toBe($own);
})->with([
    'none (system resolver)' => ['', false],
    'Google' => ['8.8.8.8', false],
    'Cloudflare with a port' => ['1.1.1.1:53', false],
    'Quad9' => ['9.9.9.9', false],
    'Cloudflare IPv6' => ['[2606:4700:4700::1111]:53', false],
    'Google IPv6 bare' => ['2001:4860:4860::8888', false],
    'an own recursor' => ['10.0.0.53', true],
    'an own recursor by name and port' => ['dnsbl-resolver.internal:5353', true],
]);

it('asks the system resolver from resolv.conf over UDP and reads NXDOMAIN as not listed', function () {
    config()->set('onhost.antispam.dnsbl.resolver', '');
    [$lookup, $spy] = dnsblLookupSpy(dnsblResolvConf("# generated\nsearch example\nnameserver 192.0.2.53\nnameserver 192.0.2.54\n"),
        fn (int $id, string $question) => pack('nnnnnn', $id, 0x8183, 1, 0, 0, 0).$question);

    $answer = $lookup->a('2.0.0.127.bl.spamcop.net');

    expect($answer->failed)->toBeFalse()->and($answer->addresses)->toBe([])
        ->and($spy->sent[0]['host'])->toBe('192.0.2.53')->and($spy->sent[0]['port'])->toBe(53)
        ->and($lookup->describe())->toBe('system resolver');
});

it('never reads a host without a resolver, or a resolver that does not answer, as not listed', function () {
    config()->set('onhost.antispam.dnsbl.resolver', '');
    [$none, $noneSpy] = dnsblLookupSpy('/nonexistent/resolv.conf');
    [$silent] = dnsblLookupSpy(dnsblResolvConf("nameserver 192.0.2.53\n"));

    expect($none->a('2.0.0.127.bl.spamcop.net')->failed)->toBeTrue()->and($noneSpy->sent)->toBe([])
        ->and($silent->a('2.0.0.127.bl.spamcop.net')->failed)->toBeTrue();
});

it('sends to the named resolver and keeps the timeout between half a second and five seconds', function () {
    config()->set('onhost.antispam.dnsbl.resolver', '[2001:db8::53]:5353');
    config()->set('onhost.antispam.dnsbl.timeout_seconds', 60);
    [$slow, $slowSpy] = dnsblLookupSpy(null);
    $slow->a('2.0.0.127.bl.spamcop.net');

    config()->set('onhost.antispam.dnsbl.timeout_seconds', 0.01);
    [$fast, $fastSpy] = dnsblLookupSpy(null);
    $fast->a('2.0.0.127.bl.spamcop.net');

    expect($slowSpy->sent[0])->toBe(['host' => '2001:db8::53', 'port' => 5353, 'timeout' => 5.0])
        ->and($fastSpy->sent[0]['timeout'])->toBe(0.5);
});
