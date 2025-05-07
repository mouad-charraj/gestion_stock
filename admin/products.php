<?php

require_once '../config.php';
require_once '../vendor/autoload.php';
require '../productNotifier.php';
require_once '../websocket_helper.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Vérifier si l'utilisateur est connecté et est un administrateur

$conn = connectDB();

// Suppression d'un produit
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);

    $query = "DELETE FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "L'article a été supprimé avec succès.";
        $_SESSION['message_type'] = "success";
        $message = json_encode([
            'type' => 'product_deleted',
            'product_id' => $product_id,
            'content' => [
                'id' => $product_id,
            ]
        ]);
        notifyClients($message);
    } else {
        $_SESSION['message'] = "Erreur lors de la suppression de l'article.";
        $_SESSION['message_type'] = "danger";
    }

    header('Location: products.php');
    exit();
}

// Récupération de tous les produits avec info fournisseur ET catégorie
$query = "SELECT p.*, s.name AS supplier_name, c.name AS category_name 
          FROM products p 
          LEFT JOIN suppliers s ON p.supplier_id = s.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.id ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$products = $stmt->get_result();

$page_title = "Gestion des articles";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestion des articles</h1>
                <a href="product_add.php" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Ajouter un article
                </a>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                    <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Prix</th>
                                    <th>Quantité</th>
                                    <th>Seuil</th>
                                    <th>Fournisseur</th>
                                    <th>Catégorie</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" width="50" height="50" class="img-thumbnail">
                                        <?php else: ?>
                                            <img src="../assets/img/no-image.png" alt="Pas d'image" width="50" height="50" class="img-thumbnail">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo substr(htmlspecialchars($product['description']), 0, 50) . (strlen($product['description']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo number_format($product['price'], 2); ?> €</td>
                                    <td id="quantity-<?php echo $product['id']; ?>">
                                        <span class="badge bg-<?php echo $product['quantity'] <= $product['min_quantity'] ? 'danger' : 'success'; ?>">
                                            <?php echo $product['quantity']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $product['min_quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($product['supplier_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Modifier
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet article?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="delete_product" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>

                                <?php if ($products->num_rows === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center">Aucun article trouvé.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script>
  const socket = new WebSocket('ws://localhost:8080');

  socket.onopen = function() {
      console.log('Connection established');
  };

  socket.onmessage = function(event) {
    const data = JSON.parse(event.data);
    console.log('Message received:', data);

    if (data.type === 'product_buyed') {
        data.content.forEach(item => {
            const td = document.getElementById(`quantity-${item.productId}`);
            if (td) {
                const span = td.querySelector('span');
                let currentQuantity = parseInt(span.textContent, 10);
                const minQuantity = item['min_quantity'];
                console.log(minQuantity)

                const newQuantity = currentQuantity - item.quantity;
                span.textContent = newQuantity;

                // Update badge color
                span.classList.remove('bg-success', 'bg-danger');
                span.classList.add(newQuantity <= minQuantity ? 'bg-danger' : 'bg-success');
            }
        });
    }
};


  socket.onerror = function(error) {
      console.log('WebSocket error:', error);
  };

  socket.onclose = function() {
      console.log('Connection closed');
  };
</script>
