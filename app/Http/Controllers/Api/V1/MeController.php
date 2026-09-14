<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Profile, TOTP enrolment, recovery codes and personal API tokens. */
final class MeController extends ApiController
{
    public function update(Request $request, AuditRecorder $audit): JsonResponse
    {
        $user = $this->api->user($request);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'locale' => ['sometimes', 'in:cs,sk,en'], 'timezone' => ['sometimes', 'timezone:all'], 'preferences' => ['sometimes', 'array']]);
        $user->forceFill($data)->save();
        $audit->record($this->api->context($request), 'me.update', 'succeeded', array_keys($data), 'user', $user->id);

        return response()->json(['data' => Presenters::user($user)]);
    }

    public function changePassword(Request $request, StepUpService $stepUp, AuditRecorder $audit, OutboxPublisher $outbox): JsonResponse
    {
        $user = $this->api->user($request);
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', Password::min(12)->letters()->numbers()->uncompromised()]]);
        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw new DomainError('password_mismatch', 'Současné heslo nesouhlasí.', 422, ['field' => 'current_password']);
        }
        // the browser that changed the password stays signed in; every other session, remembered device and step-up is out
        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
        $stepUp->revokeAll($user);
        if (config('session.driver') === 'database') {
            $others = DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->getAuthIdentifier());
            $current = $this->api->sessionId($request);
            if ($current !== null && ! str_starts_with($current, 'token:')) {
                $others->where('id', '!=', $current);
            }
            $others->delete();
        }
        $audit->record($this->api->context($request), 'me.password.change', 'succeeded', [], 'user', $user->id);
        $outbox->publish(GenericEvent::of('security.password_changed', 'user', $user->id, ['email' => $user->email, 'ip' => $request->ip()]));

        return response()->json(['data' => ['changed' => true]]);
    }

    public function totpEnroll(Request $request, Totp $totp, AuditRecorder $audit): JsonResponse
    {
        $user = $this->api->user($request);
        if ($user->hasTotp()) {
            throw new DomainError('totp_already_enabled', 'Dvoufázové ověření je již zapnuté.', 409);
        }
        $secret = $totp->generateSecret();
        $user->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => null])->save();
        $audit->record($this->api->context($request), 'me.totp.enroll', 'succeeded', [], 'user', $user->id);

        return response()->json(['data' => ['secret' => $secret, 'otpauth' => $totp->provisioningUri($secret, $user->email, (string) config('app.name', 'ONhost'))]]);
    }

    public function totpConfirm(Request $request, StepUpService $stepUp, AuditRecorder $audit, OutboxPublisher $outbox): JsonResponse
    {
        $user = $this->api->user($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if ($user->totp_secret === null) {
            throw new DomainError('totp_not_enrolled', 'Nejprve začněte nastavení autentikátoru.', 409);
        }
        if (! $stepUp->verifyTotp($user, $data['code'])) {
            throw new DomainError('mfa_invalid', 'Kód z autentikátoru nesouhlasí.', 422, ['field' => 'code']);
        }
        $user->forceFill(['totp_confirmed_at' => now()])->save();
        $codes = $stepUp->issueRecoveryCodes($user);
        $audit->record($this->api->context($request), 'me.totp.confirm', 'succeeded', [], 'user', $user->id);
        $outbox->publish(GenericEvent::of('security.mfa', 'user', $user->id, ['email' => $user->email, 'change' => 'totp_enabled']));

        return response()->json(['data' => ['enabled' => true, 'recovery_codes' => $codes]]);
    }

    public function totpDisable(Request $request, StepUpService $stepUp, AuditRecorder $audit, OutboxPublisher $outbox): JsonResponse
    {
        $user = $this->api->user($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        if ($user->is_staff && config('onhost.identity.staff_mfa_required', true)) {
            throw new DomainError('mfa_mandatory', 'Interní účty nemohou dvoufázové ověření vypnout.', 403);
        }
        if (! $stepUp->verifyTotp($user, $data['code']) && ! $stepUp->consumeRecoveryCode($user, $data['code'])) {
            throw new DomainError('mfa_invalid', 'Kód z autentikátoru nesouhlasí.', 422, ['field' => 'code']);
        }
        $user->forceFill(['totp_secret' => null, 'totp_confirmed_at' => null, 'recovery_codes' => null])->save();
        $stepUp->revokeAll($user);
        $audit->record($this->api->context($request), 'me.totp.disable', 'succeeded', [], 'user', $user->id);
        $outbox->publish(GenericEvent::of('security.mfa', 'user', $user->id, ['email' => $user->email, 'change' => 'totp_disabled']));

        return response()->json(['data' => ['enabled' => false]]);
    }

    public function tokens(Request $request): JsonResponse
    {
        $user = $this->api->user($request);
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'api_token.manage', CommandScope::organization($organization->id));
        $tokens = $user->tokens()->where('organization_id', $organization->id)->orderByDesc('created_at')->get()->map(fn ($t) => [
            'id' => (string) $t->id, 'name' => $t->name, 'scopes' => array_values(array_filter((array) $t->abilities, fn ($a) => ! str_starts_with((string) $a, 'org:'))), 'last_used_at' => $t->last_used_at?->toIso8601String(), 'expires_at' => $t->expires_at?->toIso8601String(), 'revoked_at' => $t->revoked_at?->toIso8601String(), 'created_at' => $t->created_at?->toIso8601String(),
        ])->all();

        return response()->json(['data' => $tokens, 'scopes' => ApiTokenCommand::SCOPES]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'scopes' => ['required', 'array', 'min:1'], 'scopes.*' => ['string', 'in:'.implode(',', ApiTokenCommand::SCOPES)], 'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365']]);

        return $this->dispatch(new ApiTokenCommand($organization->id, $this->idempotencyKey($request, 'token.create'), ['op' => 'create'] + $data), $this->api->context($request, $organization), 201);
    }

    public function revokeToken(Request $request, string $token): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new ApiTokenCommand($organization->id, "token.revoke:{$token}", ['op' => 'revoke', 'token_id' => $token]), $this->api->context($request, $organization));
    }
}
