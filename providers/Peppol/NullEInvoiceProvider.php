<?php

declare(strict_types=1);

namespace Onhost\Providers\Peppol;

use Onhost\Providers\Contracts\EInvoiceProvider;

/**
 * W7-1 (Peppol layer 1): the default e-invoice driver while no Peppol access point is chosen. It sends nothing, calls no
 * network and records every delivery as `skipped`; the HTTP driver `peppol` comes with layer 2 (after W7-K1).
 */
final class NullEInvoiceProvider implements EInvoiceProvider
{
    public static function providerKey(): string
    {
        return 'null';
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @return array{delivery_id:string,state:string,raw:array<string,mixed>}
     */
    public function deliver(string $invoiceNumber, string $ublXml, array $recipient): array
    {
        return ['delivery_id' => 'null:'.sha1($invoiceNumber), 'state' => 'skipped', 'raw' => []];
    }

    /** @return array{state:string,detail:string|null,raw:array<string,mixed>} */
    public function status(string $deliveryId): array
    {
        return ['state' => 'skipped', 'detail' => null, 'raw' => []];
    }

    /** @return array{valid:bool,errors:list<string>} */
    public function validate(string $ublXml): array
    {
        return ['valid' => false, 'errors' => ['no e-invoice validator configured (driver null)']];
    }
}
