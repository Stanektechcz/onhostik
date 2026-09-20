<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Observability\Tracer;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * The single entry point for every state change:
 *   AuthZ/policy -> idempotency -> transaction { handler; outbox } -> audit -> events.
 *
 * Handlers are resolved by convention: `<CommandFQCN>Handler` unless registered
 * explicitly with `register()`.
 */
final class CommandBus
{
    /** @var array<class-string<Command>, class-string<CommandHandler>> */
    private array $handlers = [];

    /** @var (\Closure(Command, CommandContext): array<string,mixed>)|null opens the request for a second person and returns what the refusal should carry (the identity domain registers it; the platform knows no approvals) */
    private ?\Closure $approvals = null;

    public function __construct(
        private readonly Container $container,
        private readonly CommandAuthorizer $authorizer,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly Tracer $tracer,
    ) {}

    /** @param class-string<Command> $command @param class-string<CommandHandler> $handler */
    public function register(string $command, string $handler): void
    {
        $this->handlers[$command] = $handler;
    }

    /** @param \Closure(Command, CommandContext): array<string,mixed> $open */
    public function onApprovalRequired(\Closure $open): void
    {
        $this->approvals = $open;
    }

    public function dispatch(Command $command, CommandContext $context): mixed
    {
        $decision = $this->authorizer->authorize($command, $context);
        if (! $decision->allowed) {
            $this->audit->record($context, $command->name(), 'denied', [
                'permission' => $command->permission(),
                'reason' => $decision->reason,
                'command' => $command->toAudit(),
            ]);
            $pending = [];
            if ($decision->requirement === 'approval' && $this->approvals !== null) {
                try {
                    $pending = ($this->approvals)($command, $context);
                } catch (Throwable $e) {
                    report($e); // the action stays refused either way; the request can be opened by asking again
                }
            }
            throw match ($decision->requirement) {
                'step_up' => new DomainError('step_up_required', $decision->reason ?? 'Step-up authentication required', 403, ['requirement' => 'step_up', 'help' => '/v1/auth/step-up']),
                'approval', 'human' => new DomainError('approval_required', $decision->reason ?? 'A second approver is required', 403, ['requirement' => $decision->requirement === 'human' ? 'human' : 'approval'] + $pending),
                default => DomainError::forbidden($decision->reason ?? 'Permission denied'),
            };
        }

        $replay = $this->idempotency->find($command->idempotencyKey(), $context);
        if ($replay !== null) {
            return $replay;
        }

        $handler = $this->resolveHandler($command);

        Context::add('command', $command->name()); // the error tracker and the trace tag the command (audit §5q-2)
        try {
            $result = $this->tracer->span('command '.$command->name(), ['onhost.command' => $command->name(), 'onhost.actor_type' => $context->actorType, 'onhost.actor_id' => $context->actorId, 'onhost.organization_id' => $context->organizationId, 'onhost.idempotency_key' => $command->idempotencyKey()], fn () => DB::transaction(function () use ($handler, $command, $context) {
                $result = $handler->handle($command, $context);
                $this->idempotency->remember($command->idempotencyKey(), $context, $result);

                return $result;
            }, 3));
        } catch (Throwable $e) {
            $this->audit->record($context, $command->name(), 'failed', [
                'permission' => $command->permission(),
                'error' => $e instanceof DomainError ? $e->error : get_class($e),
                'message' => $e->getMessage(),
                'command' => $command->toAudit(),
            ]);
            throw $e;
        }

        $this->audit->record($context, $command->name(), 'succeeded', [
            'permission' => $command->permission(),
            'command' => $command->toAudit(),
            'result' => $this->summarizeResult($result),
        ], stepUp: $decision->stepUpMethod, approvalIds: $decision->approvalIds);

        $this->outbox->relayPending();

        return $result;
    }

    private function resolveHandler(Command $command): CommandHandler
    {
        $class = $this->handlers[$command::class] ?? $command::class.'Handler';
        if (! class_exists($class)) {
            throw new \LogicException("No handler registered for command {$command->name()} ({$class})");
        }
        $handler = $this->container->make($class);
        if (! $handler instanceof CommandHandler) {
            throw new \LogicException("{$class} must implement CommandHandler");
        }

        return $handler;
    }

    private function summarizeResult(mixed $result): mixed
    {
        if (is_object($result) && method_exists($result, 'getKey')) {
            return ['id' => $result->getKey(), 'type' => class_basename($result)];
        }
        if (is_array($result)) {
            return array_slice($result, 0, 20, true);
        }
        if (is_scalar($result) || $result === null) {
            return $result;
        }

        return class_basename($result);
    }
}
