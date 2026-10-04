<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0066 (follow-up of TASK-0058): a paid transfer line waits for the transfer code (DomainService::awaitTransferCode), and
 * nothing told the customer so — the money stayed reserved, and after `onhost.domains.transfer_code_wait_days` the line failed
 * and the order was refunded without the customer ever having been asked for the code. Now the waiting line publishes
 * `domain.transfer.code_needed`; the router tells the customer in the panel and by mail what to hand over and by when. The
 * notice names the order and the line, never a code (there is none yet, and the cart drops any it was given).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class, NotificationTemplateSeeder::class]);
    Http::preventStrayRequests();
});

/** A paid (credit) order with one transfer line for `$fqdn`, fulfilled as far as it goes (the line now waits for its code). */
function tcnPaidTransfer(Organization $org, User $by, string $fqdn, array $extra = []): Order
{
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'card', 'tcn-seed-'.$org->id, new CommandContext('user', $by->id, $org->id), bankProvider: 'comgate');
    $registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => $fqdn, 'period_years' => 1, 'action' => 'transfer', 'registrant' => $registrant] + $extra]], 'CZK', ['country' => 'CZ'], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jana Nováková'], 'registry_terms_cz' => ['person' => 'Jana Nováková'], 'sla' => []];
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $by, $consents, ['mode' => 'wallet'], 'tcn:'.$fqdn, new CommandContext('user', $by->id, $org->id))['order'];
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    return $order->refresh();
}

it('publishes domain.transfer.code_needed once when a paid transfer line starts waiting for its code', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000']);

    $order = tcnPaidTransfer($org, $owner, 'prevod-kod.cz');
    $line = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

    expect($line->state)->toBe('provisioning')->and($line->config['awaiting_transfer_code'] ?? null)->not->toBeNull();
    $events = OutboxMessage::query()->where('name', 'domain.transfer.code_needed')->get();
    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->organization_id)->toBe($org->id)
        ->and($event->aggregate_type)->toBe('order')->and($event->aggregate_id)->toBe($order->id)
        ->and((array) $event->payload)->toMatchArray(['fqdn' => 'prevod-kod.cz', 'order' => $order->number, 'order_item_id' => $line->id, 'wait_days' => 30]);

    // a second pass of fulfilment (recheck, a retried job) leaves the waiting line alone and says nothing again
    driveOperations();
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'domain.transfer.code_needed')->count())->toBe(1);
});

it('tells the customer in the panel and by mail what to hand over and by when, without any code in it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000']);

    $order = tcnPaidTransfer($org, $owner, 'prevod-posta.cz', ['auth_info' => 'Kod-V-Kosiku-991']);

    $notice = Notification::query()->where('event', 'domain.transfer.code_needed')->where('audience', 'customer')->where('organization_id', $org->id)->get();
    expect($notice)->toHaveCount(1)
        ->and($notice->first()->surface)->toBe('/panel/domeny')->and($notice->first()->severity)->toBe('warn')
        ->and($notice->first()->title)->toContain('prevod-posta.cz');
    $mail = MailOutbox::query()->where('template_key', 'domain-transfer-code-needed')->where('organization_id', $org->id)->get();
    expect($mail)->toHaveCount(1)
        ->and($mail->first()->subject)->toContain('prevod-posta.cz')
        ->and((array) $mail->first()->vars)->toMatchArray(['domena' => 'prevod-posta.cz', 'objednavka' => $order->number, 'dni' => '30']);

    $everything = json_encode([OutboxMessage::query()->where('name', 'domain.transfer.code_needed')->value('payload'), $notice->first()->toArray(), $mail->first()->toArray()]);
    expect($everything)->not->toContain('Kod-V-Kosiku-991');
});
