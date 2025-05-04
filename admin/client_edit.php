<?php

require_once '../config.php';

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: clients.php');
    exit;
}

$id = $_GET['id'];
$conn = connectDB();

// Récupérer les informations du client
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: clients.php');
    exit;
}

$client = $result->fetch_assoc();
$stmt->close();

// Traitement lors de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation de base
    if (empty($name) || empty($email) || empty($phone)) {
        $error = "Tous les champs marqués d'une * sont obligatoires";
    } else {
        // Commencer une transaction
        $conn->begin_transaction();

        try {
            // Vérifier si un autre client avec le même email existe déjà
            $checkStmt = $conn->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
            $checkStmt->bind_param("si", $email, $id);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                throw new Exception("Un autre client avec cet email existe déjà");
            }

            // Mettre à jour les informations du client
            $updateStmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $updateStmt->bind_param("ssssi", $name, $email, $phone, $address, $id);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Erreur lors de la mise à jour du client: " . $conn->error);
            }

            // Commit de la transaction
            $conn->commit();

            header('Location: clients.php?updated=1');
            exit;
        } catch (Exception $e) {
            // Si une erreur se produit, rollback la transaction
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Client - Système de Gestion des Stocks</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            background-color: #007BFF;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-secondary:hover {
            background-color: #565e64;
        }
        .alert {
            padding: 12px;
            background-color: #f44336;
            color: white;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Modifier un Client</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" class="form">
            <div class="form-group">
                <label for="name">Nom*</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Téléphone*</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="address">Adresse</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address']); ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Mettre à jour</button>
                <a href="clients.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html>
