<?php
require_once '../config.php';

// Connexion à la base de données
$conn = connectDB();

// Suppression d'un fournisseur
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];

    // Vérifier si le fournisseur est associé à des produits
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        $_SESSION['error'] = "Impossible de supprimer : fournisseur lié à des produits";
    } else {
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Fournisseur supprimé avec succès";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression : " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: suppliers.php");
    exit();
}

// Récupération des fournisseurs avec leurs produits et catégories
$query = "SELECT 
            s.id, s.name, s.contact_person, s.email, s.phone, s.address,
            GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') AS products,
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS categories
          FROM suppliers s
          LEFT JOIN products p ON p.supplier_id = s.id
          LEFT JOIN categories c ON p.category_id = c.id
          GROUP BY s.id
          ORDER BY s.name";

$result = $conn->query($query);
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fournisseurs</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.min.css">
    <style>
        .products-badge, .categories-badge {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
            margin: 2px 0;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.03);
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-truck"></i> Gestion des Fournisseurs</h1>
                    <a href="supplier_add.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nouveau Fournisseur
                    </a>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']) ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']) ?></div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Catégories</th>
                                        <th>Produits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($suppliers)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="fas fa-info-circle mr-2"></i>Aucun fournisseur enregistré
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><?= $supplier['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($supplier['name']) ?></strong></td>
                                                <td><?= htmlspecialchars($supplier['contact_person']) ?: '-' ?></td>
                                                <td><?= htmlspecialchars($supplier['email']) ?></td>
                                                <td><?= htmlspecialchars($supplier['phone']) ?: '-' ?></td>
                                                <td>
                                                    <?php if ($supplier['categories']): ?>
                                                        <span class="badge bg-info text-white categories-badge" 
                                                              title="<?= htmlspecialchars($supplier['categories']) ?>">
                                                            <i class="fas fa-tag mr-1"></i>
                                                            <?= htmlspecialchars($supplier['categories']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucune catégorie</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($supplier['products']): ?>
                                                        <span class="badge bg-light text-dark products-badge" 
                                                              title="<?= htmlspecialchars($supplier['products']) ?>">
                                                            <i class="fas fa-boxes mr-1"></i>
                                                            <?= htmlspecialchars($supplier['products']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucun produit</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="supplier_edit.php?id=<?= $supplier['id'] ?>" 
                                                           class="btn btn-outline-primary"
                                                           title="Modifier">
                                                           <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="suppliers.php?delete=<?= $supplier['id'] ?>" 
                                                           class="btn btn-outline-danger" 
                                                           onclick="return confirm('Confirmer la suppression ?')"
                                                           title="Supprimer">
                                                           <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>