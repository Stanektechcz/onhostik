<?php

declare(strict_types=1);

namespace App\Domains\Reseller\Exceptions;

use RuntimeException;

/**
 * A reseller tried to take on more sub-customers than their plan allows
 * (audit K109).
 *
 * Thrown from the model layer rather than a controller: there is no single
 * place where a customer is attached to a reseller, so guarding one entry
 * point would leave the others open.
 */
final class ResellerCustomerLimitReached extends RuntimeException
{
    public static function forReseller(int $resellerId, int $limit): self
    {
        return new self(
            "Reseller #{$resellerId} dosáhl limitu {$limit} sub-zákazníků.",
        );
    }
}
