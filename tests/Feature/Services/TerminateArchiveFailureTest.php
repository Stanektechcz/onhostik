<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * A cancellation that stops at its final archive (or at the switch-off) must leave the service in the state the panel is
 * really in. It used to settle the service as SUSPENDED whenever the run failed — with `terminate_at` empty and the site
 * still serving: billing saw a suspended service, the grace and reinstatement flows (which look for `terminate_at`) never
 * saw it, nothing retried it and nobody was told.
 *
 * The panel is the stateful ISPConfig double: its site row's `active` flag is what the node serves. The final archive fails
 * the way it fails on a node without the SSH agent user and without a nightly backup of the site (both file paths refused).
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
    $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'UTC'));
});

/** An ACTIVE web hosting bought over the real routes (sign-up, cart, order, card callback, saga). @return array{0:Service,1:string} service, panel site id */
function terminateFailureActiveWeb(object $test, array &$panel, string $email, string $fqdn): array
{
    $gate = [];
    LaravelNotification::fake();
    e2eIspSiteExtras($panel);
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    [, $org] = e2eSignUp($test, $email, 'Ukončení s.r.o.');
    $test->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $fqdn]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $test->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk();
    $placed = $test->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote->json('data.quote_id'), 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk();
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $service = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->firstOrFail();
    expect($service->state)->toBe(ServiceStateMachine::ACTIVE);

    return [$service, (string) $service->primaryBinding()?->remote_id];
}

/** The cancellation as the platform asks for it when a subscription ends (SubscriptionService), run to its end. */
function terminateFailureCancel(Service $service, string $key, array $params = []): Operation
{
    $operation = app(ServiceService::class)->requestAction($service, 'terminate', CommandContext::system('subscription expired')->withScope($service->organization_id), $key, $params + ['reason' => 'subscription ended', 'final_backup' => true]);
    driveOperations();
    app(OutboxPublisher::class)->relayPending();

    return $operation->refresh();
}

/** The doctor's row about cancellations that did not finish. @return array<string,mixed>|null */
function terminateFailureDoctorRow(): ?array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    return collect($report['checks'] ?? [])->firstWhere('check', 'no cancellation left unfinished');
}

it('leaves a hosting whose final archive cannot be taken ACTIVE, as the panel still serves it, and tells the operators', function () {
    $panel = [];
    [$service, $siteId] = terminateFailureActiveWeb($this, $panel, 'archiv@ukonceni.test', 'archiv-ukonceni.cz');
    expect($panel['sites'][(int) $siteId]['active'] ?? 'y')->toBe('y');
    expect(terminateFailureDoctorRow())->toMatchArray(['status' => 'OK']);

    $operation = terminateFailureCancel($service, 'sub_expire:archive');

    $fresh = $service->fresh();
    expect($operation->state)->toBe(Operation::FAILED)->and($operation->step_label)->toBe('Záloha před zrušením')
        // the truth: nothing on the panel was switched off, so the service is not called suspended
        ->and($fresh->state)->toBe(ServiceStateMachine::ACTIVE)->and($fresh->terminate_at)->toBeNull()->and($fresh->suspended_at)->toBeNull()
        ->and($panel['sites'][(int) $siteId]['active'] ?? 'y')->toBe('y');
    // nothing was cancelled behind the customer's back either: the subscription is as it was, no deletion was announced
    expect(Subscription::query()->where('service_id', $service->id)->where('state', Subscription::CANCELLED)->exists())->toBeFalse()
        ->and(OutboxMessage::query()->where('name', 'service.deletion.scheduled')->where('aggregate_id', $service->id)->exists())->toBeFalse()
        ->and(OutboxMessage::query()->where('name', 'service.suspended')->where('aggregate_id', $service->id)->exists())->toBeFalse();
    expect(Backup::query()->where('service_id', $service->id)->where('kind', 'final')->where('state', 'failed')->exists())->toBeTrue();
    // the operators hear it: the failed operation is an alert, and the doctor keeps the service on its list until it is dealt with
    expect(OutboxMessage::query()->where('name', 'operation.failed')->where('aggregate_id', $operation->id)->exists())->toBeTrue();
    $row = terminateFailureDoctorRow();
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('1 ')->and($row['detail'])->toContain($service->id)->and($row['remedy'])->not->toBe('');

    // the cancellation can be asked for again (dunning repeats it daily with a new key), and it is not lost in a state that refuses it
    $again = terminateFailureCancel($service->fresh(), 'sub_expire:archive:retry');
    expect($again->id)->not->toBe($operation->id)->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
});

it('keeps an already suspended hosting SUSPENDED — switched off on the panel — when its cancellation fails at the archive', function () {
    $panel = [];
    [$service, $siteId] = terminateFailureActiveWeb($this, $panel, 'dluh@ukonceni.test', 'dluh-ukonceni.cz');
    app(ServiceService::class)->requestAction($service, 'suspend', CommandContext::system('dunning')->withScope($service->organization_id), 'dunning:suspend', ['reason' => 'unpaid']);
    driveOperations();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($panel['sites'][(int) $siteId]['active'])->toBe('n');
    app(OutboxPublisher::class)->relayPending();
    $before = OutboxMessage::query()->where('aggregate_id', $service->id)->whereIn('name', ['service.failed', 'service.suspended'])->count();

    $operation = terminateFailureCancel($service->fresh(), 'dunning_terminate:archive');

    $fresh = $service->fresh();
    expect($operation->state)->toBe(Operation::FAILED)
        ->and($fresh->state)->toBe(ServiceStateMachine::SUSPENDED)->and($fresh->terminate_at)->toBeNull()
        ->and($panel['sites'][(int) $siteId]['active'])->toBe('n');
    // it used to be marked FAILED and the customer was told "provisioning failed"; nothing was suspended again either
    expect(OutboxMessage::query()->where('aggregate_id', $service->id)->whereIn('name', ['service.failed', 'service.suspended'])->count())->toBe($before);
    expect(terminateFailureDoctorRow()['status'])->toBe('WARN');
});

it('puts a hosting back to ACTIVE when the panel refused to switch it off, and to SUSPENDED when it did and a later step failed', function () {
    $panel = [];
    [$service, $siteId] = terminateFailureActiveWeb($this, $panel, 'panel@ukonceni.test', 'panel-ukonceni.cz');
    $skip = ['archive_before_delete' => false, 'archive_skip_reason' => 'operator override in a test'];

    // the panel refuses the switch-off: the site serves on, and the platform says so
    $panel['refuse'] = ['sites_web_domain_update'];
    $refused = terminateFailureCancel($service, 'staff:terminate:refused', $skip);
    expect($refused->state)->toBe(Operation::FAILED)->and($refused->step_label)->toBe('Deaktivace služby')
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($panel['sites'][(int) $siteId]['active'] ?? 'y')->toBe('y');

    // the switch-off goes through, then the delegated logins cannot even be listed: the site IS off, so SUSPENDED is the truth
    $panel['refuse'] = ['sites_shell_user_get'];
    $later = terminateFailureCancel($service->fresh(), 'staff:terminate:later', $skip);
    $fresh = $service->fresh();
    expect($later->state)->toBe(Operation::FAILED)->and($later->step_label)->toBe('Odvolání delegovaných přístupů')
        ->and($fresh->state)->toBe(ServiceStateMachine::SUSPENDED)->and($panel['sites'][(int) $siteId]['active'])->toBe('n')
        ->and($fresh->terminate_at)->toBeNull();
    $row = terminateFailureDoctorRow();
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain($service->id);
});
