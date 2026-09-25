<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Metering\AnnounceDiskTotalCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * The dated notice before the plan total counts (TASK-0023 web-disk-total): the total of files, databases and mail is
 * enforced only for a service that was told in time. Only an operator sends the notice: the command is a dry run unless
 * asked to send, refuses a date that is not set or not far enough ahead, and tells each paying service once per date.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A web hosting with a measured total of `$gb` GB of its 50 GB. */
function noticeWebHosting(Organization $org, int $gb, string $state = ServiceStateMachine::ACTIVE): Service
{
    $service = featureWebService($org, 'aapanel');
    // the lab helper gives every aaPanel site id 41; each hosting here is a site of its own
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => 'n'.$service->id]);
    $gib = 1024 ** 3;
    $service->forceFill(['state' => $state, 'label' => 'Eshop', 'tags' => ['usage' => ['disk_total' => ['files' => ($gb - 2) * $gib, 'databases' => 2 * $gib, 'mail' => 0, 'total' => $gb * $gib, 'limit' => 50 * $gib,
        'pct' => (int) round($gb / 50 * 100), 'quality' => 'measured', 'unavailable' => [], 'checked_at' => now()->toIso8601String()]]]])->save();

    return $service->refresh();
}

function noticeDate(int $days): void
{
    config(['onhost.metering.web_disk_total.enforce_from' => $days === 0 ? null : now()->addDays($days)->toDateString(), 'onhost.metering.web_disk_total.notice_min_days' => 30]);
}

it('lists who would be told and writes nothing on a dry run', function () {
    [, $org] = $this->customerWithOrganization();
    $service = noticeWebHosting($org, 60);
    noticeDate(45);
    $before = OutboxMessage::query()->count();

    expect(Artisan::call('onhost:usage:disk-total-notice'))->toBe(0);
    $out = Artisan::output();

    expect($out)->toContain($service->id)->toContain('120 %')->toContain('dry run')
        ->and(data_get($service->fresh()->tags, 'usage_notices'))->toBeNull()
        ->and(OutboxMessage::query()->count())->toBe($before);
});

it('refuses to send without a date, or with a date closer than the notice period', function () {
    [, $org] = $this->customerWithOrganization();
    $service = noticeWebHosting($org, 10);

    noticeDate(0);
    expect(Artisan::call('onhost:usage:disk-total-notice', ['--send' => true]))->toBe(1);
    noticeDate(10);
    expect(Artisan::call('onhost:usage:disk-total-notice', ['--send' => true]))->toBe(1)
        ->and(data_get($service->fresh()->tags, 'usage_notices'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.disk_total.announced')->count())->toBe(0);

    // the command cannot be tricked past the rule: the bus refuses a date too close as well
    expect(fn () => app(CommandBus::class)->dispatch(new AnnounceDiskTotalCommand($org->id, 'disk-total-notice:'.$service->id.':near', ['service_id' => $service->id, 'effective' => now()->addDays(10)->toDateString()]),
        CommandContext::system('operator:disk-total-notice')))->toThrow(DomainError::class);
});

it('tells every paying service once per date, records it, and mails the customer', function () {
    [$user, $org] = $this->customerWithOrganization();
    $over = noticeWebHosting($org, 60);
    $suspended = noticeWebHosting($org, 10, ServiceStateMachine::SUSPENDED);
    $gone = noticeWebHosting($org, 10, ServiceStateMachine::TERMINATED);
    $site = noticeWebHosting($org, 1);
    $site->forceFill(['tags' => array_merge((array) $site->tags, ['parent_service_id' => $over->id, 'billing' => 'included'])])->save();
    noticeDate(45);
    $effective = now()->addDays(45)->toDateString();

    expect(Artisan::call('onhost:usage:disk-total-notice', ['--send' => true]))->toBe(0);
    app(OutboxPublisher::class)->relayPending();

    $announced = OutboxMessage::query()->where('name', 'service.disk_total.announced')->get();
    expect($announced->pluck('aggregate_id')->sort()->values()->all())->toBe(collect([$over->id, $suspended->id])->sort()->values()->all())
        ->and(data_get($over->fresh()->tags, 'usage_notices.disk_total.effective'))->toBe($effective)
        ->and(data_get($over->fresh()->tags, 'usage_notices.disk_total.sent_at'))->not->toBeNull()
        ->and(data_get($gone->fresh()->tags, 'usage_notices'))->toBeNull()
        ->and(data_get($site->fresh()->tags, 'usage_notices'))->toBeNull();
    $payload = (array) $announced->firstWhere('aggregate_id', $over->id)->payload;
    expect($payload)->toMatchArray(['effective' => $effective, 'total' => 60 * 1024 ** 3, 'limit' => 50 * 1024 ** 3, 'pct' => 120, 'quality' => 'measured', 'over' => true]);

    $mail = MailOutbox::query()->where('template_key', 'service-disk-total-notice')->where('ref_id', $over->id)->firstOrFail();
    expect($mail->to)->toBe(strtolower((string) ($org->billing_email ?: $user->email)))->and($mail->state)->not->toBe('skipped')
        ->and((string) $mail->subject)->toContain('Eshop');

    // again for the same date: nobody is told twice
    expect(Artisan::call('onhost:usage:disk-total-notice', ['--send' => true]))->toBe(0);
    expect(OutboxMessage::query()->where('name', 'service.disk_total.announced')->count())->toBe(2);
});

it('tells only the services it is given, and refuses a customer who tries to send the notice', function () {
    [$user, $org] = $this->customerWithOrganization();
    $one = noticeWebHosting($org, 20);
    $other = noticeWebHosting($org, 20);
    noticeDate(45);

    expect(Artisan::call('onhost:usage:disk-total-notice', ['--send' => true, '--service' => [$one->id]]))->toBe(0)
        ->and(data_get($one->fresh()->tags, 'usage_notices.disk_total'))->not->toBeNull()
        ->and(data_get($other->fresh()->tags, 'usage_notices'))->toBeNull();

    expect(fn () => app(CommandBus::class)->dispatch(new AnnounceDiskTotalCommand($org->id, 'disk-total-notice:'.$other->id.':user', ['service_id' => $other->id, 'effective' => now()->addDays(45)->toDateString()]), $this->contextFor($user, $org)))
        ->toThrow(DomainError::class);
    expect(data_get($other->fresh()->tags, 'usage_notices'))->toBeNull();
});
