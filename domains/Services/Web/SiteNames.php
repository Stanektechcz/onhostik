<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use InvalidArgumentException;
use Onhost\Domain\Dns\DomainPointing;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Support\Hostname;

/**
 * Who may claim a host name.
 *
 * A shared web node serves dozens of customers from one address, and a web server picks the site that answers a
 * request purely by the name in it. So the one thing the platform has to protect is that **a name belongs to one
 * service**: whoever holds it gets the requests, the certificate and the mail that follow it.
 *
 * It was protected in exactly one place — adding a further site to an existing hosting — and nowhere else. The
 * certificate action took any name the customer typed, and since a neighbour on the same node *does* answer at that
 * node's address, every "is it really pointed at us" test (`DomainPointing`) said yes; adding a domain to a site took
 * any name too; and the order, the way every hosting is really created, copied `domain` and `aliases` out of the cart
 * without looking at them at all.
 *
 * The rule, in one place:
 *  - a name another live service already holds is that service's — nobody else may claim it;
 *  - a name **under** a name another organization holds is that organization's (their wildcard would hand it to
 *    whoever serves it here);
 *  - a name under one of your own names is always yours;
 *  - anything else is free, and what happens next is answered by the layers that already answer for it: the
 *    certificate authority (does the name point here), the panel (does the vhost exist), DNS (who publishes it).
 */
final class SiteNames
{
    /** How many further names one site may carry — the panels write them all into one `server_name`. */
    public const MAX_ALIASES = 25;

    /**
     * Who competes for a name. A web vhost and a mail domain are two namespaces, not one: the same customer's
     * `muj-web.cz` is normally both a website and a mailbox domain, and refusing the second would refuse the
     * ordinary case. Inside one namespace the name belongs to one service.
     */
    public const WEB = ['web', 'managed'];

    public const MAIL = ['mail'];

    /** Lower-case ASCII (IDN as punycode), no trailing dot, or a refusal the customer can read. */
    public static function normalize(string $name, string $field = 'domain', string $what = 'Zadejte doménu webu, například muj-web.cz.'): string
    {
        try {
            return Hostname::canonical($name);
        } catch (InvalidArgumentException) {
            throw new DomainError('action_param_invalid', $what, 422, ['field' => $field, 'value' => mb_substr(trim($name), 0, 190)]);
        }
    }

    /**
     * The live service that holds this exact name in this namespace, if any.
     *
     * @param  list<string>  $families
     */
    public static function holder(string $name, ?string $exceptServiceId = null, array $families = self::WEB): ?Service
    {
        $name = mb_strtolower(rtrim(trim($name), '.'));
        $query = Service::query()->whereNotIn('state', [ServiceStateMachine::TERMINATED])->whereIn('family', $families)
            ->where(fn ($where) => $where->where('hostname', $name)
                ->orWhereIn('id', Website::query()->select('service_id')->where('domain', $name))
                ->orWhereIn('id', Website::query()->select('service_id')->whereJsonContains('aliases', $name)));
        if ($exceptServiceId !== null) {
            $query->where('id', '!=', $exceptServiceId);
        }

        return $query->first();
    }

    /**
     * A live service of **another** organization whose own name covers this one (`blog.soused.cz` under `soused.cz`).
     * Within one organization nothing is covered: a customer may split their own domain across their own services.
     */
    public static function coveringOther(string $name, ?string $organizationId, ?string $exceptServiceId = null, array $families = self::WEB): ?Service
    {
        $name = mb_strtolower(rtrim(trim($name), '.'));
        $parts = explode('.', $name);
        $suffixes = [];
        for ($i = 1; $i < count($parts) - 1; $i++) { // every parent but the public suffix itself
            $suffixes[] = implode('.', array_slice($parts, $i));
        }
        if ($suffixes === []) {
            return null;
        }
        $query = Service::query()->whereNotIn('state', [ServiceStateMachine::TERMINATED])->whereIn('family', $families)->whereIn('hostname', $suffixes);
        if ($organizationId !== null) {
            $query->where('organization_id', '!=', $organizationId);
        }
        if ($exceptServiceId !== null) {
            $query->where('id', '!=', $exceptServiceId);
        }

        return $query->first();
    }

