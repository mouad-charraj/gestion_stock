<?php
require '../config.php';
$conn = connectDB();

// Vérification de la session et du rôle admin

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

function getValue($conn, $query) {
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    return $row ? $row['total'] : 0;
}

function getTopProducts($conn) {
    return $conn->query("SELECT p.id, p.name, p.price, c.name as category, 
                        SUM(oi.quantity) AS total_qte, 
                        SUM(oi.quantity * oi.price) AS total_revenue
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        LEFT JOIN categories c ON p.category_id = c.id
                        GROUP BY oi.product_id 
                        ORDER BY total_qte DESC 
                        LIMIT 10");
}

function getStockStatus($conn) {
    return $conn->query("SELECT p.id, p.name, p.quantity, p.min_quantity, 
                        p.price, c.name as category,
                        (p.quantity * p.price) as stock_value,
                        s.name as supplier
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN suppliers s ON p.supplier_id = s.id
                        ORDER BY (p.quantity <= p.min_quantity) DESC, p.name ASC");
}

function getClientOrders($conn) {
    return $conn->query("SELECT o.id, u.username, o.total_amount, 
                        p.name as product_name, oi.quantity as product_qty,
                        p.price as unit_price, oi.price as item_price,
                        o.created_at, o.updated_at
                        FROM orders o
                        JOIN users u ON o.sender_id = u.id
                        JOIN order_items oi ON o.id = oi.order_id
                        JOIN products p ON oi.product_id = p.id
                        WHERE o.sender_type='user' AND o.receiver_type='admin'
                        ORDER BY o.created_at DESC");
}

function getSupplierOrders($conn) {
    return $conn->query("SELECT o.id, s.name as supplier_name, 
                        o.total_amount, p.name as product_name,
                        oi.quantity as product_qty, p.price as selling_price,
                        oi.price as purchase_price,
                        o.status, /* Ajout de la colonne status */
                        o.created_at, o.updated_at
                        FROM orders o
                        JOIN suppliers s ON o.receiver_id = s.id
                        JOIN order_items oi ON o.id = oi.order_id
                        JOIN products p ON oi.product_id = p.id
                        WHERE o.sender_type='admin' AND o.receiver_type='supplier'
                        ORDER BY o.created_at DESC");
}

function getRecentStockMovements($conn) {
    return $conn->query("SELECT sm.id, p.name as product_name, 
                        sm.quantity, sm.type, sm.reference,
                        sm.notes, u.username as created_by,
                        sm.created_at
                        FROM stock_movements sm
                        JOIN products p ON sm.product_id = p.id
                        JOIN users u ON sm.created_by = u.id
                        ORDER BY sm.created_at DESC
                        LIMIT 15");
}

function getProductRatings($conn) {
    return $conn->query("SELECT p.id, p.name, 
                        AVG(c.rating) as avg_rating,
                        COUNT(c.id) as review_count
                        FROM products p
                        LEFT JOIN comments c ON p.id = c.product_id
                        GROUP BY p.id
                        HAVING review_count > 0
                        ORDER BY avg_rating DESC
                        LIMIT 10");
}

$revenus = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE sender_type='user'");
$depenses = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE receiver_type='supplier'");
$benefice = $revenus - $depenses;
$topProducts = getTopProducts($conn);
$stockStatus = getStockStatus($conn);
$clientOrders = getClientOrders($conn);
$supplierOrders = getSupplierOrders($conn);
$stockMovements = getRecentStockMovements($conn);
$productRatings = getProductRatings($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Complet</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: auto;
        }
        h1, h2 {
            color: #333;
            margin-top: 30px;
        }
        h2 {
            border-bottom: 2px solid #007BFF;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007BFF;
            color: white;
            position: sticky;
            top: 0;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #e9e9e9;
        }
        .stat-box {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            gap: 20px;
        }
        .box {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #2196F3;
        }
        .box h3 {
            margin-top: 0;
            color: #333;
        }
        .box .value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .box.revenue { border-top-color: #28a745; }
        .box.expense { border-top-color: #dc3545; }
        .box.profit { border-top-color: #ffc107; }
        .export-buttons {
            margin: 30px 0;
            text-align: center;
        }
        .export-buttons a {
            text-decoration: none;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            margin: 0 10px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .export-buttons a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .export-buttons a.pdf {
            background-color: #dc3545;
        }
        .export-buttons a.excel {
            background-color: #28a745;
        }
        .low-stock {
            color: #dc3545;
            font-weight: bold;
        }
        .ok-stock {
            color: #28a745;
        }
        .warning-stock {
            color: #ffc107;
        }
        .rating-stars {
            color: #ffc107;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Tableau de Bord Administrateur</h1>
    <p>Date du rapport : <?= date('d/m/Y H:i') ?></p>

    <div class="export-buttons">
        <a href="rapport_pdf.php" class="pdf">📄 Exporter en PDF</a>
        <a href="rapport_excel.php" class="excel">📊 Exporter en Excel</a>
    </div>

    <div class="stat-box">
        <div class="box revenue">
            <h3>Revenus Totaux</h3>
            <div class="value"><?= number_format($revenus, 2) ?> €</div>
            <small>Ventes aux clients</small>
        </div>
        <div class="box expense">
            <h3>Dépenses Totales</h3>
            <div class="value"><?= number_format($depenses, 2) ?> €</div>
            <small>Commandes aux fournisseurs</small>
        </div>
        <div class="box profit">
            <h3>Bénéfice Net</h3>
            <div class="value"><?= number_format($benefice, 2) ?> €</div>
            <small>Marge bénéficiaire</small>
        </div>
    </div>

    <h2>Commandes Clients</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Produit Acheté</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Montant</th>
                <th>Date Commande</th>
                <th>Dernière Mise à Jour</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $clientOrders->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['product_qty'] ?></td>
                <td><?= number_format($row['unit_price'], 2) ?>€</td>
                <td><?= number_format($row['total_amount'], 2) ?>€</td>
                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Commandes Fournisseurs</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fournisseur</th>
                <th>Produit Commandé</th>
                <th>Quantité</th>
                <th>Prix Vente</th>
                <th>Prix Achat</th>
                <th>Montant</th>
                <th>Status</th> <!-- Nouvelle colonne -->
                <th>Date Commande</th>
                <th>Dernière Mise à Jour</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $supplierOrders->fetch_assoc()): 
            $statusClass = '';
            switch(strtolower($row['status'])) {
                case 'en attente':
                    $statusClass = 'warning-stock';
                    break;
                case 'expédiée':
                    $statusClass = 'ok-stock';
                    break;
                case 'annulée':
                    $statusClass = 'low-stock';
                    break;
                default:
                    $statusClass = '';
            }
        ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['product_qty'] ?></td>
                <td><?= number_format($row['selling_price'], 2) ?>€</td>
                <td><?= number_format($row['purchase_price'], 2) ?>€</td>
                <td><?= number_format($row['total_amount'], 2) ?>€</td>
                <td class="<?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></td> <!-- Nouvelle colonne -->
                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Top 10 des Produits Vendus</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Prix Unitaire</th>
                <th>Quantité Vendue</th>
                <th>Chiffre d'Affaires</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $topProducts->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= number_format($row['price'], 2) ?>€</td>
                <td><?= $row['total_qte'] ?></td>
                <td><?= number_format($row['total_revenue'], 2) ?>€</td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>État des Stocks</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Fournisseur</th>
                <th>Prix Unitaire</th>
                <th>Stock Actuel</th>
                <th>Stock Minimal</th>
                <th>Valeur Stock</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $stockStatus->fetch_assoc()): 
            $statusClass = '';
            $statusText = '';
            if ($row['quantity'] <= 0) {
                $statusClass = 'low-stock';
                $statusText = 'Rupture';
            } elseif ($row['quantity'] <= $row['min_quantity']) {
                $statusClass = 'warning-stock';
                $statusText = 'Stock faible';
            } else {
                $statusClass = 'ok-stock';
                $statusText = 'OK';
            }
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category']) ?></td>
                <td><?= htmlspecialchars($row['supplier']) ?></td>
                <td><?= number_format($row['price'], 2) ?>€</td>
                <td><?= $row['quantity'] ?></td>
                <td><?= $row['min_quantity'] ?></td>
                <td><?= number_format($row['stock_value'], 2) ?>€</td>
                <td class="<?= $statusClass ?>"><?= $statusText ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Mouvements de Stock Récents</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Type</th>
                <th>Quantité</th>
                <th>Référence</th>
                <th>Notes</th>
                <th>Effectué par</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $stockMovements->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= ucfirst($row['type']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= htmlspecialchars($row['reference']) ?></td>
                <td><?= htmlspecialchars($row['notes']) ?></td>
                <td><?= htmlspecialchars($row['created_by']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>Meilleurs Produits Évalués</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Note Moyenne</th>
                <th>Nombre d'Avis</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $productRatings->fetch_assoc()): 
            $stars = str_repeat('★', round($row['avg_rating']));
            $emptyStars = str_repeat('☆', 5 - round($row['avg_rating']));
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td class="rating-stars">
                    <?= $stars.$emptyStars ?> (<?= number_format($row['avg_rating'], 1) ?>)
                </td>
                <td><?= $row['review_count'] ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>