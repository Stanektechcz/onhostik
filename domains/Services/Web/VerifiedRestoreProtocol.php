<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Carbon;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;

/**
 * The protocol of a verified restore (W2-4) as the customer reads it: a JSON summary for the API and a PDF made the way invoices are
 * (dompdf, DejaVu Sans — InvoicePdfRenderer). Only facts with timestamps; each step pass / fail / not verified, the limits said out
 * loud ("this application, not the whole data centre"), and never a word that promises: `FORBIDDEN` is checked on every render.
 */
final class VerifiedRestoreProtocol
{
    /** Words a protocol never uses (a fact is a fact; "safe" and "guaranteed" are promises). */
    public const FORBIDDEN = '/bezpečn|garant|guarante|\bsafe\b/iu';

    public const STEP_LABELS = [
        'backup_selected' => 'Výběr poslední úplné zálohy',
        'target_prepared' => 'Založení oddělené zkušební kopie',
        'files_restored' => 'Obnova souborů do kopie',
        'database_imported' => 'Import databází do kopie',
        'side_effects_suppressed' => 'Potlačení e-mailů, plateb, webhooků a cronu',
        'http_200' => 'Odpověď webu kopie (HTTP 200)',
        'login' => 'Přihlášení testovacím účtem',
        'cleanup' => 'Odstranění kopie a ověření úklidu',
    ];

    public const STATUS_TEXT = [VerifiedRestoreRun::PASS => 'prošlo', VerifiedRestoreRun::FAIL => 'neprošlo', VerifiedRestoreRun::NOT_VERIFIED => 'neověřeno'];

    public function __construct(private readonly ViewFactory $views) {}

    /** @return array<string, mixed> what the API lists */
    public static function summary(VerifiedRestoreRun $run): array
    {
        return [
            'id' => $run->id, 'service_id' => $run->service_id, 'backup_id' => $run->backup_id, 'trigger' => $run->trigger, 'state' => $run->state, 'outcome' => $run->outcome,
            'backup_taken_at' => $run->backup_taken_at?->toIso8601String(), 'started_at' => $run->started_at?->toIso8601String(),
            'checks_done_at' => $run->checks_done_at?->toIso8601String(), 'finished_at' => $run->finished_at?->toIso8601String(),
            'rpo_seconds' => $run->rpo_seconds, 'rto_seconds' => $run->rto_seconds,
            'steps' => array_map(fn (array $step) => array_intersect_key($step, array_flip(['key', 'status', 'at', 'detail'])), self::orderedSteps($run)),
            'suppression' => ['applied' => array_values((array) data_get($run->suppression, 'applied', [])), 'not_applied' => array_values((array) data_get($run->suppression, 'not_applied', []))],
            'skipped_sets' => array_values((array) $run->skipped_sets),
            'pdf' => $run->state === VerifiedRestoreRun::FINISHED ? "/v1/services/{$run->service_id}/verified-restores/{$run->id}/pdf" : null,
        ];
    }

    public function render(VerifiedRestoreRun $run, Service $service): string
    {
        return Pdf::loadHTML($this->html($run, $service))->setPaper('a4')->output();
    }

    public function html(VerifiedRestoreRun $run, Service $service): string
    {
        $html = $this->views->make('restore.verified-protocol', [
            'run' => $run,
            'serviceLabel' => (string) ($service->label ?: ($service->hostname ?: $service->name)),
            'outcomeText' => match ($run->outcome) {
                'passed' => 'záloha byla obnovena do kopie a kopie odpověděla HTTP 200',
                'failed' => 'obnova neprošla — viz kroky se stavem „neprošlo“',
                'not_verified' => 'záloha byla obnovena do kopie, odpověď webu nebyla ověřena',
                default => 'běží',
            },
            'steps' => array_map(fn (array $step) => [
                'label' => self::STEP_LABELS[$step['key']] ?? (string) $step['key'], 'status_text' => self::STATUS_TEXT[$step['status']] ?? (string) $step['status'],
                'at' => self::time($step['at'] ?? null), 'detail' => (string) ($step['detail'] ?? ''),
            ], self::orderedSteps($run)),
            'applied' => array_values(array_map('strval', (array) data_get($run->suppression, 'applied', []))),
            'notApplied' => array_values(array_map('strval', (array) data_get($run->suppression, 'not_applied', []))),
            'skipped' => array_values((array) $run->skipped_sets),
            'limits' => self::limits(),
            'at' => fn (?Carbon $at) => $at === null ? '—' : $at->copy()->utc()->format('Y-m-d H:i:s'),
            'seconds' => fn (?int $s) => $s === null ? 'neměřeno' : self::duration($s),
        ])->render();

        // a protocol states facts; a word that promises is removed rather than printed (the template and the step texts are ours,
        // but a panel's error message ends up in "Zjištění" too)
        return (string) preg_replace(self::FORBIDDEN, '…', $html);
    }

    /** @return list<string> what a verified restore does not say — printed on every protocol */
    public static function limits(): array
    {
        return [
            'Ověřena byla tato aplikace (web této služby) obnovená z jedné konkrétní zálohy do oddělené zkušební kopie na stejném uzlu — ne celé datacentrum, ne jiné služby a ne zálohy pořízené později.',
            'Kontroly zjišťují jen to, co je v krocích uvedeno: import databází (porovnání tabulek), odpověď domovské stránky kopie a přihlášení, jen pokud je nastaven testovací účet. Funkce aplikace nad rámec toho ověřeny nebyly.',
            'RPO je stáří obnovené zálohy v okamžiku obnovy; RTO je doba od začátku obnovy po dokončení kontrol v této kopii. Obnova produkčního webu může trvat jinak dlouho.',
            'Krok se stavem „neověřeno“ neproběhl nebo jej nebylo možné provést; neznamená, že by prošel.',
        ];
    }

    /** @return list<array<string, mixed>> the run's steps in protocol order */
    private static function orderedSteps(VerifiedRestoreRun $run): array
    {
        $out = [];
        foreach (VerifiedRestore::STEPS as $key) {
            $step = $run->step($key);
            if ($step !== null) {
                $out[] = $step;
            }
        }

        return $out;
    }

    private static function time(mixed $iso): string
    {
        return is_string($iso) && $iso !== '' ? Carbon::parse($iso)->utc()->format('Y-m-d H:i:s') : '—';
    }

    private static function duration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return ($h > 0 ? $h.' h ' : '').($h > 0 || $m > 0 ? $m.' min ' : '').$s.' s ('.$seconds.' s)';
    }
}
