<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * TASK-0031 WP B (D31.2, D31.4): one vocabulary and one place that turns the recorded check into the tax engine's input.
 * Every caller used to pass the stored column as the verdict (`'vat_status' => $organization->vat_status`), some in their own
 * words (`payer`), and one read a rate key the rule set does not have. These patterns must not come back; VatStanding is the
 * only reader that interprets the column. The domains do not call HTTP either (the VIES client lived in domains/Tax).
 */

/** @return array<string, string> relative path => source */
function vatReadersSources(): array
{
    $root = dirname(__DIR__, 3);
    // only the interpreter of the column itself; the handlers, commands, doctor rows and presenters that write or query it
    // match none of the patterns below, so they are not excused either — a new raw reader anywhere fails here
    $allowed = ['domains/Tax/VatStanding.php'];
    $out = [];
    foreach ((new Finder)->files()->in([$root.'/app', $root.'/domains'])->name('*.php') as $file) {
        $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));
        if (! in_array($relative, $allowed, true)) {
            $out[$relative] = (string) file_get_contents($file->getRealPath());
        }
    }

    return $out;
}

it('builds every tax customer through VatStanding, never from the stored column', function () {
    $offenders = [];
    foreach (vatReadersSources() as $path => $source) {
        $source = $path === 'domains/Invoicing/InvoiceService.php' ? (string) preg_replace('/function buyerSnapshot\(.*?\n    }\n/s', '', $source) : $source;
        if (preg_match("/'vat_status'\\s*=>\\s*\\$\\w+(\\?)?->vat_status/", $source) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('knows no VAT status outside the one vocabulary and no legacy rate key', function () {
    $offenders = [];
    foreach (vatReadersSources() as $path => $source) {
        if (preg_match("/vat_status\\s*===?\\s*'payer'/", $source) === 1) {
            $offenders[] = $path.' (payer)';
        }
    }
    $partners = (string) file_get_contents(dirname(__DIR__, 3).'/domains/Partners/PartnerService.php');

    expect($offenders)->toBe([])->and(str_contains($partners, "'rates.'"))->toBeFalse();
});

it('makes no HTTP call from the tax domain', function () {
    $offenders = [];
    // CnbRates (the ČNB exchange-rate list) predates this task and is a separate finding; the VAT check must not join it
    foreach ((new Finder)->files()->in(dirname(__DIR__, 3).'/domains/Tax')->name('*.php')->notName('CnbRates.php') as $file) {
        $source = (string) file_get_contents($file->getRealPath());
        if (str_contains($source, 'Illuminate\\Http\\Client') || str_contains($source, 'Facades\\Http')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});
