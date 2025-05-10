<?php
require_once '../config.php';


$conn = connectDB();
// Vérifier si l'utilisateur est connecté et est un administrateur
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}


$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // Validation
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    
    // Modification pour inclure le rôle supplier
    $allowed_roles = ['user', 'admin', 'supplier'];
    if (!in_array($role, $allowed_roles)) {
        $errors[] = "Rôle invalide.";
    }
    
    
    // Vérifier si le nom d'utilisateur ou l'email existe déjà
    $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Ce nom d'utilisateur ou cet email est déjà utilisé.";
    }
    
    // Si pas d'erreurs, insérer l'utilisateur
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $created_at = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $created_at);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "L'utilisateur a été ajouté avec succès.";
            $_SESSION['message_type'] = "success";
            header('Location: users.php');
            exit();
        } else {
            $errors[] = "Erreur lors de l'ajout de l'utilisateur: " . $conn->error;
        }
    }
}

$page_title = "Ajouter un utilisateur";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Ajouter un utilisateur</h1>
                <a href="users.php" class="btn btn-secondary">
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
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Minimum 6 caractères</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] === 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                                <!-- Ajout du rôle supplier -->
                                <option value="supplier" <?php echo (isset($_POST['role']) && $_POST['role'] === 'supplier') ? 'selected' : ''; ?>>Fournisseur</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>