<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Services\Mail\MailDomains;

/**
 * Whose an ISPConfig record was, as far as the platform's own logs say it — never by asking the panel.
 *
 * Every listing the panel answered (`sites_shell_user_get` of a site, `mail_user_get` of a domain), every record read by
 * its id and every record the platform created left a row in `provider_calls`. They are read once, with no lower date
 * bound — a listing made long before the window still says whose a record was, since ISPConfig never reuses an id
 * (ASSUMED) — into instance → kind → id → owners. A refused call proves nothing and is skipped; a cut or unreadable
 * row is counted, not guessed at.
 */
final class IspConfigOwnershipEvidence
{
    /** listing function => [kind, id fields of a record] */
    private const LISTINGS = [
        'sites_shell_user_get' => ['shell_user', ['shell_user_id']],
        'sites_database_user_get' => ['database_user', ['database_user_id']],
        'sites_database_get' => ['database_user', ['database_user_id', 'database_ro_user_id']],
        'mail_user_get' => ['mailbox', ['mailuser_id']],
        'mail_alias_get' => ['mail_alias', ['forwarding_id']],
    ];

    /** creating function => kind of the id it answers with */
    private const CREATIONS = [
        'sites_shell_user_add' => 'shell_user', 'sites_database_user_add' => 'database_user', 'mail_user_add' => 'mailbox',
        'mail_alias_add' => 'mail_alias', 'sites_web_domain_add' => 'web_domain',
    ];

    /** @var array<string, array<string, array<int, list<array<string,mixed>>>>> */
    private array $index = [];

    /** @var array<string,int> */
    private array $unparseable = [];

    private function __construct() {}

    /** @param list<string> $instanceKeys */
    public static function build(array $instanceKeys): self
    {
        $evidence = new self;
        DB::table('provider_calls')->where('provider', 'ispconfig')->whereIn('instance_key', $instanceKeys)->whereIn('action', OwnershipAuditTargets::EVIDENCE_FUNCTIONS)
            ->select(['id', 'instance_key', 'action', 'body_code', 'request', 'response', 'created_at'])
            ->lazyById(1000, 'id')
            ->each(fn (object $row) => $evidence->read($row));

        return $evidence;
    }

    /**
     * @param  list<string>  $instanceKeys
     * @return list<array<string,mixed>> what the logs say about the record: site, name, address, how it was seen, the call
     */
    public function owners(array $instanceKeys, string $kind, int $id): array
    {
        $owners = [];
        foreach ($instanceKeys as $key) {
            foreach ($this->index[$key][$kind][$id] ?? [] as $owner) {
                $owners[] = $owner + ['instance_key' => $key];
            }
        }

        return $owners;
    }

    /** Whether the platform itself created this site (a `sites_web_domain_add` it logged). */
    public function createdSite(string $instanceKey, int $siteId): bool
    {
        return isset($this->index[$instanceKey]['web_domain'][$siteId]);
    }

    /** @return array<string,int> instance => rows that could not be read */
    public function unparseable(): array
    {
        return $this->unparseable;
    }

    private function read(object $row): void
    {
        $call = ProviderCallRow::of($row);
        $instance = (string) $row->instance_key;
        if (! $call->parsed) {
            $this->unparseable[$instance] = ($this->unparseable[$instance] ?? 0) + 1;

            return;
        }
        if ((string) $row->body_code !== 'ok') {
            return; // a refused call says nothing about whose a record is
        }
        $seen = ['call' => (string) $row->id, 'at' => (string) $row->created_at];
        $function = (string) $row->action;
        match (true) {
            isset(self::CREATIONS[$function]) => $this->readCreation($instance, $function, $call, $seen),
            $function === 'sites_database_add' => $this->readRecords($instance, 'sites_database_get', [$call->params()], [], $seen + ['via' => 'created']),
            $function === 'mail_user_update' => $this->readRecords($instance, 'mail_user_get', [['mailuser_id' => $call->primaryId()] + $call->params()], [], $seen + ['via' => 'read']),
            default => $this->readRecords($instance, $function, $call->rows(), $call->filter(), $seen + ['via' => $call->filter() === [] ? 'read' : 'listing']),
        };
    }

    /** @param array<string,string> $seen */
    private function readCreation(string $instance, string $function, ProviderCallRow $call, array $seen): void
    {
        $id = $call->createdId();
        if ($id === null) {
            return;
        }
        $params = $call->params();
        $this->add($instance, self::CREATIONS[$function], $id, $this->ownerOf($params, [], $seen + ['via' => 'created']));
    }

    /**
     * @param  list<array<string,mixed>>  $records
     * @param  array<string,mixed>  $filter
     * @param  array<string,string>  $seen
     */
    private function readRecords(string $instance, string $function, array $records, array $filter, array $seen): void
    {
        [$kind, $fields] = self::LISTINGS[$function] ?? [null, []];
        if ($kind === null) {
            return;
        }
        foreach ($records as $record) {
            foreach ($fields as $field) {
                $id = ProviderCallRow::intOf($record[$field] ?? null);
                if ($id !== null) {
                    $this->add($instance, $kind, $id, $this->ownerOf($record, $filter, $seen));
                }
            }
        }
    }

    /**
     * @param  array<string,mixed>  $record
     * @param  array<string,mixed>  $filter
     * @param  array<string,string>  $seen
     * @return array<string,mixed>
     */
    private function ownerOf(array $record, array $filter, array $seen): array
    {
        $address = $record['email'] ?? $record['source'] ?? null;
        $name = $record['username'] ?? $record['database_user'] ?? null;

        return array_filter([
            'site' => ProviderCallRow::intOf($record['parent_domain_id'] ?? $filter['parent_domain_id'] ?? null),
            'name' => is_string($name) && $name !== '' ? $name : null,
            'address' => is_string($address) && str_contains($address, '@') ? mb_strtolower($address) : null,
            'mail_domain' => is_string($address) && str_contains($address, '@') ? MailDomains::domainOf($address) : null,
        ], fn ($value) => $value !== null) + $seen;
    }

    /** @param array<string,mixed> $owner */
    private function add(string $instance, string $kind, int $id, array $owner): void
    {
        if ($kind !== 'web_domain' && ! isset($owner['site']) && ! isset($owner['name']) && ! isset($owner['mail_domain'])) {
            return; // a record that names nobody (an update that sent only a quota) is no evidence
        }
        foreach ($this->index[$instance][$kind][$id] ?? [] as $known) {
            if (($known['site'] ?? null) === ($owner['site'] ?? null) && ($known['name'] ?? null) === ($owner['name'] ?? null) && ($known['mail_domain'] ?? null) === ($owner['mail_domain'] ?? null)) {
                return; // the same thing seen again: the first sighting is kept
            }
        }
        $this->index[$instance][$kind][$id][] = $owner;
    }
}
