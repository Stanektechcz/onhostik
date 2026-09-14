<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\GuestAccountNotification;
use Onhost\Domain\Orders\Commands\PlaceOrderCommand;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Risk\Turnstile;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Guest checkout: a visitor without an account completes the order with the details entered in the checkout. The
 * platform creates a basic account (user + organization) from those details, places the order as that customer
 * (cart → quote → order through the command bus, so idempotency, audit and consents are the same as for signed-in
 * customers), starts the browser session and mails a "set your password" link together with the order number.
 * Wallet and postpaid payment modes are not available to a brand-new account.
 */
final class CheckoutController extends ApiController
{
    public function guest(Request $request, OrganizationService $organizations, QuoteService $quotes, AuditRecorder $audit, OutboxPublisher $outbox, Turnstile $turnstile): JsonResponse
    {
        $turnstile->check($request); // §5q-6: the outcome is a signal of the order check, never a refusal
        if ($request->user() !== null) {
            throw new DomainError('already_signed_in', 'Jste přihlášeni — objednávku dokončete jako přihlášený zákazník.', 409);
        }
        $data = $request->validate([
            'customer' => ['required', 'array'], 'customer.email' => ['required', 'email:rfc', 'max:190'], 'customer.name' => ['required', 'string', 'min:3', 'max:120'],
            'customer.company' => ['nullable', 'string', 'max:190'], 'customer.ico' => ['nullable', 'string', 'max:20'], 'customer.dic' => ['nullable', 'string', 'max:20'], 'customer.vat_id' => ['nullable', 'string', 'max:20'],
            'customer.country' => ['nullable', 'string', 'size:2'], 'customer.street' => ['nullable', 'string', 'max:190'], 'customer.city' => ['nullable', 'string', 'max:120'], 'customer.postal_code' => ['nullable', 'string', 'max:16'],
            'customer.phone' => ['nullable', 'string', 'max:40'], 'customer.locale' => ['nullable', 'in:cs,en'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.product_key' => ['required', 'string', 'max:60'], 'items.*.plan_key' => ['nullable', 'string', 'max:60'], 'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:100'], 'items.*.config' => ['nullable', 'array'], 'items.*.line_id' => ['nullable', 'string', 'max:40'],
            'commit_months' => ['nullable', 'integer', 'in:1,12,24'], 'currency' => ['nullable', 'in:CZK,EUR'], 'promo_code' => ['nullable', 'string', 'max:40'],
            'consents' => ['required', 'array'], 'payment' => ['required', 'array'], 'payment.mode' => ['required', 'in:gateway,bank'], 'payment.provider' => ['nullable', 'string', 'max:20'], 'payment.method' => ['nullable', 'string', 'max:40'], 'payment.return_urls' => ['nullable', 'array'],
            'partner_code' => ['nullable', 'string', 'max:40'], 'terms' => ['accepted'],
        ]);

        // a retried request (double click, reload) returns the order it already placed instead of a second account
        $key = $this->idempotencyKey($request, 'order.place');
        $replay = Order::query()->where('idempotency_key', $key)->first();
        if ($replay !== null) {
            $user = $replay->user_id ? User::query()->find($replay->user_id) : null;
            $organization = Organization::query()->find($replay->organization_id);
            if ($user !== null) {
                $this->startSession($request, $user);
            }

            return response()->json([
                'order_id' => $replay->id, 'number' => $replay->number, 'state' => $replay->state, 'redirect_url' => $replay->meta['redirect_url'] ?? null, 'payment_intent_id' => $replay->payment_intent_id, 'bank_instructions' => $replay->meta['bank_instructions'] ?? null,
                'account' => ['created' => false, 'user' => $user ? Presenters::user($user) : null, 'organization' => $organization ? Presenters::organization($organization, 'owner') : null],
            ]);
        }

        $c = $data['customer'];
        $email = strtolower(trim((string) $c['email']));
        if (User::query()->where('email', $email)->exists()) {
            throw new DomainError('account_exists', 'Účet s tímto e-mailem už existuje. Přihlaste se a dokončete objednávku.', 409, ['field' => 'email']);
        }
        $locale = (string) ($c['locale'] ?? (in_array(app()->getLocale(), ['cs', 'en'], true) ? app()->getLocale() : 'cs'));
        $ip = $request->ip();
        $agent = mb_substr((string) $request->userAgent(), 0, 250);
        $sessionId = $this->api->sessionId($request);

        [$user, $organization, $token] = DB::transaction(function () use ($organizations, $c, $email, $locale, $ip, $agent, $sessionId, $data): array {
            $user = User::query()->create(['name' => trim((string) $c['name']), 'email' => $email, 'password' => Str::random(40), 'locale' => $locale, 'timezone' => 'Europe/Prague', 'is_staff' => false, 'state' => 'active']);
            $context = new CommandContext('user', $user->id, null, null, $ip, $agent, $sessionId, correlationId: CommandContext::currentCorrelationId());
            $company = trim((string) ($c['company'] ?? ''));
            $organization = $organizations->create($user, [
                'name' => $company !== '' ? $company : trim((string) $c['name']), 'type' => $company !== '' || ! empty($c['ico']) ? 'company' : 'person',
                'ico' => $c['ico'] ?? null, 'dic' => $c['dic'] ?? null, 'vat_id' => $c['vat_id'] ?? ($c['dic'] ?? null), 'country' => strtoupper((string) ($c['country'] ?? 'CZ')),
                'street' => $c['street'] ?? null, 'city' => $c['city'] ?? null, 'postal_code' => $c['postal_code'] ?? null, 'locale' => $locale, 'billing_email' => $email,
            ], $context);
            if (! empty($data['partner_code'])) {
                app(PartnerService::class)->attribute($organization, (string) $data['partner_code'], $context);
            }
            $token = Str::random(48);
            EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'purpose' => 'reset', 'expires_at' => now()->addHours(48)]);

            return [$user, $organization, $token];
        });

