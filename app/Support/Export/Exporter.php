<?php

namespace App\Support\Export;

use Mpdf\Mpdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tabular exports: CSV (UTF-8 BOM for Excel/Arabic), XLSX, PDF (mPDF with
 * Arabic shaping & RTL) and a print-friendly HTML page.
 */
class Exporter
{
    public static function download(string $format, string $title, array $headings, iterable $rows, array $meta = [])
    {
        $file = \Illuminate\Support\Str::slug($title ?: 'export').'-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => self::csv($file, $headings, $rows),
            'xlsx' => self::xlsx($file, $headings, $rows),
            'pdf' => self::pdf($file, $title, $headings, $rows, $meta),
            default => response()->view('exports.print', ['title' => $title, 'headings' => $headings, 'rows' => $rows, 'meta' => $meta]),
        };
    }

    public static function csv(string $file, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => self::safeCell($v), array_values($r)));
            }
            fclose($out);
        }, $file.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function xlsx(string $file, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            $writer = new XlsxWriter;
            $writer->openToFile($tmp);
            $writer->addRow(Row::fromValues($headings));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => is_numeric($v) && ! preg_match('/^0\d/', (string) $v) ? $v + 0 : self::safeCell($v), array_values($r))));
            }
            $writer->close();
            readfile($tmp);
            @unlink($tmp);
        }, $file.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public static function pdf(string $file, string $title, array $headings, iterable $rows, array $meta = [])
    {
        $html = view('exports.print', ['title' => $title, 'headings' => $headings, 'rows' => $rows, 'meta' => $meta, 'pdf' => true])->render();

        return response(self::pdfFromHtml($html, count($headings) > 7 ? 'L' : 'P'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$file.'.pdf"',
        ]);
    }

    public static function pdfFromHtml(string $html, string $orientation = 'P', string $format = 'A4'): string
    {
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $format === '80mm' ? [80, 297] : $format.($orientation === 'L' ? '-L' : ''),
            'default_font' => 'dejavusans', 'tempDir' => $dir,
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'autoArabic' => true,
            'margin_left' => $format === '80mm' ? 3 : 10, 'margin_right' => $format === '80mm' ? 3 : 10,
            'margin_top' => $format === '80mm' ? 3 : 12, 'margin_bottom' => $format === '80mm' ? 3 : 12,
        ]);
        $mpdf->SetDirectionality(app()->getLocale() === 'ar' ? 'rtl' : 'ltr');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /** Neutralise spreadsheet formula injection. */
    protected static function safeCell(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && in_array($v[0], ['=', '+', '@', "\t", "\r"], true) ? "'".$v : $v;
    }
}
