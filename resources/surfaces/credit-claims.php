<?php

declare(strict_types=1);

/*
 * The prototype sentences that promise credit back in cash (G8 item 9, owner decision 2026-10-05), and what is true instead:
 * group => [the prototype's own text => the corrected text]. Read by App\Http\Support\CreditClaimsSeam. The wording of the
 * prototype lives here, not in app/Http, where nothing may speak of a balance that can be taken out.
 */
return [
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
        // H2: the admin demo cards promised a top-up bonus that no rule of the platform gives (owner rules: no implicit discounts); and a 30-day
        // download after removal that is really DeletionPolicy::retentionDays() ({retention_days} is filled in by CreditClaimsSeam::apply)
        'bonus 10 % stejný jako pro koncové zákazníky' => 'bez bonusu, stejné podmínky jako pro koncové zákazníky',
        '10% bonus, the same as for end customers' => 'no bonus, the same terms as for end customers',
        '12 000 K\\u010d dobito, 5 160 K\\u010d bonus 10 %, 1 240 K\\u010d automaticky za minut\\u00e9 SLA.' => '12 000 K\\u010d dobito, 1 240 K\\u010d automaticky za minut\\u00e9 SLA. Bonus za dobit\\u00ed se ned\\u00e1v\\u00e1.',
        '12,000 CZK topped up, 5,160 CZK from the 10% bonus, 1,240 CZK automatic for a missed SLA.' => '12,000 CZK topped up, 1,240 CZK automatic for a missed SLA. No top-up bonus is given.',
        '1 Aug \\u00b7 10% bonus credited at once\'), \'+13 200\'' => '1 Aug \\u00b7 credited at once, no bonus\'), \'+12 000\'',
        '1. 8. \\u00b7 bonus 10 % p\\u0159ipsán ihned' => '1. 8. \\u00b7 p\\u0159ipsáno ihned, bez bonusu',
        'Kdo chce kredit m\\u00edsto pen\\u011bz, m\\u00e1 bonus 10 %.' => 'Kdo chce kredit m\\u00edsto pen\\u011bz, dostane ho ve stejn\\u00e9 v\\u00fd\\u0161i.',
        'Credit instead of cash carries a 10% bonus.' => 'Credit instead of cash is credited at face value.',
        'Kredit z f\\u00f3ra m\\u00e1 stejn\\u00fd bonus 10 % jako dobit\\u00ed. \\u017d\\u00e1dn\\u00e9 zvl\\u00e1\\u0161tn\\u00ed pravidlo.' => 'Kredit z f\\u00f3ra je oby\\u010dejn\\u00fd kredit, bez bonusu. \\u017d\\u00e1dn\\u00e9 zvl\\u00e1\\u0161tn\\u00ed pravidlo.',
        'Forum credit carries the same 10% bonus as a top-up. No special rules.' => 'Forum credit is ordinary credit with no bonus. No special rules.',
        'bonus 10 % k dobit\\u00ed kreditu' => 'bonus za dobit\\u00ed se ned\\u00e1v\\u00e1 \\u00b7 k\\u00f3d jen zna\\u010d\\u00ed zdroj',
        '10% bonus on credit top-ups' => 'no top-up bonus \\u00b7 the code only marks the source',
        'Bonus je stejn\\u00fd pro v\\u0161echny. Individu\\u00e1ln\\u00ed bonusy vedou k tomu, \\u017ee nikdo nev\\u00ed, co plat\\u00ed.' => 'Z\\u00e1kladn\\u00ed cena je pro v\\u0161echny stejn\\u00e1. \\u017d\\u00e1dn\\u00fd bonus ani sleva se nep\\u0159ipisuje bez v\\u00fdslovn\\u00e9ho pravidla.',
        'The bonus is the same for everyone. Individual bonuses end with nobody knowing the price.' => 'The base price is the same for everyone. No bonus or discount is applied without an explicit rule.',
        '\'Bonus za dobití kreditu\', \'Top-up bonus\'), _(\'dobito 5 000 Kč · bonus 10 % automaticky\', \'topped up 5,000 CZK · 10% bonus automatic\'), [\'Skladomat s.r.o.\', \'+ 500 Kč\']' => '\'Dobití kreditu\', \'Credit top-up\'), _(\'dobito 5 000 Kč · bez bonusu\', \'topped up 5,000 CZK · no bonus\'), [\'Skladomat s.r.o.\', \'+ 5 000 Kč\']',
        'Bonus platí od pěti tisíc, na cokoli z katalogu.' => 'Kredit se vede v nominální výši a zaplatí cokoli z katalogu.',
        'The bonus applies from five thousand up, against anything in the catalogue.' => 'Credit is held at face value and pays for anything in the catalogue.',
        '1 služba · svět a konfigurace zůstávají ke stažení 30 dní' => '1 služba · svět a konfigurace zůstávají ke stažení {retention_days} dní',
        '1 service · the world and configs stay downloadable for 30 days' => '1 service · the world and configs stay downloadable for {retention_days} days',
    ],
    'panel' => [
        'nevyužité dny vracíme do deseti dnů' => 'nevyužité dny vracíme jako kredit',
        'refund unused days within ten days' => 'credit unused days back as account credit',
        // H2: the archive of a removed service is kept DeletionPolicy::retentionDays() days (60 by default), not 30
        'Svět a konfigurace vám necháme ke stažení 30 dní po zrušení.' => 'Svět a konfigurace vám necháme v archivu ke stažení {retention_days} dní po odstranění služby.',
        'We leave the world and the configs downloadable for 30 days after cancellation.' => 'We keep the world and the configs in the archive, downloadable for {retention_days} days after the service is removed.',
        'Svět a konfigurace necháme ke stažení 30 dní — a napíšeme' => 'Svět a konfigurace necháme v archivu ke stažení {retention_days} dní — a napíšeme',
        'We leave the world and the configs downloadable for 30 days — and tell you' => 'We keep the world and the configs in the archive, downloadable for {retention_days} days — and tell you',
    ],
    'svc-web' => [
        'kredit vracíme automaticky' => 'kredit připíšeme automaticky',
        'credit is refunded automatically' => 'credit is added automatically',
    ],
];