        $context = new CommandContext('user', $user->id, $organization->id, null, $ip, $agent, $sessionId, correlationId: CommandContext::currentCorrelationId());
        $quote = $quotes->quote(
            array_values($data['items']), $data['currency'] ?? $organization->currency ?? 'CZK',
            ['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status, 'ip_country' => null],
            (int) ($data['commit_months'] ?? 1), $data['promo_code'] ?? null, $organization, $locale,
        );
        $result = (array) $this->bus->dispatch(new PlaceOrderCommand($organization->id, $key, ['quote_id' => $quote->id, 'consents' => $data['consents'], 'payment' => $data['payment'], 'source' => 'web']), $context);
        $order = Order::query()->findOrFail((string) $result['order_id']);

        $user->notify(new GuestAccountNotification($token, $order->number, $organization->name)); // the secret goes by mail only, never into the redacted outbox
        $outbox->publish(GenericEvent::of('identity.registered', 'user', $user->id, ['email' => $user->email, 'name' => $user->name, 'organization_id' => $organization->id, 'locale' => $user->locale, 'via' => 'guest_checkout'], $organization->id));
        $audit->record($context->withScope($organization->id), 'auth.register', 'succeeded', ['email' => $user->email, 'via' => 'guest_checkout', 'order' => $order->number], 'user', $user->id);
        $this->startSession($request, $user);

        return response()->json(array_merge($result, ['account' => ['created' => true, 'user' => Presenters::user($user), 'organization' => Presenters::organization($organization, 'owner')]]), 201);
    }

    private function startSession(Request $request, User $user): void
    {
        $request->headers->remove('X-Organization'); // stale header from a previously signed-in account
        $request->query->remove('organization');
        if ($request->hasSession()) {
            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();
        } else {
            Auth::guard('web')->setUser($user);
        }
    }
}
