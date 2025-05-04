

<?php

require_once '../config.php';

// Vérifier si l'utilisateur est connecté et est un administrateur


// Récupérer les statistiques pour le dashboard
$stats = [];
$conn = connectDB();
// Nombre total d'utilisateurs
$quer = "SELECT COUNT(*) as total_users FROM users";
$stmt = $conn->prepare($quer);
$stmt->execute();
$result = $stmt->get_result();
$stats['users'] = $result->fetch_assoc()['total_users'];

// Nombre total d'articles
$quer = "SELECT COUNT(*) as total_products FROM products";
$stmt = $conn->prepare($quer);
$stmt->execute();
$result = $stmt->get_result();
$stats['products'] = $result->fetch_assoc()['total_products'];

// Nombre total de fournisseurs
$quer = "SELECT COUNT(*) as total_suppliers FROM suppliers";
$stmt = $conn->prepare($quer);
$stmt->execute();
$result = $stmt->get_result();
$stats['suppliers'] = $result->fetch_assoc()['total_suppliers'];

// Nombre total de commandes
$quer = "SELECT COUNT(*) as total_orders FROM orders";
$stmt = $conn->prepare($quer);
$stmt->execute();
$result = $stmt->get_result();
$stats['orders'] = $result->fetch_assoc()['total_orders'];

// Liste des articles en stock critique
$quer = "SELECT * FROM products WHERE quantity <= min_quantity ORDER BY quantity ASC LIMIT 5";
$stmt = $conn->prepare($quer);
$stmt->execute();
$low_stock_products = $stmt->get_result();

// Liste des dernières commandes
$quer = "SELECT o.id, o.created_at, o.status, u.username FROM orders o 
          JOIN users u ON o.sender_id = u.id 
          ORDER BY o.created_at DESC LIMIT 5";
$stmt = $conn->prepare($quer);
$stmt->execute();
$recent_orders = $stmt->get_result();

$page_title = "Dashboard Admin";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h1>Dashboard Admin</h1>
            <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mt-4">
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Utilisateurs</h5>
                    <h2 class="card-text"><?php echo $stats['users']; ?></h2>
                    <a href="users.php" class="btn btn-sm btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Articles</h5>
                    <h2 class="card-text"><?php echo $stats['products']; ?></h2>
                    <a href="products.php" class="btn btn-sm btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Fournisseurs</h5>
                    <h2 class="card-text"><?php echo $stats['suppliers']; ?></h2>
                    <a href="suppliers.php" class="btn btn-sm btn-light">Gérer</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Commandes</h5>
                    <h2 class="card-text"><?php echo $stats['orders']; ?></h2>
                    <a href="orders.php" class="btn btn-sm btn-light">Gérer</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="row mt-2">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Alertes de stock critique</h5>
                </div>
                <div class="card-body">
                    <?php if ($low_stock_products->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Quantité</th>
                                    <th>Min. Requis</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['quantity']; ?></td>
                                    <td><?php echo $product['min_quantity']; ?></td>
                                    <td>
                                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-success">Aucun article en stock critique.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Commandes récentes</h5>
                </div>
                <div class="card-body">
                    <?php if ($recent_orders->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'processing' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">Détails</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted">Aucune commande récente.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-2">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="product_add.php" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Ajouter un article
                        </a>
                        <a href="supplier_add.php" class="btn btn-warning">
                            <i class="fas fa-truck"></i> Ajouter un fournisseur
                        </a>
                        <a href="order_add.php" class="btn btn-info">
                            <i class="fas fa-shopping-cart"></i> Créer une commande
                        </a>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-chart-bar"></i> Rapports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>