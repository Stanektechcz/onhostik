<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

final class PaymentMethod extends Model
{
    protected static string $idPrefix = 'pm';

    protected $table = 'payment_methods';

    protected $hidden = ['provider_token'];

    protected function casts(): array
    {
        return ['provider_token' => 'encrypted', 'is_default' => 'boolean'];
    }
}
