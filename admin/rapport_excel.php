<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Charger le HTML
ob_start();
include 'rapport.php';
$html = ob_get_clean();

// Charger dans DOMDocument
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$tables = $xpath->query('//table');

if ($tables->length === 0) {
    die("Aucun tableau trouvé.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$currentRow = 1;

foreach ($tables as $table) {
    // Récupérer le titre
    $title = '';
    $prev = $table->previousSibling;
    while ($prev && !in_array($prev->nodeName, ['h1', 'h2', 'h3'])) {
        $prev = $prev->previousSibling;
    }
    if ($prev) {
        $title = trim($prev->textContent);
    }

    // Afficher le titre
    if ($title !== '') {
        $sheet->setCellValue("A$currentRow", $title);
        $sheet->mergeCells("A$currentRow:E$currentRow");
        $sheet->getStyle("A$currentRow")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A$currentRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow += 2;
    }

    $rows = $table->getElementsByTagName('tr');
    $firstDataRow = $currentRow;
    $maxCol = 0;

    foreach ($rows as $rowIndex => $row) {
        $isHeader = $row->getElementsByTagName('th')->length > 0;
        $cells = $isHeader ? $row->getElementsByTagName('th') : $row->getElementsByTagName('td');

        $colIndex = 1;
        foreach ($cells as $cell) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $cellValue = trim($cell->textContent);
            $cellAddress = "{$colLetter}{$currentRow}";
            $sheet->setCellValue($cellAddress, $cellValue);

            // Centrer tout le texte
            $sheet->getStyle($cellAddress)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Style pour entête
            if ($isHeader) {
                $sheet->getStyle($cellAddress)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($cellAddress)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('007BFF');
            }

            $colIndex++;
        }

        $maxCol = max($maxCol, $colIndex - 1);
        $currentRow++;
    }

    // Appliquer bordures au tableau
    $endColLetter = Coordinate::stringFromColumnIndex($maxCol);
    $range = "A$firstDataRow:{$endColLetter}" . ($currentRow - 1);
    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Laisser un espace entre les tableaux
    $currentRow += 2;
}

// Exporter le fichier
$filename = "etat_stock_complet.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
