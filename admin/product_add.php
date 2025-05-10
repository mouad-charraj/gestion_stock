<?php

require_once '../config.php';
require '../vendor/autoload.php'; // Make sure Ratchet is loaded
require '../productNotifier.php'; // Your WebSocket notifier class
require_once '../websocket_helper.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Connexion à la base de données
$conn = connectDB();


if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
// Récupération des fournisseurs et des catégories pour les listes déroulantes
$query = "SELECT id, name FROM suppliers ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$suppliers = $stmt->get_result();

$query_categories = "SELECT id, name FROM categories ORDER BY name ASC";
$stmt_categories = $conn->prepare($query_categories);
$stmt_categories->execute();
$categories = $stmt_categories->get_result();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image_url = 'default.png'; // default image name

if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../images/';

    $originalName = basename($_FILES['image_file']['name']);
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $uniqueName = pathinfo($originalName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;

    $targetPath = $uploadDir . $uniqueName;

    // Move uploaded file to images/ folder
    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetPath)) {
        $image_url = $uniqueName;
    } else {
        // handle error
        echo "Erreur lors du téléchargement de l'image.";
        exit;
    }
}

    // Récupérer et valider les données
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
    $min_quantity = filter_input(INPUT_POST, 'min_quantity', FILTER_SANITIZE_NUMBER_INT);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_SANITIZE_NUMBER_INT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT); // Nouvelle variable pour la catégorie

    // Validation
    if (empty($name)) {
        $errors[] = "Le nom de l'article est requis.";
    }

    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Le prix doit être un nombre positif.";
    }

    if (!is_numeric($quantity) || $quantity < 0) {
        $errors[] = "La quantité doit être un nombre positif ou nul.";
    }

    if (!is_numeric($min_quantity) || $min_quantity < 0) {
        $errors[] = "Le seuil minimum doit être un nombre positif ou nul.";
    }

    // Vérifier si le nom du produit existe déjà
    $check_query = "SELECT id FROM products WHERE name = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $errors[] = "Un article avec ce nom existe déjà.";
    }

    // Si pas d'erreurs, insérer le produit
    if (empty($errors)) {
        $created_at = date('Y-m-d H:i:s');

        $query = "INSERT INTO products (name, description, price, quantity, min_quantity, supplier_id, category_id, image, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdiissss", $name, $description, $price, $quantity, $min_quantity, $supplier_id, $category_id, $image_url, $created_at);

        if ($stmt->execute()) {
            $product_id = $conn->insert_id;
            $_SESSION['message'] = "L'article a été ajouté avec succès.";
            $_SESSION['message_type'] = "success";
            $message = json_encode([
                'type' => 'product_created',
                'product_id' => $product_id,
                'content' => [
                    'id' => $product_id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'quantity' => $quantity,
                    'min_quantity' => $min_quantity,
                    'supplier_id' => $supplier_id,
                    'category_id' => $category_id,
                    'image' => $image_url,
                    'created_at' => $created_at
                ]
            ]);
            notifyClients($message);
            header('Location: products.php');
            exit();
        } else {
            $errors[] = "Erreur lors de l'ajout de l'article: " . $conn->error;
        }
    }
}

$page_title = "Ajouter un article";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Ajouter un article</h1>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"  enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom de l'article *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="image_file" class="form-label">Image</label>
                                    <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
                                    <small class="text-muted">Laissez vide pour utiliser l'image par défaut</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Prix (€) *</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" 
                                    value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantité en stock *</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="0" 
                                    value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '0'; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="min_quantity" class="form-label">Seuil d'alerte minimum *</label>
                                    <input type="number" class="form-control" id="min_quantity" name="min_quantity" min="0" 
                                    value="<?php echo isset($_POST['min_quantity']) ? htmlspecialchars($_POST['min_quantity']) : '5'; ?>" required>
                                    <small class="text-muted">Quantité minimale avant alerte de stock</small>
                                </div>

                                <div class="mb-3">
                                    <label for="supplier_id" class="form-label">Fournisseur</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">-- Sélectionner un fournisseur --</option>
                                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                            <option value="<?php echo $supplier['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <!-- Sélection de la catégorie -->
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Catégorie</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">-- Sélectionner une catégorie --</option>
                                        <?php while ($category = $categories->fetch_assoc()): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
