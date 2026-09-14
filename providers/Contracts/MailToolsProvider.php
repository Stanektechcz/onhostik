<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * The rest of a mail server's feature set beyond mailboxes and aliases (MailProvider): forwards, catch-all,
 * autoresponders, spam policies, white/black lists, sieve-like filters, mailing lists, fetchmail, mailbox backups,
 * quota usage and the webmail address. ISPConfig implements it through its remote API.
 */
interface MailToolsProvider
{
    /** @return list<array{remote_id:string, source:string, destination:string, active:bool}> */
    public function listForwards(ResourceRef $domain): array;

    /** @param array{source:string, destination:string} $forward */
    public function createForward(ResourceRef $domain, array $forward): ProviderResult;

    public function deleteForward(ResourceRef $domain, string $remoteId): ProviderResult;

    /** @return array{remote_id:string, destination:string, active:bool}|null */
    public function catchAll(ResourceRef $domain): ?array;

    /** Empty destination removes the catch-all. */
    public function setCatchAll(ResourceRef $domain, string $destination): ProviderResult;

    /** @return array{enabled:bool, subject:?string, text:?string, start:?string, end:?string} */
    public function autoresponder(ResourceRef $mailbox): array;

    /** @param array{enabled:bool, subject?:string, text?:string, start?:string, end?:string} $settings */
    public function setAutoresponder(ResourceRef $mailbox, array $settings): ProviderResult;

    /** @return list<array{remote_id:string, name:string}> */
    public function spamPolicies(ResourceRef $domain): array;

    /** @return array{policy_id:?string, policy:?string} */
    public function mailboxSpamPolicy(ResourceRef $mailbox): array;

    public function setMailboxSpamPolicy(ResourceRef $mailbox, string $policyRemoteId): ProviderResult;

    /** @return list<array{remote_id:string, kind:string, address:string, active:bool}> kind whitelist|blacklist */
    public function listSpamLists(ResourceRef $domain): array;

    public function addSpamListEntry(ResourceRef $domain, string $kind, string $address): ProviderResult;

    public function deleteSpamListEntry(ResourceRef $domain, string $kind, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, name:string, source:string, op:string, term:string, action:string, target:string, active:bool}> */
    public function listFilters(ResourceRef $mailbox): array;

    /** @param array{name:string, source:string, op:string, term:string, action:string, target?:string} $filter */
    public function createFilter(ResourceRef $mailbox, array $filter): ProviderResult;

    public function deleteFilter(ResourceRef $mailbox, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, name:string, email:string, active:bool}> */
    public function listMailingLists(ResourceRef $domain): array;

    /** @param array{name:string, email:string, password:string} $list */
    public function createMailingList(ResourceRef $domain, array $list): ProviderResult;

    public function deleteMailingList(ResourceRef $domain, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, type:string, host:string, user:string, destination:string, delete:bool, active:bool}> */
    public function listFetchmail(ResourceRef $domain): array;

    /** @param array{type:string, host:string, user:string, password:string, destination:string, delete?:bool} $account */
    public function createFetchmail(ResourceRef $domain, array $account): ProviderResult;

    public function deleteFetchmail(ResourceRef $domain, string $remoteId): ProviderResult;

    /** @return list<array{remote_id:string, mailbox:string, created_at:string, size_bytes:?int}> */
    public function listMailboxBackups(ResourceRef $domain): array;

    public function backupMailbox(ResourceRef $mailbox): ProviderResult;

    public function restoreMailbox(ResourceRef $mailbox, string $backupRemoteId): ProviderResult;

    /** @return list<array{mailbox:string, used_bytes:int, quota_bytes:?int}> */
    public function mailboxUsage(ResourceRef $domain): array;

    public function webmailUrl(ResourceRef $domain): ?string;
}
