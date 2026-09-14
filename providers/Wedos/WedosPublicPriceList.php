<?php

declare(strict_types=1);

namespace Onhost\Providers\Wedos;

use Onhost\Providers\Contracts\HtmlTable;
use Onhost\Providers\Contracts\PublicPriceListScraper;

/**
 * Public domain price list of the WEDOS/Vedos registrar (https://vedos.cz/domeny/cenik/): one table row per TLD,
 * cells `.tld | registrace | prodloužení [| transfer]` where every price cell starts with the net CZK amount per year
 * ("160 Kč/rok 7,70 €/rok … 193,60 Kč/rok s DPH …"). Columns are located by the header row. Retail prices —
 * an upper bound of the wholesale cost.
 */
final class WedosPublicPriceList implements PublicPriceListScraper
{
    public static function registrarKey(): string
    {
        return 'wedos';
    }

    public function url(): string
    {
        return (string) config('onhost.wapi.public_pricelist_url', 'https://vedos.cz/domeny/cenik/');
    }

    public function parse(string $html): array
    {
        $out = [];
        $cols = null;
        foreach (HtmlTable::rows($html) as $cells) {
            $lower = array_map(fn ($c) => strtolower($c), $cells);
            if (in_array('registrace', $lower, true) || in_array('registration', $lower, true)) {
                $cols = ['register' => null, 'renew' => null, 'transfer' => null];
                foreach ($lower as $i => $text) {
                    match (true) {
                        str_starts_with($text, 'registr') => $cols['register'] = $i,
                        str_starts_with($text, 'prodl') || str_starts_with($text, 'renew') || str_starts_with($text, 'obnov') => $cols['renew'] = $i,
                        str_starts_with($text, 'transfer') || str_starts_with($text, 'převod') => $cols['transfer'] = $i,
                        default => null,
                    };
                }

                continue;
            }
            if (count($cells) < 3 || ! preg_match('/^\.([a-z0-9-]{2,})$/i', $cells[0], $m)) {
                continue;
            }
            $c = $cols ?? ['register' => 1, 'renew' => 2, 'transfer' => null];
            $tld = strtolower($m[1]);
            $register = $c['register'] !== null ? HtmlTable::czk($cells[$c['register']] ?? '') : null;
            $renew = $c['renew'] !== null ? HtmlTable::czk($cells[$c['renew']] ?? '') : null;
            if ($register === null && $renew === null) {
                continue;
            }
            $transfer = $c['transfer'] !== null ? HtmlTable::czk($cells[$c['transfer']] ?? '') : null;
            $out[$tld] = ['currency' => 'CZK', 'register' => $register, 'renew' => $renew ?? $register, 'transfer' => $transfer, 'promo' => $register !== null && $renew !== null && (float) $register < (float) $renew];
        }

        return $out;
    }
}
