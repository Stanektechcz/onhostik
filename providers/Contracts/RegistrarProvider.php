<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Registrar contract (blueprint §45). WEDOS WAPI is the primary implementation;
 * the DNS provider is a separate abstraction on purpose.
 */
interface RegistrarProvider extends ProviderAdapter
{
    /** @param list<string> $fqdns @return array<string, array{available:bool|null, reason?:string}> */
    public function checkAvailability(array $fqdns): array;

    /** @return array{periods:list<int>, default:int} */
    public function tldPeriods(string $tld): array;

    /** @return array<string,mixed> normalised domain info (expires_at, nameservers, status, registrant, nsset, dnssec) */
    public function domainInfo(string $fqdn): array;

    /** @return list<array<string,mixed>> */
    public function listDomains(): array;

    /** @param array<string,mixed> $request registrant/admin contact ids, nameservers or nsset, period, dnssec keys, rules consent */
    public function register(string $fqdn, array $request, string $clTrid, bool $testMode = false): ProviderResult;

    public function renew(string $fqdn, int $period, string $clTrid, bool $testMode = false): ProviderResult;

    public function transferCheck(string $fqdn): array;

    public function transferIn(string $fqdn, string $authInfo, array $request, string $clTrid): ProviderResult;

    public function sendAuthInfo(string $fqdn, string $clTrid): ProviderResult;

    /** @param list<string> $nameservers */
    public function updateNameservers(string $fqdn, array $nameservers, ?string $nsset, string $clTrid): ProviderResult;

    /** @param array<string,mixed> $keyset */
    public function updateKeyset(string $fqdn, array $keyset, string $clTrid): ProviderResult;

    /** @param array<string,mixed> $contact @return array{remote_id:string} */
    public function createContact(array $contact, string $clTrid): array;

    public function contactInfo(string $remoteId): array;

    /** @param array<string,mixed> $contact */
    public function updateContact(string $remoteId, array $contact, string $clTrid): ProviderResult;

    /** .CZ NSSET objects. @param list<array{name:string, addr?:list<string>}> $nameservers */
    public function createNsset(string $handle, array $nameservers, string $techContact, string $clTrid): ProviderResult;

    public function nssetInfo(string $handle): array;

    /** @return array{balance:string,currency:string} */
    public function creditInfo(): array;

    /** @return list<array<string,mixed>> */
    public function accountMovements(?string $from = null, ?string $to = null): array;

    /** Async notification queue: fetch one pending notification (poll-req). */
    public function pollRequest(): ?array;

    /** Acknowledge a processed notification (poll-ack) — only after the local transaction committed. */
    public function pollAck(string $notificationId): void;

    public function awaitStatus(AsyncHandle $handle): AsyncStatus;
}
