<?php
require '../config.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$conn = connectDB();

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
// Fonctions
function getValue($conn, $query) {
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    return $row ? $row['total'] : 0;
}
function getTopProducts($conn) {
    return $conn->query("SELECT p.name, SUM(oi.quantity) AS total_qte FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY oi.product_id ORDER BY total_qte DESC LIMIT 10");
}
function getStockStatus($conn) {
    return $conn->query("SELECT name, quantity, min_quantity FROM products ORDER BY name ASC");
}
function getClientOrders($conn) {
    return $conn->query("SELECT * FROM orders WHERE sender_type='user' AND receiver_type='admin'");
}
function getSupplierOrders($conn) {
    return $conn->query("SELECT * FROM orders WHERE sender_type='admin' AND receiver_type='supplier'");
}

// Données
$revenus = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE sender_type='user'");
$depenses = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE receiver_type='supplier'");
$benefice = $revenus - $depenses;

$clientOrders = getClientOrders($conn);
$supplierOrders = getSupplierOrders($conn);
$topProducts = getTopProducts($conn);
$stockStatus = getStockStatus($conn);

// Création Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$row = 1;

// Styles
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '007BFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$tableHeaderStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

// Titre principal
$sheet->setCellValue("A{$row}", "Rapport Complet des Transactions");
$sheet->mergeCells("A{$row}:E{$row}");
$sheet->getStyle("A{$row}")->applyFromArray([
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row += 2;

// Statistiques
$sheet->setCellValue("A{$row}", "Statistiques Générales");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:E{$row}");
$row++;

$sheet->fromArray([
    ['Revenus Totaux', number_format($revenus, 2) . ' €'],
    ['Dépenses Totales', number_format($depenses, 2) . ' €'],
    ['Bénéfice', number_format($benefice, 2) . ' €'],
], NULL, "A{$row}");
$sheet->getStyle("A{$row}:B" . ($row + 2))->applyFromArray($borderStyle);
$row += 4;

// Commandes Clients
$sheet->setCellValue("A{$row}", "Commandes Clients (Utilisateur → Admin)");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:E{$row}");
$row++;

$sheet->fromArray(['ID', 'Nom', 'Montant Total', 'Status', 'Date'], NULL, "A{$row}");
$sheet->getStyle("A{$row}:E{$row}")->applyFromArray($tableHeaderStyle);
$row++;

while ($order = $clientOrders->fetch_assoc()) {
    $sheet->fromArray([
        $order['id'],
        $order['name'],
        number_format($order['total_amount'], 2) . ' €',
        $order['status'],
        $order['created_at']
    ], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($borderStyle);
    $row++;
}
$row++;

// Commandes Fournisseurs
$sheet->setCellValue("A{$row}", "Commandes Fournisseurs (Admin → Fournisseur)");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:E{$row}");
$row++;

$sheet->fromArray(['ID', 'Nom', 'Montant Total', 'Status', 'Date'], NULL, "A{$row}");
$sheet->getStyle("A{$row}:E{$row}")->applyFromArray($tableHeaderStyle);
$row++;

while ($order = $supplierOrders->fetch_assoc()) {
    $sheet->fromArray([
        $order['id'],
        $order['name'],
        number_format($order['total_amount'], 2) . ' €',
        $order['status'],
        $order['created_at']
    ], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($borderStyle);
    $row++;
}
$row++;

// Top 10 Produits
$sheet->setCellValue("A{$row}", "Top 10 des Produits Vendus");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:B{$row}");
$row++;

$sheet->fromArray(['Produit', 'Quantité Vendue'], NULL, "A{$row}");
$sheet->getStyle("A{$row}:B{$row}")->applyFromArray($tableHeaderStyle);
$row++;

while ($prod = $topProducts->fetch_assoc()) {
    $sheet->fromArray([$prod['name'], $prod['total_qte']], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($borderStyle);
    $row++;
}
$row++;

// État des Stocks
$sheet->setCellValue("A{$row}", "État des Stocks");
$sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
$sheet->mergeCells("A{$row}:D{$row}");
$row++;

$sheet->fromArray(['Produit', 'Quantité Actuelle', 'Quantité Minimale', 'Statut'], NULL, "A{$row}");
$sheet->getStyle("A{$row}:D{$row}")->applyFromArray($tableHeaderStyle);
$row++;

while ($stock = $stockStatus->fetch_assoc()) {
    $statut = ($stock['quantity'] <= $stock['min_quantity']) ? 'Stock faible' : 'OK';
    $sheet->fromArray([
        $stock['name'],
        $stock['quantity'],
        $stock['min_quantity'],
        $statut
    ], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($borderStyle);
    $row++;
}

// Ajustement automatique des colonnes
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Export
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="rapport_complet.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
