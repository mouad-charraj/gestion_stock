<?php
require_once '../config.php';
$conn = connectDB();

// Vérifier si l'utilisateur est admin
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Filtres avec validation
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d', strtotime('-30 days'));
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');
$filter_customer = isset($_GET['customer']) ? intval($_GET['customer']) : 0;
$filter_product = isset($_GET['product']) ? intval($_GET['product']) : 0;

// Validation des dates
if (!DateTime::createFromFormat('Y-m-d', $date_start)) {
    $date_start = date('Y-m-d', strtotime('-30 days'));
}
if (!DateTime::createFromFormat('Y-m-d', $date_end)) {
    $date_end = date('Y-m-d');
}

// Récupération des filtres pour le dropdown
$customers_query = $conn->query("
    SELECT DISTINCT u.id, u.username 
    FROM users u
    JOIN orders o ON u.id = o.sender_id
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
    ORDER BY u.username
");
$customers = $customers_query->fetch_all(MYSQLI_ASSOC);

$products_query = $conn->query("
    SELECT DISTINCT p.id, p.name
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.receiver_type = 'admin' AND o.sender_type = 'user'
    ORDER BY p.name
");
$products = $products_query->fetch_all(MYSQLI_ASSOC);

// Construction de la requête avec les filtres
$where_clauses = ["o.receiver_type = 'admin' AND o.sender_type = 'user'"];
$params = [];
$types = '';

if (!empty($date_start)) {
    $where_clauses[] = "o.created_at >= ?";
    $params[] = $date_start . ' 00:00:00';
    $types .= 's';
}

if (!empty($date_end)) {
    $where_clauses[] = "o.created_at <= ?";
    $params[] = $date_end . ' 23:59:59';
    $types .= 's';
}

if (!empty($filter_customer)) {
    $where_clauses[] = "o.sender_id = ?";
    $params[] = $filter_customer;
    $types .= 'i';
}

if (!empty($filter_product)) {
    $where_clauses[] = "EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.product_id = ?)";
    $params[] = $filter_product;
    $types .= 'i';
}

$where_clause = implode(" AND ", $where_clauses);

// Requête pour les commandes
$orders_query = "
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
    WHERE $where_clause
    GROUP BY o.id, u.username, o.created_at, o.total_amount
    ORDER BY o.created_at DESC
";

// Préparation et exécution de la requête
$stmt = $conn->prepare($orders_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Statistiques de la période
$stats_query = "
    SELECT 
        COUNT(DISTINCT o.id) AS total_orders,
        COALESCE(SUM(o.total_amount), 0) AS total_revenue,
        COUNT(DISTINCT o.sender_id) AS total_customers
    FROM orders o
    WHERE $where_clause
";

$stmt = $conn->prepare($stats_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

// Calcul du bénéfice (20% de marge)
$stats['total_profit'] = $stats['total_revenue'] * 0.2;

// Top produits de la période
$top_products_query = "
    SELECT 
        p.id,
        p.name,
        SUM(oi.quantity) AS total_sold,
        SUM(oi.quantity * oi.price) AS total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE $where_clause
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 10
";

$stmt = $conn->prepare($top_products_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$top_products = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des ventes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .summary-card {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .product-badge {
            width: 100%;
            display: flex;
            justify-content: space-between;
        }
        .filter-form {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .table-actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
<?php include '../includes/admin_header.php'; ?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Historique des ventes</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Tableau de bord
            </a>
            <a href="rapport.php?from=<?= $date_start ?>&to=<?= $date_end ?>&customer=<?= $filter_customer ?>&product=<?= $filter_product ?>" class="btn btn-outline-success">
                <i class="fas fa-file-export me-1"></i> Exporter ce rapport
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filter-form">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_start" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_start" name="date_start" value="<?= $date_start ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label for="date_end" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_end" name="date_end" value="<?= $date_end ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
                <label for="customer" class="form-label">Client</label>
                <select class="form-select" id="customer" name="customer">
                    <option value="">Tous les clients</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>" <?= $filter_customer == $customer['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="product" class="form-label">Produit</label>
                <select class="form-select" id="product" name="product">
                    <option value="">Tous les produits</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= $filter_product == $product['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($product['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- Résumé des ventes pour la période -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card summary-card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total des ventes</h5>
                    <p class="summary-value"><?= number_format($stats['total_revenue'], 2) ?> €</p>
                    <p class="card-text">Période: <?= date('d/m/Y', strtotime($date_start)) ?> - <?= date('d/m/Y', strtotime($date_end)) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Bénéfice</h5>
                    <p class="summary-value"><?= number_format($stats['total_profit'], 2) ?> €</p>
                    <p class="card-text">Marge de 20% sur les ventes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Nombre de commandes</h5>
                    <p class="summary-value"><?= $stats['total_orders'] ?></p>
                    <p class="card-text">
                        <?php if ($stats['total_orders'] > 0): ?>
                            Moyenne: <?= number_format($stats['total_revenue'] / $stats['total_orders'], 2) ?> €/commande
                        <?php else: ?>
                            Aucune commande
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Clients actifs</h5>
                    <p class="summary-value"><?= $stats['total_customers'] ?></p>
                    <p class="card-text">
                        <?php if ($stats['total_customers'] > 0): ?>
                            Moyenne: <?= number_format($stats['total_revenue'] / $stats['total_customers'], 2) ?> €/client
                        <?php else: ?>
                            Aucun client
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tableau des commandes -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i> Commandes (<?= count($orders) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($orders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Produits</th>
                                        <th class="table-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?= $order['id'] ?></td>
                                            <td><?= htmlspecialchars($order['customer']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                            <td class="text-end"><?= number_format($order['order_total'], 2) ?> €</td>
                                            <td><?= $order['products_details'] ?></td>
                                            <td class="table-actions">
                                                <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="delete_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info m-3">
                            Aucune commande ne correspond aux critères sélectionnés.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top produits -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Top produits vendus
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($top_products) > 0): ?>
                        <ul class="list-group">
                            <?php foreach ($top_products as $product): ?>
                                <li class="list-group-item">
                                    <div class="product-badge">
                                        <span><?= htmlspecialchars($product['name']) ?></span>
                                        <div>
                                            <span class="badge bg-primary me-1"><?= $product['total_sold'] ?> vendus</span>
                                            <span class="badge bg-success"><?= number_format($product['total_revenue'], 2) ?> €</span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Aucun produit vendu pendant cette période.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Graphique mensuel - Placeholder -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i> Évolution des ventes
                    </h5>
                </div>
                <div class="card-body">
                    <div id="sales-chart" style="height: 300px;">
                        <div class="text-center text-muted">
                            <i class="fas fa-chart-line fa-4x mb-3"></i>
                            <p>Le graphique d'évolution des ventes sera disponible prochainement.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validation des dates
        const dateStart = document.getElementById('date_start');
        const dateEnd = document.getElementById('date_end');
        
        dateStart.addEventListener('change', function() {
            if (dateStart.value > dateEnd.value) {
                dateEnd.value = dateStart.value;
            }
        });
        
        dateEnd.addEventListener('change', function() {
            if (dateEnd.value < dateStart.value) {
                dateStart.value = dateEnd.value;
            }
        });
    });
</script>
</body>
</html>