<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EU VAT number validation via the VIES SOAP web service.
 *
 * Results are cached for 24 h to avoid hammering VIES (which has
 * rate limits and occasional downtime).
 *
 * On VIES unavailability: returns null (inconclusive) so that the
 * caller can decide whether to block or allow the transaction.
 */
final class ViesVatValidator
{
    private const TTL = 86400; // 24 h

    /**
     * Validate a VAT number against VIES.
     *
     * @return bool|null  true = valid, false = invalid, null = service unavailable
     */
    public function validate(string $vatNumber): ?bool
    {
        $normalized = $this->normalize($vatNumber);

        if ($normalized === null) {
            return false; // malformed
        }

        [$countryCode, $number] = $normalized;

        $cacheKey = "vies_vat:{$countryCode}:{$number}";

        return Cache::remember($cacheKey, self::TTL, function () use ($countryCode, $number): ?bool {
            return $this->callVies($countryCode, $number);
        });
    }

    /** @return array{string,string}|null */
    private function normalize(string $vatNumber): ?array
    {
        $clean = preg_replace('/[\s.\-]/', '', strtoupper(trim($vatNumber)));

        if ($clean === null || strlen($clean) < 4) {
            return null;
        }

        // Extract 2-letter country prefix
        $countryCode = substr($clean, 0, 2);
        $number      = substr($clean, 2);

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            return null;
        }

        return [$countryCode, $number];
    }

    private function callVies(string $countryCode, string $vatNumber): ?bool
    {
        try {
            $soap = sprintf(
                '<?xml version="1.0" encoding="UTF-8"?>' .
                '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"' .
                ' xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">' .
                '<soapenv:Body><urn:checkVat>' .
                '<urn:countryCode>%s</urn:countryCode>' .
                '<urn:vatNumber>%s</urn:vatNumber>' .
                '</urn:checkVat></soapenv:Body></soapenv:Envelope>',
                htmlspecialchars($countryCode, ENT_XML1),
                htmlspecialchars($vatNumber, ENT_XML1),
            );

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=UTF-8',
                'SOAPAction'   => '',
            ])->timeout(8)->withBody($soap, 'text/xml')->post(
                'https://ec.europa.eu/taxation_customs/vies/services/checkVatService'
            );

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            // Parse <valid> element from the SOAP response
            if (preg_match('/<.*:valid>(true|false)<\/.*:valid>/i', $body, $matches)) {
                return $matches[1] === 'true';
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('VIES VAT validation failed', [
                'country' => $countryCode,
                'error'   => $e->getMessage(),
            ]);

            return null; // unavailable — caller decides
        }
    }
}
