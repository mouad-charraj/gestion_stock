
<?php

require_once '../config.php';
$conn = connectDB();
// Vérifier si l'utilisateur est connecté et est un administrateur
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Suppression d'un utilisateur
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
    
    // Vérifier qu'on ne supprime pas l'utilisateur connecté
    if ($user_id != $_SESSION['user_id']) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "L'utilisateur a été supprimé avec succès.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Erreur lors de la suppression de l'utilisateur.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Vous ne pouvez pas supprimer votre propre compte!";
        $_SESSION['message_type'] = "danger";
    }
    
    header('Location: users.php');
    exit();
}

// Récupération de tous les utilisateurs
$query = "SELECT * FROM users";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->get_result();

$page_title = "Gestion des utilisateurs";
include '../includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Gestion des utilisateurs</h1>
                <a href="user_add.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Ajouter un utilisateur
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
                                    <th>Nom d'utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                    <span class="badge bg-<?php 
                                        echo $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'user' ? 'primary' : 
                                        ($user['role'] === 'Supplier' ? 'success' : 'secondary')); ?>">
                                        <?php 
                                        echo ucfirst($user['role']); ?>
                                    </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                        class="btn btn-outline-primary" 
                                        title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" 
                                            onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?');" 
                                            style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" 
                                                    class="btn btn-outline-danger" 
                                                    title="Supprimer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if ($users->num_rows === 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucun utilisateur trouvé.</td>
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
</div> <!-- container-fluid -->

<?php include '../includes/footer.php'; ?>
</body>
</html>