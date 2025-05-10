<?php
require_once '../config.php';
require_once '../vendor/autoload.php';

session_start();
$conn = connectDB();

// Vérifier si admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 1. Récupération des données
$data = [
    'stats' => getStatistics($conn),
    'customer_orders' => getCustomerOrders($conn),
    'supplier_orders' => getSupplierOrders($conn),
    'top_products' => getTopProducts($conn),
    'stock_status' => getStockStatus($conn)
];

// Fonctions de récupération
function getStatistics($conn) {
    $q = "SELECT 
            (SELECT SUM(total_amount) FROM orders WHERE sender_type='user') as revenue,
            (SELECT SUM(total_amount) FROM orders WHERE receiver_type='supplier') as expenses,
            (SELECT COUNT(DISTINCT sender_id) FROM orders WHERE sender_type='user') as customers,
            (SELECT COUNT(*) FROM products WHERE quantity <= min_quantity) as low_stock";
    return $conn->query($q)->fetch_assoc();
}

function getCustomerOrders($conn) {
    $q = "SELECT o.id, u.username as customer, u.email, o.total_amount, o.created_at
          FROM orders o
          JOIN users u ON o.sender_id = u.id
          WHERE o.sender_type='user' AND o.receiver_type='admin'
          ORDER BY o.created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getSupplierOrders($conn) {
    $q = "SELECT o.id, s.name as supplier, o.total_amount, o.status, o.created_at
          FROM orders o
          JOIN suppliers s ON o.receiver_id = s.id
          WHERE o.sender_type='admin' AND o.receiver_type='supplier'
          ORDER BY o.created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getTopProducts($conn) {
    $q = "SELECT p.name, SUM(oi.quantity) as total_qte
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          GROUP BY oi.product_id
          ORDER BY total_qte DESC
          LIMIT 10";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function getStockStatus($conn) {
    $q = "SELECT name, quantity, min_quantity,
          CASE 
            WHEN quantity <= 0 THEN 'Rupture'
            WHEN quantity < min_quantity THEN 'Stock faible'
            ELSE 'OK'
          END as status
          FROM products
          ORDER BY status, name";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

// Création du PDF
$html = '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #007BFF; text-align: center; }
    h2 { color: #343A40; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { background-color: #007BFF; color: white; text-align: left; }
    th, td { border: 1px solid #ddd; padding: 8px; }
    .stat-box { display: flex; margin-bottom: 20px; }
    .stat-item { flex: 1; background: #f8f9fa; padding: 15px; margin: 0 5px; 
                border-left: 4px solid #007BFF; }
    .low-stock { color: red; font-weight: bold; }
    .text-center { text-align: center; }
</style>';

$html .= '<h1 class="text-center">Rapport Complet</h1>';
$html .= '<p class="text-center">Généré le '.date('d/m/Y H:i').'</p>';

// Statistiques
$html .= '<div class="stat-box">
    <div class="stat-item">
        <strong>Revenus totaux:</strong><br>
        '.number_format($data['stats']['revenue'], 2).' €
    </div>
    <div class="stat-item">
        <strong>Dépenses totales:</strong><br>
        '.number_format($data['stats']['expenses'], 2).' €
    </div>
    <div class="stat-item">
        <strong>Bénéfice net:</strong><br>
        '.number_format($data['stats']['revenue'] - $data['stats']['expenses'], 2).' €
    </div>
    <div class="stat-item">
        <strong>Stocks faibles:</strong><br>
        '.$data['stats']['low_stock'].' produits
    </div>
</div>';

// Commandes clients
$html .= '<h2>Commandes Clients (Utilisateur → Admin)</h2>';
$html .= '<table>
    <tr>
        <th>ID</th>
        <th>Client</th>
        <th>Email</th>
        <th>Montant</th>
        <th>Date</th>
    </tr>';
foreach ($data['customer_orders'] as $order) {
    $html .= '<tr>
        <td>'.$order['id'].'</td>
        <td>'.htmlspecialchars($order['customer']).'</td>
        <td>'.htmlspecialchars($order['email']).'</td>
        <td>'.number_format($order['total_amount'], 2).' €</td>
        <td>'.$order['created_at'].'</td>
    </tr>';
}
$html .= '</table>';

// Commandes fournisseurs (ajouté)
$html .= '<h2>Commandes Fournisseurs (Admin → Fournisseur)</h2>';
$html .= '<table>
    <tr>
        <th>ID</th>
        <th>Fournisseur</th>
        <th>Montant</th>
        <th>Statut</th>
        <th>Date</th>
    </tr>';
foreach ($data['supplier_orders'] as $order) {
    $html .= '<tr>
        <td>'.$order['id'].'</td>
        <td>'.htmlspecialchars($order['supplier']).'</td>
        <td>'.number_format($order['total_amount'], 2).' €</td>
        <td>'.$order['status'].'</td>
        <td>'.$order['created_at'].'</td>
    </tr>';
}
$html .= '</table>';

// Top produits
$html .= '<h2>Top 10 des Produits Vendus</h2>';
$html .= '<table>
    <tr>
        <th>Produit</th>
        <th>Quantité vendue</th>
    </tr>';
foreach ($data['top_products'] as $product) {
    $html .= '<tr>
        <td>'.htmlspecialchars($product['name']).'</td>
        <td>'.$product['total_qte'].'</td>
    </tr>';
}
$html .= '</table>';

// État des stocks
$html .= '<h2>État des Stocks</h2>';
$html .= '<table>
    <tr>
        <th>Produit</th>
        <th>Quantité</th>
        <th>Seuil min</th>
        <th>Statut</th>
    </tr>';
foreach ($data['stock_status'] as $product) {
    $class = ($product['status'] !== 'OK') ? 'class="low-stock"' : '';
    $html .= '<tr>
        <td>'.htmlspecialchars($product['name']).'</td>
        <td>'.$product['quantity'].'</td>
        <td>'.$product['min_quantity'].'</td>
        <td '.$class.'>'.$product['status'].'</td>
    </tr>';
}
$html .= '</table>';

// Génération du PDF
$dompdf = new \Dompdf\Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="rapport_complet_'.date('Y-m-d').'.pdf"');
echo $dompdf->output();
exit;