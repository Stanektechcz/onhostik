<?php

declare(strict_types=1);

namespace Onhost\Providers\Subreg;

use Onhost\Providers\Contracts\HtmlTable;
use Onhost\Providers\Contracts\PublicPriceListScraper;

/**
 * Public domain price list of Subreg.CZ (https://subreg.cz/cz/cenik-domen/): rows
 * `.tld | info | min. years | new | renew | transfer`, prices as "177 Kč * (213,82 Kč)" — net first,
 * the asterisk marks a first-year promotion, the bracket the amount with VAT. Columns are located
 * by the header row so a reordered table still parses.
 */
final class SubregPublicPriceList implements PublicPriceListScraper
{
    public static function registrarKey(): string
    {
        return 'subreg';
    }

    public function url(): string
    {
        return (string) config('onhost.subreg.public_pricelist_url', 'https://subreg.cz/cz/cenik-domen/');
    }

    public function parse(string $html): array
    {
        $out = [];
        $cols = null;
        foreach (HtmlTable::rows($html) as $cells) {
            $lower = array_map(fn ($c) => strtolower($c), $cells);
            if (in_array('tld', $lower, true) && (in_array('new', $lower, true) || in_array('nová', $lower, true))) {
                $cols = ['new' => null, 'renew' => null, 'transfer' => null, 'min' => null];
                foreach ($lower as $i => $text) {
                    match (true) {
                        $text === 'new' || $text === 'nová' || str_starts_with($text, 'registr') => $cols['new'] = $i,
                        $text === 'renew' || str_starts_with($text, 'prodl') || str_starts_with($text, 'obnov') => $cols['renew'] = $i,
                        $text === 'transfer' || str_starts_with($text, 'převod') => $cols['transfer'] = $i,
                        str_starts_with($text, 'min') => $cols['min'] = $i,
                        default => null,
                    };
                }

                continue;
            }
            if (count($cells) < 4 || ! preg_match('/^\.([a-z0-9-]{2,})$/i', $cells[0], $m)) {
                continue;
            }
            $c = $cols ?? (count($cells) >= 7 ? ['new' => 3, 'renew' => 4, 'transfer' => 5, 'min' => 2] : ['new' => 2, 'renew' => 3, 'transfer' => 4, 'min' => null]);
            $tld = strtolower($m[1]);
            $register = $c['new'] !== null ? HtmlTable::czk($cells[$c['new']] ?? '') : null;
            $renew = $c['renew'] !== null ? HtmlTable::czk($cells[$c['renew']] ?? '') : null;
            if ($register === null && $renew === null) {
                continue;
            }
            $out[$tld] = [
                'currency' => 'CZK', 'register' => $register, 'renew' => $renew ?? $register, 'transfer' => $c['transfer'] !== null ? HtmlTable::czk($cells[$c['transfer']] ?? '') : null,
                'promo' => str_contains((string) ($cells[$c['new']] ?? ''), '*'), 'min_years' => $c['min'] !== null ? max(1, (int) ($cells[$c['min']] ?? 1)) : 1,
            ];
        }

        return $out;
    }
}
