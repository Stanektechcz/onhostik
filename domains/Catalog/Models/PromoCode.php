<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class PromoCode extends Model
{
    protected $table = 'promo_codes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_to' => 'datetime', 'applies_to' => 'array', 'first_period_only' => 'boolean', 'value' => 'float', 'max_uses' => 'integer', 'uses' => 'integer'];
    }

    public function isUsable(): bool
    {
        return $this->state === 'active'
            && ($this->valid_from === null || $this->valid_from->isPast())
            && ($this->valid_to === null || $this->valid_to->isFuture())
            && ($this->max_uses === null || $this->uses < $this->max_uses);
    }

    public function discountFor(Money $net, string $productFamily): Money
    {
        $applies = $this->applies_to ?? [];
        if ($applies !== [] && ! in_array($productFamily, $applies, true) && ! in_array('*', $applies, true)) {
            return Money::zero($net->currency);
        }
        if ($this->kind === 'percent') {
            return $net->percent((string) $this->value);
        }
        if ($this->currency !== null && $this->currency !== $net->currency->value) {
            return Money::zero($net->currency);
        }
        $fixed = Money::decimal((string) $this->value, $net->currency);

        return $fixed->greaterThan($net) ? $net : $fixed;
    }
}
