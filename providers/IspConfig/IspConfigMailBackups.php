<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\MailboxBackupRetention;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * MailboxBackupRetention for ISPConfig (TASK-0024): the server backs every mailbox up on its own nightly run, keeping
 * `backup_copies` of them when `backup_interval` is set; a mail plan's `backup_days` is that number.
 *
 * The shared mail server also holds historical customers' mail, and the remote API answers one administrator session that
 * never asks whose record an id is. So nothing is written before the domain is proven the platform's — its row carries
 * the group of the organisation's `onh_` client — and each mailbox proven to carry that same group and an address inside
 * the domain. What cannot be proven is reported, never touched.
 */
trait IspConfigMailBackups
{
    /**
     * "domain id|client id|name" → the group of the ONhost client proven to own it. The adapter lives as long as the queue
     * worker (ProviderRegistry caches it), so a proof holds only for the very reference it was made for: the same id
     * handed over under another name or client is proven again.
     *
     * @var array<string,int>
     */
    private array $provenMailGroups = [];

    public function mailboxBackupRetention(ResourceRef $domain): array
    {
        $group = $this->provenMailGroup($domain);
        $suffix = '@'.$this->mailDomainName($domain);
        $out = [];
        foreach ((array) $this->api->call('mail_user_get', ['primary_id' => ['email' => '%'.$suffix]]) as $row) {
            if (! is_array($row) || empty($row['mailuser_id'])) {
                continue;
            }
            $out[] = [
                'remote_id' => (string) $row['mailuser_id'], 'address' => (string) ($row['email'] ?? ''),
                'interval' => (string) (($row['backup_interval'] ?? '') ?: 'none'), 'copies' => (int) ($row['backup_copies'] ?? 0),
                'owned' => self::mailboxOwnedBy($row, $group, $suffix),
            ];
        }

        return $out;
    }

    public function setMailboxBackupRetention(ResourceRef $domain, string $mailboxRemoteId, int $copies): ProviderResult
    {
        if ($copies < 1 || $copies > MailboxBackupRetention::MAX_COPIES) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'A mailbox keeps between 1 and '.MailboxBackupRetention::MAX_COPIES." daily backups, not {$copies}.");
        }
        if (! ctype_digit($mailboxRemoteId)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The mailbox does not belong to this mail domain');
        }
        $group = $this->provenMailGroup($domain);
        $suffix = '@'.$this->mailDomainName($domain);
        $row = $this->api->call('mail_user_get', ['primary_id' => (int) $mailboxRemoteId]);
        $row = is_array($row) && array_is_list($row) ? (array) ($row[0] ?? []) : (array) $row;
        if (empty($row['mailuser_id']) || (string) $row['mailuser_id'] !== $mailboxRemoteId || ! self::mailboxOwnedBy($row, $group, $suffix)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The mailbox does not belong to this mail domain');
        }
        $mailbox = new ResourceRef('mailbox', $mailboxRemoteId, $domain->node, ['client_id' => (int) $domain->meta['client_id'], 'email' => (string) $row['email']], $domain->serviceId);
        $this->updateMailUser($mailbox, ['backup_interval' => 'daily', 'backup_copies' => $copies]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), $mailbox, ['copies' => $copies, 'mailbox' => (string) $row['email']]);
    }

    /**
     * The group of the organisation's ONhost client, once the domain is proven to be ITS: the binding names the client,
     * the panel says that client is one the platform made (`onh_…`), and the mail domain row is this very domain and
     * carries that client's group — the same proof `createMailDomain` asks before it adopts a domain.
     */
    private function provenMailGroup(ResourceRef $domain): int
    {
        $clientId = (int) ($domain->meta['client_id'] ?? 0);
        if ($domain->remoteType !== 'mail_domain' || $clientId <= 0 || ! ctype_digit($domain->remoteId)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The mail domain names no ONhost client; its mailboxes are not proven ours and are not touched.');
        }
        $name = $this->mailDomainName($domain);
        $key = $domain->remoteId.'|'.$clientId.'|'.$name;
        if (isset($this->provenMailGroups[$key])) {
            return $this->provenMailGroups[$key];
        }
        $client = $this->api->call('client_get', ['client_id' => $clientId]);
        $client = is_array($client) && array_is_list($client) ? (array) ($client[0] ?? []) : (array) $client;
        $group = str_starts_with((string) ($client['username'] ?? ''), 'onh_') ? $this->groupOf($clientId) : null;
        $row = $group === null ? null : $this->getMailDomain((int) $domain->remoteId);
        if ($group === null || $row === null || (int) ($row['sys_groupid'] ?? 0) !== $group || mb_strtolower(trim((string) ($row['domain'] ?? ''))) !== $name) {
            throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "The mail domain {$name} is not proven to be ONhost's own; it is historical mail and its mailboxes are not touched.");
        }

        return $this->provenMailGroups[$key] = $group;
    }

    /** @param  array<string,mixed>  $row */
    private static function mailboxOwnedBy(array $row, int $group, string $suffix): bool
    {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

        return (int) ($row['sys_groupid'] ?? 0) === $group && str_ends_with($email, $suffix) && strlen($email) > strlen($suffix);
    }
}
