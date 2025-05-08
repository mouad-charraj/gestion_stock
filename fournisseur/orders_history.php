<?php
require_once '../config.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'supplier') {
    redirect('../login_form.php');
}

$conn = connectDB();

// Récupérer l'ID du fournisseur
$supplier_id = 0;
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ? OR name = ?");
$stmt->bind_param('ss', $_SESSION['user_email'], $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $supplier_data = $result->fetch_assoc();
    $supplier_id = $supplier_data['id'];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Compter le nombre total de commandes terminées
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM orders 
    WHERE receiver_id = ? AND receiver_type = 'supplier' AND status = 'terminée'
");
$count_stmt->bind_param('i', $supplier_id);
$count_stmt->execute();
$total_orders = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $per_page);

// Récupérer les commandes terminées paginées
$orders = [];
$order_stmt = $conn->prepare("
    SELECT o.id, o.status, o.created_at, o.updated_at, 
           p.name AS product_name, c.name AS category_name,
           oi.quantity, oi.price, o.total_amount,
           u.username AS admin_name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON o.sender_id = u.id
    WHERE o.receiver_id = ? AND o.receiver_type = 'supplier' 
    AND o.status = 'terminée'
    ORDER BY o.updated_at DESC
    LIMIT ? OFFSET ?
");
$order_stmt->bind_param('iii', $supplier_id, $per_page, $offset);
$order_stmt->execute();
$result = $order_stmt->get_result();

while ($order = $result->fetch_assoc()) {
    $orders[] = $order;
}

$page_title = "Historique des Commandes";
include '../includes/supplier_header.php';
?>

<div class="container-fluid mt-4">
    <div class="card shadow mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-history"></i> Historique des Commandes Terminées</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>Prix Unitaire</th>
                            <th>Total</th>
                            <th>Demandé par</th>
                            <th>Date Terminée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['product_name']) ?></td>
                                <td><?= htmlspecialchars($order['category_name']) ?></td>
                                <td><?= $order['quantity'] ?></td>
                                <td><?= number_format($order['price'], 2) ?> €</td>
                                <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                <td><?= htmlspecialchars($order['admin_name']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Aucune commande terminée trouvée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="text-center text-muted">
                Affichage des commandes <?= $offset + 1 ?> à <?= min($offset + $per_page, $total_orders) ?> sur <?= $total_orders ?> commandes terminées
            </div>
        </div>
    </div>
</div>

