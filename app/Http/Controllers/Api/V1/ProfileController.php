<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user     = $request->user();
        $customer = $user?->customer;

        return response()->json([
            'data' => [
                'id'       => $user?->id,
                'name'     => $user?->name,
                'email'    => $user?->email,
                'locale'   => $user?->locale,
                'customer' => $customer ? [
                    'type'               => $customer->type,
                    'company_name'       => $customer->company_name,
                    'preferred_currency' => $customer->preferred_currency,
                    'country_code'       => $customer->country_code,
                ] : null,
            ],
        ]);
    }
}
