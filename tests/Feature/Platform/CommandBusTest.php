<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Audit\HashChain;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

class RenameProjectTestCommand implements Command
{
    public static int $executions = 0;

    public function __construct(public readonly string $organizationId, public readonly string $name, public readonly string $key) {}

    public function permission(): ?string
    {
        return 'project.manage';
    }

    public function scope(): ?CommandScope
    {
        return CommandScope::organization($this->organizationId);
    }

    public function idempotencyKey(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return 'test.project.rename';
    }

    public function toAudit(): array
    {
        return ['name' => $this->name];
    }
}

class RenameProjectTestCommandHandler implements CommandHandler
{
    public function handle(Command $command, CommandContext $context): mixed
    {
        RenameProjectTestCommand::$executions++;

        return ['renamed' => $command->name];
    }
}

final class DangerousTestCommand extends RenameProjectTestCommand implements RiskAwareCommand
{
    public function permission(): ?string
    {
        return 'backup.delete';
    }

    public function name(): string
    {
        return 'test.backup.delete';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::CRITICAL;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return true;
    }
}

final class DangerousTestCommandHandler extends RenameProjectTestCommandHandler {}

beforeEach(function () {
    RenameProjectTestCommand::$executions = 0;
});

it('executes an authorized command exactly once per idempotency key and audits it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);
    $ctx = $this->contextFor($owner, $org);

    $first = $bus->dispatch(new RenameProjectTestCommand($org->id, 'Shop', 'idem-1'), $ctx);
    $second = $bus->dispatch(new RenameProjectTestCommand($org->id, 'Shop', 'idem-1'), $ctx);

    expect($first)->toBe(['renamed' => 'Shop'])
        ->and($second)->toBe(['renamed' => 'Shop'])
        ->and(RenameProjectTestCommand::$executions)->toBe(1);

    $audit = AuditEvent::query()->where('action', 'test.project.rename')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->result)->toBe('succeeded')
        ->and($audit->actor_id)->toBe($owner->id)
        ->and($audit->organization_id)->toBe($org->id);
});

it('denies and audits commands outside the caller scope', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$other, $otherOrg] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);

    expect(fn () => $bus->dispatch(new RenameProjectTestCommand($otherOrg->id, 'X', 'idem-2'), $this->contextFor($owner, $otherOrg)))
        ->toThrow(DomainError::class);
    expect(RenameProjectTestCommand::$executions)->toBe(0)
        ->and(AuditEvent::query()->where('result', 'denied')->count())->toBe(1);
});

it('requires step-up and a second approver for critical commands', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);
    $ctx = $this->contextFor($owner, $org);
    $command = new DangerousTestCommand($org->id, 'gen-1', 'idem-3');

    try {
        $bus->dispatch($command, $ctx);
        $this->fail('expected step-up denial');
    } catch (DomainError $e) {
        expect($e->status)->toBe(403);
    }

    app(StepUpService::class)->grant($owner, 'totp', 'test-session', '127.0.0.1');
    try {
        $bus->dispatch($command, $ctx);
        $this->fail('expected approval denial');
    } catch (DomainError $e) {
        expect($e->getMessage())->toContain('approval');
    }

    $approval = Approval::query()->create([
        'action' => 'test.backup.delete', 'payload_hash' => HashChain::hashPayload($command->toAudit()),
        'requested_by' => $owner->id, 'state' => 'approved', 'decided_by' => 'usr_second_person', 'decided_at' => now(),
        'expires_at' => now()->addHour(), 'organization_id' => $org->id,
    ]);
    $ctxWithApproval = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session', approvalIds: [$approval->id]);
    $result = $bus->dispatch($command, $ctxWithApproval);

    expect($result)->toBe(['renamed' => 'gen-1'])
        ->and($approval->refresh()->state)->toBe('consumed')
        ->and(AuditEvent::query()->where('action', 'test.backup.delete')->where('result', 'succeeded')->value('step_up_method'))->toBe('totp');
});

it('refuses an approval the requester gave themselves, one given for another payload, and one already used (H336)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);
    app(StepUpService::class)->grant($owner, 'totp', 'test-session', '127.0.0.1');
    $command = new DangerousTestCommand($org->id, 'gen-1', 'idem-h336-1');
    $approval = fn (string $decidedBy, array $audit) => Approval::query()->create([
        'action' => 'test.backup.delete', 'payload_hash' => HashChain::hashPayload($audit), 'requested_by' => $owner->id, 'state' => 'approved',
        'decided_by' => $decidedBy, 'decided_at' => now(), 'expires_at' => now()->addHour(), 'organization_id' => $org->id,
    ]);
    $with = fn (Approval $a) => new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session', approvalIds: [$a->id]);
    $refused = function (Command $command, CommandContext $context) use ($bus): void {
        expect(fn () => $bus->dispatch($command, $context))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('approval_required'));
    };

    // the author cannot be their own second pair of eyes
    $own = $approval($owner->id, $command->toAudit());
    $refused($command, $with($own));
    expect($own->refresh()->state)->toBe('approved'); // and the attempt did not burn it

    // an approval is for exactly what was shown to the approver
    $other = $approval('usr_second_person', (new DangerousTestCommand($org->id, 'gen-OTHER', 'idem-h336-x'))->toAudit());
    $refused($command, $with($other));

    // a real one works once
    $real = $approval('usr_second_person', $command->toAudit());
    expect($bus->dispatch($command, $with($real)))->toBe(['renamed' => 'gen-1'])->and($real->refresh()->state)->toBe('consumed');
    $refused(new DangerousTestCommand($org->id, 'gen-1', 'idem-h336-2'), $with($real));
    expect(AuditEvent::query()->where('action', 'test.backup.delete')->where('result', 'succeeded')->count())->toBe(1);
});

it('keeps the audit hash chain verifiable', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);
    foreach (['a', 'b', 'c'] as $k) {
        $bus->dispatch(new RenameProjectTestCommand($org->id, $k, "chain-{$k}"), $this->contextFor($owner, $org));
    }
    $events = AuditEvent::query()->orderBy('created_at')->orderBy('id')->get();
    $prev = HashChain::GENESIS;
    foreach ($events as $event) {
        expect($event->prev_hash)->toBe($prev)
            ->and(HashChain::verifyEvent($event, $prev))->toBeTrue();
        $prev = $event->hash;
    }
    expect($events->count())->toBeGreaterThanOrEqual(4);
});
