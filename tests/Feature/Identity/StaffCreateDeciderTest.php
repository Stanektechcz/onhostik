<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0041 — P0-16 red team (MEDIUM), owning task TASK-0037 (permission program IF-10, D8, §10 O4).
 *
 * The sole approver's own critical action waits a time lock (24 h). `onhost:staff:create --role=platform_owner` made a second
 * holder of `iam.approval.decide` at once, straight into the database: the solo operator created a second account and approved
 * their own requests with it — the lock was a formality. A further approver made from the command line is now itself a
 * time-locked request: the existing approvers hear of it at once and can cancel it; the account exists only when the command
 * is repeated after the lock. The first account of an installation, and staff who do not decide approvals, are made as before.
 */

function scdCreate(string $email, string $role): int
{
    return test()->artisan('onhost:staff:create', ['email' => $email, '--role' => $role])
        ->expectsQuestion('Password (at least 12 characters)', 'dlouhe-heslo-2026')->run();
}

it('makes the first approver of an installation at once, and staff who decide nothing at any time', function () {
    expect(ApprovalService::deciders())->toHaveCount(0);
    expect(scdCreate('prvni@onhost.test', 'platform_owner'))->toBe(0);
    expect(ApprovalService::deciders()->pluck('email')->all())->toBe(['prvni@onhost.test']);

    expect(scdCreate('podpora@onhost.test', 'support_l1'))->toBe(0);
    expect(User::query()->where('email', 'podpora@onhost.test')->where('is_staff', true)->exists())->toBeTrue()
        ->and(Approval::query()->count())->toBe(0);
});

it('time-locks a further approver made from the command line: nothing is made until the lock ran out, and the approvers hear of it', function () {
    $solo = $this->staff('platform_owner');
    config(['onhost.identity.four_eyes' => false]);

    expect(scdCreate('druhy@onhost.test', 'platform_owner'))->toBe(1);
    expect(User::query()->where('email', 'druhy@onhost.test')->exists())->toBeFalse()
        ->and(ApprovalService::deciders()->pluck('id')->all())->toBe([$solo->id]);
    $lock = Approval::query()->where('action', 'identity.staff.create')->sole();
    expect($lock->state)->toBe('pending')->and(ApprovalService::timeLockOf($lock))->not->toBeNull()
        ->and(json_encode($lock->payload))->not->toContain('dlouhe-heslo-2026')
        ->and(OutboxMessage::query()->where('name', 'iam.approval.time_locked')->where('aggregate_id', $lock->id)->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'identity.staff.create')->exists())->toBeTrue();

    // the solo operator cannot approve it away — the lock runs out, it is not the second person
    $decide = fn () => app(ApprovalService::class)->decide($lock->fresh(), $solo, 'approved', null, new CommandContext('user', $solo->id, null, null, '127.0.0.1', 'pest', 's', stepUpMethod: 'totp'));
    expect($decide)->toThrow(fn (DomainError $e) => expect($e->error)->toBe('time_lock_not_approvable'));
    // a repeat before the lock ran out makes nothing and opens no second request
    expect(scdCreate('druhy@onhost.test', 'platform_owner'))->toBe(1);
    expect(Approval::query()->where('action', 'identity.staff.create')->count())->toBe(1)->and(User::query()->where('email', 'druhy@onhost.test')->exists())->toBeFalse();

    // after the lock: the repeat makes the account, and the request is spent
    $this->travel(ApprovalService::timeLockHours() * 60 + 1)->minutes();
    expect(scdCreate('druhy@onhost.test', 'platform_owner'))->toBe(0);
    $second = User::query()->where('email', 'druhy@onhost.test')->sole();
    expect(PolicyBinding::query()->where('principal_id', $second->id)->value('role_key'))->toBe('platform_owner')
        ->and($lock->fresh()->state)->toBe('consumed');
});

it('lets an approver cancel a further approver asked for from the command line, and a cancelled one is never made', function () {
    $solo = $this->staff('billing_finance_admin'); // decides approvals too
    expect(scdCreate('tajny@onhost.test', 'iam_admin'))->toBe(1);
    $lock = Approval::query()->where('action', 'identity.staff.create')->sole();

    app(ApprovalService::class)->decide($lock, $solo, 'rejected', 'Nikoho dalšího nezakládáme.', new CommandContext('user', $solo->id, null, null, '127.0.0.1', 'pest', 's', stepUpMethod: 'totp'));
    expect($lock->fresh()->state)->toBe('cancelled');
    $this->travel(ApprovalService::timeLockHours() * 60 + 1)->minutes();
    expect(scdCreate('tajny@onhost.test', 'iam_admin'))->toBe(1);
    expect(User::query()->where('email', 'tajny@onhost.test')->exists())->toBeFalse();
});
