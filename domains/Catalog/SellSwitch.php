<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

/** The launch sell-switch matrix (config/onhost-sell.php): may a product family be sold NEW? Fail-closed. */
final class SellSwitch
{
    public static function allows(?string $family): bool
    {
        $own = $family === null ? null : config('onhost-sell.families.'.$family);

        return is_bool($own) ? $own : (bool) config('onhost-sell.default', false);
    }
}
