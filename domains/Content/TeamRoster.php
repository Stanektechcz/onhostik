<?php

declare(strict_types=1);

namespace Onhost\Domain\Content;

/**
 * The people on the public team page (I-R19, owner 2026-10-08): Filip, Roman, Adrian and Markéta, in the owner's order. Adrian is the
 * operator (the sole trader Adrian Staněk, IČO 08094616, I-R11), so he alone carries a role — "Zakladatel"; the others' roles were not
 * given and are not invented ("Tým ONhost"). No surnames beyond what the owner gave, no tenures, no bios, no photos: an initial stands
 * in for the avatar. A change of the team is a change of this list.
 */
final class TeamRoster
{
    /** @var list<array{name:string, role:array{cs:string, en:string}}> */
    public const PEOPLE = [
        ['name' => 'Filip', 'role' => ['cs' => 'Tým ONhost', 'en' => 'ONhost team']],
        ['name' => 'Roman', 'role' => ['cs' => 'Tým ONhost', 'en' => 'ONhost team']],
        ['name' => 'Adrian', 'role' => ['cs' => 'Zakladatel', 'en' => 'Founder']],
        ['name' => 'Markéta', 'role' => ['cs' => 'Tým ONhost', 'en' => 'ONhost team']],
    ];

    /** @return list<array{ini:string, name:string, role:string}> */
    public static function forLocale(string $locale): array
    {
        return array_map(fn (array $p) => [
            'ini' => mb_strtoupper(mb_substr($p['name'], 0, 1)), 'name' => $p['name'], 'role' => $p['role'][$locale === 'en' ? 'en' : 'cs'],
        ], self::PEOPLE);
    }
}
