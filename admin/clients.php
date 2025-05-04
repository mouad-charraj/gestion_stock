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
        $error = "Ce fournisseur ne peut pas être supprimé car il est associé à des produits.";
    } else {
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success = "Client supprimé avec succès";
        } else {
            $error = "Erreur lors de la suppression du fournisseur: " . $conn->error;
        }
        $stmt->close();
    }
}

// Récupération des fournisseurs avec leurs catégories et produits
$query = "
    SELECT s.id AS supplier_id, s.name AS supplier_name, s.email, s.phone, s.address,
           c.name AS category_name, p.name AS product_name
    FROM clients s
    LEFT JOIN products p ON p.supplier_id = s.id
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY s.name, c.name, p.name
";

$result = $conn->query($query);

$clients = [];

while ($row = $result->fetch_assoc()) {
    $sid = $row['supplier_id'];
    if (!isset($clients[$sid])) {
        $clients[$sid] = [
            'id' => $sid,
            'name' => $row['supplier_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'categories' => []
        ];
    }

    if ($row['category_name']) {
        $catName = $row['category_name'];
        if (!isset($clients[$sid]['categories'][$catName])) {
            $clients[$sid]['categories'][$catName] = [];
        }
        $clients[$sid]['categories'][$catName][] = $row['product_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Système de Gestion des Stocks</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.min.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Gestion des Clients</h1>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <a href="client_add.php" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Ajouter un Client
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Email</th>                                       
                                        <th>Téléphone</th>
                                        <th>Adresse</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun fournisseur trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $supplier): ?>
                                            <tr>
                                                <td><?php echo $supplier['id']; ?></td>
                                                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                                                <td class="actions">
                                                    <div class="btn-group">
                                                        <a href="client_edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Modifier</a>
                                                        <a href="clients.php?delete=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur?')">Supprimer</a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php if (!empty($supplier['categories'])): ?>
                                                <tr>
                                                    <td colspan="7">
                                                        <strong>Catégories et produits :</strong>
                                                        <ul>
                                                            <?php foreach ($supplier['categories'] as $category => $products): ?>
                                                                <li>
                                                                    <strong><?php echo htmlspecialchars($category); ?> :</strong>
                                                                    <?php echo implode(', ', array_map('htmlspecialchars', $products)); ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
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

    <?php include 'includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html>
