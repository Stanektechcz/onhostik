<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Marketplace\Commands\MarketplaceStaffCommand;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Marketplace\Models\MarketplaceListing;
use Onhost\Domain\Marketplace\Models\MarketplaceOrder;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Platform\Commands\CommandScope;

/** Marketplace curation (audit §5j-1): listings waiting for publication, orders and disputes. */
final class MarketplaceController extends ApiController
{
    public function listings(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $query = MarketplaceListing::query()->orderByDesc('created_at');
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return response()->json(['data' => $query->limit(300)->get()->map(fn (MarketplaceListing $l) => $marketplace->presentListing($l, true))->values()->all()]);
    }

    public function orders(Request $request, MarketplaceService $marketplace): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $query = MarketplaceOrder::query()->orderByDesc('created_at');
        if ($request->filled('state')) {
            $query->whereIn('state', $request->query('state') === 'open' ? MarketplaceOrder::OPEN : [(string) $request->query('state')]);
        }
        $rows = $query->limit(300)->get();
        $organizations = Organization::query()->whereIn('id', $rows->pluck('organization_id'))->pluck('name', 'id');
        $partners = Partner::query()->whereIn('id', $rows->pluck('partner_id'))->get()->keyBy('id');
        $partnerNames = Organization::query()->whereIn('id', $partners->pluck('organization_id'))->pluck('name', 'id');

        return response()->json(['data' => $rows->map(fn (MarketplaceOrder $o) => $marketplace->presentOrder($o, true) + ['organization' => $organizations[$o->organization_id] ?? null, 'partner' => $partnerNames[$partners->get($o->partner_id)?->organization_id] ?? null])->values()->all(), 'open' => MarketplaceOrder::query()->whereIn('state', MarketplaceOrder::OPEN)->count(), 'disputed' => MarketplaceOrder::query()->where('state', MarketplaceOrder::DISPUTED)->count()]);
    }

    public function listingState(Request $request, string $listing): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:published,paused,retired'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new MarketplaceStaffCommand($this->idempotencyKey($request, "marketplace.staff.listing:{$listing}:".now()->format('YmdHi')), ['op' => 'listing.state', 'listing_id' => $listing, 'state' => $data['state'], 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function resolve(Request $request, string $order): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:refund,deliver'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->dispatch(new MarketplaceStaffCommand($this->idempotencyKey($request, "marketplace.staff.resolve:{$order}"), ['op' => 'dispute.resolve', 'order_id' => $order, 'decision' => $data['decision'], 'reason' => $data['reason']]), $this->api->context($request, null, $data['reason']));
    }
}
