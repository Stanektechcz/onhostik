<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;

/*
 * Mailbox password rotation without a plaintext password anywhere (audit §5i-5): the account owner issues a signed
 * one-time link, the mailbox user sets the password on a plain page, the ordinary action carries it to the node,
 * the link dies. A tampered or used link answers with a refusal, never a form.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('issues a one-time signed link, sets the password through the ordinary action and burns the link', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureMailService($org);
    $calls = [];
    Http::fake(function ($request) use (&$calls) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = [$function, $request->data()];
        $answer = match ($function) {
            'login' => 'sess-mbpw', 'logout' => true, 'monitor_jobqueue_count' => 0,
            'mail_user_get' => [['mailuser_id' => 21, 'email' => 'jana@shop.cz', 'name' => 'Jana', 'quota' => 1048576 * 1024, 'postfix' => 'y', 'disableimap' => 'n']],
            'mail_user_update' => true,
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
    $this->actingAs($user, 'sanctum');
    $this->withHeader('Idempotency-Key', 'ml-0')->postJson("/v1/services/{$service->id}/mailbox-password-link", ['remote_id' => '99'])->assertNotFound();
    $link = $this->withHeader('Idempotency-Key', 'ml-1')->postJson("/v1/services/{$service->id}/mailbox-password-link", ['remote_id' => '21'])->assertCreated()->json('data');
    $this->flushHeaders();
    expect($link['url'])->toContain('/mailbox/password/')->toContain('signature=')->and($link['mailbox'])->toBe('jana@shop.cz');
    $path = parse_url($link['url'], PHP_URL_PATH).'?'.parse_url($link['url'], PHP_URL_QUERY);

    // the page: only on the signed link; a tampered signature is refused; the form validates the password
    $this->get($path)->assertOk()->assertSee('jana@shop.cz')->assertSee('Nastavit heslo');
    $this->get(parse_url($link['url'], PHP_URL_PATH).'?expires=9999999999&signature=deadbeef')->assertStatus(403);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->post($path, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post($path, ['password' => 'Velmi-Tajne-Heslo-2026', 'password_confirmation' => 'jine-heslo-2026'])->assertSessionHasErrors('password');
    $this->post($path, ['password' => 'Velmi-Tajne-Heslo-2026', 'password_confirmation' => 'Velmi-Tajne-Heslo-2026'])->assertOk()->assertSee('Heslo je nastavené');

    // the ordinary action carried the password to the node once; the link is gone
    $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('queued_at')->firstOrFail();
    expect(driveOperation($operation)->state)->toBe(Operation::SUCCEEDED)->and($operation->desired['action'])->toBe('mailbox.update');
    $update = collect($calls)->first(fn ($c) => $c[0] === 'mail_user_update');
    expect($update[1]['primary_id'])->toBe(21)->and($update[1]['params']['password'])->toBe('Velmi-Tajne-Heslo-2026');
    expect(json_encode(DB::table('provider_calls')->pluck('request')->all()))->not->toContain('Velmi-Tajne-Heslo-2026'); // never in the call log
    $this->get($path)->assertOk()->assertSee('Odkaz už neplatí');
    $this->post($path, ['password' => 'Velmi-Tajne-Heslo-2026', 'password_confirmation' => 'Velmi-Tajne-Heslo-2026'])->assertOk()->assertSee('Odkaz už neplatí');
});
