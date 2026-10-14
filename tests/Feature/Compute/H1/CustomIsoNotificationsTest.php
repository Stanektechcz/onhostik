<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * H1 (phase H, TASK-0121): the four `service.iso.*` events of a customer's own installation image reach the organization's
 * feed. G5 published them and routed none — a member who did not click the button never learnt that a server now boots from an
 * image somebody uploaded, or that the image is gone. In-app only (the person who acted got the answer on the spot; nothing is
 * mailed), in Czech and, for an English organization, in English with no Czech phrase left (Lexicon). A detach that is only
 * the first step of a delete says nothing of its own: the deletion notice follows.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** @param array<string,mixed> $payload */
function h1IsoEvent(string $event, array $payload, string $organizationId): void
{
    app(OutboxPublisher::class)->publish(GenericEvent::of($event, 'service', 'svc_h1_iso', $payload, $organizationId));
    app(OutboxPublisher::class)->relayPending();
}

function h1IsoNotice(string $event, string $organizationId): ?Notification
{
    return Notification::query()->where('event', $event)->where('organization_id', $organizationId)->where('audience', 'customer')->first();
}

it('tells the organization that an image passed the scan and is in its library', function () {
    [, $org] = $this->customerWithOrganization();
    h1IsoEvent('service.iso.uploaded', ['iso_id' => 'iso_01h1', 'name' => 'debian-13-netinst.iso', 'size_bytes' => 662700032, 'label' => 'Compute 4'], $org->id);

    $n = h1IsoNotice('service.iso.uploaded', $org->id);
    expect($n)->not->toBeNull()
        ->and($n->title)->toBe('Vlastní ISO nahráno: debian-13-netinst.iso')
        ->and($n->body)->toContain('632 MB')->toContain('antivirovou kontrolou')
        ->and($n->surface)->toBe('/panel/sluzby')->and($n->severity)->toBe('info')
        ->and(Notification::query()->where('event', 'service.iso.uploaded')->where('audience', 'internal')->exists())->toBeFalse();
});

it('says whether the server boots from the attached image or keeps starting from its disk', function () {
    [, $org] = $this->customerWithOrganization();
    h1IsoEvent('service.iso.attached', ['iso_id' => 'iso_01h1', 'name' => 'freebsd-14.iso', 'boot_first' => true, 'label' => 'Compute 4'], $org->id);
    h1IsoEvent('service.iso.attached', ['iso_id' => 'iso_02h1', 'name' => 'drivers.iso', 'boot_first' => false, 'label' => 'Compute 8'], $org->id);

    $rows = Notification::query()->where('event', 'service.iso.attached')->where('organization_id', $org->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->title)->toBe('Vlastní ISO připojeno k serveru Compute 4')
        ->and($rows[0]->body)->toContain('nabootuje z obrazu freebsd-14.iso')->and($rows[0]->severity)->toBe('warn')
        ->and($rows[1]->title)->toBe('Vlastní ISO připojeno k serveru Compute 8')
        ->and($rows[1]->body)->toContain('drivers.iso')->toContain('dál startuje ze svého disku')->and($rows[1]->severity)->toBe('info');
});

it('says the drive and boot order are back after a detach, and stays silent when the detach is only the first step of a delete', function () {
    [, $org] = $this->customerWithOrganization();
    h1IsoEvent('service.iso.detached', ['iso_id' => 'iso_01h1', 'name' => 'freebsd-14.iso', 'reason' => 'the customer detached it', 'cause' => 'customer', 'label' => 'Compute 4'], $org->id);
    h1IsoEvent('service.iso.detached', ['iso_id' => 'iso_02h1', 'name' => 'old.iso', 'reason' => 'the image is being deleted', 'cause' => 'delete', 'label' => 'Compute 4'], $org->id);

    $rows = Notification::query()->where('event', 'service.iso.detached')->where('organization_id', $org->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->title)->toBe('Vlastní ISO odpojeno od serveru Compute 4')
        ->and($rows[0]->body)->toContain('freebsd-14.iso')->toContain('pořadí bootování');
});

it('tells the organization that an image and its copies are gone', function () {
    [, $org] = $this->customerWithOrganization();
    h1IsoEvent('service.iso.deleted', ['iso_id' => 'iso_01h1', 'name' => 'freebsd-14.iso'], $org->id);

    $n = h1IsoNotice('service.iso.deleted', $org->id);
    expect($n)->not->toBeNull()
        ->and($n->title)->toBe('Vlastní ISO smazáno: freebsd-14.iso')
        ->and($n->body)->toContain('kvótě organizace');
});

it('words every custom ISO notice in English for an English organization, with no Czech left', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'iso@shop.uk', 'locale' => 'en'], ['locale' => 'en', 'name' => 'ISO Ltd']);
    h1IsoEvent('service.iso.uploaded', ['iso_id' => 'iso_01h1', 'name' => 'a.iso', 'size_bytes' => 1048576, 'label' => 'vm-1'], $org->id);
    h1IsoEvent('service.iso.attached', ['iso_id' => 'iso_01h1', 'name' => 'a.iso', 'boot_first' => true, 'label' => 'vm-1'], $org->id);
    h1IsoEvent('service.iso.attached', ['iso_id' => 'iso_01h1', 'name' => 'a.iso', 'boot_first' => false, 'label' => 'vm-1'], $org->id);
    h1IsoEvent('service.iso.detached', ['iso_id' => 'iso_01h1', 'name' => 'a.iso', 'cause' => 'customer', 'label' => 'vm-1'], $org->id);
    h1IsoEvent('service.iso.deleted', ['iso_id' => 'iso_01h1', 'name' => 'a.iso'], $org->id);

    $rows = Notification::query()->where('organization_id', $org->id)->where('event', 'like', 'service.iso.%')->get();
    expect($rows)->toHaveCount(5);
    foreach ($rows as $n) {
        expect(array_merge(Lexicon::untranslated((string) $n->title), Lexicon::untranslated((string) $n->body)))->toBe([], "{$n->event}: {$n->title} | {$n->body}");
    }
    expect($rows->firstWhere('event', 'service.iso.deleted')->title)->toBe('Custom ISO deleted: a.iso');
});
