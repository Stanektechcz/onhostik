<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;

/**
 * What the audit of past provider calls looks at (TASK-0020): the service actions that named ONE ISPConfig record by an
 * id the customer sent, and which the platform acted on without checking the service owned it until TASK-0005.
 *
 * The mail half is derived from the guard itself (ServiceActionWorkflow::OWN_MAIL_TARGETS), so an action the guard
 * starts to cover cannot be missed here — OwnershipAuditTargetsTest keeps the two lists equal.
 */
final class OwnershipAuditTargets
{
    /**
     * Web actions: action => [kind, parameter naming the target, write functions, how a write is tied to the operation].
     * The tie is [path in the logged request body, parameter of the operation it must equal]; null = by time only.
     *
     * @var array<string, array{0:string, 1:string, 2:list<string>, 3:array{0:string,1:string}|null}>
     */
    public const WEB = [
        'shell.key' => ['shell_user', 'remote_id', ['sites_shell_user_update'], ['primary_id', 'remote_id']],
        'dbuser.password' => ['database_user', 'remote_id', ['sites_database_user_update'], ['primary_id', 'remote_id']],
        'dbuser.delete' => ['database_user', 'remote_id', ['sites_database_user_delete'], ['primary_id', 'remote_id']],
    ];

    /**
     * Mail actions of the guard: action => [kind, write functions, tie]. The target parameter is the guard's own.
     * `mailbox.backup` writes nothing (ISPConfig backs mailboxes up on its own schedule); `spam.policy` writes the
     * mailbox's spam-filter row, whose id is not the mailbox's, so it is tied by time only; `fetchmail.create` names a
     * mailbox by its address.
     *
     * @var array<string, array{0:string, 1:list<string>, 2:array{0:string,1:string}|null}>
     */
    public const MAIL_FUNCTIONS = [
        'mailbox.update' => ['mailbox', ['mail_user_update'], ['primary_id', 'remote_id']],
        'mailbox.delete' => ['mailbox', ['mail_user_delete'], ['primary_id', 'remote_id']],
        'alias.delete' => ['mail_alias', ['mail_alias_delete'], ['primary_id', 'remote_id']],
        'autoresponder.set' => ['mailbox', ['mail_user_update'], ['primary_id', 'remote_id']],
        'spam.policy' => ['mailbox', ['mail_spamfilter_user_update', 'mail_spamfilter_user_add'], null],
        'filter.create' => ['mailbox', ['mail_user_filter_add'], ['params.mailuser_id', 'remote_id']],
        'filter.delete' => ['mailbox', ['mail_user_filter_delete'], ['primary_id', 'remote_id']],
        'mailbox.backup' => ['mailbox', [], null],
        'mailbox.restore' => ['mailbox', ['mail_user_backup'], ['primary_id', 'backup_id']],
        'fetchmail.create' => ['address', ['mail_fetchmail_add'], ['params.destination', 'destination']],
    ];

    /**
     * Where the operator looks a record up on the ISPConfig master: kind => [function prefix, sys_datalog dbtable, id column].
     * Kept equal to IspConfigConnector::DATALOG_TABLES by a reflection test.
     *
     * @var array<string, array{0:string, 1:string, 2:string}>
     */
    public const DATALOG = [
        'shell_user' => ['sites_shell_user', 'shell_user', 'shell_user_id'],
        'database_user' => ['sites_database_user', 'web_database_user', 'database_user_id'],
        'mailbox' => ['mail_user', 'mail_user', 'mailuser_id'],
        'mail_alias' => ['mail_alias', 'mail_forwarding', 'forwarding_id'],
    ];

    /**
     * Writes whose `primary_id` IS the record of a kind: a write of these that no operation explains is judged by its
     * owner. (Filter, spam-filter and backup writes carry ids of other records and are only judged through an operation.)
     *
     * @var array<string, string> function => kind
     */
    public const UNATTRIBUTED_WRITES = [
        'sites_shell_user_update' => 'shell_user', 'sites_shell_user_delete' => 'shell_user',
        'sites_database_user_update' => 'database_user', 'sites_database_user_delete' => 'database_user',
        'mail_user_update' => 'mailbox', 'mail_user_delete' => 'mailbox', 'mail_alias_delete' => 'mail_alias',
    ];

    /** Logged calls whose request or answer says whose a record is (listings, reads, creations). */
    public const EVIDENCE_FUNCTIONS = [
        'sites_shell_user_get', 'sites_shell_user_add', 'sites_database_get', 'sites_database_add', 'sites_database_user_get', 'sites_database_user_add',
        'mail_user_get', 'mail_user_add', 'mail_user_update', 'mail_alias_get', 'mail_alias_add', 'sites_web_domain_add',
    ];

    /** @return array<string, array{kind:string, target:string, functions:list<string>, match:array{0:string,1:string}|null}> */
    public static function targets(): array
    {
        $targets = [];
        foreach (self::WEB as $action => [$kind, $target, $functions, $match]) {
            $targets[$action] = ['kind' => $kind, 'target' => $target, 'functions' => $functions, 'match' => $match];
        }
        foreach (ServiceActionWorkflow::OWN_MAIL_TARGETS as $action => [, $target]) {
            [$kind, $functions, $match] = self::MAIL_FUNCTIONS[$action]; // a guarded action missing here fails OwnershipAuditTargetsTest
            $targets[$action] = ['kind' => $kind, 'target' => $target, 'functions' => $functions, 'match' => $match];
        }

        return $targets;
    }

    /** `web_database_user database_user_id:99` — the sys_datalog row (dbtable, dbidx) that holds the record's history. */
    public static function datalogKey(string $kind, string $id): ?string
    {
        $table = self::DATALOG[$kind] ?? null;

        return $table === null ? null : $table[1].' '.$table[2].':'.$id;
    }
}
