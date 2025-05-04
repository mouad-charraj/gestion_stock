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
    } elseif (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
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