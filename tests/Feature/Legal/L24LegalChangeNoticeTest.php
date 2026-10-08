<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\LegalEntitySeeder;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * L-24 (docs/legal/LEGAL_REVIEW_2026-10.md): a change of the terms binds an existing customer only when they were told in time,
 * with the reasons and their right to leave (§ 1752 OZ, VOP čl. 15): at least 30 days ahead, the privacy policy 14. The publish
 * step already refuses an earlier effective day; now publishing also tells every customer who accepted a document that changes —
 * a mandatory mail and a panel notice, sent the moment the version is published, which is the notice period before it applies.
 */

beforeEach(function () {
    $this->seed([LegalEntitySeeder::class]);
    config(['onhost.legal_entity.phone' => '+420 000 000 000']);
    LegalEntity::query()->where('key', 'onhost-cz')->update(['ico' => '12345678']);
});

it('tells every customer who accepted a changed document, once, by a mandatory mail and in the panel', function () {
    [, $customer] = $this->customerWithOrganization([], ['billing_email' => 'fakturace@zakaznik.test']);
    [, $stranger] = $this->customerWithOrganization();
    Consent::query()->create(['organization_id' => $customer->id, 'kind' => 'terms', 'document_key' => 'terms', 'document_version' => '2026-09', 'accepted_at' => now()->subMonth()]);
    Consent::query()->create(['organization_id' => $customer->id, 'kind' => 'privacy', 'document_key' => 'privacy', 'document_version' => '2026-09', 'accepted_at' => now()->subMonth()]);
    $day = now('Europe/Prague')->addDays(31)->toDateString();

    // a dry run tells nobody
    $this->artisan('onhost:legal:publish', ['version' => '2026-10', '--effective-from' => $day, '--approved-by' => 'Mgr. Test, advokát'])->assertExitCode(0);
    expect(OutboxMessage::query()->where('name', 'legal.document.changed')->count())->toBe(0);

    $this->artisan('onhost:legal:publish', ['version' => '2026-10', '--effective-from' => $day, '--approved-by' => 'Mgr. Test, advokát', '--apply' => true, '--yes' => true])
        ->expectsOutputToContain('customers told')->assertExitCode(0);
    app(OutboxPublisher::class)->relayPending();

    $event = OutboxMessage::query()->where('name', 'legal.document.changed')->sole();
    $keys = collect((array) data_get($event->payload, 'documents'))->pluck('key')->all();
    expect($event->organization_id)->toBe($customer->id)->and(data_get($event->payload, 'version'))->toBe('2026-10')
        ->and(data_get($event->payload, 'effective_from'))->toBe($day)->and($keys)->toContain('terms')->toContain('privacy')->toContain('complaints')
        ->and(collect((array) data_get($event->payload, 'documents'))->firstWhere('key', 'terms')['url'])->toEndWith('/dokumenty/vop/2026-10');
    expect(NotificationService::TEMPLATE_KINDS['legal-change'])->toBe('legal.notice');
    $mail = MailOutbox::query()->where('template_key', 'legal-change')->sole();
    expect($mail->to)->toBe('fakturace@zakaznik.test')->and($mail->vars['ucinnost'])->toBe(CarbonImmutable::parse($day)->format('j. n. Y'))
        ->and($mail->vars['dokumenty'])->toContain('Všeobecné obchodní podmínky')
        ->and(Notification::query()->where('organization_id', $customer->id)->where('title', 'like', 'Změna smluvních dokumentů%')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $stranger->id)->count())->toBe(0);
});
