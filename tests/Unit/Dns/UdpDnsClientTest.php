<?php

declare(strict_types=1);

use Onhost\Platform\Dns\Dnsbl\UdpDnsClient;

/** A hand-built answer: header, the question echoed, then A records with a compressed name pointer (0xC00C). */
function dnsAnswer(int $id, string $name, array $addresses, int $rcode = 0): string
{
    $question = substr((string) UdpDnsClient::query($id, $name), 12);
    $packet = pack('nnnnnn', $id, 0x8180 | $rcode, 1, count($addresses), 0, 0).$question;
    foreach ($addresses as $ip) {
        $packet .= pack('nnnNn', 0xC00C, 1, 1, 300, 4).inet_pton($ip);
    }

    return $packet;
}

it('builds a recursive A question for the name', function () {
    $packet = (string) UdpDnsClient::query(0x1234, '2.0.0.127.zen.spamhaus.org');

    expect(bin2hex(substr($packet, 0, 12)))->toBe('123401000001000000000000')
        ->and(substr($packet, 12))->toBe("\x012\x010\x010\x03127\x03zen\x08spamhaus\x03org\x00\x00\x01\x00\x01");
});

it('refuses a name with an empty or an over-long label', function () {
    expect(UdpDnsClient::query(1, 'a..b'))->toBeNull()
        ->and(UdpDnsClient::query(1, str_repeat('x', 64).'.example'))->toBeNull();
});

it('reads the A records of an answer and treats NXDOMAIN as not listed', function () {
    $listed = UdpDnsClient::parse(dnsAnswer(7, '2.0.0.127.zen.spamhaus.org', ['127.0.0.2', '127.0.0.4']), 7);
    $clean = UdpDnsClient::parse(dnsAnswer(7, '1.0.0.127.zen.spamhaus.org', [], 3), 7);

    expect($listed->failed)->toBeFalse()->and($listed->addresses)->toBe(['127.0.0.2', '127.0.0.4'])
        ->and($clean->failed)->toBeFalse()->and($clean->addresses)->toBe([]);
});

it('never reads a failure as not listed: SERVFAIL, another id, a short packet, a timeout', function () {
    $client = new UdpDnsClient('192.0.2.53', 53, 1.0, fn () => null);

    expect(UdpDnsClient::parse(dnsAnswer(7, 'x.example', [], 2), 7)->failed)->toBeTrue()
        ->and(UdpDnsClient::parse(dnsAnswer(8, 'x.example', ['127.0.0.2']), 7)->failed)->toBeTrue()
        ->and(UdpDnsClient::parse('abc', 7)->failed)->toBeTrue()
        ->and($client->a('2.0.0.127.zen.spamhaus.org')->failed)->toBeTrue();
});

it('sends the question to the named resolver and port', function () {
    $seen = [];
    $client = new UdpDnsClient('192.0.2.53', 5353, 1.0, function (string $host, string $packet, int $port) use (&$seen) {
        $seen = [$host, $port];
        $id = unpack('n', substr($packet, 0, 2))[1];

        return dnsAnswer($id, '2.0.0.127.bl.spamcop.net', ['127.0.0.2']);
    });

    expect($client->a('2.0.0.127.bl.spamcop.net')->addresses)->toBe(['127.0.0.2'])->and($seen)->toBe(['192.0.2.53', 5353]);
});
