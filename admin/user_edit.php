<?php

require_once '../config.php';

// Vérifier si l'utilisateur est connecté et est un administrateur


// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "ID d'utilisateur invalide.";
    $_SESSION['message_type'] = "danger";
    header('Location: users.php');
    exit();
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$conn = connectDB();
// Récupérer les informations de l'utilisateur
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Utilisateur non trouvé.";
    $_SESSION['message_type'] = "danger";
    header('Location: users.php');
    exit();
}

$user = $result->fetch_assoc();
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
    
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }
    }
    
    if ($role !== 'user' && $role !== 'admin') {
        $errors[] = "Rôle invalide.";
    }
    
    // Vérifier si le nom d'utilisateur ou l'email existe déjà (sauf pour l'utilisateur actuel)
    $check_query = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ssi", $username, $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Ce nom d'utilisateur ou cet email est déjà utilisé.";
    }
    
    // Si pas d'erreurs, mettre à jour l'utilisateur
    if (empty($errors)) {
        if (!empty($password)) {
            // Mettre à jour avec nouveau mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $user_id);
        } else {
            // Mettre à jour sans changer le mot de passe
            $query = "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssi", $username, $email, $role, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "L'utilisateur a été mis à jour avec succès.";
            $_SESSION['message_type'] = "success";
            header('Location: users.php');
            exit();
        } else {
            $errors[] = "Erreur lors de la mise à jour de l'utilisateur: " . $conn->error;
        }
    }
}

$page_title = "Modifier un utilisateur";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Modifier un utilisateur</h1>
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
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $user_id); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : $user['username']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : $user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Laissez vide pour conserver le mot de passe actuel. Minimum 6 caractères.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user" <?php echo ((isset($_POST['role']) ? $_POST['role'] : $user['role']) === 'user') ? 'selected' : ''; ?>>Utilisateur</option>
                                <option value="admin" <?php echo ((isset($_POST['role']) ? $_POST['role'] : $user['role']) === 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>