<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
require '../productNotifier.php';
require_once '../websocket_helper.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

if (!isLoggedIn() || $_SESSION['user_role'] !== 'supplier') {
    redirect('../login_form.php');
}
$conn = connectDB();

// Vérifier si l'utilisateur est un fournisseur
$is_supplier = ($_SESSION['user_role'] === 'supplier');


$supplier_id = 0;

if ($is_supplier) {
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ? OR name = ?");
    $stmt->bind_param('ss', $_SESSION['user_email'], $_SESSION['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $supplier_data = $result->fetch_assoc();
        $supplier_id = $supplier_data['id'];
    }
}

// Traitement AJAX pour la mise à jour du statut
if ($is_supplier && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_order_status') {
    header('Content-Type: application/json');
    
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    if (!in_array($status, ['en attente', 'en cours', 'terminée', 'annulée'])) {
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        exit;
    }

    $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND receiver_id = ? AND receiver_type = 'supplier'");

    $check_stmt->bind_param('ii', $order_id, $supplier_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée ou non autorisée']);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($status === 'terminée') {
            $items = $conn->query("SELECT
                oi.product_id,
                oi.quantity,
                p.min_quantity AS min_quantity
            FROM
                order_items oi
            JOIN
                products p ON oi.product_id = p.id
            WHERE
                oi.order_id = $order_id;");
            $products = [];
            while ($item = $items->fetch_assoc()) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $min_quantity = $item['min_quantity'];
                
                $conn->query("UPDATE products SET quantity = quantity + $quantity WHERE id = $product_id");
                $conn->query("INSERT INTO stock_movements (product_id, quantity, type, reference, created_by, created_at) VALUES ($product_id, $quantity, 'entrée', 'Commande #$order_id', $supplier_id, NOW())");
                array_push($products, [
                    'productId' => $product_id,
                    'quantity' => $quantity,
                    'min_quantity' => $min_quantity
                ]);
            }
            $message = json_encode([
                'type' => 'product_purchased',
                'content' => $products
            ]);
            notifyClients($message);
        }

        $update_stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->bind_param('si', $status, $order_id);
        $update_stmt->execute();
        
        $conn->commit();

        $message = json_encode([
            'type' => 'supplier_change_status',
            'order_id' => $order_id,
            'status' => $status,
        ]);
        notifyClients($message);
        echo json_encode([
            'success' => true,
            'new_status' => $status,
            'badge_class' => $status === 'en attente' ? 'info' : 
                           ($status === 'en cours' ? 'warning' : 
                           ($status === 'terminée' ? 'success' : 'danger'))
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// Récupérer les commandes groupées par statut (max 4 terminées)
$orders_by_status = [
    'en attente' => [],
    'en cours' => [],
    'terminée' => []
];

if ($is_supplier && $supplier_id > 0) {
    // Commandes en attente et en cours (toutes)
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
        AND o.status IN ('en attente', 'en cours')
        ORDER BY o.created_at DESC
    ");
    $order_stmt->bind_param('i', $supplier_id);
    $order_stmt->execute();
    $result = $order_stmt->get_result();
    
    while ($order = $result->fetch_assoc()) {
        $orders_by_status[$order['status']][] = $order;
    }

    // Seulement 4 dernières commandes terminées pour le dashboard
    $completed_stmt = $conn->prepare("
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
        LIMIT 4
    ");
    $completed_stmt->bind_param('i', $supplier_id);
    $completed_stmt->execute();
    $completed_result = $completed_stmt->get_result();
    
    while ($order = $completed_result->fetch_assoc()) {
        $orders_by_status['terminée'][] = $order;
    }
}

$page_title = "Tableau de bord fournisseur";
include '../includes/supplier_header.php';
?>

<div class="container-fluid mt-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($is_supplier): ?>
    <div class="row">
        <!-- Colonne En Attente -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Commandes En Attente</h5>
                </div>
                <div class="card-body p-2" id="pending-orders">
                    <?php foreach ($orders_by_status['en attente'] as $order): ?>
                        <div class="card mb-2 order-card" id="order-<?= $order['id'] ?>">
                            <div class="card-body p-2">
                                <h6 class="card-title">#<?= $order['id'] ?> - <?= htmlspecialchars($order['product_name']) ?></h6>
                                <p class="card-text small mb-1">Quantité: <?= $order['quantity'] ?></p>
                                <p class="card-text small mb-1">Total: <?= number_format($order['total_amount'], 2) ?> €</p>
                                <div class="d-flex justify-content-between mt-2">
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'en cours')" class="btn btn-sm btn-warning">En Cours</button>
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'terminée')" class="btn btn-sm btn-success">Terminer</button>
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'annulée')" class="btn btn-sm btn-danger">Annuler</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($orders_by_status['en attente'])): ?>
                        <div class="text-center text-muted py-3">Aucune commande en attente</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne En Cours -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-truck"></i> Commandes En Cours</h5>
                </div>
                <div class="card-body p-2" id="in-progress-orders">
                    <?php foreach ($orders_by_status['en cours'] as $order): ?>
                        <div class="card mb-2 order-card" id="order-<?= $order['id'] ?>">
                            <div class="card-body p-2">
                                <h6 class="card-title">#<?= $order['id'] ?> - <?= htmlspecialchars($order['product_name']) ?></h6>
                                <p class="card-text small mb-1">Quantité: <?= $order['quantity'] ?></p>
                                <p class="card-text small mb-1">Total: <?= number_format($order['total_amount'], 2) ?> €</p>
                                <div class="d-flex justify-content-between mt-2">
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'en attente')" class="btn btn-sm btn-info">Retour Attente</button>
                                    <button onclick="updateOrderStatus(<?= $order['id'] ?>, 'terminée')" class="btn btn-sm btn-success">Terminer</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($orders_by_status['en cours'])): ?>
                        <div class="text-center text-muted py-3">Aucune commande en cours</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne Terminée -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-check-circle"></i> Commandes Terminées</h5>
                    <a href="orders_history.php" class="btn btn-sm btn-light">Voir tout l'historique</a>
                </div>
                <div class="card-body p-2" id="completed-orders">
                    <?php foreach ($orders_by_status['terminée'] as $order): ?>
                        <div class="card mb-2 order-card">
                            <div class="card-body p-2">
                                <h6 class="card-title">#<?= $order['id'] ?> - <?= htmlspecialchars($order['product_name']) ?></h6>
                                <p class="card-text small mb-1">Quantité: <?= $order['quantity'] ?></p>
                                <p class="card-text small mb-1">Total: <?= number_format($order['total_amount'], 2) ?> €</p>
                                <p class="card-text small text-success"><i class="fas fa-check"></i> Terminée le <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($orders_by_status['terminée'])): ?>
                        <div class="text-center text-muted py-3">Aucune commande terminée</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    const supplier_id = <?php echo $_SESSION['user_id']; ?>;
