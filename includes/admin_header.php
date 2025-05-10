<?php
$page_title = "Admin Panel";
require_once '../config.php';// Assure-toi que $conn est bien défini ici

$low_stock_popup_querys = $conn->query("SELECT name, quantity, id FROM products WHERE quantity <= min_quantity");
$low_stock_products = [];
if ($low_stock_popup_querys && $low_stock_popup_querys->num_rows > 0) {
    while ($product = $low_stock_popup_querys->fetch_assoc()) {
        $low_stock_products[] = $product;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? $page_title : "Admin Panel"; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .notification-badge {
            position: absolute;
            top: 0;
            right: 5px;
            background: red;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-menu-notification {
            max-height: 300px;
            overflow-y: auto;
            width: 300px;
        }

        .dropdown-notification {
            position: relative;
        }
    </style>
</head>
<body>
<header class="main-header">
    <div class="container-fluid d-flex justify-content-between align-items-center py-3 px-4 bg-dark text-white">
        <div class="logo">
            <h1 class="h4 m-0"><i class="fas fa-warehouse me-2"></i>Gestion des Stocks</h1>
        </div>
        <nav class="main-nav">
            <ul class="nav">
                <!-- Liens classiques -->
                <li class="nav-item"><a class="nav-link text-white" href="dashboard.php"><i class="fas fa-chart-line me-1"></i> Tableau de bord</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="products.php"><i class="fas fa-boxes me-1"></i> Produits</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="categories.php"><i class="fas fa-tags me-1"></i> Catégories</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="suppliers.php"><i class="fas fa-truck me-1"></i> Fournisseurs</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users me-1"></i> Utilisateurs</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="ventes.php"><i class="fas fa-shopping-bag me-1"></i> Ventes</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="orders.php"><i class="fas fa-receipt me-1"></i> Commandes</a></li>

                <!-- Notification stock critique -->
                <li class="nav-item dropdown me-3">
                    <a class="nav-link text-white position-relative dropdown-toggle" href="#" id="stockDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if (count($low_stock_products) > 0): ?>
                            <span class="notification-badge" id="notification-count"><?= count($low_stock_products) ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-notification" aria-labelledby="stockDropdown">
                        <li class="dropdown-header fw-bold">Produits en stock critique</li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (count($low_stock_products) > 0): ?>
                            <?php foreach ($low_stock_products as $product): ?>
                                <li id="product-risk-<?= htmlspecialchars($product['id']) ?>" class="dropdown-menu-notification-risk">
                                    <a class="dropdown-item d-flex justify-content-between" href="products.php">
                                        <?= htmlspecialchars($product['name']) ?>
                                        <span class="badge bg-danger"><?= $product['quantity'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><span class="dropdown-item text-muted">Aucun produit critique</span></li>
                        <?php endif; ?>
                    </ul>
                </li>


                <!-- Utilisateur -->
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                </li>

                <!-- Déconnexion -->
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                </li>
            </ul>
        </nav>
    </div>
</header>

</body>
</html>
