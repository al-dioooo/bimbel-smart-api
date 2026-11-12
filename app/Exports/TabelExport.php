<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One report table as a worksheet: a title block, the headings, the rows
 * and an optional total row. Shared by every report export.
 *
 * Text is always written as text: a value such as a mentor's own name
 * starting with "=" must never become a formula in the admin's workbook.
 * Numbers stay numbers.
 */
class TabelExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $keterangan  lines under the title (filters, print date)
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, mixed>|null  $total
     */
    public function __construct(
        private string $judul,
        private array $keterangan,
        private array $headings,
        private array $rows,
        private ?array $total = null,
    ) {
        // false = do not turn numbers into text; formulas stay text (default).
        $this->setNumericConversion(false);
    }

    public function title(): string
    {
        return mb_substr($this->judul, 0, 31);
    }

    public function headings(): array
    {
        return [
            [$this->judul],
            ...array_map(fn ($line) => [$line], $this->keterangan),
            [],
            $this->headings,
        ];
    }

    public function array(): array
    {
        return $this->total ? [...$this->rows, $this->total] : $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $headingRow = count($this->keterangan) + 3;
        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $headingRow => ['font' => ['bold' => true]],
        ];

        if ($this->total) {
            $styles[$headingRow + count($this->rows) + 1] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
