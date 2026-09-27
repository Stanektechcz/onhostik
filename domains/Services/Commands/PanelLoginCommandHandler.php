<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Runs PanelLoginCommand (TASK-0039, P0-14): the checks the bus cannot make (staff mode, the ticket, the reason, the family),
 * then the panel's one-time login link. The link itself is a credential: it is never returned from here — the bus keeps a
 * handler's answer for replays and puts a summary of it into the audit — but parked for a minute under a random handle that
 * the controller takes out once (PanelLoginCommandHandler::take).
 */
final class PanelLoginCommandHandler implements CommandHandler
{
    private const LINK_SECONDS = 60;

    public function __construct(private readonly ServiceFeatures $features, private readonly Authorizer $authorizer, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof PanelLoginCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        [$staff, $service, $ticket, $reason] = self::assertEligible($command, $context, $this->authorizer); // again: the ticket may have closed meanwhile
        $consented = PanelLoginCommand::consented($ticket, $service);

        [$tools, $ref] = $this->features->toolsFor($service);
        $url = $tools->panelLoginUrl($ref);
        if ($url === null) {
            throw new DomainError('panel_login_unavailable', 'This panel offers no staff login link; use the panel credentials from the instance settings.', 409);
        }
        $handle = Str::random(48);
        Cache::put(self::cacheKey($handle), $url, self::LINK_SECONDS);

        $scoped = $context->withScope($service->organization_id);
        $this->audit->record($scoped, 'staff.panel_login', 'succeeded', [
            'reason' => $reason, 'ticket_number' => $ticket->number, 'family' => $service->family, 'consented' => $consented,
            'approval_ids' => $context->verifiedApprovalIds,
        ], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.staff_panel_login', 'service', $service->id, [
            'service' => (string) ($service->label ?: ($service->hostname ?: $service->name)), 'ticket_number' => $ticket->number, 'ticket_id' => $ticket->id,
            'staff_name' => (string) $staff->name, 'reason' => $reason, 'consented' => $consented, 'at' => now()->toIso8601String(),
        ], $service->organization_id));

        return ['handle' => $handle, 'expires_in_seconds' => self::LINK_SECONDS, 'ticket_number' => $ticket->number, 'consented' => $consented];
    }

    /**
     * What must hold before anybody is asked to approve and again before the link is made: staff mode, the service, a reason, an
     * open customer ticket about the service, the console of its family. The controller asks it BEFORE the dispatch — the bus
     * opens the request for a second person before any handler runs, and nobody should be asked to approve a sign-on that
     * could never happen.
     *
     * @return array{0: User, 1: Service, 2: Ticket, 3: string}
     */
    public static function assertEligible(PanelLoginCommand $command, CommandContext $context, Authorizer $authorizer): array
    {
        $staff = StaffActor::user($context) ?? throw new DomainError('staff_mode_required', 'Přihlášení do panelu zákazníka patří jen podpoře ONhost ve správě.', 403);
        $service = $command->service() ?? throw DomainError::notFound('service');
        $reason = trim((string) $command->get('reason', ''));
        if (mb_strlen($reason) < PanelLoginCommand::REASON_MIN) {
            throw new DomainError('reason_required', 'Uveďte důvod přihlášení do panelu zákazníka (aspoň '.PanelLoginCommand::REASON_MIN.' znaků); zapíše se do auditu.', 422, ['field' => 'reason', 'min' => PanelLoginCommand::REASON_MIN]);
        }
        $ticket = PanelLoginCommand::ticketFor($service, (string) $command->get('ticket_id', ''))
            ?? throw new DomainError('support_ticket_required', 'Přihlášení do panelu zákazníka potřebuje otevřený tiket k této službě, který zákazník založil v portálu.', 422, ['field' => 'ticket_id']);
        if (! StaffActor::consoleCovers($staff, (string) $service->family, $authorizer)) {
            throw new DomainError('staff_family_mismatch', "Vaše role nemá konzoli služeb typu {$service->family}.", 403, ['family' => $service->family]);
        }

        return [$staff, $service, $ticket, $reason];
    }

    /** The one-time link the handler parked, once; null when it was taken already or ran out. */
    public static function take(string $handle): ?string
    {
        $url = $handle === '' ? null : Cache::pull(self::cacheKey($handle));

        return is_string($url) && $url !== '' ? $url : null;
    }

    private static function cacheKey(string $handle): string
    {
        return 'onhost:panel-login:'.hash('sha256', $handle);
    }
}
