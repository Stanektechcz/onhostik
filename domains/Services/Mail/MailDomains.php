<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * Where a service's mail actually lives.
 *
 * A mail service is one mail domain and that is its primary resource. A **web** service is a site, and its mail is a
 * resource of its own — one mail domain for each name the site answers for that the customer wants mail in. The
 * platform let them make an address in any of those names (`MailAddresses`) and then made the mail domain for the
 * site's own name whatever the address said: the node never accepted mail for the second domain, so the mailbox
 * looked made and received nothing. Everything that walks a service's mail then took the first mail domain and
 * stopped there — a second domain's mailboxes were not listed (and so never counted against the number the plan
 * sells), not stopped when the service was suspended, and not archived before the service was removed.
 */
final class MailDomains
{
    /** The listings that are about a service's mail and not about its site. */
    public const KINDS = [
        'mailboxes', 'aliases', 'dkim', 'mail_forwards', 'mail_catchall', 'mail_autoresponder', 'mail_spam', 'mail_spam_lists',
        'mail_filters', 'mail_lists', 'mail_fetchmail', 'mail_backups', 'mail_usage', 'mail_access',
    ];

    /** Every mail domain the service has, oldest first. @return Collection<int, ProviderBinding> */
    public static function bindingsOf(Service $service): Collection
    {
        if ((string) $service->id === '') {
            return new Collection;
        }

        return ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->orderBy('created_at')->orderBy('id')->get();
    }

    /** @return list<ResourceRef> */
    public static function refsOf(Service $service): array
    {
        return self::bindingsOf($service)->map(fn (ProviderBinding $binding) => $binding->ref())->values()->all();
    }

    /** The one the platform reaches for when nothing names a domain: the service's first, which is its site's own. */
    public static function firstRefOf(Service $service): ?ResourceRef
    {
        return self::refsOf($service)[0] ?? null;
    }

    /** The name a mail domain binding stands for. */
    public static function nameOf(ProviderBinding $binding): string
    {
        return mb_strtolower(trim((string) (($binding->meta['domain'] ?? '') ?: '')));
    }

    /** The service's mail domain for a name, if it has one. */
    public static function forDomain(Service $service, string $domain): ?ProviderBinding
    {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }

        return self::bindingsOf($service)->first(fn (ProviderBinding $binding) => self::nameOf($binding) === $domain);
    }

    /** The domain part of an address, lower case. */
    public static function domainOf(string $address): string
    {
        $at = mb_strpos($address, '@');

        return $at === false ? '' : mb_strtolower(trim((string) mb_substr($address, $at + 1)));
    }

    /**
     * A listing read from every mail domain of the service at once: what the customer has is what all of their
     * domains hold together, and a number the plan sells is counted over all of them.
     *
     * The fallback is read only when it IS a mail domain — a mail service's own resource. A web hosting that has no
     * mail domain yet has no mailboxes: reading its site as if it were one asked the mail server for `%@` + a name
     * the site's binding did not carry, which is every mailbox on the shared server. The customer was shown all of
     * them, and the ownership check that compares a mailbox id with this listing passed for any of them.
     *
     * @param  callable(ResourceRef): list<array<string,mixed>>  $read
     * @return list<array<string,mixed>>
     */
    public static function across(Service $service, ResourceRef $fallback, callable $read): array
    {
        $refs = self::refsOf($service) ?: ($fallback->remoteType === 'mail_domain' ? [$fallback] : []);
        $out = [];
        foreach ($refs as $ref) {
            foreach ($read($ref) as $row) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * The row of one of the service's own mail domains whose `$field` is `$value` (compared without case), or null.
     *
     * A mailbox is named to the platform by an id or an address, and the panel behind a shared mail server answers one
     * administrator session that never asks whose mailbox it was handed. The only proof that a mailbox is this
     * service's is that the service's own domains list it — so every action and every read that targets ONE mailbox
     * looks it up here first, and a value that is not found means "not yours", never "try the panel".
     *
     * @param  callable(ResourceRef): list<array<string,mixed>>  $read
     * @return array<string,mixed>|null
     */
    public static function ownRow(Service $service, ResourceRef $fallback, callable $read, string $field, string $value): ?array
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return null;
        }
        foreach (self::across($service, $fallback, $read) as $row) {
            if (mb_strtolower(trim((string) ($row[$field] ?? ''))) === $value) {
                return $row;
            }
        }

        return null;
    }
}
