<?php

declare(strict_types=1);

use Onhost\Domain\Provisioning\Audit\OwnershipAuditTargets;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Providers\IspConfig\IspConfigConnector;

/*
 * The audit of past provider calls (TASK-0020) must look at exactly what the TASK-0005 guard now protects. Both lists
 * are derived here from the guard and the connector themselves, so neither side can grow without the other noticing.
 */

it('names the same sys_datalog rows the ISPConfig connector follows', function () {
    $tables = (new ReflectionClassConstant(IspConfigConnector::class, 'DATALOG_TABLES'))->getValue();

    foreach (OwnershipAuditTargets::DATALOG as $kind => [$prefix, $table, $column]) {
        expect($tables[$prefix] ?? null)->toBe([$table, $column], "kind {$kind}");
    }
    expect(OwnershipAuditTargets::datalogKey('database_user', '99'))->toBe('web_database_user database_user_id:99');
});

it('audits every mail action the ownership guard covers, with the guard\'s own parameter', function () {
    $targets = OwnershipAuditTargets::targets();

    expect(array_keys(OwnershipAuditTargets::MAIL_FUNCTIONS))->toEqualCanonicalizing(array_keys(ServiceActionWorkflow::OWN_MAIL_TARGETS));
    foreach (ServiceActionWorkflow::OWN_MAIL_TARGETS as $action => [, $param]) {
        expect($targets)->toHaveKey($action)
            ->and($targets[$action]['target'])->toBe($param);
    }
});

it('audits the three web actions that took a remote id on trust', function () {
    $targets = OwnershipAuditTargets::targets();

    expect($targets['shell.key']['kind'])->toBe('shell_user')
        ->and($targets['shell.key']['functions'])->toBe(['sites_shell_user_update'])
        ->and($targets['dbuser.password']['kind'])->toBe('database_user')
        ->and($targets['dbuser.delete']['functions'])->toBe(['sites_database_user_delete']);
    foreach ($targets as $action => $target) {
        expect(in_array($target['kind'], ['shell_user', 'database_user', 'mailbox', 'mail_alias', 'address'], true))->toBeTrue($action);
    }
});
