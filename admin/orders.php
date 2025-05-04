<?php
$page_title = "Gestion des Commandes";
require_once '../config.php';
$conn = connectDB();

// Récupération des catégories
$categories = [];
$res = $conn->query("SELECT * FROM categories");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Get current admin user ID
$admin_id = null;
// Option 1: If you're using sessions to track the logged-in admin
if (isset($_SESSION['user_id'])) {
    $admin_id = $_SESSION['user_id'];
} else {
    // Option 2: Get the first admin user from the database
    $admin_result = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_data = $admin_result->fetch_assoc()) {
        $admin_id = $admin_data['id'];
    } else {
        // If no admin found, you may need to create one or show an error
        die("No admin user found. Please create an admin user first.");
    }
}

// Données du formulaire pour commandes fournisseurs
$selected_category = $_POST['category'] ?? '';
$selected_supplier = $_POST['supplier'] ?? '';
$selected_product = $_POST['product'] ?? '';
$quantity = $_POST['quantity'] ?? '';

// Traitement d'une commande fournisseur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_category && $selected_supplier && $selected_product && $quantity > 0) {
    // Use the valid admin ID instead of 0
    $conn->query("INSERT INTO orders (sender_id, receiver_id, sender_type, receiver_type, total_amount, status, created_at, updated_at)
                  VALUES ($admin_id, $selected_supplier, 'admin', 'supplier', 0, 'en cours', NOW(), NOW())");
    $order_id = $conn->insert_id;

    $res = $conn->query("SELECT price FROM products WHERE id = $selected_product");
    $price_data = $res->fetch_assoc();
    $price = $price_data['price'];
    $total = $price * $quantity;

    $conn->query("INSERT INTO order_items (order_id, product_id, quantity) VALUES ($order_id, $selected_product, $quantity)");

    $conn->query("UPDATE orders SET total_amount = $total WHERE id = $order_id");

    header("Location: orders.php");
    exit;
}

// Mise à jour du statut d'une commande
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['order_id']) && isset($_GET['status'])) {
    $order_id = (int)$_GET['order_id'];
    $status = $_GET['status'] === 'terminée' ? 'terminée' : 'en cours';
    
    // Debug - afficher les valeurs
    echo "<!--Debug: order_id=$order_id, status=$status-->";
    
    // Préparer et exécuter la requête de mise à jour
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    $result = $stmt->execute();
    
    // Vérifier si la mise à jour a réussi
    if ($result) {
        $_SESSION['message'] = "Statut de la commande #$order_id mis à jour avec succès.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Erreur lors de la mise à jour du statut : " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: orders.php");
    exit;
}

// Fournisseurs filtrés
$suppliers = [];
if ($selected_category) {
    $res = $conn->query("SELECT DISTINCT s.id, s.name FROM suppliers s 
                         JOIN products p ON p.supplier_id = s.id 
                         WHERE p.category_id = $selected_category");
    while ($row = $res->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Produits filtrés
$products = [];
if ($selected_category && $selected_supplier) {
    $res = $conn->query("SELECT * FROM products WHERE category_id = $selected_category AND supplier_id = $selected_supplier");
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}

// Commandes avec informations détaillées - COMMANDES FOURNISSEURS
$supplier_orders = [];
$res = $conn->query("
    SELECT o.*, s.name AS supplier_name, 
           p.name AS product_name, p.price AS unit_price,
           c.name AS category_name, oi.quantity
    FROM orders o
    JOIN suppliers s ON o.receiver_id = s.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.sender_type = 'admin' AND o.receiver_type = 'supplier'
    ORDER BY o.created_at DESC
");
while ($row = $res->fetch_assoc()) {
    $supplier_orders[] = $row;
}

// Commandes avec informations détaillées - COMMANDES CLIENTS
$client_orders = [];
// Débogage de la requête SQL des commandes clients
$client_query = "
    SELECT o.*, 
           u.username AS client_name,
           p.name AS product_name, 
           p.price AS unit_price,
           c.name AS category_name, 
           oi.quantity,
           (p.price * oi.quantity) AS line_total
    FROM orders o
    LEFT JOIN users u ON o.sender_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.sender_type = 'client' AND o.receiver_type = 'admin'
    ORDER BY o.created_at DESC
";

echo "<!--Debug query: $client_query-->";
$res = $conn->query($client_query);

if ($res === false) {
    echo "<!--SQL Error: " . $conn->error . "-->";
} else {
    while ($row = $res->fetch_assoc()) {
        $client_orders[] = $row;
    }
}

// Afficher un message si la requête n'a pas retourné de résultats
if (empty($client_orders)) {
    echo "<!--No client orders found-->";
    // Vérifier directement dans la table des commandes
    $check_orders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE sender_type = 'client' AND receiver_type = 'admin'");
    $order_count = $check_orders->fetch_assoc();
    echo "<!--Raw orders count: " . $order_count['count'] . "-->";
}

// Include the header file
include '../includes/admin_header.php';
?>

<!-- Contenu principal -->
<main class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary">
                    <h6 class="m-0 font-weight-bold text-white">Nouvelle Commande Fournisseur</h6>
                </div>
                <div class="card-body">
                    <form method="post" class="row">
                        <div class="col-md-3 mb-3">
                            <label for="category" class="form-label">Catégorie</label>
                            <select name="category" id="category" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Choisir une catégorie --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $selected_category ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (!empty($suppliers)): ?>
                        <div class="col-md-3 mb-3">
                            <label for="supplier" class="form-label">Fournisseur</label>
                            <select name="supplier" id="supplier" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Choisir un fournisseur --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>" <?= $sup['id'] == $selected_supplier ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($products)): ?>
                        <div class="col-md-3 mb-3">
                            <label for="product" class="form-label">Article</label>
                            <select name="product" id="product" class="form-select">
                                <option value="">-- Choisir un article --</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?= $prod['id'] ?>" <?= $prod['id'] == $selected_product ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prod['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="quantity" class="form-label">Quantité</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                        </div>

                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Commandes clients -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-success">
                    <h6 class="m-0 font-weight-bold text-white">Commandes Clients</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Catégorie</th>
                                    <th>Article</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Montant total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($client_orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['client_name'] ?? 'Client #'.$order['sender_id']) ?></td>
                                        <td><?= htmlspecialchars($order['category_name']) ?></td>
                                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td><?= number_format($order['unit_price'], 2) ?> €</td>
                                        <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                        <td>
                                            <span class="badge <?= $order['status'] === 'terminée' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <?php if ($order['status'] !== 'terminée'): ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=terminée" class="btn btn-sm btn-success">
                                                    Marquer terminée
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=en_cours" class="btn btn-sm btn-warning">
                                                    Marquer en cours
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($client_orders)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Aucune commande client trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Commandes fournisseurs -->
            <div class="card shadow">
                <div class="card-header py-3 bg-warning">
                    <h6 class="m-0 font-weight-bold text-dark">Commandes Fournisseurs</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Fournisseur</th>
                                    <th>Catégorie</th>
                                    <th>Article</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Montant total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supplier_orders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($order['category_name']) ?></td>
                                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td><?= number_format($order['unit_price'], 2) ?> €</td>
                                        <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                        <td>
                                            <span class="badge <?= $order['status'] === 'terminée' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <?php if ($order['status'] !== 'terminée'): ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=terminée" class="btn btn-sm btn-success">
                                                    Marquer terminée
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=en_cours" class="btn btn-sm btn-warning">
                                                    Marquer en cours
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($supplier_orders)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Aucune commande fournisseur trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>