<?php
require_once '../config.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
$conn = connectDB();

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $category = trim($_POST['category']);
    
    // Validation de base
    if (empty($name) || empty($email) || empty($phone)) {
        $_SESSION['error'] = "Tous les champs marqués d'une * sont obligatoires";
    } else {
        // Vérifier si un fournisseur avec le même email existe déjà
        $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $_SESSION['error'] = "Un fournisseur avec cet email existe déjà";
        } else {
            $conn->begin_transaction();

            try {
                $role = 'supplier';
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                $stmt->execute();
                $insertedId = $stmt->insert_id;
                $stmt->close();

                // Insert into suppliers
                $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, category_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $name, $contact, $email, $phone, $address, $category, $insertedId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $_SESSION['success'] = "Fournisseur ajouté avec succès";
                header('Location: suppliers.php');
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
        $checkStmt->close();
    }
}

// Récupérer les catégories
$categories = $conn->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);

$page_title = "Ajouter un Fournisseur";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-truck mr-2"></i>Ajouter un Fournisseur</h1>
                <a href="suppliers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom du fournisseur *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 6 caractères</small>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Téléphone *</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Catégorie *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="" disabled selected>Sélectionnez une catégorie</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" 
                                                <?= (isset($_POST['category']) && $_POST['category'] == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Personne de contact</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?= isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : '' ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="suppliers.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times mr-2"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>