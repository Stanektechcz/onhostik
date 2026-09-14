<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Minimal HTML table reader for public price lists: every `<tr>` becomes a list of whitespace-normalised cell texts. */
final class HtmlTable
{
    /** @return list<list<string>> */
    public static function rows(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        $rows = [];
        foreach ($xpath->query('//tr') as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }
            $cells = [];
            foreach ($xpath->query('./th|./td', $tr) as $cell) {
                $cells[] = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $cell->textContent)));
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /** First "1 234,56 Kč"-style amount in a cell as a decimal string, or null. */
    public static function czk(string $cell): ?string
    {
        if (! preg_match('/(\d[\d ]*(?:,\d{1,2})?)\s*Kč/u', $cell, $m)) {
            return null;
        }
        $amount = str_replace([' ', ','], ['', '.'], $m[1]);

        return is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null;
    }
}
