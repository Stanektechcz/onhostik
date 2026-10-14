<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\LegalDocumentController;
use DateTimeInterface;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;

/**
 * The legal block of the public footer (C-R10a, J4 / TASK-0155; Comgate review rows 1, 3, 4, 5, 7, 16): links to the documents in
 * force — a draft (the `2026-10` texts before the lawyer, the complaints rules among them) is not listed until it is published —
 * and the consumer's out-of-court dispute body, the Czech Trade Inspection (ČOI). The EU ODR platform is not linked: it was shut
 * down on 20 July 2025 (Regulation (EU) 2024/3228; LEGAL_REVIEW_2026-10 L-13).
 */
final class PublicLegalLinks
{
    /** Footer order: what a reviewer looks for first. Key => [cs, en]. */
    private const DOCUMENTS = [
        'terms' => ['Obchodní podmínky', 'Terms of service'],
        'complaints' => ['Reklamační řád', 'Complaints procedure'],
        'withdrawal_waiver' => ['Odstoupení od smlouvy', 'Withdrawal from the contract'],
        'privacy' => ['Ochrana osobních údajů', 'Privacy policy'],
        'sla' => ['SLA', 'SLA'],
    ];

    public const COI_ADR = 'https://adr.coi.cz/cs';

    /**
     * What each document covers, written from its own headings (`resources/legal/<key>.md`), for the page "Podmínky, GDPR a
     * cookies" (L3 / M1). Key => [cs, en]; every key of LegalDocumentController::SLUGS has a line.
     */
    private const DESCRIPTIONS = [
        'terms' => ['Objednávka, ceny a platby, poskytování služeb, povinnosti zákazníka, zálohy a ukončení, odpovědnost, ustanovení pro spotřebitele.', 'Orders, prices and payments, provision of services, customer duties, backups and termination, liability, consumer provisions.'],
        'privacy' => ['Jaké údaje zpracováváme a proč, komu je předáváme, vaše práva, cookies a analytika, bezpečnost a změny.', 'What data we process and why, who receives it, your rights, cookies and analytics, security and changes.'],
        'dpa' => ['Předmět a doba zpracování, povinnosti zpracovatele, další zpracovatelé, odpovědnost.', 'Subject and duration of processing, processor duties, sub-processors, liability.'],
        'sla' => ['Rozsah, měsíční dostupnost podle třídy, incidenty a komunikace, kompenzace, zálohy a obnova, bezpečnost.', 'Scope, monthly availability by class, incidents and communication, compensation, backups and recovery, security.'],
        'withdrawal_waiver' => ['Poučení o právu spotřebitele na odstoupení, žádost o zahájení plnění před uplynutím lhůty a vzorový formulář.', 'The consumer withdrawal notice, the request to start before the period ends and the model withdrawal form.'],
        'auto_renew' => ['Co se obnovuje automaticky, úhrada obnovení, neuhrazené obnovení, vypnutí a zapnutí.', 'What renews automatically, paying a renewal, an unpaid renewal, switching it off and on.'],
        'registrar_terms' => ['Držitel domény, registrace a obnovení, převody, DNS, ukončení a expirace, odpovědnost.', 'Domain holder, registration and renewal, transfers, DNS, termination and expiry, liability.'],
        'complaints' => ['Rozsah, soulad služby se smlouvou, uplatnění reklamace, práva z vadného plnění, mimosoudní řešení sporů.', 'Scope, conformity of the service with the contract, filing a complaint, rights from defective performance, out-of-court disputes.'],
        'aup' => ['Zakázaný obsah a činnosti, sdílené prostředky a e-mail, vlastní software, dohled, oznámení protiprávního obsahu.', 'Prohibited content and activities, shared resources and e-mail, own software, oversight, notices of illegal content.'],
    ];

    /** @return list<array{0:string, 1:string}> [label, href] */
    public static function forLocale(string $locale): array
    {
        $en = $locale === 'en';
        $slugs = array_flip(LegalDocumentController::SLUGS);
        $links = [];
        foreach (self::DOCUMENTS as $key => [$cs, $english]) {
            $slug = $slugs[$key] ?? null;
            $document = ConsentDocument::current($key);
            if ($slug === null || $document === null || LegalDocuments::path($key, (string) $document->version) === null) {
                continue; // not in force (a draft or nothing at all): no link to a 404
            }
            $links[] = [$en ? $english : $cs, '/dokumenty/'.$slug];
        }
        $links[] = [$en ? 'All documents' : 'Všechny dokumenty', '/dokumenty'];
        $links[] = [$en ? 'Out-of-court disputes: ČOI' : 'Mimosoudní řešení sporů: ČOI', self::COI_ADR];

        return $links;
    }

    /**
     * The documents in force, in the order `/dokumenty` lists them (L3 / M1, K7-3): `[title, description, version, effective
     * from, '', address]` — the row shape of the prototype's document list (`d[0]`…`d[4]`) plus the address `d[5]` the row opens.
     * Only a published version with a text is listed (as LegalDocumentController::index), so the page never names a version
     * `/dokumenty` does not have, and a draft never appears.
     *
     * @return list<array{0:string, 1:string, 2:string, 3:string, 4:string, 5:string}>
     */
    public static function documents(string $locale): array
    {
        $en = $locale === 'en';
        $rows = [];
        foreach (LegalDocumentController::SLUGS as $slug => $key) {
            $document = ConsentDocument::current($key);
            if ($document === null || LegalDocuments::path($key, (string) $document->version) === null) {
                continue;
            }
            $titles = (array) $document->title;
            $from = $document->effective_from;
            $rows[] = [
                (string) ($titles[$en ? 'en' : 'cs'] ?? $titles['cs'] ?? $key),
                self::DESCRIPTIONS[$key][$en ? 1 : 0],
                (string) $document->version,
                $from instanceof DateTimeInterface ? $from->format($en ? 'j M Y' : 'j. n. Y') : '',
                '',
                '/dokumenty/'.$slug,
            ];
        }

        return $rows;
    }
}
