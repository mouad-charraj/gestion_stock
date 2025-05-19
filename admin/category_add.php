<?php
require_once '../config.php';

// Connexion à la base de données
$conn = connectDB();


if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}


// Traitement du formulaire d'ajout de catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);

    // Validation simple pour s'assurer que le champ n'est pas vide
    if (empty($nom)) {
        $error = "Le nom de la catégorie ne peut pas être vide.";
    } else {
        // Insertion de la nouvelle catégorie dans la base de données
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $nom);

        if ($stmt->execute()) {
            $success = "Catégorie ajoutée avec succès.";
        } else {
            $error = "Erreur lors de l'ajout de la catégorie: " . $conn->error;
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une catégorie - Système de Gestion des Stocks</title>
    <style>
        

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h1 {
            margin-bottom: 20px;
            font-size: 28px;
            color: #212529;
        }

        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ced4da;
        }

        input[type="submit"] {
            background-color: #28a745;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #218838;
        }
    </style>
    
</head>

<body>
   

    <div class="container">
        <h1>Ajouter une catégorie</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="category_add.php" method="POST">
            <label for="nom">Nom de la catégorie</label>
            <input type="text" name="nom" id="nom" required>

            <input type="submit" value="Ajouter la catégorie">
        </form>

        <div style="margin-top: 20px;">
            <a href="categories.php" class="btn">Retour aux catégories</a>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html>
