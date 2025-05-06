<?php
require_once 'config.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'supplier') {
    redirect('login_form.php');
}

$conn = connectDB();

// Récupérer l'ID du fournisseur connecté
$supplier_id = 0;
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $supplier_id = $user_data['id']; // Utilisez l'ID utilisateur comme référence
}

// Récupérer les produits du fournisseur
$products = [];
if ($supplier_id > 0) {
    // Correction: Utilisez la jointure avec la table suppliers via user_id
    $product_stmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN suppliers s ON p.supplier_id = s.id
        WHERE s.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $product_stmt->bind_param('i', $supplier_id);
    $product_stmt->execute();
    $products = $product_stmt->get_result();
}

$page_title = "Mes Produits";
include 'includes/supplier_header.php';
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-boxes"></i> Mes Produits</h5>
            <a href="add_product.php" class="btn btn-light btn-sm">
                <i class="fas fa-plus"></i> Ajouter un produit
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Stock Min</th>
                            <th>Date Création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="images/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="max-height: 50px;">
                                        <?php else: ?>
                                            <img src="assets/img/no-image.png" alt="Pas d'image" style="max-height: 50px;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><?= number_format($product['price'], 2) ?> €</td>
                                    <td class="<?= $product['quantity'] <= $product['min_quantity'] ? 'text-danger fw-bold' : '' ?>">
                                        <?= $product['quantity'] ?>
                                    </td>
                                    <td><?= $product['min_quantity'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= $product['id'] ?>)" class="btn btn-sm btn-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Aucun produit enregistré</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(productId) {
    if (confirm("Êtes-vous sûr de vouloir supprimer ce produit ?")) {
        window.location.href = 'delete_product.php?id=' + productId;
    }
}
</script>

