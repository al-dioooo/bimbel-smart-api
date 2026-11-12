<?php

namespace App\Support;

use App\Exports\TabelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report downloads. Every report table goes through table(), so the PDF
 * and the Excel file of a report always hold the same rows.
 */
class Laporan
{
    public const FORMATS = ['pdf', 'xlsx'];

    /**
     * @param  array<int, string>  $keterangan
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows  plain values; numbers stay numbers in Excel
     * @param  array<int, mixed>|null  $total
     * @param  array<int, int>  $rupiah  column indexes shown as Rupiah in the PDF
     */
    public static function table(string $format, string $filename, string $judul, array $keterangan, array $headings, array $rows, ?array $total = null, array $rupiah = []): Response
    {
        $keterangan[] = 'Dicetak '.Carbon::now()->locale('id')->translatedFormat('j F Y H:i');

        if ($format === 'xlsx') {
            return Excel::download(new TabelExport($judul, $keterangan, $headings, $rows, $total), "{$filename}.xlsx");
        }

        $tampil = fn (array $row) => array_map(
            fn ($value, $i) => in_array($i, $rupiah, true) && is_numeric($value) ? self::rupiah($value) : $value,
            $row,
            array_keys($row),
        );

        return self::pdf('pdf.tabel', [
            'judul' => $judul,
            'keterangan' => $keterangan,
            'headings' => $headings,
            'rows' => array_map($tampil, $rows),
            'total' => $total ? $tampil($total) : null,
            'numeric' => self::numericColumns($rows),
        ], count($headings) > 6 ? 'landscape' : 'portrait')->download("{$filename}.pdf");
    }

    /** A4 PDF from a Blade view; only the glyphs used are embedded. */
    public static function pdf(string $view, array $data, string $orientation = 'portrait'): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->setOption(['isFontSubsettingEnabled' => true]);
    }

    public static function rupiah(float|int|string $value): string
    {
        $value = (float) $value;

        return ($value < 0 ? '-' : '').'Rp'.number_format(abs($value), 0, ',', '.');
    }

    /** Right-align the columns whose values are all numbers. */
    private static function numericColumns(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return array_keys(array_filter(
            array_keys($rows[0]),
            fn ($i) => collect($rows)->every(fn ($row) => is_numeric($row[$i] ?? null)),
        ));
    }
}
