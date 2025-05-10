<?php

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
        COUNT(DISTINCT o.sender_id) AS total_customers
    FROM orders o
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
");
$stats = $stats_query->fetch_assoc();

// Calcul du bénéfice (20% de marge sur les achats)
$profit_query = $conn->query("
    SELECT 
        COALESCE(SUM(o.total_amount * 0.2), 0) AS total_profit
    FROM orders o
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
");
$profit_data = $profit_query->fetch_assoc();
$stats['total_profit'] = $profit_data['total_profit'];

// Commandes aux fournisseurs (dépenses)
$expenses_query = $conn->query("
    SELECT 
        COALESCE(SUM(o.total_amount), 0) AS total_expenses
    FROM orders o
    WHERE o.sender_type = 'admin' AND o.receiver_type = 'supplier'
");
$expenses_data = $expenses_query->fetch_assoc();
$stats['total_expenses'] = $expenses_data['total_expenses'];

// Statistiques des dernières 24h
$today_stats_query = $conn->query("
    SELECT 
        COUNT(DISTINCT o.id) AS orders_24h,
        COALESCE(SUM(o.total_amount), 0) AS revenue_24h
    FROM orders o
    WHERE 
        o.receiver_type = 'admin' AND 
        o.sender_type = 'user' AND 
        o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$today_stats = $today_stats_query->fetch_assoc();

// Statistiques de la semaine
$week_stats_query = $conn->query("
    SELECT 
        COUNT(DISTINCT o.id) AS orders_week,
        COALESCE(SUM(o.total_amount), 0) AS revenue_week
    FROM orders o
    WHERE 
        o.receiver_type = 'admin' AND 
        o.sender_type = 'user' AND 
        o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$week_stats = $week_stats_query->fetch_assoc();

// Dernières commandes des clients (5 dernières)
$latest_orders_query = $conn->query("
    SELECT 
        o.id,
        u.username AS customer,
        o.created_at,
        o.total_amount AS order_total
    FROM orders o
    JOIN users u ON o.sender_id = u.id
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
    ORDER BY o.created_at DESC
    LIMIT 5
");
$latest_orders = $latest_orders_query->fetch_all(MYSQLI_ASSOC);

// Dernières commandes aux fournisseurs (5 dernières)
$latest_supplier_orders_query = $conn->query("
    SELECT 
        o.id,
        s.name AS supplier,
        o.created_at,
        o.total_amount AS order_total
    FROM orders o
    JOIN suppliers s ON o.receiver_id = s.id
    WHERE o.sender_type = 'admin' AND o.receiver_type = 'supplier'
    ORDER BY o.created_at DESC
    LIMIT 5
");
$latest_supplier_orders = $latest_supplier_orders_query->fetch_all(MYSQLI_ASSOC);

// Stocks faibles
$low_stock_query = $conn->query("
    SELECT 
        p.id, 
        p.name, 
        p.quantity,
        s.name AS supplier_name
    FROM products p
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.quantity <= 5
    ORDER BY p.quantity ASC
    LIMIT 5
");
$low_stock = $low_stock_query->fetch_all(MYSQLI_ASSOC);

// Requête pour les produits en stock critique (pour le popup)
$low_stock_popup_query = $conn->query("
    SELECT name, quantity FROM products WHERE quantity <= min_quantity
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card { 
            transition: transform 0.3s; 
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            height: 100%;
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .small-stat {
            font-size: 1.2rem;
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.6;
        }
        .period-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        /* Styles pour le popup d'alerte */
        .stock-alert {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff3cd;
            color: rgb(133, 4, 4);
            border: 2px solid #ffeeba;
            padding: 20px 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            border-radius: 8px;
            text-align: center;
            max-width: 500px;
            font-family: Arial, sans-serif;
        }

        .triangle-icon {
            font-size: 40px;
            color: rgb(255, 7, 7);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            z-index: 999;
            display: none;
        }

        .close-btn {
            margin-top: 15px;
            padding: 5px 15px;
            background-color: rgb(255, 7, 7);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .close-btn:hover {
            background-color: rgb(224, 0, 0);
        }
    </style>
</head>
<body>
<?php include '../includes/admin_header.php'; ?>

<?php if ($low_stock_popup_query && $low_stock_popup_query->num_rows > 0): ?>
    <div class="overlay" id="overlay"></div>
    <div class="stock-alert" id="stockAlert">
        <div class="triangle-icon">&#9888;</div>
        <h3>Stock critique détecté</h3>
        <ul>
            <?php while ($product = $low_stock_popup_query->fetch_assoc()): ?>
                <li><?= htmlspecialchars($product['name']) ?> (Quantité: <?= $product['quantity'] ?>)</li>
            <?php endwhile; ?>
        </ul>
        <button class="close-btn" onclick="closeAlert()">Fermer</button>
    </div>

    <script>
        window.onload = function() {
            document.getElementById("stockAlert").style.display = "block";
            document.getElementById("overlay").style.display = "block";
        };

        function closeAlert() {
            document.getElementById("stockAlert").style.display = "none";
            document.getElementById("overlay").style.display = "none";
        }
    </script>
<?php endif; ?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        
        <h2>Tableau de bord</h2>
        <strong>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>!</strong>
        <div>
            <a href="ventes.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-history me-1"></i>Historique des ventes
            </a>
            <a href="rapport.php" class="btn btn-outline-success">
                <i class="fas fa-file-alt me-1"></i>Générer un rapport
            </a>
        </div>
    </div>

    <!-- Statistiques globales -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card stat-card bg-primary text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5>Revenus</h5>
                        <div class="stat-value"><?= number_format($stats['total_revenue'], 2) ?> €</div>
                        <div>
                            <span class="badge bg-light text-primary">
                                <i class="fas fa-clock me-1"></i><?= number_format($today_stats['revenue_24h'], 2) ?> € <span class="period-label">24h</span>
                            </span>
                            <span class="badge bg-light text-primary">
                                <i class="fas fa-calendar-week me-1"></i><?= number_format($week_stats['revenue_week'], 2) ?> € <span class="period-label">7j</span>
                            </span>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card bg-success text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5>Bénéfice</h5>
                        <div class="stat-value"><?= number_format($stats['total_profit'], 2) ?> €</div>
                        <div class="small-stat">
                            <i class="fas fa-calculator me-1"></i>Marge de 20%
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card bg-danger text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5>Dépenses</h5>
                        <div class="stat-value"><?= number_format($stats['total_expenses'], 2) ?> €</div>
                        <div class="small-stat">
                            <i class="fas fa-shopping-basket me-1"></i>Commandes fournisseurs
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card stat-card bg-info text-white p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5>Commandes</h5>
                        <div class="stat-value"><?= $stats['total_orders'] ?></div>
                        <div>
                            <span class="badge bg-light text-info">
                                <i class="fas fa-clock me-1"></i><?= $today_stats['orders_24h'] ?> <span class="period-label">24h</span>
                            </span>
                            <span class="badge bg-light text-info">
                                <i class="fas fa-calendar-week me-1"></i><?= $week_stats['orders_week'] ?> <span class="period-label">7j</span>
                            </span>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Dernières commandes des clients -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Dernières commandes clients</h5>
                    <a href="ventes.php" class="btn btn-sm btn-light">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['customer']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><strong><?= number_format($order['order_total'], 2) ?> €</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières commandes aux fournisseurs -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Dernières commandes fournisseurs</h5>
                    <a href="orders.php" class="btn btn-sm btn-light">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Fournisseur</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_supplier_orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['supplier']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><strong><?= number_format($order['order_total'], 2) ?> €</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Produits en stock faible -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Produits en stock faible</h5>
                    <a href="products.php" class="btn btn-sm btn-dark">Gérer les stocks</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($low_stock) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Fournisseur</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock as $product): ?>
                                        <tr class="<?= $product['quantity'] <= 2 ? 'table-danger' : 'table-warning' ?>">
                                            <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                                            <td><?= $product['quantity'] ?></td>
                                            <td><?= htmlspecialchars($product['supplier_name']) ?></td>
                                            <td>
                                                <a href="orders.php?supplier=<?= $product['supplier_name'] ?>&product=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-plus me-1"></i>Commander
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success m-3">
                            Tous les produits sont en stock suffisant
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>