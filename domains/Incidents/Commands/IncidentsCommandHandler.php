<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Commands;

use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Incidents\MaintenanceService;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class IncidentsCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly IncidentService $incidents,
        private readonly MaintenanceService $maintenance,
        private readonly SlaService $sla,
    ) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof IncidentCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $p = $command->payload;

        return match ($command->op()) {
            'open' => Presenters::incident($this->incidents->open($p, $context), true),
            'update' => Presenters::incident($this->incidents->update($this->incident($command), (string) $command->get('note'), $context, $command->get('state'), (bool) $command->get('public', true)), true),
            'resolve' => Presenters::incident($this->incidents->resolve($this->incident($command), (string) $command->get('note', ''), $context), true),
            'postmortem' => Presenters::incident($this->incidents->postmortem($this->incident($command), $p, $context), true),
            'maintenance.schedule' => Presenters::maintenance($this->maintenance->schedule($p, $context)),
            'maintenance.approve' => Presenters::maintenance($this->maintenance->approve($this->maintenance($command), $context)),
            'maintenance.cancel' => Presenters::maintenance($this->maintenance->cancel($this->maintenance($command), (string) $command->get('reason', ''), $context)),
            'maintenance.complete' => Presenters::maintenance($this->maintenance->complete($this->maintenance($command), $context, $command->get('note'))),
            'probe.register' => (function () use ($p, $context) {
                $result = $this->sla->registerProbe($p, $context);

                return Presenters::probe($result['probe']) + ['token' => $result['token']];
            })(),
            'credit.candidates' => $this->sla->creditCandidates($this->incident($command), $context)->map(fn (SlaCredit $c) => Presenters::credit($c))->values()->all(),
            'credit.approve' => Presenters::credit($this->sla->approve($this->credit($command), $context)),
            'credit.reject' => Presenters::credit($this->sla->reject($this->credit($command), (string) $command->get('reason', ''), $context)),
            'credit.issue' => Presenters::credit($this->sla->issue($this->credit($command), $context)),
            default => throw new DomainError('incident_op_unknown', "Unknown incident operation {$command->op()}.", 422),
        };
    }

    private function incident(IncidentCommand $command): Incident
    {
        $id = (string) $command->get('incident_id');
        $incident = Incident::query()->find($id) ?? Incident::query()->where('number', $id)->first();
        if ($incident === null) {
            throw DomainError::notFound('incident');
        }

        return $incident;
    }

    private function maintenance(IncidentCommand $command): Maintenance
    {
        $m = Maintenance::query()->find((string) $command->get('maintenance_id'));
        if ($m === null) {
            throw DomainError::notFound('maintenance');
        }

        return $m;
    }

    private function credit(IncidentCommand $command): SlaCredit
    {
        $c = SlaCredit::query()->find((string) $command->get('credit_id'));
        if ($c === null) {
            throw DomainError::notFound('sla_credit');
        }

        return $c;
    }
}
