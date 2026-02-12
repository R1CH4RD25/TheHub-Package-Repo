<?php

namespace Hub\Package;

use Hub\Database;
use Hub\Package\PackageAuditLogger;

/**
 * Export Handler
 *
 * Handles data export from package queries with format support for
 * CSV, Excel (via PhpSpreadsheet), and JSON.
 *
 * Security:
 * - Exports go through QueryRouter enforcement pipeline (scope + masking)
 * - Rate limited separately from queries (export.* rate key)
 * - Audit logged with full context
 * - Field masking applied (masked fields export as "****")
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 */
class ExportHandler
{
    /** Maximum rows per export to prevent memory issues */
    private const MAX_EXPORT_ROWS = 10000;

    /** Supported export formats */
    private const SUPPORTED_FORMATS = ['csv', 'xlsx', 'json'];

    private PolicyEngine $policy;
    private ?QueryRouter $queryRouter;

    public function __construct(
        ?PolicyEngine $policy = null,
        ?QueryRouter $queryRouter = null
    ) {
        $this->policy = $policy ?? new PolicyEngine();
        $this->queryRouter = $queryRouter;
    }

    /**
     * Execute an export request
     *
     * @param \Hub\User $user Current user
     * @param string $packageId Package identifier
     * @param string $queryName Query to export data from
     * @param array $params Query parameters (filters, sorting)
     * @param string $format Export format: csv, xlsx, json
     * @param array $columns Column definitions from component config
     * @param array $pageConfig Page configuration for access check
     * @param string|null $filename Custom filename (without extension)
     *
     * @return array ['content' => string, 'filename' => string, 'mimeType' => string, 'rowCount' => int]
     *
     * @throws \RuntimeException on access denied, rate limit, or format error
     */
    public function export(
        \Hub\User $user,
        string $packageId,
        string $queryName,
        array $params,
        string $format,
        array $columns = [],
        array $pageConfig = [],
        ?string $filename = null
    ): array {
        // Validate format
        $format = strtolower($format);
        if (!in_array($format, self::SUPPORTED_FORMATS)) {
            throw new \RuntimeException("Unsupported export format: {$format}. Supported: " . implode(', ', self::SUPPORTED_FORMATS));
        }

        // Check page access
        if (!empty($pageConfig) && !$this->policy->canAccessPage($user, $pageConfig)) {
            throw new \RuntimeException('Access denied: Cannot export from this page');
        }

        // Check export permission
        $exportPermission = "{$packageId}.export";
        if (!$this->policy->check($user, $exportPermission)) {
            PackageAuditLogger::logAccessDenied($packageId, $queryName, $exportPermission, $user->id);
            throw new \RuntimeException('Access denied: Missing export permission');
        }

        // Rate limit check (exports use separate rate key)
        $rateLimitKey = "{$packageId}.export.{$queryName}";
        if ($this->policy->isRateLimited($user, $rateLimitKey)) {
            PackageAuditLogger::logRateLimited($packageId, $queryName, 0, 0, $user->id);
            throw new \RuntimeException('Export rate limit exceeded. Please wait before exporting again.');
        }

        // Fetch data through enforcement pipeline (scoping + masking applied)
        $params['pageSize'] = self::MAX_EXPORT_ROWS;
        $params['page'] = 1;
        $params['export'] = true; // Signal to query handler

        $result = $this->executeQuery($user, $packageId, $queryName, $params, $pageConfig);
        $rows = $result['data'] ?? [];
        $total = $result['meta']['total'] ?? count($rows);

        if (empty($rows)) {
            throw new \RuntimeException('No data to export');
        }

        // Resolve columns from data if not provided
        if (empty($columns)) {
            $columns = $this->inferColumnsFromData($rows[0]);
        }

        // Generate filename
        $safePackage = preg_replace('/[^a-zA-Z0-9-_]/', '', str_replace('.', '-', $packageId));
        $timestamp = date('Y-m-d_His');
        $filename = $filename ?? "{$safePackage}_{$queryName}_{$timestamp}";

        // Record rate hit
        $this->policy->recordRateHit($user, $rateLimitKey);

        // Generate export
        $startTime = microtime(true);
        $exportResult = match ($format) {
            'csv'  => $this->generateCsv($rows, $columns, $filename),
            'xlsx' => $this->generateXlsx($rows, $columns, $filename),
            'json' => $this->generateJson($rows, $columns, $filename),
        };

        $executionTime = microtime(true) - $startTime;

        // Audit log
        PackageAuditLogger::logQuery(
            $packageId,
            "export.{$queryName}",
            array_merge($params, ['format' => $format]),
            count($rows),
            round($executionTime * 1000, 2),
            $user->id
        );

        $exportResult['rowCount'] = count($rows);
        $exportResult['totalAvailable'] = $total;

        return $exportResult;
    }

    /**
     * Stream an export directly to the HTTP response
     *
     * @param array $exportResult Result from export()
     */
    public function stream(array $exportResult): void
    {
        $mimeType = $exportResult['mimeType'] ?? 'application/octet-stream';
        $filename = $exportResult['filename'] ?? 'export';
        $content = $exportResult['content'] ?? '';

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }

