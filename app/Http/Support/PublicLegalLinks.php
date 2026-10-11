<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\LegalDocumentController;
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
}
