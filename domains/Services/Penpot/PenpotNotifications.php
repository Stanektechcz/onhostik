<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * The Penpot events in the customer's feed (TASK-0123), in the organization's language: the phrases are Czech and
 * NotificationService swaps them through the Lexicon for an English organization. A listener of its own rather than arms in
 * NotificationRouter, so the router stays one file other work can change at the same time; staff hear an unreachable
 * instance as well.
 *
 * TASK-0131 (I1): a paid Penpot line that the delivery refused because no node can run it (`order.fulfilment_failed` with
 * `penpot_unavailable`) is told to the customer in plain words; the settlement's own `order.refunded` says the amount.
 */
final class PenpotNotifications
{
    public const EVENTS = ['penpot.instance.ready', 'penpot.instance.unreachable', 'penpot.instance.recovered', 'penpot.owner_password.changed'];

    /** The refusal of a paid line at delivery the customer hears about from this listener (the node is missing or full). */
    public const REFUSED = 'penpot_unavailable';

    private const SURFACE = '/panel/sluzby';

    public function __construct(private readonly NotificationService $notifications) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name === 'order.fulfilment_failed') {
            $this->refused($m);

            return;
        }
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

    /**
     * A Penpot line of this organization's order failed at delivery because no Penpot node can run it. The item is read back from
     * the database under the event's organization: a payload never makes the platform speak about another customer's order.
     */
    private function refused(OutboxMessage $m): void
    {
        $p = (array) $m->payload;
        if (($p['error'] ?? null) !== self::REFUSED || $m->organization_id === null) {
            return;
        }
        $item = OrderItem::query()->whereKey((string) ($p['item_id'] ?? ''))->where('product_key', PenpotInstances::PRODUCT)
            ->whereHas('order', fn ($q) => $q->where('organization_id', $m->organization_id))->first();
        if ($item === null) {
            return;
        }
        $money = (int) $item->total_minor > 0
            ? ' Částku za Penpot vracíme (na kredit, nebo odečtením z faktury); podrobnosti jsou ve zprávě o vrácení.'
            : ' Penpot je v ceně tarifu, nic se neúčtovalo.';
        $this->customer($m, 'Penpot jsme nemohli zřídit', 'Server pro Penpot teď není připravený, proto jsme Penpot nezřídili.'.$money
            .' Objednat ho můžete znovu v detailu služby, až bude server připravený. Objednávka č. '.(string) ($p['number'] ?? ''), 'warn');
    }

    private function customer(OutboxMessage $m, string $title, string $body, string $severity): void
    {
        $organization = $m->organization_id !== null ? Organization::query()->find($m->organization_id) : null;
        $this->notifications->notify('customer', 'service', $title, $body, self::SURFACE, $m->organization_id, null, $m->aggregate_type, $m->aggregate_id, $m->name, $severity, $organization->locale ?? 'cs');
    }
}
