<?php

declare(strict_types=1);

use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Errors\DomainError;

/*
 * An invitation is a way into a customer's organization. Its accept token is stored hashed — and the link that carries it
 * sat in clear text in `mail_outbox.vars`, which `GET /v1/staff/outbox` returned to staff: whoever may manage mail
 * templates could accept an invitation as the owner of any customer. The mail still has to be rendered when it is sent,
 * so the link is kept exactly until then; staff never see it.
 */

beforeEach(fn () => $this->seed(NotificationTemplateSeeder::class));

it('shows staff the mail queue without the one-time link and keeps no link once the mail is out', function () {
    Mail::fake();
    config(['onhost.outbox.eager' => false]); // the queue is looked at before the mail leaves
    [$owner, $org] = $this->customerWithOrganization();
    app(OrganizationService::class)->invite($org, 'novy.clen@example.cz', 'viewer', $this->contextFor($owner, $org));
    $mail = MailOutbox::query()->where('template_key', 'invitation')->firstOrFail();
    preg_match('/pozvanka=([^&"]+)/', (string) json_encode($mail->vars, JSON_UNESCAPED_SLASHES), $m);
    $token = rawurldecode($m[1] ?? '');
    expect(strlen($token))->toBeGreaterThan(20);

    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $queue = (string) $this->getJson('/v1/staff/outbox?state=queued')->assertOk()->getContent();
    expect($queue)->toContain('novy.clen@example.cz')->not->toContain($token)->toContain('pozvanka=[redacted]');

    // delivered: what was said stays, the secret does not
    expect(app(NotificationService::class)->sendQueued()['sent'])->toBe(1);
    expect((string) DB::table('mail_outbox')->where('id', $mail->id)->value('vars'))->not->toContain($token)->toContain('Test s.r.o.');
    // and a message that carried one is not sent again with a dead link
    expect(fn () => app(NotificationService::class)->sendNow($mail->fresh()))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('mail_secret_not_kept'));
    expect($mail->fresh()->state)->toBe('sent'); // not put back into the queue
});
