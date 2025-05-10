<?php
$page_title = "Gestion des Commandes";
require_once '../config.php';
require_once '../vendor/autoload.php';
require '../productNotifier.php';
require_once '../websocket_helper.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
$conn = connectDB();


if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
// Récupération des catégories
$categories = [];
$res = $conn->query("SELECT * FROM categories ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// Get current admin user ID
$admin_id = null;
if (isset($_SESSION['user_id'])) {
    $admin_id = $_SESSION['user_id'];
} else {
    $admin_result = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_data = $admin_result->fetch_assoc()) {
        $admin_id = $admin_data['id'];
    } else {
        die("No admin user found. Please create an admin user first.");
    }
}

// Données du formulaire pour commandes fournisseurs
$selected_category = $_POST['category'] ?? '';
$selected_supplier = $_POST['supplier'] ?? '';
$selected_product = $_POST['product'] ?? '';
$quantity = $_POST['quantity'] ?? '';

// Traitement d'une commande fournisseur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order']) && $selected_category && $selected_supplier && $selected_product && $quantity > 0) {
    // Récupérer le prix unitaire du produit
    $res = $conn->query("SELECT price FROM products WHERE id = $selected_product");
    $price_data = $res->fetch_assoc();
    $selling_price = $price_data['price'];
    
    // Calculer le prix d'achat (80% du prix de vente)
    $purchase_price = $selling_price * 0.8;
    $total = $purchase_price * $quantity;
    
    // Récupérer le nom du produit pour la commande
    $res = $conn->query("SELECT name FROM products WHERE id = $selected_product");
    $product_data = $res->fetch_assoc();
    $product_name = $product_data['name'];
    
    
    // Insérer la commande
    $stmt = $conn->prepare("INSERT INTO orders (name, sender_id, receiver_id, sender_type, receiver_type, total_amount, status, created_at, updated_at)
                  VALUES (?, ?, ?, 'admin', 'supplier', ?, 'en attente', NOW(), NOW())");
    $order_name = "Commande de " . $product_name;
    $stmt->bind_param("siid", $order_name, $admin_id, $selected_supplier, $total);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Insérer l'élément de commande
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiid", $order_id, $selected_product, $quantity, $purchase_price);
    $stmt->execute();

    $_SESSION['message'] = "Commande fournisseur créée avec succès.";
    $_SESSION['message_type'] = "success";

    $message = json_encode([
        'type' => 'supplier_buyed',
        'order_id' => $order_id,
        'content' => [
            "type" => "new_order", // you can customize this event type
            "name" => $order_name,
            "sender_id" => $admin_id,
            "receiver_id" => $selected_supplier,
            "sender_type" => "admin",
            "receiver_type" => "supplier",
            "total_amount" => $total,
            "status" => "en attente",
            "created_at" => date("Y-m-d H:i:s"), // or use NOW() from DB if you retrieve it
            "updated_at" => date("Y-m-d H:i:s"),
            "order_id" => $order_id, // the auto-incremented ID
            "quantity" => $quantity
        ]
    ]);
    notifyClients($message);
    
    // Rediriger pour éviter la soumission multiple du formulaire
    header("Location: orders.php");
    exit;
}

