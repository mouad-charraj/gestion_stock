

<?php

require_once '../config.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin


// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = $_GET['id'];

// Récupérer les détails de la commande
$order = null;
$order_stmt = $conn->prepare("
    SELECT o.*, u.username, u.full_name, u.email 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: orders.php');
    exit;
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

// Récupérer les articles de la commande
$items = [];
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

if ($items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
}
$items_stmt->close();

// Mettre à jour le statut d'une commande
if (isset($_POST['update_status']) && isset($_POST['status'])) {
    $status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $status, $order_id);
    
    if ($update_stmt->execute()) {
        $order['status'] = $status;
        $success = "Statut de la commande mis à jour avec succès";
    } else {
        $error = "Erreur lors de la mise à jour du statut: " . $conn->error;
    }
    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Commande #<?php echo $order_id; ?> - Système de Gestion des Stocks</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Détails de la Commande #<?php echo $order_id; ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="order-info">
            <div class="order-summary">
                <h2>Informations de la commande</h2>
                <p><strong>Client:</strong> <?php echo htmlspecialchars($order['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                <p><strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                <p><strong>Total:</strong> <?php echo number_format($order['total_amount'], 2); ?> €</p>
                
                <form method="post" class="status-form">
                    <label for="status"><strong>Statut:</strong></label>
                    <select name="status" id="status">
                        <option value="en attente" <?php echo ($order['status'] == 'en attente') ? 'selected' : ''; ?>>En attente</option>
                        <option value="confirmée" <?php echo ($order['status'] == 'confirmée') ? 'selected' : ''; ?>>Confirmée</option>
                        <option value="expédiée" <?php echo ($order['status'] == 'expédiée') ? 'selected' : ''; ?>>Expédiée</option>
                        <option value="livrée" <?php echo ($order['status'] == 'livrée') ? 'selected' : ''; ?>>Livrée</option>
                        <option value="annulée" <?php echo ($order['status'] == 'annulée') ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                    <button type="submit" name="update_status" class="btn">Mettre à jour</button>
                </form>
            </div>
        </div>
        
        <h2>Articles commandés</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Image</th>
                    <th>Prix unitaire</th>
                    <th>Quantité</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucun article trouvé</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>
                                <?php if ($item['image']): ?>
                                    <img src="../uploads/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="thumbnail">
                                <?php else: ?>
                                    <img src="../assets/images/no-image.jpg" alt="No Image" class="thumbnail">
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($item['price'], 2); ?> €</td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['price'] * $item['quantity'], 2); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                    <td><strong><?php echo number_format($order['total_amount'], 2); ?> €</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="actions">
            <a href="orders.php" class="btn">Retour à la liste des commandes</a>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html> = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt