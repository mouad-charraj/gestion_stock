<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - StockFlow</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .form-container h1 {
            text-align: center;
            margin-bottom: 24px;
            color: #333;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 10px;
            background-color: #2563eb;
            color: white;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #1e40af;
        }

        .alert {
            padding: 12px;
            margin-bottom: 16px;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        a.btn-link.text-decoration-none {
            text-decoration: none !important;
        }
        a.btn-link.text-decoration-none:hover,
        a.btn-link.text-decoration-none:focus,
        a.btn-link.text-decoration-none:active,
        a.btn-link.text-decoration-none:visited {
            text-decoration: none !important;
        }


    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Connexion</h1>
            
            <?php
            require_once 'config.php';
            $alert = getAlert();
            if ($alert) {
                echo '<div class="alert alert-' . $alert['type'] . '">' . $alert['message'] . '</div>';
            }
            ?>
            
            <form action="login.php" method="post">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Se connecter</button>
                </div>
                
                <p class="text-center">Pas encore de compte? <a href="signup_form.php" class="btn btn-link text-decoration-none text-primary fw-semibold" style="padding: 0;">S'inscrire</a>

                </p>
            </form>
        </div>
    </div>
    
</body>
</html>


