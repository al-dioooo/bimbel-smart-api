<?php

namespace App\Support;

use App\Exports\TabelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report downloads. Every report table goes through table(), so the PDF
 * and the Excel file of a report always hold the same rows.
 */
class Laporan
{
    public const FORMATS = ['pdf', 'xlsx'];

    /** Most rows an export renders; dompdf is far slower than the Excel writer. */
    public const MAX_ROWS = ['pdf' => 1000, 'xlsx' => 20000];

    /**
     * @param  array<int, string>  $keterangan
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows  plain values; numbers stay numbers in Excel
     * @param  array<int, mixed>|null  $total
     * @param  array<int, int>  $rupiah  column indexes shown as Rupiah in the PDF
     */
    public static function table(string $format, string $filename, string $judul, array $keterangan, array $headings, array $rows, ?array $total = null, array $rupiah = []): Response
    {
        self::ensureWithinLimit($format, count($rows));

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

    /**
     * The row limit to put on an export query: one more than MAX_ROWS, so
     * table() can tell a full export from one that was cut off.
     */
    public static function limit(string $format): int
    {
        return (self::MAX_ROWS[$format] ?? self::MAX_ROWS['pdf']) + 1;
    }

    /**
     * A4 PDF from a Blade view; only the glyphs used are embedded. Remote
     * resources and embedded PHP are off and local files are read only from
     * the views, whatever the package defaults are.
     */
    public static function pdf(string $view, array $data, string $orientation = 'portrait'): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->setOption([
                'isFontSubsettingEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'chroot' => resource_path('views'),
            ]);
    }

    private static function ensureWithinLimit(string $format, int $count): void
    {
        $max = self::MAX_ROWS[$format] ?? self::MAX_ROWS['pdf'];
        if ($count > $max) {
            throw ValidationException::withMessages([
                'format' => 'Data terlalu banyak untuk diunduh sebagai '.strtoupper($format)." (lebih dari {$max} baris). Persempit periode atau filter"
                    .($format === 'pdf' ? ', atau unduh dalam format xlsx.' : '.'),
            ]);
        }
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
