<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Illuminate\Support\Collection;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisoryMatch;

/**
 * The draft blog post of an announced case (SN-6), written from the case's structured data — the software, the CVE, the severity,
 * the fixed release, what the platform did — never from a feed's text and never naming a customer, a site or how many there were.
 * It claims only what the case shows: "updated" only when an update ran, nothing like "we are 100 % safe". Staff edit and publish it.
 */
final class BlogDraft
{
    /**
     * @param  Collection<int, AdvisoryMatch>  $matches
     * @return array<string,mixed> ContentCommand `post.create` input
     */
    public static function for(Advisory $advisory, Collection $matches): array
    {
        $software = $matches->map(fn (AdvisoryMatch $m) => AdvisoryCases::software($m))->unique()->values();
        $fixed = $matches->pluck('fixed_in')->filter()->unique()->values();
        $name = $software->isEmpty() ? $advisory->key : $software->take(3)->implode(', ');
        $severity = ['critical' => 'kritická', 'high' => 'vysoká', 'medium' => 'střední', 'low' => 'nízká'][$advisory->severity] ?? 'zatím neurčená';
        $updated = $matches->contains(fn (AdvisoryMatch $m) => $m->state === AdvisoryMatch::FIXED);
        $told = $matches->contains(fn (AdvisoryMatch $m) => $m->notified_at !== null);
        $cve = str_starts_with($advisory->key, 'CVE-') ? $advisory->key : null;

        $what = 'V softwaru '.$name.' byla zveřejněna bezpečnostní chyba'.($cve ? ' '.$cve : '').'. Závažnost: '.$severity.'.'
            .($advisory->kev ? ' Podle amerického úřadu CISA se chyba už aktivně zneužívá.' : '');
        $who = 'Týká se každého, kdo provozuje '.$name.($fixed->isEmpty() ? ' v zranitelné verzi; opravená verze zatím nevyšla.' : ' ve verzi starší než '.$fixed->implode(' / ').'.');
        $did = $updated
            ? 'U webů, které u nás běží a které jsme mohli bezpečně aktualizovat, jsme opravu nasadili sami a ověřili, že weby fungují. Zákazníky, u kterých to bez jejich zásahu nešlo, jsme upozornili přímo v panelu a e-mailem.'
            : ($told ? 'Zákazníky, kterých se chyba týká, jsme upozornili přímo v panelu a e-mailem: co je zranitelné, kde a co mají udělat.' : 'Chybu sledujeme a dotčené zákazníky upozorníme.');
        $advice = $fixed->isEmpty()
            ? 'Dokud oprava nevyjde, zvažte dočasné vypnutí dotčeného doplňku a sledujte oznámení jeho autora.'
            : 'Aktualizujte '.$name.' alespoň na verzi '.$fixed->implode(' / ').'. Před aktualizací si web zazálohujte a po ní zkontrolujte, že funguje.';

        return [
            'title' => ['cs' => mb_substr('Bezpečnostní upozornění: '.$name.($cve ? ' ('.$cve.')' : ''), 0, 250)],
            'category' => ['cs' => 'Bezpečnost'],
            'excerpt' => ['cs' => mb_substr($what, 0, 500)],
            'body' => ['cs' => [
                ['Co se stalo', $what],
                ['Koho se to týká', $who],
                ['Co jsme udělali', $did],
                ['Co doporučujeme', $advice],
                ['Zdroje', implode("\n", array_slice($advisory->present()['references'], 0, 5))],
            ]],
            'author' => 'Tým ONhost',
            'read_minutes' => 3,
        ];
    }
}
