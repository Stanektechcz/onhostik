<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Errors\DomainError;

/**
 * Macros, queues and SLA policies of the support desk (blueprint §68.4–68.6), written by support.queue.manage through
 * SupportConfigCommand. The controller validates the shape; this keeps the desk whole: keys stay unique, a queue escalates
 * to a queue that exists, and nothing a ticket still points at is removed. Every change is audited with its old and new
 * fields.
 */
final class SupportConfigService
{
    /** The policies TicketService picks by the customer's SLA class; they can be changed, never removed. */
    public const BUILT_IN_POLICIES = ['standard', 'business', 'ha'];

    private const MODELS = ['macro' => SupportMacro::class, 'queue' => SupportQueue::class, 'sla_policy' => SlaPolicy::class];

    private const FIELDS = [
        'macro' => ['key', 'name', 'category', 'body', 'actions'],
        'queue' => ['key', 'name', 'skills', 'escalates_to', 'state'],
        'sla_policy' => ['key', 'name', 'targets', 'business_hours_only', 'business_hours'],
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function create(string $kind, array $data, CommandContext $context): Model
    {
        $class = $this->modelOf($kind);
        $fields = array_intersect_key($data, array_flip(self::FIELDS[$kind]));
        if (! isset($fields['key']) || $class::query()->where('key', $fields['key'])->exists()) {
            throw new DomainError('support_key_taken', 'Tento klíč už existuje.', 422, ['field' => 'key']);
        }
        $this->check($kind, $fields, null);
        $model = $class::query()->create($fields + $this->defaults($kind));
        $this->record($context, "support.{$kind}.create", $kind, $model, null);

        return $model;
    }

    /** @param array<string, mixed> $data */
    public function update(string $kind, string $id, array $data, CommandContext $context): Model
    {
        $model = $this->find($kind, $id);
        $fields = array_intersect_key($data, array_flip(self::FIELDS[$kind]));
        if (isset($fields['key']) && $fields['key'] !== $model->getAttribute('key')) {
            throw new DomainError('support_key_immutable', 'Klíč se po založení nemění (odkazují na něj tikety a fronty).', 422, ['field' => 'key']);
        }
        $this->check($kind, $fields, $model);
        $before = $this->fieldsOf($kind, $model);
        if ($kind === 'sla_policy') {
            $fields['version'] = (int) $model->getAttribute('version') + 1; // tickets keep the targets they were opened with (meta.targets)
        }
        $model->forceFill($fields)->save();
        $this->record($context, "support.{$kind}.update", $kind, $model, $before);

        return $model;
    }

    public function delete(string $kind, string $id, CommandContext $context): void
    {
        $model = $this->find($kind, $id);
        $this->assertRemovable($kind, $model);
        $before = $this->fieldsOf($kind, $model);
        $model->delete();
        $this->audit->record($context, "support.{$kind}.delete", 'succeeded', ['key' => $before['key'] ?? null, 'changes' => array_map(fn ($v) => ['from' => $v, 'to' => null], $before)], $kind, $model->getKey(), before: $before, after: null);
    }

    /** @return array<string, mixed> */
    public static function present(string $kind, Model $model): array
    {
        $out = ['id' => $model->getKey()];
        foreach (self::FIELDS[$kind] as $field) {
            $out[$field] = $model->getAttribute($field);
        }
        if ($kind === 'sla_policy') {
            $out['version'] = (int) $model->getAttribute('version');
        }
        if ($kind === 'queue') {
            $out['open'] = Ticket::query()->where('queue_id', $model->getKey())->whereNotIn('state', [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED])->count();
        }

        return $out + ['updated_at' => $model->getAttribute('updated_at')?->toIso8601String()];
    }

    /** @param array<string, mixed> $fields */
    private function check(string $kind, array $fields, ?Model $current): void
    {
        if ($kind === 'queue' && array_key_exists('escalates_to', $fields) && $fields['escalates_to'] !== null) {
            $key = (string) ($current?->getAttribute('key') ?? $fields['key'] ?? '');
            if ($fields['escalates_to'] === $key || ! SupportQueue::query()->where('key', $fields['escalates_to'])->exists()) {
                throw new DomainError('queue_escalation_invalid', 'Eskalace musí vést do jiné existující fronty.', 422, ['field' => 'escalates_to']);
            }
        }
        if ($kind === 'queue' && ($fields['state'] ?? null) === 'archived' && $current !== null) {
            $this->assertNoOpenTickets($current);
        }
        if ($kind === 'macro' && isset($fields['actions']['state']) && ! array_key_exists((string) $fields['actions']['state'], TicketStateMachine::machine()->toArray())) {
            throw new DomainError('macro_action_invalid', 'Makro může ticket převést jen do existujícího stavu.', 422, ['field' => 'actions.state']);
        }
        if ($kind === 'sla_policy') {
            $only = (bool) ($fields['business_hours_only'] ?? $current?->getAttribute('business_hours_only') ?? false);
            $hours = array_key_exists('business_hours', $fields) ? $fields['business_hours'] : $current?->getAttribute('business_hours');
            if ($only && SlaClock::window((array) ($hours ?? [])) === null) {
                throw new DomainError('business_hours_invalid', 'Pracovní doba musí mít dny 1–7, začátek před koncem (HH:MM) a platné časové pásmo.', 422, ['field' => 'business_hours']);
            }
        }
    }

    private function assertRemovable(string $kind, Model $model): void
    {
        $key = (string) $model->getAttribute('key');
        $inUse = match ($kind) {
            'queue' => Ticket::query()->where('queue_id', $model->getKey())->exists() || SupportQueue::query()->where('escalates_to', $key)->exists(),
            'sla_policy' => in_array($key, self::BUILT_IN_POLICIES, true) || Ticket::query()->where('sla_policy_id', $model->getKey())->exists(),
            default => false,
        };
        if ($inUse) {
            throw new DomainError('support_config_in_use', $kind === 'queue' ? 'Na frontu odkazují tikety nebo jiná fronta; archivujte ji.' : 'Politiku používají tikety nebo ji vybírá SLA třída zákazníka.', 409, ['kind' => $kind, 'key' => $key]);
        }
    }

    private function assertNoOpenTickets(Model $queue): void
    {
        if (Ticket::query()->where('queue_id', $queue->getKey())->whereNotIn('state', [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED])->exists()) {
            throw new DomainError('queue_has_open_tickets', 'Fronta má otevřené tikety; nejdřív je přesuňte.', 409, ['field' => 'state']);
        }
    }

    /** @return array<string, mixed> */
    private function defaults(string $kind): array
    {
        return match ($kind) {
            'queue' => ['state' => 'active', 'skills' => []],
            'macro' => ['actions' => []],
            'sla_policy' => ['business_hours_only' => false, 'version' => 1],
            default => [],
        };
    }

    /** @return class-string<Model> */
    private function modelOf(string $kind): string
    {
        return self::MODELS[$kind] ?? throw new DomainError('support_kind_unknown', 'Unknown support setting.', 422, ['field' => 'kind']);
    }

    private function find(string $kind, string $id): Model
    {
        $class = $this->modelOf($kind);

        return $class::query()->whereKey($id)->first() ?? $class::query()->where('key', $id)->first() ?? throw DomainError::notFound(str_replace('_', ' ', $kind));
    }

    /** @return array<string, mixed> */
    private function fieldsOf(string $kind, Model $model): array
    {
        $out = [];
        foreach (self::FIELDS[$kind] as $field) {
            $out[$field] = $model->getAttribute($field);
        }

        return $out;
    }

    /** @param array<string, mixed>|null $before */
    private function record(CommandContext $context, string $action, string $kind, Model $model, ?array $before): void
    {
        $after = $this->fieldsOf($kind, $model);
        $changes = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
            }
        }
        $this->audit->record($context, $action, 'succeeded', ['key' => $after['key'] ?? null, 'changes' => $changes], $kind, $model->getKey(), before: $before, after: $after);
    }
}
