<!-- signup_form.php -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Gestion des Stocks</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Inscription</h1>
            
            <?php
            require_once 'config.php';
            $alert = getAlert();
            if ($alert) {
                echo '<div class="alert alert-' . $alert['type'] . '">' . $alert['message'] . '</div>';
            }
            
            if (isset($_SESSION['signup_errors'])) {
                echo '<div class="alert alert-danger">';
                foreach ($_SESSION['signup_errors'] as $error) {
                    echo '<p>' . $error . '</p>';
                }
                echo '</div>';
                unset($_SESSION['signup_errors']);
            }
            ?>
            
            <form action="signup.php" method="post">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                    <small>Le mot de passe doit contenir au moins 8 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">S'inscrire</button>
                </div>
                
                <p class="text-center">Déjà inscrit? <a href="login_form.php">Se connecter</a></p>
            </form>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>