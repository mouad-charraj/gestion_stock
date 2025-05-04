<?php
require_once '../config.php';

$conn = connectDB();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // Validation simple
    if (empty($name) || empty($email) || empty($phone)) {
        $errors[] = "Les champs nom, email et téléphone sont obligatoires.";
    }

    // Vérifier si l'email existe déjà dans la table `users`
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $errors[] = "Un compte utilisateur avec cet email existe déjà.";
    }

    $checkStmt->close();

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insertion dans users
            $stmtUser = $conn->prepare("INSERT INTO users (username, email) VALUES (?, ?)");
            $stmtUser->bind_param("ss", $name, $email);
            $stmtUser->execute();
            $userId = $stmtUser->insert_id;
            $stmtUser->close();

            // Insertion dans clients
            $stmtClient = $conn->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
            $stmtClient->bind_param("ssss", $name, $email, $phone, $address);
            $stmtClient->execute();
            $stmtClient->close();

            $conn->commit();
            $success = "Client ajouté avec succès.";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $errors[] = "L'email est déjà utilisé.";
            } else {
                $errors[] = "Erreur lors de l'ajout du client : " . $e->getMessage();
            }
        }
    }
}

include '../includes/admin_header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un client</title>
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container mt-4">
        <h1>Ajouter un client</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="name">Nom *</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="phone">Téléphone *</label>
                <input type="text" id="phone" name="phone" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="address">Adresse</label>
                <textarea id="address" name="address" class="form-control"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Ajouter</button>
        </form>
    </div>
</body>
</html>
