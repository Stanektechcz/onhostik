<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Marketplace\Commands\MarketplaceCommand;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The marketplace (audit §5j-1): public listings, the customer's orders (ordered from credit, accepted or disputed),
 * and the partner side (listings, deliveries). Every write is a MarketplaceCommand on the acting organization.
 */
final class MarketplaceController extends ApiController
{
    // ── public ────────────────────────────────────────────────────────────────

    public function index(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        $query = MarketplaceListing::query()->where('state', MarketplaceListing::PUBLISHED)->orderBy('category')->orderBy('title');
        if ($request->filled('category')) {
            $query->where('category', (string) $request->query('category'));
        }
        if ($request->filled('currency')) {
            $query->where('currency', strtoupper((string) $request->query('currency')));
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (MarketplaceListing $l) => $marketplace->presentListing($l))->values()->all(), 'categories' => MarketplaceService::CATEGORIES]);
    }

    public function show(MarketplaceService $marketplace, string $listing): JsonResponse
    {
        $model = MarketplaceListing::query()->where('state', MarketplaceListing::PUBLISHED)->where(fn ($q) => $q->whereKey($listing)->orWhere('key', $listing))->first();
        if ($model === null) {
            throw DomainError::notFound('marketplace_listing');
        }

        return response()->json(['data' => $marketplace->presentListing($model)]);
    }

    // ── customer ──────────────────────────────────────────────────────────────

    public function orders(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $query = MarketplaceOrder::query()->where('organization_id', $organization->id);
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (MarketplaceOrder $o) => $marketplace->presentOrder($o));
    }

    public function order(Request $request, string $listing): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['brief' => ['required', 'string', 'min:10', 'max:4000'], 'service_id' => ['nullable', 'string', 'max:40'], 'reason' => ['nullable', 'string', 'max:250']]);
        $model = MarketplaceListing::query()->where(fn ($q) => $q->whereKey($listing)->orWhere('key', $listing))->first();
        if ($model === null) {
            throw DomainError::notFound('marketplace_listing');
        }

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.order:{$model->id}"), ['op' => 'order', 'listing_id' => $model->id, 'brief' => $data['brief'], 'service_id' => $data['service_id'] ?? null]), $this->api->context($request, $organization, $data['reason'] ?? null), 201);
    }

    public function accept(Request $request, string $order): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.accept:{$order}"), ['op' => 'accept', 'order_id' => $order]), $this->api->context($request, $organization));
    }

    public function dispute(Request $request, string $order): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.dispute:{$order}"), ['op' => 'dispute', 'order_id' => $order, 'reason' => $data['reason']]), $this->api->context($request, $organization, $data['reason']));
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.cancel:{$order}"), ['op' => 'cancel', 'order_id' => $order]), $this->api->context($request, $organization));
    }

    // ── partner portal ────────────────────────────────────────────────────────

    public function partnerListings(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        [$organization, $partner] = $this->partner($request);

        return response()->json(['data' => MarketplaceListing::query()->where('partner_id', $partner->id)->orderByDesc('created_at')->limit(200)->get()->map(fn (MarketplaceListing $l) => $marketplace->presentListing($l, true))->values()->all(), 'categories' => MarketplaceService::CATEGORIES, 'commission_pct' => (int) config('onhost.marketplace.commission_pct', 20)]);
    }

    public function createListing(Request $request): JsonResponse
    {
        [$organization] = $this->partner($request);
        $data = $request->validate($this->listingRules(true));

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, 'marketplace.listing.create:'.$data['key']), ['op' => 'listing.create'] + $data), $this->api->context($request, $organization), 201);
    }

    public function updateListing(Request $request, string $listing): JsonResponse
    {
        [$organization] = $this->partner($request);
        $data = $request->validate($this->listingRules(false));

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.listing.update:{$listing}"), ['op' => 'listing.update', 'listing_id' => $listing] + $data), $this->api->context($request, $organization));
    }

    public function listingState(Request $request, string $listing): JsonResponse
    {
        [$organization] = $this->partner($request);
        $data = $request->validate(['state' => ['required', 'in:published,paused']]);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.listing.state:{$listing}:".now()->format('YmdHi')), ['op' => 'listing.state', 'listing_id' => $listing, 'state' => $data['state']]), $this->api->context($request, $organization));
    }

    public function partnerOrders(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        [, $partner] = $this->partner($request);
        $query = MarketplaceOrder::query()->where('partner_id', $partner->id);
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (MarketplaceOrder $o) => $marketplace->presentOrder($o) + ['customer_organization_id' => $o->organization_id, 'partner_share' => ['minor' => $o->partner_minor, 'currency' => $o->currency]]);
    }

    public function start(Request $request, string $order): JsonResponse
    {
        [$organization] = $this->partner($request);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.start:{$order}"), ['op' => 'order.start', 'order_id' => $order]), $this->api->context($request, $organization));
    }

    public function deliver(Request $request, string $order): JsonResponse
    {
        [$organization] = $this->partner($request);
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:4000'], 'evidence' => ['nullable', 'array', 'max:10']]);

        return $this->dispatch(new MarketplaceCommand($organization->id, $this->idempotencyKey($request, "marketplace.deliver:{$order}:".now()->format('YmdHi')), ['op' => 'order.deliver', 'order_id' => $order, 'note' => $data['note'], 'evidence' => (array) ($data['evidence'] ?? [])]), $this->api->context($request, $organization)); // §5o-2: the checklist behind a monthly deliverable
    }

    /** A file behind a checklist item of the running period (audit §5p-3): stored under a temporary name, moved by the command. */
    public function uploadEvidence(Request $request, string $order): JsonResponse
    {
        [$organization] = $this->partner($request);
        $data = $request->validate(['key' => ['required', 'string', 'max:31'], 'file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,txt,csv,zip,log']]);
        $upload = $data['file'];
        $tmp = $upload->storeAs('marketplace-evidence/tmp', Str::lower(Str::random(16)).'.'.$upload->getClientOriginalExtension(), 'local');

        return $this->dispatch(new MarketplaceCommand($organization->id, "marketplace.evidence:{$order}:{$data['key']}:".Str::lower(Str::random(16)), ['op' => 'order.evidence', 'order_id' => $order, 'key' => $data['key'], 'tmp_path' => $tmp, 'name' => $upload->getClientOriginalName(), 'mime' => (string) $upload->getClientMimeType(), 'size' => (int) $upload->getSize()]), $this->api->context($request, $organization), 201);
    }

    /** The customer downloads a file the period report carried (audit §5p-3). */
    public function evidenceFile(Request $request, MarketplaceService $marketplace, string $order, int $entry, string $key): StreamedResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $model = MarketplaceOrder::query()->where('organization_id', $organization->id)->find($order) ?? throw DomainError::notFound('marketplace_order');
        $file = $marketplace->evidenceFile($model, $entry, $key);

        return Storage::disk('local')->download($file['path'], $file['name'], ['Content-Type' => $file['mime']]);
    }

    /** @return array{0:Organization, 1:Partner} */
    private function partner(Request $request): array
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $partner = Partner::query()->where('organization_id', $organization->id)->first();
        if ($partner === null || $partner->state !== 'active') {
            throw new DomainError('partner_not_active', 'The partner portal is available to approved partners.', 403, ['state' => $partner?->state]);
        }

        return [$organization, $partner];
    }

    /** @return array<string, list<string>> */
    private function listingRules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'key' => [$req, 'string', 'max:60'], 'title' => [$req, 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:4000'], 'category' => ['nullable', 'string', 'max:30'],
            'price_minor' => [$req, 'integer', 'min:10000', 'max:100000000'], 'billing' => ['nullable', 'in:oneoff,monthly'], 'delivery_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }
}
