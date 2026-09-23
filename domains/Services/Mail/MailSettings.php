<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;

/**
 * How a mailbox is reached. The platform could make a mailbox and hand over its password, and then said nothing at
 * all about where to put it: no server name, no port, no encryption — the one thing every customer asks support for
 * on the day they set up their phone. The names and ports are the node's (Dovecot and Postfix as ISPConfig sets them
 * up); they live here once, so the panel, the password page and the automatic configuration of mail clients all say
 * the same thing.
 *
 * The host is the same name the domain's MX points at, so a customer who moved their DNS elsewhere still reaches the
 * right server.
 */
final class MailSettings
{
    /**
     * @return array{host:string, imap:array<string,mixed>, pop3:array<string,mixed>, smtp:array<string,mixed>, username:string, webmail:?string}
     */
    public static function of(Service $service, ?string $webmail = null): array
    {
        $host = self::host($service);

        return [
            'host' => $host,
            'imap' => ['host' => $host, 'port' => (int) config('onhost.mail.imap_port', 993), 'security' => 'SSL/TLS'],
            'pop3' => ['host' => $host, 'port' => (int) config('onhost.mail.pop3_port', 995), 'security' => 'SSL/TLS'],
            'smtp' => ['host' => $host, 'port' => (int) config('onhost.mail.smtp_port', 587), 'security' => 'STARTTLS', 'auth' => true],
            'username' => 'address', // the whole e-mail address, never the part before the @
            'webmail' => $webmail,
        ];
    }

    /** The mail server of this service: what its own panel says, or the platform's. */
    public static function host(?Service $service = null): string
    {
        $instance = $service?->provider_instance_id === null ? null : ProviderInstance::query()->find($service->provider_instance_id);

        return (string) ($instance?->option('mail_host') ?: config('onhost.dns.mail_host', 'mail.onhost.cz'));
    }

    /**
     * Everything a domain needs in DNS for its mail to arrive, to be accepted where it is sent, and to be found by a
     * mail client without the customer looking anything up. One builder for both sagas — the mail service's own and
     * the one a web service runs at its first mailbox — because a record that only one of them publishes is a
     * difference nobody means to sell.
     *
     * @return list<array<string,mixed>>
     */
    public static function records(string $domain, ?string $host = null, ?string $dkimSelector = null, ?string $dkimPublic = null): array
    {
        $host = $host !== null && $host !== '' ? $host : self::host();
        $records = [
            ['name' => '@', 'type' => 'MX', 'content' => $host.'.', 'ttl' => 3600, 'prio' => 10],
            ['name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 mx include:'.config('onhost.dns.spf_include').' -all', 'ttl' => 3600],
            ['name' => '_dmarc', 'type' => 'TXT', 'content' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@'.$domain, 'ttl' => 3600],
            ...self::autoconfigRecords($domain, $host),
        ];
        if ((string) $dkimSelector !== '' && (string) $dkimPublic !== '') {
            $records[] = ['name' => $dkimSelector.'._domainkey', 'type' => 'TXT', 'content' => 'v=DKIM1; k=rsa; p='.preg_replace('/\s+|-----[A-Z ]+-----/', '', (string) $dkimPublic), 'ttl' => 3600];
        }

        return $records;
    }

    /**
     * The DNS records a mail client looks for before it asks anybody: Thunderbird reads `autoconfig.<domain>`,
     * Outlook asks `_autodiscover._tcp`. With these, a customer types their address and password and nothing else.
     *
     * @return list<array<string,mixed>>
     */
    public static function autoconfigRecords(string $domain, ?string $host = null): array
    {
        $host = $host !== null && $host !== '' ? $host : self::host();

        return [
            ['name' => 'autoconfig', 'type' => 'CNAME', 'content' => $host.'.', 'ttl' => 3600],
            // the priority of an SRV record is its own field here, as it is for MX: content is weight, port, target
            ['name' => '_autodiscover._tcp', 'type' => 'SRV', 'content' => '1 443 '.$host.'.', 'ttl' => 3600, 'prio' => 0],
        ];
    }
}
