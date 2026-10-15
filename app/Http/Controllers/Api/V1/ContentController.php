<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Content\Commands\WaitlistCommand;
use Onhost\Domain\Content\ContentService;
use Onhost\Domain\Content\Models\Lead;
use Onhost\Domain\Content\Waitlist\WaitlistService;
use Onhost\Domain\Risk\Turnstile;

/** Public content and forms for Onhost.dc.html (`onhost-data.js` / `onhost-content.js` shapes). */
final class ContentController extends ApiController
{
    public function posts(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->posts($this->locale($request), $request->query('cat')));
    }

    public function post(Request $request, string $slug, ContentService $content): JsonResponse
    {
        return $this->ok($content->post($slug, $this->locale($request)));
    }

    public function kb(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->kb($this->locale($request), $request->query('q'), $request->query('cat')));
    }

    public function kbArticle(Request $request, string $slug, ContentService $content): JsonResponse
    {
        return $this->ok($content->kbArticle($slug, $this->locale($request)));
    }

    public function changelog(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->changelog($this->locale($request), $request->query('tag')));
    }

    public function locations(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->locations($this->locale($request)));
    }

    public function stock(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->stock($this->locale($request)));
    }

    public function lead(Request $request, ContentService $content, Turnstile $turnstile): JsonResponse
    {
        $turnstile->requireForForm($request, 'lead'); // §5r-6: contact and support requests
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_diff(Lead::KINDS, ['tender', 'reseller']))], 'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:190'], 'message' => ['nullable', 'string', 'max:8000'], 'meta' => ['nullable', 'array'], 'consent' => ['accepted'],
        ]);
        $lead = $content->lead($data + ['source' => 'web'], $this->api->context($request), $request->user() ? $this->api->organization($request, false) : null);

        return response()->json(['data' => ['id' => $lead->id, 'kind' => $lead->kind, 'state' => $lead->state]], 201);
    }

    public function tender(Request $request, ContentService $content, Turnstile $turnstile): JsonResponse
    {
        $turnstile->requireForForm($request, 'tender');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'], 'company' => ['required', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:8000'], 'deadline' => ['nullable', 'date'], 'authority' => ['nullable', 'string', 'max:190'], 'scope' => ['nullable', 'string', 'max:2000'], 'consent' => ['accepted'],
        ]);
        $lead = $content->tenderRequest($data, $this->api->context($request), $this->locale($request));

        return response()->json(['data' => ['id' => $lead->id, 'documents' => $lead->meta['documents'] ?? [], 'state' => $lead->state]], 201);
    }

    public function resellerTiers(Request $request, ContentService $content): JsonResponse
    {
        return $this->ok($content->resellerTiers($this->locale($request)));
    }

    public function resellerApply(Request $request, ContentService $content, Turnstile $turnstile): JsonResponse
    {
        $turnstile->requireForForm($request, 'partner_application'); // §5r-6: the partner portal's door
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'], 'company' => ['required', 'string', 'max:190'],
            'clients' => ['nullable', 'string', 'max:40'], 'site' => ['nullable', 'string', 'max:190'], 'model' => ['nullable', 'in:share,oneoff'], 'message' => ['nullable', 'string', 'max:4000'], 'consent' => ['accepted'],
        ]);
        $organization = $request->user() ? $this->api->organization($request, false) : null;
        $result = $content->resellerApply($data, $this->api->context($request, $organization), $organization);

        return response()->json(['data' => ['lead_id' => $result['lead']->id, 'partner' => $result['partner'] ? ['id' => $result['partner']->id, 'code' => $result['partner']->code, 'state' => $result['partner']->state] : null]], 201);
    }

    /**
     * E2: "tell me when {product} is available" — double opt-in. The answer is the same for a new, a pending and a confirmed
     * address (202), so the form tells nobody who is on the list; the confirmation mail goes out through the outbox.
     */
    public function waitlistSignup(Request $request, Turnstile $turnstile): JsonResponse
    {
        $turnstile->requireForForm($request, 'waitlist');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'], 'product' => ['required', 'string', 'max:64', 'regex:'.WaitlistService::PRODUCT_PATTERN],
            'consent' => ['accepted'], 'consent_version' => ['required', 'string', 'max:20'],
        ]);

        return $this->ok($this->bus->dispatch(new WaitlistCommand('waitlist.signup:'.Str::ulid(), [
            'op' => 'signup', 'email' => $data['email'], 'product' => $data['product'], 'consent_version' => $data['consent_version'], 'locale' => $this->locale($request),
        ]), $this->api->context($request)), 202);
    }

    public function waitlistConfirm(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:128']]);

        return $this->ok($this->bus->dispatch(new WaitlistCommand('waitlist.confirm:'.Str::ulid(), ['op' => 'confirm', 'token' => $data['token']]), $this->api->context($request)));
    }

    public function waitlistWithdraw(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:128']]);

        return $this->ok($this->bus->dispatch(new WaitlistCommand('waitlist.withdraw:'.Str::ulid(), ['op' => 'withdraw', 'token' => $data['token']]), $this->api->context($request)));
    }

    private function locale(Request $request): string
    {
        return strtolower((string) $request->query('locale', 'cs')) === 'en' ? 'en' : 'cs';
    }
}
