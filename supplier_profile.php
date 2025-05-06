<?php
require_once 'config.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'supplier') {
    redirect('login_form.php');
}

$conn = connectDB();

// Récupérer les infos du fournisseur
$supplier_info = [];
$user_info = [];

// REQUÊTE COMPLÈTEMENT REVISITÉE
$stmt = $conn->prepare("
    SELECT u.*, s.name as supplier_name, s.contact_person, s.email as supplier_email, 
           s.phone, s.address, s.category_id, s.created_at as supplier_created_at
    FROM users u
    INNER JOIN suppliers s ON u.id = s.user_id
    WHERE u.id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_info = $result->fetch_assoc();
    $supplier_info = $user_info;
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($current_password, $user_info['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param('si', $hashed_password, $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                $_SESSION['message'] = "Mot de passe mis à jour avec succès";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Erreur lors de la mise à jour";
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            $_SESSION['message'] = "Les mots de passe ne correspondent pas";
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = "Mot de passe actuel incorrect";
        $_SESSION['message_type'] = 'danger';
    }
    redirect('profile.php');
}

$page_title = "Mon Profil";
include 'includes/supplier_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Section Informations -->
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-circle"></i> Informations Professionnelles</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Nom de l'entreprise:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= htmlspecialchars($supplier_info['supplier_name'] ?? 'Non renseigné') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Personne de contact:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= htmlspecialchars($supplier_info['contact_person'] ?? 'Non renseigné') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Email professionnel:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= htmlspecialchars($supplier_info['supplier_email'] ?? 'Non renseigné') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Téléphone:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= htmlspecialchars($supplier_info['phone'] ?? 'Non renseigné') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Adresse:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= htmlspecialchars($supplier_info['address'] ?? 'Non renseignée') ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label fw-bold">Inscrit depuis:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?= date('d/m/Y', strtotime($supplier_info['supplier_created_at'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Mot de passe -->
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Sécurité du Compte</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe (min. 6 caractères)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmation du nouveau mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'supplier_footer.php'; ?>