<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $domains = DomainRegistration::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id))
            ->get()
            ->map(fn (DomainRegistration $d) => [
                'id'            => $d->id,
                'domain'        => $d->fqdn(),
                'registrar'     => $d->registrar,
                'registered_at' => $d->registered_at?->toDateString(),
                'expires_at'    => $d->expires_at?->toDateString(),
                'auto_renew'    => $d->auto_renew,
                'nameservers'   => $d->nameservers,
            ]);

        return response()->json(['data' => $domains]);
    }
}