// Mise à jour du statut d'une commande
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['order_id']) && isset($_GET['status'])) {
    $order_id = (int)$_GET['order_id'];
    $status = $_GET['status'];
    
    // Vérifier que le statut est valide
    if (in_array($status, ['en attente', 'en cours', 'terminée', 'annulée'])) {
        // Démarrer une transaction
        $conn->begin_transaction();
        
        try {
            // Si le statut passe à "terminée", mettre à jour le stock
            if ($status === 'terminée') {
                // Récupérer les articles de la commande
                $order_items = $conn->query("
                    SELECT oi.product_id, oi.quantity 
                    FROM order_items oi 
                    WHERE oi.order_id = $order_id
                ");
                
                // Mettre à jour le stock pour chaque produit
                while ($item = $order_items->fetch_assoc()) {
                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];
                    
                    // Augmenter la quantité en stock
                    $conn->query("
                        UPDATE products 
                        SET quantity = quantity + $quantity 
                        WHERE id = $product_id
                    ");
                    
                    // Ajouter un mouvement de stock
                    $conn->query("
                        INSERT INTO stock_movements 
                        (product_id, quantity, type, reference, created_by, created_at) 
                        VALUES ($product_id, $quantity, 'entrée', 'Commande fournisseur #$order_id', $admin_id, NOW())
                    ");
                }
            }
            
            // Mettre à jour le statut de la commande
            $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $status, $order_id);
            $stmt->execute();
            
            // Valider la transaction
            $conn->commit();
            
            $_SESSION['message'] = "Statut de la commande #$order_id mis à jour avec succès.";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollback();
            
            $_SESSION['message'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    }
    
    header("Location: orders.php");
    exit;
}

// Fournisseurs filtrés par catégorie
$suppliers = [];
if ($selected_category) {
    $res = $conn->query("SELECT DISTINCT s.id, s.name FROM suppliers s 
                         JOIN products p ON p.supplier_id = s.id 
                         WHERE p.category_id = $selected_category
                         ORDER BY s.name");
    while ($row = $res->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Produits filtrés par catégorie et fournisseur
$products = [];
if ($selected_category && $selected_supplier) {
    $res = $conn->query("SELECT * FROM products 
                         WHERE category_id = $selected_category 
                         AND supplier_id = $selected_supplier
                         ORDER BY name");
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}

// Commandes fournisseurs avec informations détaillées
$supplier_orders = [];
$supplier_query = "
    SELECT o.*, s.name AS supplier_name, 
           p.name AS product_name, p.price AS selling_price, oi.price AS purchase_price,
           c.name AS category_name, oi.quantity
    FROM orders o
    JOIN suppliers s ON o.receiver_id = s.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.sender_type = 'admin' AND o.receiver_type = 'supplier'
    ORDER BY o.created_at DESC
";
$res = $conn->query($supplier_query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $supplier_orders[] = $row;
    }
}

// Include the header file
include '../includes/admin_header.php';
?>

<!-- Contenu principal -->
<main class="container-fluid mt-3">
    <div class="row">
        <div class="col-12">
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            endif; 
            ?>
            
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
                                    <option value="<?= $prod['id'] ?>">
                                        <?= htmlspecialchars($prod['name']) ?> 
                                        (Vente: <?= number_format($prod['price'], 2) ?> € | 
                                        Achat: <?= number_format($prod['price'] * 0.8, 2) ?> €)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="quantity" class="form-label">Quantité</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" name="submit_order" class="btn btn-success w-100">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
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
                                    <th>Prix vente</th>
                                    <th>Prix achat</th>
                                    <th>Montant total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_active_orders = false;
                                foreach ($supplier_orders as $order): 
                                    // N'affichez pas les commandes terminées dans ce tableau
                                    if ($order['status'] === 'terminée') continue;
                                    $has_active_orders = true;
                                ?>
                                    <tr id="order-<?= $order['id'] ?>">
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($order['category_name']) ?></td>
                                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td><?= number_format($order['selling_price'], 2) ?> €</td>
                                        <td><?= number_format($order['purchase_price'], 2) ?> €</td>
                                        <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                        <td class="order-status">
                                            <?php
                                            $badge_class = 'bg-warning text-dark';
                                            if ($order['status'] === 'en attente') {
                                                $badge_class = 'bg-info text-dark';
                                            } elseif ($order['status'] === 'annulée') {
                                                $badge_class = 'bg-danger';
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td class="order-actions">
                                            <?php if ($order['status'] === 'en attente'): ?>
                                                <div class="btn-group">
                                                    <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=en cours" class="btn btn-sm btn-primary">
                                                        Marquer en cours
                                                    </a>
                                                    <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=terminée" class="btn btn-sm btn-success">
                                                        Marquer terminée
                                                    </a>
                                                </div>
                                            <?php elseif ($order['status'] === 'en cours'): ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=terminée" class="btn btn-sm btn-success">
                                                    Marquer terminée
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($order['status'] !== 'annulée'): ?>
                                                <a href="?action=update_status&order_id=<?= $order['id'] ?>&status=annulée" class="btn btn-sm btn-danger">
                                                    Annuler
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$has_active_orders): ?>
                                    <tr>
                                        <td colspan="11" class="text-center">Aucune commande active trouvée</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Commandes terminées -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold">Commandes Terminées</h6>
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
                                    <th>Prix vente</th>
                                    <th>Prix achat</th>
                                    <th>Montant total</th>
                                    <th>Statut</th>
                                    <th>Date terminée</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_completed_orders = false;
                                foreach ($supplier_orders as $order): 
                                    if ($order['status'] !== 'terminée') continue;
                                    $has_completed_orders = true;
                                ?>
                                    <tr id="order-<?= $order['id'] ?>">
                                        <td><?= $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($order['category_name']) ?></td>
                                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                                        <td><?= $order['quantity'] ?></td>
                                        <td><?= number_format($order['selling_price'], 2) ?> €</td>
                                        <td><?= number_format($order['purchase_price'], 2) ?> €</td>
                                        <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                        <td class="order-status">
                                            <span class="badge bg-success">
                                                Terminée
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$has_completed_orders): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Aucune commande terminée trouvée</td>
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

<script>
// Script pour éviter la soumission du formulaire lors du changement de catégorie ou fournisseur
document.addEventListener('DOMContentLoaded', function() {
    // Réinitialiser les sélections après soumission du formulaire
    const form = document.querySelector('form');
    const submitBtn = document.querySelector('button[name="submit_order"]');
    
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            // Ajouter une validation côté client
            const product = document.getElementById('product').value;
            const quantity = document.getElementById('quantity').value;
            
            if (!product || quantity < 1) {
                alert('Veuillez sélectionner un article et indiquer une quantité valide.');
                return false;
            }
        });
    }
});
  const socket = new WebSocket('ws://localhost:8080');

  socket.onopen = function() {
      console.log('Connection established');
  };

  socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Message received:', data);

    if (data.type === 'supplier_change_status') {
        const orderId = data.order_id;
        const newStatus = data.status;

        const row = document.getElementById(`order-${orderId}`);
        if (row) {
            const statusCell = row.querySelector('.order-status');
            if (statusCell) {
                let badgeClass = 'bg-warning text-dark'; // default
                if (newStatus === 'en attente') {
                    badgeClass = 'bg-info text-dark';
                } else if (newStatus === 'annulée') {
                    badgeClass = 'bg-danger';
                }

                const label = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);

                statusCell.innerHTML = `
                    <span class="badge ${badgeClass}">
                        ${label}
                    </span>
                `;
            }
            // Find the actions cell
            const actionsCell = row.querySelector('.order-actions');
            if (actionsCell) {
                let buttonsHtml = '';

                if (newStatus === 'en attente') {
                    buttonsHtml += `
                        <div class="btn-group">
                            <a href="?action=update_status&order_id=${orderId}&status=en cours" class="btn btn-sm btn-primary">
                                Marquer en cours
                            </a>
                            <a href="?action=update_status&order_id=${orderId}&status=terminée" class="btn btn-sm btn-success">
                                Marquer terminée
                            </a>
                        </div>
                    `;
                } else if (newStatus === 'en cours') {
                    buttonsHtml += `
                        <a href="?action=update_status&order_id=${orderId}&status=terminée" class="btn btn-sm btn-success">
                            Marquer terminée
                        </a>
                    `;
                }

                if (newStatus !== 'annulée') {
                    buttonsHtml += `
                        <a href="?action=update_status&order_id=${orderId}&status=annulée" class="btn btn-sm btn-danger">
                            Annuler
                        </a>
                    `;
                }

                actionsCell.innerHTML = buttonsHtml;
            }
        }

        if (newStatus === 'terminée') {
        // Supprimer la ligne de l'ancienne table
        row.remove();

        // Créer une nouvelle ligne HTML avec les mêmes données
        const cells = row.querySelectorAll('td');
        const date = new Date();
        const formattedDate = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        const newRow = document.createElement('tr');
        newRow.id = `order-${orderId}`;
        newRow.innerHTML = `
            <td>${cells[0].innerText}</td>
            <td>${cells[1].innerText}</td>
            <td>${cells[2].innerText}</td>
            <td>${cells[3].innerText}</td>
            <td>${cells[4].innerText}</td>
            <td>${cells[5].innerText}</td>
            <td>${cells[6].innerText}</td>
            <td>${cells[7].innerText}</td>
            <td class="order-status"><span class="badge bg-success">Terminée</span></td>
            <td>${formattedDate}</td>
        `;

        // Trouver le <tbody> de la table des commandes terminées
        const completedTableBody = document.querySelector('.card.shadow.mt-4 tbody');

        if (completedTableBody) {
            completedTableBody.appendChild(newRow);
        }
    }
    }
    else if (data.type === 'product_purchased') {
    let data = JSON.parse(event.data);
    data.content.forEach(item => {
        const el = document.getElementById(`product-risk-${item.productId}`);
        if (el) {
            const badge = el.querySelector('.badge');
            if (badge) {
                let currentQty = parseInt(badge.textContent);
                let newQty = currentQty + parseInt(item.quantity);
                badge.textContent = newQty;

                if (newQty > parseInt(item.min_quantity)) {
                    el.remove();
                }
            }
        }
    });

    // 🟢 Update the notification count badge
    const updatedList = document.getElementsByClassName('dropdown-menu-notification-risk');
    const notifCount = document.getElementById('notification-count');
    if (notifCount) {
        notifCount.textContent = updatedList.length;
        if (updatedList.length === 0) {
            notifCount.remove(); // Optional: remove badge if empty
        }
    }
}


};





  socket.onerror = function(error) {
      console.log('WebSocket error:', error);
  };

  socket.onclose = function() {
      console.log('Connection closed');
  };
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>