function updateOrderStatus(orderId, newStatus) {
    if (!confirm(`Voulez-vous vraiment mettre cette commande en statut "${newStatus}"?`)) {
        return;
    }
    console.log(`ajax_action=update_order_status&order_id=${orderId}&status=${newStatus}`)
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax_action=update_order_status&order_id=${orderId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Statut de la commande #${orderId} mis à jour avec succès!`);
            window.location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue lors de la mise à jour du statut');
    });
}

const socket = new WebSocket('ws://localhost:8080');

  socket.onopen = function() {
      console.log('Connection established');
  };

  socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Message received:', data);

    if (data.type === "supplier_buyed" && data.content?.status === "en attente" && supplier_id == data.content.receiver_id) {
        const order = data.content;

        // Check if the order already exists
        if (document.getElementById(`order-${order.order_id}`)) {
            console.log("Order already exists in DOM.");
            return;
        }

        // Format total amount
        const totalFormatted = parseFloat(order.total_amount).toFixed(2);

        // Create new order card
        const orderCard = document.createElement('div');
        orderCard.className = 'card mb-2 order-card';
        orderCard.id = `order-${order.order_id}`;
        orderCard.innerHTML = `
            <div class="card-body p-2">
                <h6 class="card-title">#${order.order_id} - ${order.name}</h6>
                <p class="card-text small mb-1">Quantité: ${order.quantity}</p>
                <p class="card-text small mb-1">Total: ${totalFormatted} €</p>
                <div class="d-flex justify-content-between mt-2">
                    <button onclick="updateOrderStatus(${order.order_id}, 'en cours')" class="btn btn-sm btn-warning">En Cours</button>
                    <button onclick="updateOrderStatus(${order.order_id}, 'terminée')" class="btn btn-sm btn-success">Terminer</button>
                    <button onclick="updateOrderStatus(${order.order_id}, 'annulée')" class="btn btn-sm btn-danger">Annuler</button>
                </div>
            </div>
        `;

        // Append to pending orders
        const pendingContainer = document.getElementById('pending-orders');
        pendingContainer.prepend(orderCard);

        // Optional: remove "Aucune commande en attente" if it exists
        const emptyMsg = pendingContainer.querySelector('.text-muted');
        if (emptyMsg) emptyMsg.remove();
    }
};



  socket.onerror = function(error) {
      console.log('WebSocket error:', error);
  };

  socket.onclose = function() {
      console.log('Connection closed');
  };
</script>

