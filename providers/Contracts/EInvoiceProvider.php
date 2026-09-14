<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** SK eFaktúra 2027 / Peppol delivery through a certified external provider (§64.5). */
interface EInvoiceProvider
{
    public static function providerKey(): string;

    /** @return array{delivery_id:string,state:string,raw:array<string,mixed>} */
    public function deliver(string $invoiceNumber, string $ublXml, array $recipient): array;

    /** @return array{state:string,detail:string|null,raw:array<string,mixed>} */
    public function status(string $deliveryId): array;

    /** Validate the document against the provider's EN16931 validator without sending. @return array{valid:bool,errors:list<string>} */
    public function validate(string $ublXml): array;
}