    /** Refuses a name this organization may not claim, and answers with the name as it will be stored. */
    public static function assertFree(string $name, ?string $organizationId, ?string $exceptServiceId = null, string $field = 'domain', array $families = self::WEB): string
    {
        $name = self::normalize($name, $field);
        $holder = self::holder($name, $exceptServiceId, $families);
        if ($holder !== null) {
            // the customer's own service is named, so they can act on it; somebody else's never is
            if ($organizationId !== null && $holder->organization_id === $organizationId) {
                throw new DomainError('site_name_taken', "Doménu {$name} už obsluhuje vaše služba ".($holder->label ?: $holder->name).'. Zrušte ji, nebo zvolte jiné jméno.', 409, ['field' => $field, 'domain' => $name]);
            }
            // a claim nobody proved, refusing somebody who can prove the name, is a squat — support decides, and gets
            // the evidence in the audit trail instead of a customer's word against a customer's word
            $mine = self::organizationProof($organizationId, $name);
            if ($mine !== null && self::recordedProof($holder, $name) === null) {
                app(AuditRecorder::class)->record(CommandContext::system('site.claim')->withScope((string) $organizationId), 'service.name_disputed', 'succeeded',
                    ['domain' => $name, 'claimed_by' => $holder->id, 'claimed_since' => (string) (data_get($holder->tags, 'name_claim.unproved_since') ?? $holder->created_at), 'asked_by_proof' => $mine], 'service', $holder->id);
            }

            throw new DomainError('site_name_taken', "Doménu {$name} u nás zatím obsluhuje jiná služba. ".($mine !== null
                ? 'Vedeme ji pro vás, takže jméno uvolníme — napište prosím podpoře.'
                : 'Pokud je vaše, napište podpoře a vlastnictví ověříme.'), 409, ['field' => $field, 'domain' => $name]);
        }
        $parent = self::coveringOther($name, $organizationId, $exceptServiceId, $families);
        if ($parent !== null) {
            throw new DomainError('site_name_taken', "Doména {$parent->hostname} patří jinému zákazníkovi, takže {$name} pod ní nelze přidat.", 409, ['field' => $field, 'domain' => $name]);
        }

        return $name;
    }

    /**
     * What proves this service may hold this name, or null when nothing does.
     *
     * A claim that nothing has to prove is a weapon: order the cheapest hosting for somebody else's domain, never
     * point it anywhere, and its real owner can never be hosted here. Nothing new is asked of an honest customer —
     * the platform already knows three things that prove a name, and the daily DNS check works the third one out
     * anyway. Order matters only for the answer's wording: the two cheap database answers come before the resolver.
     */
    public static function proof(Service $service, ?string $name = null): ?string
    {
        $name = mb_strtolower(rtrim(trim($name ?? (string) ($service->hostname ?? '')), '.'));
        if ($name === '') {
            return null;
        }
        $held = self::organizationProof((string) $service->organization_id, $name);
        if ($held !== null) {
            return $held;
        }
        $addresses = DomainPointing::addressesOf($service);

        return $addresses !== [] && app(DomainPointing::class)->pointsAt($name, $addresses) ? 'dns' : null;
    }

