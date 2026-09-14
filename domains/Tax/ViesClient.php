<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Models\VatValidation;
use Throwable;

/**
 * VIES VAT number validation via the European Commission REST API. Stores
 * evidence (consultation number, name/address) for the reverse-charge decision.
 * On VIES outage the previous status is kept and `unknown` is returned — never
 * a silent "valid".
 */
final class ViesClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /** @return array{status:string, validation:?VatValidation} status: valid | invalid | unknown */
    public function validate(string $vatId, ?Organization $organization = null): array
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $vatId) ?? '');
        if (strlen($normalized) < 4) {
            return ['status' => 'invalid', 'validation' => null];
        }
        $countryCode = substr($normalized, 0, 2);
        $number = substr($normalized, 2);
        try {
            $response = $this->http->timeout(8)->acceptJson()->post((string) config('onhost.billing.vies_endpoint'), [
                'countryCode' => $countryCode,
                'vatNumber' => $number,
                'requesterMemberStateCode' => 'CZ',
                'requesterNumber' => preg_replace('/^CZ/', '', (string) env('ONHOST_VAT_ID', '')),
            ]);
        } catch (Throwable $e) {
            return ['status' => 'unknown', 'validation' => null];
        }
        if (! $response->successful() || $response->json('userError', 'VALID') !== 'VALID' && $response->json('userError') !== 'INVALID') {
            $userError = (string) $response->json('userError', 'SERVICE_UNAVAILABLE');
            if (in_array($userError, ['INVALID_INPUT', 'INVALID'], true)) {
                return $this->record($organization, $normalized, false, null, null, null, $response->json() ?? []);
            }

            return ['status' => 'unknown', 'validation' => null];
        }
        $valid = (bool) $response->json('valid', false);

        return $this->record($organization, $normalized, $valid, $response->json('name'), $response->json('address'), $response->json('requestIdentifier'), $response->json() ?? []);
    }

    /** @return array{status:string, validation:VatValidation} */
    private function record(?Organization $organization, string $vatId, bool $valid, ?string $name, ?string $address, ?string $consultation, array $raw): array
    {
        $validation = VatValidation::query()->create([
            'organization_id' => $organization?->id,
            'vat_id' => $vatId,
            'valid' => $valid,
            'name' => $name === null ? null : mb_substr(trim($name), 0, 250),
            'address' => $address === null ? null : mb_substr(trim(preg_replace('/\s+/', ' ', $address) ?? ''), 0, 500),
            'consultation_number' => $consultation,
            'source' => 'vies',
            'raw' => $raw,
            'checked_at' => now(),
        ]);
        if ($organization !== null) {
            $organization->forceFill(['vat_status' => $valid ? 'valid' : 'invalid', 'vat_validated_at' => now(), 'customer_class' => $valid ? 'b2b' : $organization->customer_class])->save();
        }

        return ['status' => $valid ? 'valid' : 'invalid', 'validation' => $validation];
    }
}