    /**
     * Generate CSV export
     */
    private function generateCsv(array $rows, array $columns, string $filename): array
    {
        $output = fopen('php://temp', 'r+');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Header row
        $headers = array_map(fn($col) => $col['label'] ?? $col['title'] ?? ucfirst(str_replace('_', ' ', $col['key'] ?? '')), $columns);
        fputcsv($output, $headers);

        // Data rows
        foreach ($rows as $row) {
            $csvRow = [];
            foreach ($columns as $col) {
                $key = $col['key'] ?? '';
                $value = $row[$key] ?? '';

                // Format value based on column type
                $value = $this->formatValueForExport($value, $col);
                $csvRow[] = $value;
            }
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return [
            'content' => $content,
            'filename' => $filename . '.csv',
            'mimeType' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * Generate Excel (XLSX) export
     *
     * Uses PhpSpreadsheet if available, falls back to CSV
     */
    private function generateXlsx(array $rows, array $columns, string $filename): array
    {
        // Check if PhpSpreadsheet is available
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Fallback to CSV with xlsx-compatible formatting
            error_log('[ExportHandler] PhpSpreadsheet not available, falling back to CSV');
            return $this->generateCsv($rows, $columns, $filename);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export');

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2D5F8A'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        // Write headers
        foreach ($columns as $colIdx => $col) {
            $cellCol = $colIdx + 1;
            $label = $col['label'] ?? $col['title'] ?? ucfirst(str_replace('_', ' ', $col['key'] ?? ''));
            $sheet->setCellValue([$cellCol, 1], $label);
        }

        // Apply header style
        $lastCol = count($columns);
        $sheet->getStyle([1, 1, $lastCol, 1])->applyFromArray($headerStyle);

        // Write data rows
        foreach ($rows as $rowIdx => $row) {
            $excelRow = $rowIdx + 2; // Row 1 is header
            foreach ($columns as $colIdx => $col) {
                $cellCol = $colIdx + 1;
                $key = $col['key'] ?? '';
                $value = $row[$key] ?? '';
                $value = $this->formatValueForExport($value, $col);

                $sheet->setCellValue([$cellCol, $excelRow], $value);

                // Format specific column types
                $type = $col['type'] ?? 'text';
                if (in_array($type, ['number', 'currency', 'integer'])) {
                    $sheet->getStyle([$cellCol, $excelRow])
                        ->getNumberFormat()
                        ->setFormatCode($type === 'currency' ? '$#,##0.00' : '#,##0');
                }
            }
        }

        // Auto-size columns
        foreach ($columns as $colIdx => $col) {
            $sheet->getColumnDimensionByColumn($colIdx + 1)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Auto-filter
        $sheet->setAutoFilter([1, 1, $lastCol, count($rows) + 1]);

        // Write to memory
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        return [
            'content' => $content,
            'filename' => $filename . '.xlsx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Generate JSON export
     */
    private function generateJson(array $rows, array $columns, string $filename): array
    {
        // Filter to only export configured columns
        $filteredRows = [];
        foreach ($rows as $row) {
            $filteredRow = [];
            foreach ($columns as $col) {
                $key = $col['key'] ?? '';
                if ($key && array_key_exists($key, $row)) {
                    $filteredRow[$key] = $row[$key];
                }
            }
            $filteredRows[] = $filteredRow;
        }

        $content = json_encode([
            'exportedAt' => date('c'),
            'rowCount' => count($filteredRows),
            'columns' => array_map(fn($c) => $c['key'] ?? '', $columns),
            'data' => $filteredRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return [
            'content' => $content,
            'filename' => $filename . '.json',
            'mimeType' => 'application/json',
        ];
    }

    /**
     * Format a value for export based on column type
     */
    private function formatValueForExport($value, array $column): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $type = $column['type'] ?? 'text';

        switch ($type) {
            case 'date':
                return is_string($value) && strtotime($value) ? date('Y-m-d', strtotime($value)) : (string)$value;

            case 'datetime':
                return is_string($value) && strtotime($value) ? date('Y-m-d H:i:s', strtotime($value)) : (string)$value;

            case 'boolean':
                return $value ? 'Yes' : 'No';

            case 'currency':
                return number_format((float)$value, 2, '.', '');

            case 'masked':
                return '****';

            case 'json':
                return is_array($value) ? json_encode($value) : (string)$value;

            case 'list':
                return is_array($value) ? implode('; ', $value) : (string)$value;

            default:
                return is_array($value) ? json_encode($value) : (string)$value;
        }
    }

    /**
     * Infer column definitions from a data row
     */
    private function inferColumnsFromData(array $row): array
    {
        $columns = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, '_')) continue; // Skip internal fields
            $columns[] = [
                'key' => $key,
                'label' => ucfirst(str_replace('_', ' ', $key)),
                'type' => $this->inferColumnType($value),
            ];
        }
        return $columns;
    }

    /**
     * Infer column type from a value
     */
    private function inferColumnType($value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'number';
        if (is_array($value)) return 'json';
        if (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return 'date';
            if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) return 'datetime';
            if (preg_match('/^\d+\.\d{2}$/', $value)) return 'currency';
        }
        return 'text';
    }

    /**
     * Execute query through enforcement pipeline or fallback
     */
    private function executeQuery(
        \Hub\User $user,
        string $packageId,
        string $queryName,
        array $params,
        array $pageConfig
    ): array {
        if ($this->queryRouter) {
            return $this->queryRouter->execute(
                $user,
                $packageId,
                $pageConfig,
                $queryName,
                $params,
                []
            );
        }

        // Fallback: empty result
        return ['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'perPage' => 50]];
    }
}
