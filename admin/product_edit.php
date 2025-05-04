<?php

require_once '../config.php';
require '../vendor/autoload.php'; // Ratchet
require '../productNotifier.php';
require_once '../websocket_helper.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Check product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID d'article invalide.";
    $_SESSION['message_type'] = "danger";
    header('Location: products.php');
    exit();
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$conn = connectDB();

// Fetch product data
$query = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Article non trouvé.";
    $_SESSION['message_type'] = "danger";
    header('Location: products.php');
    exit();
}

$product = $result->fetch_assoc();

// Fetch suppliers
$query = "SELECT id, name FROM suppliers ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$suppliers = $stmt->get_result();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
    $min_quantity = filter_input(INPUT_POST, 'min_quantity', FILTER_SANITIZE_NUMBER_INT);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_SANITIZE_NUMBER_INT) ?: null;

    $image_url = $product['image']; // default to existing image

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['image']['tmp_name'];
        $fileName = basename($_FILES['image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExt, $allowed)) {
            $newFileName = uniqid() . '.' . $fileExt;
            $destination = '../images/' . $newFileName;
            if (move_uploaded_file($fileTmp, $destination)) {
                $image_url = $newFileName;
            } else {
                $errors[] = "Erreur lors du téléchargement de l'image.";
            }
        } else {
            $errors[] = "Format d'image non autorisé (jpg, jpeg, png, gif).";
        }
    }

    // Validation
    if (empty($name)) $errors[] = "Le nom de l'article est requis.";
    if (empty($price) || !is_numeric($price) || $price <= 0) $errors[] = "Le prix doit être un nombre positif.";
    if (!is_numeric($quantity) || $quantity < 0) $errors[] = "La quantité doit être un nombre positif ou nul.";
    if (!is_numeric($min_quantity) || $min_quantity < 0) $errors[] = "Le seuil minimum doit être un nombre positif ou nul.";

    // Check duplicate name
    $check_query = "SELECT id FROM products WHERE name = ? AND id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $name, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) $errors[] = "Un article avec ce nom existe déjà.";

    if (empty($errors)) {
        $query = "UPDATE products SET name = ?, description = ?, price = ?, quantity = ?, 
                min_quantity = ?, supplier_id = ?, image = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssdiissi", $name, $description, $price, $quantity, $min_quantity, $supplier_id, $image_url, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "L'article a été mis à jour avec succès.";
            $_SESSION['message_type'] = "success";

            $message = json_encode([
                'type' => 'product_updated',
                'product_id' => $product_id,
                'content' => [
                    'id' => $product_id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'quantity' => $quantity,
                    'min_quantity' => $min_quantity,
                    'supplier_id' => $supplier_id,
                    'image' => $image_url
                ]
            ]);
            notifyClients($message);
            header('Location: products.php');
            exit();
        } else {
            $errors[] = "Erreur lors de la mise à jour de l'article: " . $conn->error;
        }
    }
}

$page_title = "Modifier un article";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifier un article</h1>
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
                    <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $product_id); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom de l'article *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? $product['description']); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="image" class="form-label">Image (JPEG, PNG, GIF)</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                </div>

                                <?php if (!empty($product['image'])): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Image actuelle</label>
                                        <div>
                                            <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Prix (€) *</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantité en stock *</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="0"
                                           value="<?php echo htmlspecialchars($_POST['quantity'] ?? $product['quantity']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="min_quantity" class="form-label">Seuil d'alerte minimum *</label>
                                    <input type="number" class="form-control" id="min_quantity" name="min_quantity" min="0"
                                           value="<?php echo htmlspecialchars($_POST['min_quantity'] ?? $product['min_quantity']); ?>" required>
                                    <small class="text-muted">Quantité minimale avant alerte de stock</small>
                                </div>

                                <div class="mb-3">
                                    <label for="supplier_id" class="form-label">Fournisseur</label>
                                    <select class="form-select" id="supplier_id" name="supplier_id">
                                        <option value="">-- Sélectionner un fournisseur --</option>
                                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                            <option value="<?php echo $supplier['id']; ?>"
                                                <?php echo ((isset($_POST['supplier_id']) ? $_POST['supplier_id'] : $product['supplier_id']) == $supplier['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
