<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

session_start();
$conn = connectDB();

// Vérifier si admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 1. Récupération des données
$data = [
    'stock' => getStockData($conn),
    'movements' => getMovementData($conn),
    'supplier_orders' => getSupplierOrders($conn),
    'customer_orders' => getCustomerOrders($conn),
    'stats' => getStatistics($conn)
];

// Fonctions de récupération
function getStockData($conn) {
    $q = "SELECT p.id, p.name, c.name as category, s.name as supplier, 
          p.quantity, p.min_quantity, p.price as sale_price,
          CASE 
            WHEN p.quantity <= 0 THEN 'Rupture'
            WHEN p.quantity < p.min_quantity THEN 'Stock faible'
            ELSE 'OK'
          END as status
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN suppliers s ON p.supplier_id = s.id
          ORDER BY status, p.quantity";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getMovementData($conn) {
    $q = "SELECT sm.id, p.name as product, sm.quantity, sm.type, 
          u.username as user, sm.created_at
          FROM stock_movements sm
          JOIN products p ON sm.product_id = p.id
          JOIN users u ON sm.created_by = u.id
          ORDER BY sm.created_at DESC LIMIT 100";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getSupplierOrders($conn) {
    $q = "SELECT o.id, s.name as supplier, SUM(oi.quantity) as total_quantity,
          SUM(oi.quantity * oi.price) as total_amount, o.status, o.created_at, o.updated_at
          FROM orders o
          JOIN suppliers s ON o.receiver_id = s.user_id
          JOIN order_items oi ON o.id = oi.order_id
          WHERE o.receiver_type = 'supplier' AND o.sender_type = 'admin'
          GROUP BY o.id ORDER BY o.created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getCustomerOrders($conn) {
    $q = "SELECT o.id, u.username as customer, SUM(oi.quantity) as total_quantity,
          SUM(oi.quantity * oi.price) as total_amount, o.status, o.created_at
          FROM orders o
          JOIN users u ON o.sender_id = u.id
          JOIN order_items oi ON o.id = oi.order_id
          WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
          GROUP BY o.id ORDER BY o.created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getStatistics($conn) {
    $q = "SELECT 
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
    return $conn->query($q)->fetch_assoc();
}

// HTML pour PDF
$html = '<style>
    h1, h2 { font-family: sans-serif; color: #333; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
    th, td { border: 1px solid #999; padding: 6px 8px; }
    th { background-color: #eee; }
</style>';

$html .= '<h1>Rapport de stock - ' . date('d/m/Y') . '</h1>';

$html .= '<h2>État des stocks</h2>';
$html .= generateHtmlTable($data['stock'], ['ID', 'Produit', 'Catégorie', 'Fournisseur', 'Quantité', 'Min', 'Prix', 'Statut']);

$html .= '<h2>Mouvements récents</h2>';
$html .= generateHtmlTable($data['movements'], ['ID', 'Produit', 'Quantité', 'Type', 'Utilisateur', 'Date']);

$html .= '<h2>Commandes clients</h2>';
$html .= generateHtmlTable($data['customer_orders'], ['ID', 'Client', 'Quantité totale', 'Montant total', 'Statut', 'Date']);

$html .= '<h2>Commandes fournisseurs</h2>';
$html .= generateHtmlTable($data['supplier_orders'], ['ID', 'Fournisseur', 'Quantité totale', 'Montant total', 'Statut', 'Créée le', 'Modifiée le']);

$html .= '<h2>Statistiques (30 derniers jours)</h2>';
$html .= '<ul>';
$html .= '<li><strong>Revenus :</strong> ' . number_format($data['stats']['revenue'], 2) . ' €</li>';
$html .= '<li><strong>Dépenses :</strong> ' . number_format($data['stats']['expenses'], 2) . ' €</li>';
$html .= '<li><strong>Clients différents :</strong> ' . $data['stats']['customers'] . '</li>';
$html .= '<li><strong>Produits en stock faible :</strong> ' . $data['stats']['low_stock'] . '</li>';
$html .= '</ul>';

// Génération du PDF avec Dompdf
$dompdf = new \Dompdf\Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rapport_stock.pdf"');
echo $dompdf->output();
exit;

// Fonction utilitaire
function generateHtmlTable($data, $headers) {
    if (empty($data)) return '<p>Aucune donnée.</p>';
    $html = '<table><tr>';
    foreach ($headers as $h) {
        $html .= '<th>' . $h . '</th>';
    }
    $html .= '</tr>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}
