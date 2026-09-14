<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Models;

use Onhost\Platform\Eloquent\Model;

/** Immutable tax rule set. A new version is created for every change (S55). */
final class TaxRuleVersion extends Model
{
    protected static string $idPrefix = 'trv';

    protected $table = 'tax_rule_versions';

    protected function casts(): array
    {
        return ['rules' => 'array', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'version' => 'integer'];
    }
}
