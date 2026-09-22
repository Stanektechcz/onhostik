<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Commands;

use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\ComplianceTimer;
use Onhost\Domain\Compliance\Models\CyberIncident;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ComplianceCommandHandler implements CommandHandler
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ComplianceCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $p = $command->payload;

        return match ($command->op()) {
            'cyber.open' => Presenters::cyberIncident($this->compliance->openCyberIncident($p, $context)),
            'cyber.transition' => Presenters::cyberIncident($this->compliance->transitionCyberIncident($this->cyber($command), (string) $command->get('state'), $context, $command->get('summary'))),
            'cyber.evidence' => Presenters::cyberIncident($this->compliance->attachEvidence($this->cyber($command), $p, $context)),
            'timer.submit' => Presenters::timer($this->compliance->submitTimer($this->timer($command), (string) $command->get('authority_reference', ''), (array) $command->get('evidence', []), $context)),
            'timer.waive' => Presenters::timer($this->compliance->waiveTimer($this->timer($command), (string) $command->get('reason', ''), $context)),
            'abuse.triage' => Presenters::abuseCase($this->compliance->triageAbuse($this->abuse($command), (string) $command->get('decision', ''), (string) $command->get('reason', ''), $context), true),
            'abuse.notify' => Presenters::abuseCase($this->compliance->notifyCustomer($this->abuse($command), (string) $command->get('statement', ''), $context), true),
            'abuse.action' => Presenters::abuseCase($this->compliance->actionAbuse($this->abuse($command), (string) $command->get('action', ''), (string) $command->get('reason', ''), $context), true),
            'abuse.close' => Presenters::abuseCase($this->compliance->closeAbuse($this->abuse($command), $context, $command->get('note'), $command->get('restore') === null ? null : (bool) $command->get('restore')), true),
            'legal_hold' => (function () use ($command, $context) {
                $organization = Organization::query()->find((string) $command->get('organization_id'));
                if ($organization === null) {
                    throw DomainError::notFound('organization');
                }
                $organization = $this->compliance->setLegalHold($organization, (bool) $command->get('hold', true), (string) $command->get('reason', ''), $context);

                return ['organization_id' => $organization->id, 'legal_hold' => (bool) ($organization->settings['legal_hold'] ?? false)];
            })(),
            'data_request.process' => $this->compliance->processDataRequests(),
            default => throw new DomainError('compliance_op_unknown', "Unknown compliance operation {$command->op()}.", 422),
        };
    }

    private function cyber(ComplianceCommand $command): CyberIncident
    {
        $id = (string) $command->get('case_id');
        $case = CyberIncident::query()->find($id) ?? CyberIncident::query()->where('number', $id)->first();
        if ($case === null) {
            throw DomainError::notFound('cyber_incident');
        }

        return $case;
    }

    private function timer(ComplianceCommand $command): ComplianceTimer
    {
        $timer = ComplianceTimer::query()->find((string) $command->get('timer_id'));
        if ($timer === null) {
            throw DomainError::notFound('compliance_timer');
        }

        return $timer;
    }

    private function abuse(ComplianceCommand $command): AbuseCase
    {
        $id = (string) $command->get('case_id');
        $case = AbuseCase::query()->find($id) ?? AbuseCase::query()->where('number', $id)->first();
        if ($case === null) {
            throw DomainError::notFound('abuse_case');
        }

        return $case;
    }
}
