<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domains\Api\Support\ApiQuery;
use App\Domains\Api\Support\ProblemDetails;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Domains v2 — cursor paginated with expiry/auto-renew detail.
 */
final class DomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return ProblemDetails::make(403, 'no-customer-account', 'Účet bez zákaznického profilu',
                'K tomuto tokenu není přiřazen zákaznický účet.', $request);
        }

        $query = ApiQuery::apply(
            DomainRegistration::query()
                ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id)),
            $request,
            sortable: ['id', 'expires_at', 'registered_at', 'created_at'],
            filterable: ['registrar', 'auto_renew'],
        );

        $paginator = ApiQuery::paginate($query, $request);

        $rows = collect($paginator->items())
            ->map(fn (DomainRegistration $d): array => [
                'id'            => $d->id,
                'domain'        => $d->fqdn(),
                'registrar'     => $d->registrar,
                'registered_at' => $d->registered_at?->toDateString(),
                'expires_at'    => $d->expires_at?->toDateString(),
                'auto_renew'    => (bool) $d->auto_renew,
                'nameservers'   => $d->nameservers,
                'days_to_expiry' => $d->expires_at?->diffInDays(now(), false) !== null
                    ? (int) now()->diffInDays($d->expires_at, false)
                    : null,
            ])
            ->values()
            ->all();

        return response()->json(ApiQuery::envelope($paginator, ApiQuery::sparse($rows, $request)));
    }
}
