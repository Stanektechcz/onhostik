<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * Prototype sentences that promise money back for credit (G8 item 9, owner decision 2026-10-05): credit is NEVER paid back in
 * cash. The prototypes (`apps/surfaces/*.dc.html`, `onhost-content.js`, `onhost-svc-*.js`) must stay byte-identical, so the served
 * copy is corrected here, on its way out, exactly like every other seam. Each needle is the prototype's own text; a test pins that
 * every one still exists in the prototype (so a rewritten sentence cannot silently escape the seam) and that nothing still says it.
 */
final class CreditClaimsSeam
{
    /** @var array<string, array<string, string>> group => [prototype text => the true text] */
    public const CLAIMS = [
        'content' => [
            'Nevyčerpaný zůstatek se převádí a vracíme ho na požádání.' => 'Nevyčerpaný zůstatek se převádí do dalšího období; v hotovosti se nevrací, ale zaplatíte jím cokoli z katalogu.',
            'Při dobití od pěti tisíc korun přidáváme deset procent navíc. ' => '',
            'Top-ups from five thousand crowns get ten percent extra. ' => '',
            'Unused balance rolls over and is refundable on request.' => 'Unused balance rolls over to the next period; it is never paid back in cash, but it pays for anything in the catalogue.',
        ],
        'admin' => [
            'Kredit neexpiruje, má stejný bonus pro všechny a vrací se na požádání.' => 'Kredit neexpiruje a v hotovosti se nevrací.',
            'Credit never expires, carries the same bonus for everyone and is refunded on request.' => 'Credit never expires and is never paid back in cash.',
            'it never expires, carries the same bonus for everyone and is refunded on request.' => 'it never expires and is never paid back in cash.',
            'm\\u00e1 stejn\\u00fd bonus pro v\\u0161echny a vrac\\u00ed se, kdy\\u017e o to z\\u00e1kazn\\u00edk po\\u017e\\u00e1d\\u00e1.' => 'v hotovosti se nevrac\\u00ed.',
            'Nevyu\\u017eit\\u00fd kredit vrac\\u00edme, kdy\\u017e o to po\\u017e\\u00e1d\\u00e1.' => 'Nevyu\\u017eit\\u00fd kredit se v hotovosti nevrac\\u00ed.',
            'We refund unused credit on request.' => 'Unused credit is never paid back in cash.',
            '_(\'Vr\\u00e1tit nevyu\\u017eit\\u00fd kredit\', \'Refund unused credit\')' => '_(\'Kredit se nevrac\\u00ed v hotovosti\', \'Credit is not paid back in cash\')',
            '_(\'Nevyu\\u017eit\\u00fd kredit vrac\\u00edme na \\u00fa\\u010det. Bonus 10 % se p\\u0159i vr\\u00e1cen\\u00ed neu\\u010dtuje zp\\u011btn\\u011b \\u2014 to bychom si vybírali za slu\\u0161nost.\', \'Unused credit goes back to the bank account. The 10% bonus is not clawed back \\u2014 that would be charging for decency.\')' => '_(\'Kredit se na \\u00fa\\u010det nevrac\\u00ed; z\\u00e1kazn\\u00edk jej vyu\\u017eije na cokoli z katalogu. Hodnotu vracen\\u00e9 slu\\u017eby vrac\\u00edme jen jako kredit.\', \'Credit never goes back to a bank account; the customer spends it on anything in the catalogue. The value of a returned service comes back as credit only.\')',
            'vratn\\u00fd na \\u017e\\u00e1dost' => 'v hotovosti se nevrac\\u00ed',
            'refundable on request' => 'never paid back in cash',
            'vr\\u00e1cen\\u00ed nespot\\u0159ebovan\\u00e9ho kreditu' => 'nespot\\u0159ebovan\\u00fd kredit se v hotovosti nevrac\\u00ed',
            'unused credit refunded' => 'unused credit is not paid back in cash',
            'Kredit nepropadá a na požádání ho vracíme. Držíme na něj rezervu.' => 'Kredit nepropadá a v hotovosti se nevrací. Držíme na něj rezervu.',
            'Credit never expires and we refund it on request. We hold a reserve against it.' => 'Credit never expires and is never paid back in cash. We hold a reserve against it.',
        ],
        'panel' => [
            'nevyužité dny vracíme do deseti dnů' => 'nevyužité dny vracíme jako kredit',
            'refund unused days within ten days' => 'credit unused days back as account credit',
        ],
        'svc-web' => [
            'kredit vracíme automaticky' => 'kredit připíšeme automaticky',
            'credit is refunded automatically' => 'credit is added automatically',
        ],
    ];

    /** The corrected copy of a prototype script or page; `$groups` names which claim groups apply to it. @param list<string> $groups */
    public static function apply(string $source, array $groups): string
    {
        foreach ($groups as $group) {
            $source = strtr($source, self::CLAIMS[$group] ?? []);
        }

        return $source;
    }
}
