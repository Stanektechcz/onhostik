<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Tax\VatReports;
use Onhost\Platform\Errors\DomainError;

/**
 * Drafts of the control statement (KH) and the EC sales list (SH) of a period, as CSV or as EPO XML, for the accountant (G2).
 * Read only: it writes nothing and submits nothing — the accountant checks the draft and files it.
 *
 *   php artisan onhost:vat:export kh --period=2026-10 --format=xml > kh-2026-10.xml
 *   php artisan onhost:vat:export sh --period=2026-Q4 --format=csv
 */
final class VatReportExport extends Command
{
    protected $signature = 'onhost:vat:export {report : kh (kontrolní hlášení) or sh (souhrnné hlášení)} {--period= : YYYY-MM or YYYY-Qn} {--format=csv : csv or xml}';

    protected $description = 'Draft the VAT control statement (KH) or EC sales list (SH) of a period as CSV/XML for the accountant (read only, nothing is filed)';

    public function handle(VatReports $reports): int
    {
        $report = strtolower((string) $this->argument('report'));
        $format = strtolower((string) $this->option('format'));
        if (! in_array($report, ['kh', 'sh'], true) || ! in_array($format, ['csv', 'xml'], true)) {
            $this->error('The report is kh or sh, the format csv or xml.');

            return self::FAILURE;
        }
        try {
            $data = $report === 'kh' ? $reports->kh((string) $this->option('period')) : $reports->sh((string) $this->option('period'));
        } catch (DomainError $e) {
            $this->error("{$e->error}: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->output->write(match ($report.'.'.$format) {
            'kh.csv' => VatReports::khCsv($data),
            'kh.xml' => VatReports::khXml($data),
            'sh.csv' => VatReports::shCsv($data),
            default => VatReports::shXml($data),
        });
        foreach ($data['warnings'] as $warning) {
            $this->getOutput()->getErrorStyle()->warning($warning);
        }

        return self::SUCCESS;
    }
}
