<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX writer built on PHP's bundled ZipArchive (audit D63).
 *
 * An .xlsx file is just a ZIP of XML parts, so a spreadsheet export does not
 * justify pulling in phpoffice/phpspreadsheet — a dependency that would need
 * to be installed, updated and security-tracked forever for one feature.
 *
 * Scope is deliberately small and honest: a single sheet, a bold header row,
 * strings and numbers. No formulas, styling beyond the header, or multiple
 * sheets. If those are ever needed, THAT is the moment to take the library.
 *
 * Strings are written inline (no shared-string table). That costs a little
 * file size on very repetitive data but keeps the format simple enough to be
 * obviously correct.
 */
final class XlsxWriter
{
    /**
     * Build an XLSX file and return its raw bytes.
     *
     * @param  list<string>            $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function build(array $headers, array $rows, string $sheetName = 'Export'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Could not allocate a temporary file for the XLSX export.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not open the XLSX archive for writing.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($headers, $rows));

        $zip->close();

        $contents = file_get_contents($path);
        @unlink($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read back the generated XLSX file.');
        }

        return $contents;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function sheet(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        if ($headers !== []) {
            $xml .= '<row r="1">';
            foreach ($headers as $index => $header) {
                // style s="1" is the bold header format defined in styles().
                $xml .= '<c r="' . self::cellRef($index, 1) . '" t="inlineStr" s="1"><is><t xml:space="preserve">'
                    . self::escape((string) $header) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $rowNumber = $headers === [] ? 1 : 2;

        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">';

            $columnIndex = 0;
            foreach ($row as $value) {
                $xml .= self::cell(self::cellRef($columnIndex, $rowNumber), $value);
                $columnIndex++;
            }

            $xml .= '</row>';
            $rowNumber++;
        }

        return $xml . '</sheetData></worksheet>';
    }

    private static function cell(string $ref, string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"/>';
        }

        // Numbers go in as numbers so Excel can sum them; anything else, and
        // anything that would lose meaning (leading zeros, long digit strings
        // like invoice numbers or VAT ids), stays text.
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
            . self::escape($value) . '</t></is></c>';
    }

    /** 0 → A1, 26 → AA1 … */
    private static function cellRef(int $columnIndex, int $rowNumber): string
    {
        $letters = '';
        $index   = $columnIndex;

        do {
            $letters = chr(65 + ($index % 26)) . $letters;
            $index   = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $letters . $rowNumber;
    }

    private static function escape(string $value): string
    {
        // Control characters are illegal in XML 1.0 and make Excel refuse the
        // whole file, so strip them rather than emit an unopenable export.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        // Excel rejects sheet names over 31 chars or containing : \ / ? * [ ]
        $safeName = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/u', '', $sheetName) ?: 'Export', 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::escape($safeName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** Two formats: 0 = default, 1 = bold (the header row). */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }
}