    /**
     * What an **organization** can show for a name without asking DNS: the domain is registered here, or its zone
     * is run here. A parent counts — holding `firma.cz` proves `blog.firma.cz`.
     */
    public static function organizationProof(?string $organizationId, string $name): ?string
    {
        if ($organizationId === null || $organizationId === '') {
            return null;
        }
        $name = mb_strtolower(rtrim(trim($name), '.'));
        $parts = explode('.', $name);
        $names = [];
        for ($i = 0; $i < max(1, count($parts) - 1); $i++) {
            $names[] = implode('.', array_slice($parts, $i));
        }
        if (Domain::query()->whereIn('fqdn_ascii', $names)->where('organization_id', $organizationId)
            ->whereNotIn('state', [DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT, DomainStateMachine::FAILED])->exists()) {
            return 'domain';
        }

        return DnsZone::query()->whereIn('name', $names)->where('organization_id', $organizationId)->where('state', 'active')->exists() ? 'zone' : null;
    }

    /**
     * What a holder can show, without asking DNS again: the daily check has already written its answer down
     * (`tags.name_claim`), and a refusal is no place to wait for a resolver.
     */
    public static function recordedProof(Service $holder, string $name): ?string
    {
        $held = self::organizationProof((string) $holder->organization_id, $name);

        return $held ?? (($proof = data_get($holder->tags, 'name_claim.proof')) === null ? null : (string) $proof);
    }

    /**
     * The names this service itself serves: its own domain and the further names written into its vhost.
     *
     * @return list<string>
     */
    public static function heldBy(Service $service): array
    {
        $names = [mb_strtolower((string) ($service->hostname ?? '')), mb_strtolower((string) $service->spec('domain', ''))];
        foreach (Website::query()->where('service_id', $service->id)->get() as $website) {
            $names[] = mb_strtolower((string) $website->domain);
            foreach ((array) $website->aliases as $alias) {
                $names[] = mb_strtolower(trim((string) $alias));
            }
        }

        return array_values(array_filter(array_unique($names), fn (string $name) => $name !== ''));
    }

    /**
     * A name this service may be asked to serve: one of its own, one under its own, or one nobody here holds.
     * Answers with the name as the panel will get it.
     */
    public static function assertAllowed(Service $service, string $name, string $field = 'domain'): string
    {
        $name = self::normalize($name, $field, 'Zadejte platné jméno, například '.($service->hostname ?: 'muj-web.cz').'.');
        foreach (self::heldBy($service) as $own) {
            if ($name === $own || str_ends_with($name, '.'.$own)) {
                return $name;
            }
        }

        return self::assertFree($name, (string) $service->organization_id, $service->id, $field, in_array((string) $service->family, self::MAIL, true) ? self::MAIL : self::WEB);
    }

    /**
     * The further names an order asks for: host names, without the site's own name, without repeats, capped.
     *
     * @param  mixed  $aliases  whatever the cart sent
     * @return list<string>
     */
    public static function aliases(mixed $aliases, string $domain, ?string $organizationId, ?string $exceptServiceId = null): array
    {
        if ($aliases === null || $aliases === '' || $aliases === []) {
            return [];
        }
        if (! is_array($aliases)) {
            throw new DomainError('action_param_invalid', 'Další jména webu zadejte jako seznam domén.', 422, ['field' => 'aliases']);
        }
        if (count($aliases) > self::MAX_ALIASES) {
            throw new DomainError('action_param_invalid', 'Jeden web unese nejvýš '.self::MAX_ALIASES.' dalších jmen.', 422, ['field' => 'aliases']);
        }
        $out = [];
        foreach ($aliases as $alias) {
            if (! is_string($alias) && ! is_int($alias)) {
                throw new DomainError('action_param_invalid', 'Další jména webu zadejte jako seznam domén.', 422, ['field' => 'aliases']);
            }
            $name = self::normalize((string) $alias, 'aliases', 'Další jméno webu musí být doména, například www.muj-web.cz.');
            if ($name === $domain || in_array($name, $out, true)) {
                continue; // the site's own name and a repeat are not a second name, they are the same name
            }
            if (! str_ends_with($name, '.'.$domain)) {
                self::assertFree($name, $organizationId, $exceptServiceId, 'aliases');
            }
            $out[] = $name;
        }

        return $out;
    }
}
