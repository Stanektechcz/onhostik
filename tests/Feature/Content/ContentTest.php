<?php

declare(strict_types=1);

use Database\Seeders\ContentSeeder;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Outbox\OutboxPublisher;

beforeEach(fn () => $this->seed(ContentSeeder::class));

it('serves the prototype content in the shapes onhost-data.js and onhost-content.js expect', function () {
    $posts = $this->getJson('/v1/posts')->assertOk();
    expect($posts->json('data'))->toHaveCount(12)->and($posts->json('data.0'))->toHaveKeys(['slug', 'cat', 'title', 'excerpt', 'date', 'read', 'author', 'featured']);
    $cs = $this->getJson('/v1/posts/pue-118')->assertOk();
    $en = $this->getJson('/v1/posts/pue-118?locale=en')->assertOk();
    expect($cs->json('data.title'))->toBe('Jak jsme dostali PUE v Praze na 1,18')->and($en->json('data.title'))->toBe('How we got Prague PUE down to 1.18')->and(count($cs->json('data.body')))->toBeGreaterThanOrEqual(4);
    $this->getJson('/v1/posts/neexistuje')->assertNotFound();

    $kb = $this->getJson('/v1/kb?q=ssh')->assertOk();
    expect($kb->json('data.0.slug'))->toBe('ssh-klic')->and($this->getJson('/v1/kb')->json('data'))->toHaveCount(12);
    expect($this->getJson('/v1/kb/prenos-domeny')->assertOk()->json('data.body.1.0'))->toBe('Postup');

    $fixes = $this->getJson('/v1/changelog?tag=fix')->assertOk();
    expect($fixes->json('data'))->toHaveCount(2)->and($fixes->json('data.0.2'))->toBe('OPRAVA')->and($fixes->json('data.0.0'))->toBe('22. 8.');
    expect($this->getJson('/v1/changelog?tag=fix&locale=en')->json('data.0.2'))->toBe('FIX');
    expect($this->getJson('/v1/locations')->assertOk()->json('data.0'))->toBe(['PRG', 'Praha', 'Česko', '4 ms', 1]);
    expect($this->getJson('/v1/locations?locale=en')->json('data.2.2'))->toBe('Germany');
    $stock = $this->getJson('/v1/stock')->assertOk();
    expect($stock->json('data'))->toHaveCount(5)->and($stock->json('data.0.0'))->toBe('EPYC 9354')->and($stock->json('data.0.4'))->toBe('24 kusů skladem')->and($stock->json('data.0.5'))->toBe('ok')->and($stock->json('data.3.4'))->toBe('dodání 5 dnů');
    expect($this->getJson('/v1/reseller/tiers')->assertOk()->json('data.0.m'))->toBe('20 %');
});

it('records leads and tender requests, notifies sales, mails the tender pack, and lets staff work the inbox and content', function () {
    $this->postJson('/v1/leads', ['kind' => 'migration', 'name' => 'Jan Novák', 'email' => 'jan@firma.cz', 'company' => 'Firma s.r.o.', 'message' => 'Chceme přenést 12 webů.'])->assertUnprocessable()->assertJsonValidationErrors(['consent']);
    $this->postJson('/v1/leads', ['kind' => 'tender', 'name' => 'x', 'email' => 'jan@firma.cz', 'consent' => true])->assertUnprocessable();
    $lead = $this->postJson('/v1/leads', ['kind' => 'migration', 'name' => 'Jan Novák', 'email' => 'jan@firma.cz', 'company' => 'Firma s.r.o.', 'message' => 'Chceme přenést 12 webů.', 'meta' => ['current_provider' => 'jiný hosting'], 'consent' => true])->assertCreated();
    expect(Lead::query()->find($lead->json('data.id'))->meta['current_provider'])->toBe('jiný hosting');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('kind', 'sales')->exists())->toBeTrue();

    $tender = $this->postJson('/v1/tender/request', ['name' => 'Marie Dvořáková', 'email' => 'zakazky@mesto.cz', 'company' => 'Město Kolín', 'authority' => 'Město Kolín', 'deadline' => now()->addDays(30)->toDateString(), 'consent' => true])->assertCreated();
    expect($tender->json('data.documents'))->toHaveCount(6);
    $mail = MailOutbox::query()->where('template_key', 'tender-docs')->where('to', 'zakazky@mesto.cz')->firstOrFail();
    expect($mail->vars['dokumenty'])->toContain('Technická specifikace');

    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $inbox = $this->getJson('/v1/staff/leads?kind=tender')->assertOk()->assertHeader('X-Total-Count', '1');
    $this->postJson("/v1/staff/leads/{$inbox->json('data.0.id')}/transition", ['state' => 'contacted', 'note' => 'Voláno, pošlou zadávací dokumentaci.'])->assertOk()->assertJsonPath('data.state', 'contacted');

    $this->postJson('/v1/staff/content/changelog', [])->assertStatus(405);
    $this->putJson('/v1/staff/content/changelog', ['entry_date' => now()->toDateString(), 'tag' => 'api', 'title' => ['cs' => 'Veřejné API pro obsah'], 'body' => ['cs' => 'Blog, KB, changelog a lokality jsou dostupné přes /v1.']])->assertForbidden();
    $this->actingAs($this->staff('marketing_content'), 'sanctum');
    $this->putJson('/v1/staff/content/changelog', ['entry_date' => now()->toDateString(), 'tag' => 'api', 'title' => ['cs' => 'Veřejné API pro obsah'], 'body' => ['cs' => 'Blog, KB, changelog a lokality jsou dostupné přes /v1.']])->assertOk();
    expect($this->getJson('/v1/changelog')->json('data.0.3'))->toBe('Veřejné API pro obsah');
    $this->putJson('/v1/staff/content/stock', ['sku' => 'epyc-9354', 'name' => 'EPYC 9354', 'spec' => ['cs' => '32 jader / 256 GB / 4× 3,84 TB NVMe'], 'price_minor' => 890000, 'available' => 3, 'availability' => 'warn'])->assertOk();
    expect($this->getJson('/v1/stock')->json('data.0.4'))->toBe('3 kusy skladem');
});
