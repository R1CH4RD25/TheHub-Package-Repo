<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\FuelRecord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

Auth::requireRole(['super_admin', 'admin', 'manager']);

$fuelRecord = new FuelRecord();
$format = $_GET['format'] ?? 'csv';

// Get filtered records
$filters = [
    'start_date' => $_GET['start_date'] ?? null,
    'end_date' => $_GET['end_date'] ?? null,
    'vehicle_id' => $_GET['vehicle_id'] ?? null,
    'purpose_code' => $_GET['purpose_code'] ?? null,
];

$records = $fuelRecord->getAll(array_filter($filters));

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Headers
$headers = ['Date', 'Vehicle', 'Vehicle Number', 'User', 'Purpose Code', 'Purpose', 'Mileage', 'Fuel (gal)', 'Notes'];
$sheet->fromArray($headers, null, 'A1');

// Style header row
$headerStyle = $sheet->getStyle('A1:I1');
$headerStyle->getFont()->setBold(true);
$headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$headerStyle->getFill()->getStartColor()->setARGB('FFE0E0E0');

// Data rows
$row = 2;
foreach ($records as $record) {
    $sheet->fromArray([
        $record['entry_date'],
        $record['vehicle_name'],
        $record['vehicle_number'] ?? '',
        $record['user_name'],
        $record['purpose_code'],
        $record['purpose_description'],
        $record['mileage'],
        $record['fuel_amount'],
        $record['notes'] ?? ''
    ], null, "A{$row}");
    $row++;
}

// Auto-size columns
foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Generate filename
$filename = 'fuel_records_' . date('Y-m-d');
if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
    $filename .= '_' . ($filters['start_date'] ?? 'all') . '_to_' . ($filters['end_date'] ?? 'all');
}

// Output file
if ($format === 'xlsx') {
    $writer = new Xlsx($spreadsheet);
    $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $filename .= '.xlsx';
} else {
    $writer = new Csv($spreadsheet);
    $contentType = 'text/csv';
    $filename .= '.csv';
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
