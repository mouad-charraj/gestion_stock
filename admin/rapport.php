<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
$conn = connectDB();

// Vérifier si admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Détection du format demandé
$format = $_GET['format'] ?? 'html';

// 1. Récupérer toutes les données nécessaires
$data = [
    'stock' => getStockData($conn),
    'movements' => getMovementData($conn),
    'supplier_orders' => getSupplierOrders($conn),
    'customer_orders' => getCustomerOrders($conn),
    'stats' => getStatistics($conn)
];

// Fonctions pour récupérer les données adaptées à votre schéma
function getStockData($conn) {
    $query = "SELECT p.id, p.name, c.name as category, 
              s.name as supplier, p.quantity, p.min_quantity,
              p.price as sale_price,
              CASE 
                WHEN p.quantity <= 0 THEN 'Rupture'
                WHEN p.quantity < p.min_quantity THEN 'Stock faible'
                ELSE 'OK'
              END as status
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              LEFT JOIN suppliers s ON p.supplier_id = s.id
              ORDER BY status, p.quantity";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

function getMovementData($conn) {
    $query = "SELECT sm.id, p.name as product, sm.quantity, 
              sm.type, u.username as user, sm.created_at,
              sm.reference, sm.notes
              FROM stock_movements sm
              JOIN products p ON sm.product_id = p.id
              JOIN users u ON sm.created_by = u.id
              ORDER BY sm.created_at DESC
              LIMIT 100";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

function getSupplierOrders($conn) {
    $query = "SELECT o.id, s.name as supplier, 
              SUM(oi.quantity) as total_quantity,
              SUM(oi.quantity * oi.price) as total_amount,
              o.status, o.created_at, o.updated_at
              FROM orders o
              JOIN suppliers s ON o.receiver_id = s.user_id
              JOIN order_items oi ON o.id = oi.order_id
              WHERE o.receiver_type = 'supplier'
              AND o.sender_type = 'admin'
              GROUP BY o.id
              ORDER BY o.created_at DESC";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

function getCustomerOrders($conn) {
    $query = "SELECT o.id, u.username as customer, 
              SUM(oi.quantity) as total_quantity,
              SUM(oi.quantity * oi.price) as total_amount,
              o.status, o.created_at
              FROM orders o
              JOIN users u ON o.sender_id = u.id
              JOIN order_items oi ON o.id = oi.order_id
              WHERE o.receiver_type = 'admin'
              AND o.sender_type = 'user'
              GROUP BY o.id
              ORDER BY o.created_at DESC";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

function getStatistics($conn) {
    $query = "SELECT 
        (SELECT SUM(oi.quantity * oi.price) 
         FROM orders o JOIN order_items oi ON o.id = oi.order_id
         WHERE o.receiver_type = 'admin' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as revenue,
        
        (SELECT SUM(oi.quantity * oi.price) 
         FROM orders o JOIN order_items oi ON o.id = oi.order_id
         WHERE o.receiver_type = 'supplier' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as expenses,
         
        (SELECT COUNT(DISTINCT o.sender_id)
         FROM orders o 
         WHERE o.receiver_type = 'admin' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as customers,
         
        (SELECT COUNT(*) FROM products WHERE quantity < min_quantity) as low_stock";
    
    return $conn->query($query)->fetch_assoc();
}

// Génération Excel (adaptée)
function generateExcelReport($data) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Titre
    $sheet->setCellValue('A1', 'Rapport de stock - ' . date('d/m/Y'));
    $sheet->mergeCells('A1:G1');
    
    // État des stocks
    $sheet->setCellValue('A3', 'État des stocks');
    $sheet->fromArray([
        ['ID', 'Produit', 'Catégorie', 'Fournisseur', 'Quantité', 'Stock min', 'Prix', 'Statut']
    ], null, 'A4');
    
    $row = 5;
    foreach ($data['stock'] as $item) {
        $sheet->fromArray([
            $item['id'],
            $item['name'],
            $item['category'],
            $item['supplier'],
            $item['quantity'],
            $item['min_quantity'],
            $item['sale_price'],
            $item['status']
        ], null, 'A'.$row);
        $row++;
    }
    
    // Mouvements de stock
    $sheet->setCellValue('A'.($row+2), 'Mouvements récents');
    $sheet->fromArray([
        ['ID', 'Produit', 'Quantité', 'Type', 'Utilisateur', 'Date']
    ], null, 'A'.($row+3));
    
    $row += 4;
    foreach ($data['movements'] as $movement) {
        $sheet->fromArray([
            $movement['id'],
            $movement['product'],
            $movement['quantity'],
            $movement['type'],
            $movement['user'],
            $movement['created_at']
        ], null, 'A'.$row);
        $row++;
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="rapport_stock.xlsx"');
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// Génération PDF (adaptée)
function generatePdfReport($data) {
    $html = '<h1>Rapport de stock</h1>';
    $html .= '<h2>État des stocks</h2>';
    $html .= generateHtmlTable($data['stock'], ['ID', 'Produit', 'Catégorie', 'Fournisseur', 'Quantité', 'Stock min', 'Prix', 'Statut']);
    
    $html .= '<h2>Mouvements récents</h2>';
    $html .= generateHtmlTable($data['movements'], ['ID', 'Produit', 'Quantité', 'Type', 'Utilisateur', 'Date']);
    
    $html .= '<h2>Statistiques</h2>';
    $html .= '<p>Revenus (30j): ' . number_format($data['stats']['revenue'], 2) . ' €</p>';
    $html .= '<p>Dépenses (30j): ' . number_format($data['stats']['expenses'], 2) . ' €</p>';
    
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="rapport_stock.pdf"');
    echo $dompdf->output();
    exit;
}

function generateHtmlTable($data, $headers) {
    $html = '<table border="1"><tr>';
    foreach ($headers as $header) {
        $html .= '<th>'.$header.'</th>';
    }
    $html .= '</tr>';
    
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>'.htmlspecialchars($cell).'</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    return $html;
}

// Affichage selon le format
switch ($format) {
    case 'excel':
        generateExcelReport($data);
        break;
    case 'pdf':
        generatePdfReport($data);
        break;
    default:
        echo '<h1>Rapport de stock</h1>';
        echo '<a href="?format=excel">Export Excel</a> | ';
        echo '<a href="?format=pdf">Export PDF</a>';
        
        echo '<h2>État des stocks</h2>';
        echo generateHtmlTable($data['stock'], ['ID', 'Produit', 'Catégorie', 'Fournisseur', 'Quantité', 'Stock min', 'Prix', 'Statut']);
        
        echo '<h2>Mouvements récents</h2>';
        echo generateHtmlTable($data['movements'], ['ID', 'Produit', 'Quantité', 'Type', 'Utilisateur', 'Date']);
        
        echo '<h2>Statistiques</h2>';
        echo '<p>Revenus (30j): ' . number_format($data['stats']['revenue'], 2) . ' €</p>';
        echo '<p>Dépenses (30j): ' . number_format($data['stats']['expenses'], 2) . ' €</p>';
        break;
}