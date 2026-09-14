<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface MailProvider extends ProviderAdapter
{
    public function createMailDomain(ResourceSpec $spec): ProviderResult;

    public function deleteMailDomain(ResourceRef $domain): ProviderResult;

    /** @param array{address:string,password:string,quota_mb:int,name?:string} $mailbox */
    public function createMailbox(ResourceRef $domain, array $mailbox): ProviderResult;

    public function updateMailbox(ResourceRef $mailbox, array $changes): ProviderResult;

    public function deleteMailbox(ResourceRef $mailbox): ProviderResult;

    /** @param array{source:string,destination:string} $alias */
    public function createAlias(ResourceRef $domain, array $alias): ProviderResult;

    /** @return list<array{remote_id:string,address:string,name:?string,quota_mb:?int,used_mb:?int,active:bool}> */
    public function listMailboxes(ResourceRef $domain): array;

    /** @return list<array{remote_id:string,source:string,destination:string,active:bool}> */
    public function listAliases(ResourceRef $domain): array;

    public function deleteAlias(ResourceRef $alias): ProviderResult;

    /** @return array{selector:string,public_key:string,dns_record:string}|null */
    public function dkim(ResourceRef $domain): ?array;

    /** Receive-only mode used by dunning (LimitMailbox step). */
    public function setSendingEnabled(ResourceRef $domain, bool $enabled): ProviderResult;

    public function awaitStatus(AsyncHandle $handle): AsyncStatus;
}
