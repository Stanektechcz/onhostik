<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Lexicon coverage in CI (audit §5r-7): every event the router turns into a customer or user row is rendered for an
 * English organization with a neutral payload; a Czech word left in a title or body fails the build and names the
 * event, so a new phrase in the router cannot ship without its English line.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** The event names the router's match arms name (both single names and comma lists). @return list<string> */
function lexiconRouterEvents(): array
{
    $source = (string) file_get_contents(base_path('domains/Notifications/NotificationRouter.php'));
    preg_match_all("/^\s*((?:'[a-z0-9_]+(?:\.[a-z0-9_]+)+',\s*)*'[a-z0-9_]+(?:\.[a-z0-9_]+)+')\s*=>/m", $source, $m);
    $names = [];
    foreach ($m[1] as $arm) {
        preg_match_all("/'([^']+)'/", $arm, $n);
        array_push($names, ...$n[1]);
    }

    return array_values(array_unique($names));
}

it('leaves no Czech phrase in any customer or user notification of an English organization', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'lex@shop.uk', 'locale' => 'en'], ['locale' => 'en', 'name' => 'Lexicon Ltd']);
    $events = lexiconRouterEvents();
    expect(count($events))->toBeGreaterThan(50);
    $payload = [
        'number' => 'ON-1', 'reason' => null, 'url' => 'https://example.test', 'error' => 'timeout', 'minutes' => 4, 'ref' => 'main', 'sha' => 'abcdef1', 'domain' => 'example.test', 'domains' => ['example.test'],
        'expires_at' => '2026-10-01', 'label' => 'web-1', 'hostname' => 'web-1', 'period' => 'year', 'plan_name' => 'Pro', 'amount' => '100,00 Kč', 'percent' => 5, 'name' => 'Item', 'title' => 'Item', 'tier' => 'Silver', 'days' => 3,
        'product_key' => 'game', 'ip' => '203.0.113.9', 'count' => 2, 'total' => '100,00 Kč', 'currency' => 'CZK', 'user_id' => $owner->id,
    ];
    $outbox = app(OutboxPublisher::class);
    foreach ($events as $event) {
        try {
            $outbox->publish(GenericEvent::of($event, 'organization', $org->id, $payload, $org->id));
        } catch (Throwable) {
            // an event whose publisher insists on a shape is covered by its own feature test
        }
    }
    // Relay the whole outbox, not one batch: one relay takes 100 messages and the router names over 300 events, so a
    // single call rendered only the first batch — on PostgreSQL a different batch per run, which is how a Czech phrase
    // could hide for a week and then fail a merge it had nothing to do with (dev run 38634578429).
    for ($round = 0; $round < 20; $round++) {
        try {
            if ($outbox->relayPending(500) === 0) {
                break;
            }
        } catch (Throwable) {
        }
    }
    expect(OutboxMessage::query()->whereNull('published_at')->where('attempts', 0)->count())->toBe(0); // every event was rendered or tried
    $left = [];
    Notification::query()->where('locale', 'en')->get()->each(function (Notification $n) use (&$left) {
        $words = array_merge(Lexicon::untranslated((string) $n->title), Lexicon::untranslated((string) $n->body));
        if ($words !== []) {
            $left[$n->event] = $n->title.' | '.$n->body;
        }
    });
    expect($left)->toBe([]);
});

it('renders both API token decisions and every complaint outcome in English', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'lex2@shop.uk', 'locale' => 'en'], ['locale' => 'en', 'name' => 'Lexicon Two Ltd']);
    $outbox = app(OutboxPublisher::class);
    foreach (['approved', 'rejected'] as $decision) {
        $outbox->publish(GenericEvent::of('iam.token_approval.decided', 'approval', 'apr_'.$decision, ['decision' => $decision, 'action' => 'service.delete', 'token_name' => 'ci'], $org->id));
    }
    foreach (['accepted', 'partially_accepted', 'rejected'] as $i => $outcome) {
        $outbox->publish(GenericEvent::of('ticket.complaint.resolved', 'ticket', 'tkt_'.$outcome, ['number' => 'R-'.$i, 'outcome' => $outcome, 'resolved_at' => '2026-10-14T10:00:00Z', 'resolution' => 'Done.'], $org->id));
    }
    $outbox->relayPending();

    $rows = Notification::query()->where('organization_id', $org->id)->where('locale', 'en')->get();
    $text = $rows->map(fn (Notification $n) => $n->title.' | '.$n->body)->implode("\n");
    expect($rows)->toHaveCount(5)
        ->and(Lexicon::untranslated($text))->toBe([])
        ->and($text)->toContain('API token request approved: service.delete', 'API token request declined: service.delete',
            'Complaint accepted on 14. 10. 2026', 'Complaint partially accepted on 14. 10. 2026', 'Complaint rejected on 14. 10. 2026');
});
