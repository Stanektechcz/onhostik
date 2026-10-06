<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * The Penpot events in the customer's feed (TASK-0123), in the organization's language: the phrases are Czech and
 * NotificationService swaps them through the Lexicon for an English organization. A listener of its own rather than arms in
 * NotificationRouter, so the router stays one file other work can change at the same time; staff hear an unreachable
 * instance as well.
 */
final class PenpotNotifications
{
    public const EVENTS = ['penpot.instance.ready', 'penpot.instance.unreachable', 'penpot.instance.recovered', 'penpot.owner_password.changed'];

    private const SURFACE = '/panel/sluzby';

    public function __construct(private readonly NotificationService $notifications) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if (! in_array($m->name, self::EVENTS, true)) {
            return;
        }
        $p = (array) $m->payload;
        $label = (string) ($p['label'] ?? '');
        match ($m->name) {
            'penpot.instance.ready' => $this->customer($m, 'Penpot je připraven', 'Adresa: '.(string) ($p['url'] ?? '').'. Přihlašovací e-mail: '.(string) ($p['owner_email'] ?? '').'. Heslo k účtu si nastavíte v panelu u služby.', 'info'),
            'penpot.instance.unreachable' => (function () use ($m, $label, $p): void {
                $this->customer($m, 'Penpot neodpovídá', 'Instance '.$label.' teď neodpovídá. Technici o tom vědí a řeší to.', 'warn');
                $this->notifications->notify('internal', 'service', 'Penpot '.$label.' neodpovídá', 'Kontejnery: '.(string) ($p['status'] ?? '?').', HTTP '.(string) ($p['http'] ?? '0').' (docs/runbooks/penpot.md)', '/sprava/sluzby', $m->organization_id, null, $m->aggregate_type, $m->aggregate_id, $m->name, 'hot');
            })(),
            'penpot.instance.recovered' => $this->customer($m, 'Penpot opět běží', 'Instance '.$label.' znovu odpovídá.', 'info'),
            default => $this->customer( // penpot.owner_password.changed (EVENTS is the filter above)
                $m, 'Heslo k Penpotu bylo změněno', 'Heslo účtu '.(string) ($p['owner_email'] ?? '').' v instanci '.$label.' bylo změněno. Pokud jste to nebyli vy, změňte ho znovu a napište podpoře.', 'warn'),
        };
    }

    private function customer(OutboxMessage $m, string $title, string $body, string $severity): void
    {
        $organization = $m->organization_id !== null ? Organization::query()->find($m->organization_id) : null;
        $this->notifications->notify('customer', 'service', $title, $body, self::SURFACE, $m->organization_id, null, $m->aggregate_type, $m->aggregate_id, $m->name, $severity, $organization->locale ?? 'cs');
    }
}
