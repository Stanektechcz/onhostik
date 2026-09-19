<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

/**
 * What makes a downloaded archive usable without us (Brain card H28). The parts are already plain formats — a tar.gz
 * of the files, SQL dumps, JSON metadata — but a zip of files with no word about them is only portable for whoever
 * packed it. Two text files travel with every download: `SHA256SUMS` in the format `sha256sum -c` reads, so the
 * customer can prove the download is whole with a tool every system has, and `README.txt`, which says what each file
 * is, how to put it back on any ordinary server, and — just as important — what is NOT in the archive.
 */
final class PortableArchive
{
    /** Packages built before these files existed are rebuilt on the next download. */
    public const FORMAT = 2;

    /** @param array<string,string> $sums file name → sha256 of exactly the bytes that went into the zip */
    public static function sums(array $sums): string
    {
        ksort($sums);
        $out = '';
        foreach ($sums as $name => $hash) {
            $out .= $hash.'  '.$name."\n";
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $manifest  the set's manifest.json (service_id, family, created_at, gaps …)
     * @param  array<string,int>  $files  file name → bytes, as packed
     */
    public static function readme(array $manifest, array $files): string
    {
        ksort($files);
        $lines = [
            'ONhost — archiv zrušené služby / archive of a cancelled service',
            '================================================================',
            '',
            'Služba / service:   '.(string) ($manifest['service_id'] ?? '?').'  ('.(string) ($manifest['family'] ?? '?').')',
            'Vytvořeno / created: '.(string) ($manifest['created_at'] ?? '?'),
            '',
            'Tento archiv obsahuje jen běžné formáty. K obnově nepotřebujete ONhost ani žádný náš nástroj.',
            'This archive holds ordinary formats only. You need neither ONhost nor any tool of ours to restore it.',
            '',
            '1. Ověření úplnosti / verify the download',
            '   sha256sum -c SHA256SUMS          (Linux, macOS: shasum -a 256 -c SHA256SUMS)',
            '   Windows PowerShell: Get-FileHash <soubor> -Algorithm SHA256   a porovnat se SHA256SUMS',
            '',
            '2. Obsah / contents',
        ];
        foreach ($files as $name => $bytes) {
            $lines[] = sprintf('   %-34s %12s B   %s', $name, number_format($bytes, 0, '', ' '), self::describe($name));
        }
        $lines[] = '';
        $lines[] = '3. Obnova / restore';
        $seen = [];
        foreach (array_keys($files) as $name) {
            foreach (self::howTo($name) as $key => $text) {
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $lines[] = '   '.$text;
                }
            }
        }
        $lines[] = '';
        $lines[] = '4. Co v archivu NENÍ / what is NOT in this archive';
        $gaps = array_values(array_filter(array_map('strval', (array) ($manifest['gaps'] ?? []))));
        foreach ($gaps === [] ? ['nic nechybí — všechny části služby se podařilo uložit / nothing is missing'] : $gaps as $gap) {
            $lines[] = '   - '.$gap;
        }
        $lines[] = '   - hesla a klíče: databázové uživatele, FTP a poštovní hesla si na novém serveru nastavte znovu';
        $lines[] = '     passwords and keys: create database users, FTP and mailbox passwords anew on the new server';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function describe(string $name): string
    {
        return match (true) {
            $name === 'service.json' => 'popis služby: tarif, limity, domény / the service: plan, limits, domains',
            $name === 'manifest.json' => 'soupis částí a kontrolních součtů / parts and checksums',
            str_starts_with($name, 'site-files') => 'soubory webu / the site\'s files',
            str_starts_with($name, 'database-') => 'výpis databáze (SQL) / database dump (SQL)',
            str_starts_with($name, 'game-backup') => 'svět a nastavení herního serveru / game world and configuration',
            str_starts_with($name, 'mail') => 'pošta: seznam schránek, aliasů a DKIM — bez obsahu zpráv / mailboxes, aliases, DKIM — no message content',
            str_starts_with($name, 'dns') => 'DNS záznamy / DNS records',
            default => 'data služby / service data',
        };
    }

    /** @return array<string,string> instruction key → text (one line per kind of part, however many parts there are) */
    private static function howTo(string $name): array
    {
        return match (true) {
            str_ends_with($name, '.tar.gz') && str_starts_with($name, 'site-files') => ['site' => 'Web:       mkdir web && tar -xzf site-files.tar.gz -C web     → obsah nahrajte do kořene webu / upload into the web root'],
            str_ends_with($name, '.zip') && str_starts_with($name, 'site-files') => ['site' => 'Web:       unzip site-files.zip -d web                          → obsah nahrajte do kořene webu / upload into the web root'],
            str_starts_with($name, 'database-') => ['db' => 'Databáze:  mysql -u UZIVATEL -p NAZEV_DB < database-<jméno>.sql    (MariaDB/MySQL; vytvořte nejdřív prázdnou databázi / create an empty database first)'],
            str_starts_with($name, 'game-backup') => ['game' => 'Hra:       tar -xzf game-backup.tar.gz -C /cesta/k/serveru     → stejná verze hry a modů / same game and mod versions'],
            str_starts_with($name, 'mail') => ['mail' => 'Pošta:     mail-domain.json je seznam schránek a aliasů k založení u nového poskytovatele; zprávy v něm nejsou (viz bod 4) / a list to re-create, messages are not included (see 4)'],
            str_starts_with($name, 'dns') => ['dns' => 'DNS:       záznamy přepište do správy DNS u nového poskytovatele / re-create the records at the new DNS provider'],
            default => [],
        };
    }
}
