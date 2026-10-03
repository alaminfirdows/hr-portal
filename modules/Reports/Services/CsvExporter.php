<?php

namespace Modules\Reports\Services;

use Modules\Reports\Contracts\Report;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporter
{
    /**
     * @param  array<int, array<string, scalar|null>>  $rows
     */
    public function stream(Report $report, array $rows): StreamedResponse
    {
        $filename = str_replace('.', '-', $report->key()).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report, $rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads unicode correctly
            $columns = $report->columns();
            fputcsv($out, array_map(fn (array $c) => $c['label'], $columns), ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (array $c) => $this->cell($row[$c['key']] ?? null), $columns), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formula injection in text cells.
     */
    private function cell(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return is_int($value) || is_float($value) ? $value : (string) $value;
    }
}
