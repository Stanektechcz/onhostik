<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A mail provider whose server keeps mailbox backups on its own schedule, with a retention the platform can set per
 * mailbox (TASK-0024, owner decision 3: a mail plan's `backup_days`).
 *
 * Both methods act only inside a mail domain the provider can prove the platform made, and only on mailboxes it can prove
 * belong to that domain's owner: a shared mail server also holds historical customers' mail, which is never written. A
 * domain that is not provably the platform's is refused with CONFLICT (VALIDATION when the reference names no owner at
 * all); nothing is sent to the panel but reads.
 */
interface MailboxBackupRetention
{
    /** The largest number of daily copies the platform will ask for; no plan sells more. */
    public const MAX_COPIES = 365;

    /**
     * Every mailbox the domain's listing answers with, its current backup interval and copies, and whether it is provably
     * the platform's (`owned`). Read-only.
     *
     * @return list<array{remote_id:string, address:string, interval:string, copies:int, owned:bool}>
     */
    public function mailboxBackupRetention(ResourceRef $domain): array;

    /**
     * Daily backups kept for `$copies` days on one mailbox of the domain, after proving again that the mailbox is the
     * platform's (NOT_FOUND otherwise, with no write). `$copies` outside 1..MAX_COPIES is VALIDATION. The rest of the
     * mailbox record is kept as it is; the password is never sent back.
     */
    public function setMailboxBackupRetention(ResourceRef $domain, string $mailboxRemoteId, int $copies): ProviderResult;
}
