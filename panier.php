<?php
session_start();
require_once 'config.php';
$conn = connectDB();

// Structure du panier : [id => ['quantity' => qty, 'price' => price], ...]
$cart = &$_SESSION['cart'];
$cart = $cart ?? [];
$total = 0;
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';

// Obtenir l'ID utilisateur actuel
$user_id = $_SESSION['user_id'] ?? 1; // Assurez-vous d'avoir un système de connexion qui définit user_id

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mettre à jour les quantités dans le panier (avant toute autre action)
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $id => $qty) {
            $id = (int)$id;
            $qty = max(1, (int)$qty);
            if (isset($cart[$id])) {
                $cart[$id]['quantity'] = $qty;
            }
        }
    }

    // Vider le panier
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        $_SESSION['cart_count'] = 0;
        $_SESSION['message'] = "Le panier a été vidé.";
        $_SESSION['message_type'] = "info";
        header('Location: panier.php');
        exit;
    }

    // Mettre à jour le panier (rafraîchir les quantités)
    if (isset($_POST['update_cart'])) {
        $_SESSION['message'] = "Panier mis à jour avec succès.";
        $_SESSION['message_type'] = "success";
        header('Location: panier.php');
        exit;
    }

    // Soumettre la commande
    if (isset($_POST['submit_order']) && !empty($cart)) {
        // Calculer le montant total de la commande
        $total_amount = 0;
        foreach ($cart as $id => $item) {
            $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $total_amount += $product['price'] * $item['quantity'];
        }

        // Récupérer un ID d'administrateur valide pour receiver_id
        $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $admin_id = 1; // Valeur par défaut
        if ($admin_data = $admin_query->fetch_assoc()) {
            $admin_id = $admin_data['id'];
        }
        
        // Créer la commande dans la table orders
        $status = "en cours";
        $sender_type = "client";
        $receiver_type = "admin";
        
        // Debug - afficher des informations
        echo "<!--Debug: user_id=$user_id, admin_id=$admin_id, sender_type=$sender_type, receiver_type=$receiver_type, total=$total_amount-->";
        
        $stmt = $conn->prepare("INSERT INTO orders (sender_id, receiver_id, sender_type, receiver_type, total_amount, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("iissds", $user_id, $admin_id, $sender_type, $receiver_type, $total_amount, $status);
        
        if (!$stmt->execute()) {
            echo "Erreur lors de la création de la commande: " . $stmt->error;
            exit;
        }
        
        $order_id = $conn->insert_id;
        
        // Vérifier si l'ID de commande est valide
        if ($order_id <= 0) {
            echo "Erreur: ID de commande invalide.";
            exit;
        }

        // Ajouter les éléments de la commande
        foreach ($cart as $id => $item) {
            $qty = $item['quantity'];
            
            // Insérer dans order_items
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $order_id, $id, $qty);
            
            if (!$stmt->execute()) {
                echo "Erreur lors de l'ajout des articles: " . $stmt->error;
                exit;
            }
            
            // Mettre à jour le stock des produits
            $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $qty, $id);
            $stmt->execute();
        }

        // Vider le panier après la commande
        $_SESSION['cart'] = [];
        $_SESSION['cart_count'] = 0;
        $_SESSION['message'] = "Commande #" . $order_id . " passée avec succès.";
        $_SESSION['message_type'] = "success";
        header('Location: panier.php');
        exit;
    }
}

include 'includes/user_header.php';
?>

<div class="container mt-5">
    <h2>Mon panier</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
        <div class="alert alert-info">Votre panier est vide.</div>
    <?php else: ?>
        <form method="POST" id="cart-form">
            <ul class="list-group mb-3" id="cart-items">
                <?php foreach ($cart as $id => $item): ?>
                    <?php 
                    // Récupération des infos produit
                    $stmt = $conn->prepare("SELECT name, price FROM products WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();

                    // Pré-calcul pour affichage initial
                    $unit_price = $product['price'];
                    $line_total = $unit_price * $item['quantity'];
                    $total += $line_total;
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-unit-price="<?= $unit_price ?>">
                        <div>
                            <?= htmlspecialchars($product['name']) ?>
                            <input
                                type="number"
                                name="quantities[<?= $id ?>]"
                                value="<?= $item['quantity'] ?>"
                                min="1"
                                class="form-control d-inline-block quantity-input"
                                style="width: 70px; margin-left: 10px;"
                            >
                        </div>
                        <span class="line-total"><?= number_format($line_total, 2) ?> €</span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h4>Total : <span id="grand-total"><?= number_format($total, 2) ?> €</span></h4>

            <div class="btn-group" role="group">
                <button type="submit" name="update_cart" class="btn btn-primary">Mettre à jour le panier</button>
                <button type="submit" name="clear_cart" class="btn btn-warning">Vider le panier</button>
                <button type="submit" name="submit_order" class="btn btn-success">Soumettre la commande</button>
            </div>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function updateTotals() {
                let total = 0;
                document.querySelectorAll('#cart-items .list-group-item').forEach(function(item) {
                    const qtyInput = item.querySelector('.quantity-input');
                    const qty = parseInt(qtyInput.value) || 1;
                    const unit = parseFloat(item.getAttribute('data-unit-price')) || 0;
                    const lineTotal = qty * unit;
                    item.querySelector('.line-total').textContent = lineTotal.toFixed(2) + ' €';
                    total += lineTotal;
                });
                document.getElementById('grand-total').textContent = total.toFixed(2) + ' €';
            }

            document.querySelectorAll('.quantity-input').forEach(function(input) {
                input.addEventListener('change', updateTotals);
            });
        });
        </script>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; ?>