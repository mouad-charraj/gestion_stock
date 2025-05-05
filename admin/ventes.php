<?php
session_start();
require_once '../config.php';
$conn = connectDB();

// Vérifier si l'utilisateur est admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Statistiques globales
$stats_query = $conn->query("
    SELECT 
        COUNT(DISTINCT o.id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_revenue,
        COALESCE(AVG(o.total_amount), 0) AS avg_order_value,
        COUNT(DISTINCT o.sender_id) AS total_customers
    FROM orders o
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
");
$stats = $stats_query->fetch_assoc();

// Commandes récentes avec détails
$orders_query = $conn->query("
    SELECT 
        o.id,
        u.username AS customer,
        o.created_at,
        o.total_amount AS order_total,
        GROUP_CONCAT(
            CONCAT(p.name, ' (', oi.quantity, ' × ', oi.price, '€)') 
            SEPARATOR '<br>'
        ) AS products_details
    FROM orders o
    JOIN users u ON o.sender_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
    GROUP BY o.id, u.username, o.created_at, o.total_amount
    ORDER BY o.created_at DESC
    LIMIT 20
");
$orders = $orders_query->fetch_all(MYSQLI_ASSOC);

// Top produits
$top_query = $conn->query("
    SELECT 
        p.id,
        p.name,
        AVG(oi.price) AS unit_price,
        SUM(oi.quantity) AS total_sold,
        SUM(oi.quantity * oi.price) AS total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products = $top_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord des ventes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card { transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .product-badge { min-width: 80px; }
    </style>
</head>
<body>
<?php include '../includes/admin_header.php'; ?>

<div class="container-fluid mt-4">
    <!-- Cartes stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white p-3">
                <h5>Commandes</h5>
                <div class="stat-value"><?= $stats['total_orders'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white p-3">
                <h5>CA Total</h5>
                <div class="stat-value"><?= number_format($stats['total_revenue'], 2) ?> €</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white p-3">
                <h5>Panier moyen</h5>
                <div class="stat-value"><?= number_format($stats['avg_order_value'], 2) ?> €</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark p-3">
                <h5>Clients</h5>
                <div class="stat-value"><?= $stats['total_customers'] ?></div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <h5>Top Produits</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php foreach ($top_products as $product): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                    <small><?= number_format($product['unit_price'], 2) ?> €/unité</small>
                                </div>
                                <span class="badge bg-primary rounded-pill product-badge">
                                    <?= $product['total_sold'] ?> ventes<br>
                                    <?= number_format($product['total_revenue'], 2) ?> €
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Dernières Commandes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Articles</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['customer']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><?= $order['products_details'] ?></td>
                                        <td><strong><?= number_format($order['order_total'], 2) ?> €</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>