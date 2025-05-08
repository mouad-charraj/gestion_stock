<?php
// login.php - Traitement de la connexion
require_once 'config.php';
session_start(); // Démarrer la session

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $conn = connectDB();
    // Utilisation de requête préparée pour éviter l'injection SQL
    $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Vérification du mot de passe
        if (password_verify($password, $user['password'])) {
            // Connexion réussie : on stocke les infos en session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_role'] = $user['role'];

            displayAlert('Connexion réussie', 'success');
            // Redirection selon le rôle
            switch ($user['role']) {
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
                case 'supplier':
                case 'fournisseur': // selon votre nomenclature
                    redirect('fournisseur.php');
                    break;
                default:
                    redirect('index.php');
                    break;
            }
            exit;
        } else {
            displayAlert('Mot de passe incorrect', 'danger');
        }
    } else {
        displayAlert('Utilisateur non trouvé', 'danger');
    }
    // En cas d'erreur ou de mauvais identifiants, retour au formulaire
    redirect('login_form.php');
    $stmt->close();
    $conn->close();
} else {
    // Si accès direct sans POST
    redirect('login_form.php');
}
?>
