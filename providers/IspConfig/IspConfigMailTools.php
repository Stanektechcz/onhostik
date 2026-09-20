<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * MailToolsProvider for ISPConfig: forwards, catch-all, autoresponders, spam policies with white/black lists,
 * mailbox filters, mailing lists (when the server has a list manager), fetchmail, mailbox backups, quota usage and
 * the webmail address — each one a remote function ISPConfig grants the provisioning user.
 */
trait IspConfigMailTools
{
    public function listForwards(ResourceRef $domain): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_forward_get', ['primary_id' => ['source' => '%@'.$this->mailDomainName($domain)]]) as $row) {
            if (is_array($row) && ! empty($row['forwarding_id'])) {
                $out[] = ['remote_id' => (string) $row['forwarding_id'], 'source' => (string) $row['source'], 'destination' => (string) $row['destination'], 'active' => ($row['active'] ?? 'y') === 'y'];
            }
        }

        return $out;
    }

    public function createForward(ResourceRef $domain, array $forward): ProviderResult
    {
        $id = (int) $this->api->call('mail_forward_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'source' => (string) $forward['source'], 'destination' => (string) $forward['destination'], 'type' => 'forward', 'active' => 'y']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mail_forward', (string) $id, $domain->node, [], $domain->serviceId), ['created' => true]);
    }

    public function deleteForward(ResourceRef $domain, string $remoteId): ProviderResult
    {
        if (collect($this->listForwards($domain))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('mail_forward_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null, ['deleted' => true]);
    }

    public function catchAll(ResourceRef $domain): ?array
    {
        $row = collect((array) $this->api->call('mail_catchall_get', ['primary_id' => ['domain' => $this->mailDomainName($domain)]]))->first();

        return is_array($row) && ! empty($row['forwarding_id']) ? ['remote_id' => (string) $row['forwarding_id'], 'destination' => (string) $row['destination'], 'active' => ($row['active'] ?? 'y') === 'y'] : null;
    }

    public function setCatchAll(ResourceRef $domain, string $destination): ProviderResult
    {
        $current = $this->catchAll($domain);
        if ($destination === '') {
            if ($current === null) {
                return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
            }
            $this->api->call('mail_catchall_delete', ['primary_id' => (int) $current['remote_id']], true);

            return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null, ['deleted' => true]);
        }
        if ($current !== null) {
            $this->api->call('mail_catchall_update', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'primary_id' => (int) $current['remote_id'], 'params' => ['destination' => $destination, 'active' => 'y']], true);

            return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mail_catchall', $current['remote_id'], $domain->node, [], $domain->serviceId), ['updated' => true]);
        }
        $id = (int) $this->api->call('mail_catchall_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'domain' => $this->mailDomainName($domain), 'destination' => $destination, 'type' => 'catchall', 'active' => 'y']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mail_catchall', (string) $id, $domain->node, [], $domain->serviceId), ['created' => true]);
    }

    public function autoresponder(ResourceRef $mailbox): array
    {
        $row = $this->api->call('mail_user_get', ['primary_id' => (int) $mailbox->remoteId]);
        $row = is_array($row) ? $row : [];
        $date = fn (mixed $v) => is_string($v) && $v !== '' && ! str_starts_with($v, '0000') ? substr($v, 0, 10) : null;

        return ['enabled' => (($row['autoresponder'] ?? 'n') === 'y'), 'subject' => $row['autoresponder_subject'] ?? null, 'text' => $row['autoresponder_text'] ?? null, 'start' => $date($row['autoresponder_start_date'] ?? null), 'end' => $date($row['autoresponder_end_date'] ?? null)];
    }

    public function setAutoresponder(ResourceRef $mailbox, array $settings): ProviderResult
    {
        $params = ['autoresponder' => ! empty($settings['enabled']) ? 'y' : 'n'];
        foreach (['subject' => 'autoresponder_subject', 'text' => 'autoresponder_text'] as $k => $field) {
            if (array_key_exists($k, $settings)) {
                $params[$field] = (string) $settings[$k];
            }
        }
        foreach (['start' => 'autoresponder_start_date', 'end' => 'autoresponder_end_date'] as $k => $field) {
            if (! empty($settings[$k])) {
                $params[$field] = substr((string) $settings[$k], 0, 10).($k === 'start' ? ' 00:00:00' : ' 23:59:59');
            }
        }
        $this->api->call('mail_user_update', ['client_id' => (int) ($mailbox->meta['client_id'] ?? 0), 'primary_id' => (int) $mailbox->remoteId, 'params' => $params], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), $mailbox, ['enabled' => $params['autoresponder'] === 'y']);
    }

    public function spamPolicies(ResourceRef $domain): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_policy_get', ['primary_id' => ['policy_name' => '%']]) as $row) {
            if (is_array($row) && ! empty($row['id'])) {
                $out[] = ['remote_id' => (string) $row['id'], 'name' => (string) ($row['policy_name'] ?? $row['id'])];
            }
        }

        return $out;
    }

    public function mailboxSpamPolicy(ResourceRef $mailbox): array
    {
        $row = $this->api->call('mail_user_get', ['primary_id' => (int) $mailbox->remoteId]);
        $email = is_array($row) ? (string) ($row['email'] ?? '') : '';
        $filter = $email === '' ? null : collect((array) $this->api->call('mail_spamfilter_user_get', ['primary_id' => ['email' => $email]]))->first();
        $policyId = is_array($filter) ? (string) ($filter['policy_id'] ?? '') : '';
        $policy = $policyId === '' ? null : collect($this->spamPolicies($mailbox))->firstWhere('remote_id', $policyId);

        return ['policy_id' => $policyId !== '' ? $policyId : null, 'policy' => $policy['name'] ?? null];
    }

    public function setMailboxSpamPolicy(ResourceRef $mailbox, string $policyRemoteId): ProviderResult
    {
        $row = $this->api->call('mail_user_get', ['primary_id' => (int) $mailbox->remoteId]);
        $email = is_array($row) ? (string) ($row['email'] ?? '') : '';
        if ($email === '') {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The mailbox does not exist');
        }
        $filter = collect((array) $this->api->call('mail_spamfilter_user_get', ['primary_id' => ['email' => $email]]))->first();
        if (is_array($filter) && ! empty($filter['id'])) {
            $this->api->call('mail_spamfilter_user_update', ['client_id' => (int) ($mailbox->meta['client_id'] ?? 0), 'primary_id' => (int) $filter['id'], 'params' => ['policy_id' => (int) $policyRemoteId]], true);
        } else {
            $this->api->call('mail_spamfilter_user_add', ['client_id' => (int) ($mailbox->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $mailbox->node, 'priority' => 10, 'policy_id' => (int) $policyRemoteId, 'email' => $email, 'fullname' => $email, 'local' => 'Y']], true);
        }

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), $mailbox, ['policy_id' => $policyRemoteId]);
    }

    public function listSpamLists(ResourceRef $domain): array
    {
        $out = [];
        foreach ($this->domainSpamUserIds($domain) as $rid) {
            foreach ([['mail_spamfilter_whitelist_get', 'whitelist'], ['mail_spamfilter_blacklist_get', 'blacklist']] as [$fn, $kind]) {
                foreach ((array) $this->api->call($fn, ['primary_id' => ['rid' => $rid]]) as $row) {
                    if (is_array($row) && ! empty($row['wblist_id'])) {
                        $out[] = ['remote_id' => (string) $row['wblist_id'], 'kind' => $kind, 'address' => (string) ($row['email'] ?? ''), 'active' => ($row['active'] ?? 'y') === 'y'];
                    }
                }
            }
        }

        return $out;
    }

    public function addSpamListEntry(ResourceRef $domain, string $kind, string $address): ProviderResult
    {
        $rid = $this->ensureDomainSpamUser($domain);
        $fn = $kind === 'blacklist' ? 'mail_spamfilter_blacklist_add' : 'mail_spamfilter_whitelist_add';
        $id = (int) $this->api->call($fn, ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'wb' => $kind === 'blacklist' ? 'B' : 'W', 'rid' => $rid, 'email' => $address, 'priority' => 5, 'active' => 'y']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mail_wblist', (string) $id, $domain->node, ['kind' => $kind], $domain->serviceId), ['created' => true]);
    }

    public function deleteSpamListEntry(ResourceRef $domain, string $kind, string $remoteId): ProviderResult
    {
        if (collect($this->listSpamLists($domain))->first(fn ($e) => $e['remote_id'] === $remoteId && $e['kind'] === $kind) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call($kind === 'blacklist' ? 'mail_spamfilter_blacklist_delete' : 'mail_spamfilter_whitelist_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null, ['deleted' => true]);
    }

    public function listFilters(ResourceRef $mailbox): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_user_filter_get', ['primary_id' => ['mailuser_id' => (int) $mailbox->remoteId]]) as $row) {
            if (is_array($row) && ! empty($row['filter_id'])) {
                $out[] = ['remote_id' => (string) $row['filter_id'], 'name' => (string) ($row['rulename'] ?? ''), 'source' => (string) ($row['source'] ?? ''), 'op' => (string) ($row['op'] ?? ''), 'term' => (string) ($row['searchterm'] ?? ''), 'action' => (string) ($row['action'] ?? ''), 'target' => (string) ($row['target'] ?? ''), 'active' => ($row['active'] ?? 'y') === 'y'];
            }
        }

        return $out;
    }

    public function createFilter(ResourceRef $mailbox, array $filter): ProviderResult
    {
        $id = (int) $this->api->call('mail_user_filter_add', ['client_id' => (int) ($mailbox->meta['client_id'] ?? 0), 'params' => [
            'mailuser_id' => (int) $mailbox->remoteId, 'rulename' => (string) $filter['name'], 'source' => (string) $filter['source'], 'op' => (string) $filter['op'], 'searchterm' => (string) $filter['term'],
            'action' => (string) $filter['action'], 'target' => (string) ($filter['target'] ?? ''), 'active' => 'y',
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), new ResourceRef('mail_filter', (string) $id, $mailbox->node, [], $mailbox->serviceId), ['created' => true]);
    }

    public function deleteFilter(ResourceRef $mailbox, string $remoteId): ProviderResult
    {
        if (collect($this->listFilters($mailbox))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('mail_user_filter_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), null, ['deleted' => true]);
    }

    public function listMailingLists(ResourceRef $domain): array
    {
        $out = [];
        try {
            foreach ((array) $this->api->call('mail_mailinglist_get', ['primary_id' => ['domain' => $this->mailDomainName($domain)]]) as $row) {
                if (is_array($row) && ! empty($row['mailinglist_id'])) {
                    $out[] = ['remote_id' => (string) $row['mailinglist_id'], 'name' => (string) ($row['listname'] ?? ''), 'email' => (string) ($row['email'] ?? ''), 'active' => true];
                }
            }
        } catch (ProviderException) {
            // no list manager on the server
        }

        return $out;
    }

    public function createMailingList(ResourceRef $domain, array $list): ProviderResult
    {
        $id = (int) $this->api->call('mail_mailinglist_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'domain' => $this->mailDomainName($domain), 'listname' => (string) $list['name'], 'email' => (string) $list['email'], 'password' => (string) $list['password']]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mailing_list', (string) $id, $domain->node, [], $domain->serviceId), ['created' => true]);
    }

    public function deleteMailingList(ResourceRef $domain, string $remoteId): ProviderResult
    {
        if (collect($this->listMailingLists($domain))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('mail_mailinglist_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null, ['deleted' => true]);
    }

    public function listFetchmail(ResourceRef $domain): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_fetchmail_get', ['primary_id' => ['destination' => '%@'.$this->mailDomainName($domain)]]) as $row) {
            if (is_array($row) && ! empty($row['mailget_id'])) {
                $out[] = ['remote_id' => (string) $row['mailget_id'], 'type' => (string) ($row['type'] ?? 'pop3'), 'host' => (string) ($row['source_server'] ?? ''), 'user' => (string) ($row['source_username'] ?? ''), 'destination' => (string) ($row['destination'] ?? ''), 'delete' => ($row['source_delete'] ?? 'n') === 'y', 'active' => ($row['active'] ?? 'y') === 'y'];
            }
        }

        return $out;
    }

    public function createFetchmail(ResourceRef $domain, array $account): ProviderResult
    {
        $id = (int) $this->api->call('mail_fetchmail_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $domain->node, 'type' => (string) $account['type'], 'source_server' => (string) $account['host'], 'source_username' => (string) $account['user'], 'source_password' => (string) $account['password'],
            'source_delete' => ! empty($account['delete']) ? 'y' : 'n', 'source_read_all' => 'n', 'destination' => (string) $account['destination'], 'active' => 'y',
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('fetchmail', (string) $id, $domain->node, [], $domain->serviceId), ['created' => true]);
    }

    public function deleteFetchmail(ResourceRef $domain, string $remoteId): ProviderResult
    {
        if (collect($this->listFetchmail($domain))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('mail_fetchmail_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null, ['deleted' => true]);
    }

    public function listMailboxBackups(ResourceRef $domain): array
    {
        $out = [];
        foreach ($this->listMailboxes($domain) as $box) {
            try {
                foreach ((array) $this->api->call('mail_user_backup_list', ['primary_id' => (int) $box['remote_id']]) as $row) {
                    if (is_array($row) && ! empty($row['backup_id'])) {
                        $out[] = ['remote_id' => (string) $row['backup_id'], 'mailbox' => (string) $box['address'], 'created_at' => date('c', (int) ($row['tstamp'] ?? 0)), 'size_bytes' => isset($row['filesize']) ? (int) $row['filesize'] : null];
                    }
                }
            } catch (ProviderException) {
                // backups disabled for the domain
            }
        }

        return $out;
    }

    public function backupMailbox(ResourceRef $mailbox): ProviderResult
    {
        $this->api->call('mail_user_backup', ['primary_id' => (int) $mailbox->remoteId, 'action_type' => 'backup'], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node, [], 20, 3600), $mailbox, ['requested' => true]);
    }

    public function restoreMailbox(ResourceRef $mailbox, string $backupRemoteId): ProviderResult
    {
        $this->api->call('mail_user_backup', ['primary_id' => (int) $mailbox->remoteId, 'action_type' => 'restore', 'backup_id' => (int) $backupRemoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node, ['backup_id' => $backupRemoteId], 20, 3600), $mailbox, ['requested' => true]);
    }

    public function mailboxUsage(ResourceRef $domain): array
    {
        $out = [];
        $suffix = '@'.$this->mailDomainName($domain);
        foreach ((array) $this->api->call('mailquota_get_by_user', ['client_id' => (int) ($domain->meta['client_id'] ?? 0)]) as $row) {
            if (is_array($row) && str_ends_with((string) ($row['email'] ?? ''), $suffix)) {
                $out[] = ['mailbox' => (string) $row['email'], 'used_bytes' => (int) ($row['used'] ?? 0), 'quota_bytes' => isset($row['quota']) && (int) $row['quota'] > 0 ? (int) $row['quota'] : null];
            }
        }

        return $out;
    }

    public function webmailUrl(ResourceRef $domain): ?string
    {
        $url = (string) $this->instance->option('webmail_url', '');

        return $url !== '' ? $url : null;
    }

    private function mailDomainName(ResourceRef $domain): string
    {
        $name = (string) ($domain->meta['domain'] ?? '');
        if ($name === '') {
            $row = $this->api->call('mail_domain_get', ['primary_id' => (int) $domain->remoteId]);
            $name = is_array($row) ? (string) ($row['domain'] ?? '') : '';
        }

        return $name;
    }

    /** @return list<int> spamfilter user ids of the domain (the `@domain` policy holder and every mailbox) */
    private function domainSpamUserIds(ResourceRef $domain): array
    {
        $ids = [];
        foreach ((array) $this->api->call('mail_spamfilter_user_get', ['primary_id' => ['email' => '%@'.$this->mailDomainName($domain)]] /* anchored at the @: `%example.cz` is also somebody else's myexample.cz */) as $row) {
            if (is_array($row) && ! empty($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    private function ensureDomainSpamUser(ResourceRef $domain): int
    {
        $email = '@'.$this->mailDomainName($domain);
        $row = collect((array) $this->api->call('mail_spamfilter_user_get', ['primary_id' => ['email' => $email]]))->first();
        if (is_array($row) && ! empty($row['id'])) {
            return (int) $row['id'];
        }
        $policies = $this->spamPolicies($domain);
        $policy = (int) ($policies[0]['remote_id'] ?? 1);

        return (int) $this->api->call('mail_spamfilter_user_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'priority' => 5, 'policy_id' => $policy, 'email' => $email, 'fullname' => $email, 'local' => 'Y']], true);
    }
}
