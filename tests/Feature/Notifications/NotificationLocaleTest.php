<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * In-app notifications in the organization's language (audit §5q-7): an English organization reads English rows,
 * a Czech one is untouched, a user-addressed security notice follows the user's language; the lexicon covers the
 * router's customer-facing phrases and leaves live values alone.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

it('writes customer rows in the organization language and user rows in the user language', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'john@shop.uk', 'locale' => 'en'], ['locale' => 'en', 'name' => 'Shop UK Ltd']);
    [$petra, $czech] = $this->customerWithOrganization(['email' => 'petra@shop.cz'], ['name' => 'Shop CZ s.r.o.']);
    $outbox = app(OutboxPublisher::class);
    $outbox->publish(GenericEvent::of('loyalty.discount.granted', 'organization', $org->id, ['percent' => 5], $org->id));
    $outbox->publish(GenericEvent::of('loyalty.discount.granted', 'organization', $czech->id, ['percent' => 5], $czech->id));
    $outbox->publish(GenericEvent::of('security.password_changed', 'user', $owner->id, ['ip' => '203.0.113.9'], $org->id));
    $outbox->relayPending();

    $en = Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('event', 'loyalty.discount.granted')->firstOrFail();
    expect($en->title)->toBe('Permanent loyalty discount 5 %')->and($en->body)->toBe('It applies to every further order.')->and($en->locale)->toBe('en');
    $cs = Notification::query()->where('audience', 'customer')->where('organization_id', $czech->id)->where('event', 'loyalty.discount.granted')->firstOrFail();
    expect($cs->title)->toBe('Trvalá věrnostní sleva 5 %')->and($cs->body)->toBe('Platí na každou další objednávku.')->and($cs->locale)->toBe('cs');
    $security = Notification::query()->where('user_id', $owner->id)->where('event', 'security.password_changed')->firstOrFail();
    expect($security->title)->toBe('Password changed')->and($security->locale)->toBe('en');

    // the API row carries the language, mails already have their own `en` templates
    $this->actingAs($owner, 'sanctum');
    $rows = $this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/notifications')->assertOk()->json('data');
    expect(collect($rows)->firstWhere('event', 'loyalty.discount.granted'))->toMatchArray(['title' => 'Permanent loyalty discount 5 %', 'locale' => 'en']);

    // the lexicon: values stay, phrases go, Slovak stays Czech, an unknown locale is refused into Czech
    expect(Lexicon::translate('Objednávka přijata', 'en'))->toBe('Order received')
        ->and(Lexicon::translate('Kredit vystačí ještě 12 dní · na obnovy chybí 1 200,00 Kč', 'en'))->toBe('Credit lasts another 12 days · renewals short by 1 200,00 Kč')
        ->and(Lexicon::translate('Web opět běží · výpadek 4 min', 'en'))->toBe('Website is up again · outage 4 min')
        ->and(Lexicon::translate('Služba je aktivní', 'sk'))->toBe('Služba je aktivní')->and(Lexicon::translate(null, 'en'))->toBeNull();
    $samples = ['Objednávka zaplacena', 'Služba byla pozastavena pro neplacení', 'Kredit dobit automaticky o 500,00 Kč', 'Zakázka převzata: SEO audit', 'Nová úroveň věrnostního programu: Silver', 'Účet dočasně uzamčen', 'Marketplace: SEO care se nepodařilo prodloužit', 'Nedodáno v termínu: můžete si vzít kredit zpět', 'Stěhování serveru mc-01 je naplánované', 'Certifikát se nepodařilo vystavit'];
    foreach ($samples as $sample) {
        expect(Lexicon::untranslated((string) Lexicon::translate($sample, 'en')))->toBe([], "left Czech in: {$sample}");
    }
    expect(Lexicon::untranslated('Order received for Kč'))->toBe([])->and(Lexicon::untranslated('Objednávka received'))->toBe(['Objednávka']);
    $row = app(NotificationService::class)->notify('customer', 'order', 'Objednávka přijata', 'Služby se právě zřizují.', '/panel', $org->id, null, 'order', 'o_1', 'order.placed', 'info', 'xx');
    expect($row->locale)->toBe('cs')->and($row->title)->toBe('Objednávka přijata');
});
