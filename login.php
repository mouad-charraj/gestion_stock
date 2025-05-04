<?php
// login.php - Traitement de la connexion
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = escape($_POST['username']);
    $password = $_POST['password'];
    
    $conn = connectDB();
    $query = "SELECT id, username, password, role FROM users WHERE username = '$username'";
    $result = $conn->query($query);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Vérification du mot de passe
        if (password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            displayAlert('Connexion réussie', 'success');
            
            // Redirection selon le rôle
            if ($user['role'] == 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('index.php');
            }
        } else {
            displayAlert('Mot de passe incorrect', 'danger');
            redirect('login_form.php');
        }
    } else {
        displayAlert('Utilisateur non trouvé', 'danger');
        redirect('login_form.php');
    }
    
    $conn->close();
} else {
    redirect('login_form.php');
}
?>

<?php
// signup.php - Traitement de l'inscription
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = escape($_POST['username']);
    $email = escape($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation des données
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est requis";
    }
    
    if (empty($email)) {
        $errors[] = "L'adresse email est requise";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    if ($password != $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    // Vérification de l'unicité du nom d'utilisateur et de l'email
    $conn = connectDB();
    $query = "SELECT id FROM users WHERE username = '$username' OR email = '$email'";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $errors[] = "Ce nom d'utilisateur ou cette adresse email est déjà utilisé(e)";
    }
    
    if (empty($errors)) {
        // Hachage du mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertion dans la base de données
        $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', 'user')";
        
        if ($conn->query($query) === TRUE) {
            displayAlert('Inscription réussie, vous pouvez maintenant vous connecter', 'success');
            redirect('login_form.php');
        } else {
            displayAlert('Erreur lors de l\'inscription: ' . $conn->error, 'danger');
            redirect('signup_form.php');
        }
    } else {
        $_SESSION['signup_errors'] = $errors;
        redirect('signup_form.php');
    }
    
    $conn->close();
} else {
    redirect('signup_form.php');
}
?>

<?php
// logout.php - Déconnexion
require_once 'config.php';

// Destruction de la session
session_unset();
session_destroy();

displayAlert('Vous avez été déconnecté avec succès', 'success');
redirect('login_form.php');
?>