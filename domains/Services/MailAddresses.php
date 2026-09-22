<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Errors\DomainError;

/**
 * An address the platform creates has to be in a domain the service itself hosts.
 *
 * Nothing checked it: `mailbox.create`, `alias.create`, `forward.create` and `list.create` validated that the value
 * *looks* like an e-mail address and sent it to the panel as it came. On a shared panel every customer's mail lives
 * in the same installation, so a request for `ceo@somebody-elses-domain.cz` was one the platform was willing to make
 * on a customer's behalf — and whether the panel would have refused it was never the platform's to assume. It is the
 * same bug class the audit keeps finding: a value from the customer reaching a provider call without being measured
 * against what that customer actually owns.
 *
 * What a service may make addresses in: the mail domains it has (a mail service), and the site it is (`web`,
 * `managed`) together with the extra names that site answers for. Where mail goes — the destination of an alias, a
 * forward or a catch-all — is deliberately not restricted: forwarding your own mail out is the point of it.
 */
final class MailAddresses
{
    /**
     * The domains this service may make addresses in, lower-case.
     *
     * @return list<string>
     */
    public static function of(Service $service): array
    {
        $domains = [];
        foreach (MailDomain::query()->where('service_id', $service->id)->pluck('domain') as $domain) {
            $domains[] = mb_strtolower(trim((string) $domain));
        }
        if (in_array($service->family, ['web', 'managed'], true)) {
            $site = Website::query()->where('service_id', $service->id)->first();
            $domains[] = mb_strtolower(trim((string) ($site->domain ?? '')));
            foreach ((array) ($site->aliases ?? []) as $alias) {
                $domains[] = mb_strtolower(trim((string) $alias));
            }
        }
        // a service that is still being provisioned has no projection row yet; what it was ordered for is the truth
        $domains[] = mb_strtolower(trim((string) ($service->spec('domain') ?: $service->hostname)));

        return array_values(array_unique(array_filter($domains, fn (string $domain) => $domain !== '')));
    }

    /**
     * The address, once it is in one of the service's own domains.
     *
     * @throws DomainError when it is not
     */
    public static function assertOwn(Service $service, string $address, string $field, string $action): string
    {
        $address = mb_strtolower(trim($address));
        $domain = mb_strtolower((string) mb_substr($address, (int) mb_strpos($address, '@') + 1));
        $own = self::of($service);
        if ($domain === '' || ! in_array($domain, $own, true)) {
            throw new DomainError('mail_domain_not_yours', "{$action}: adresu lze vytvořit jen v doméně této služby (".($own === [] ? 'služba zatím žádnou nemá' : implode(', ', array_slice($own, 0, 5))).').', 422, ['field' => $field, 'domains' => $own]);
        }

        return $address;
    }
}
