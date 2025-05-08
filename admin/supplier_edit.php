<?php

require_once '../config.php';

$conn = connectDB();

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
} 

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: suppliers.php');
    exit;
}

$id = $_GET['id'];

// Récupérer les informations du fournisseur
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: suppliers.php');
    exit;
}

$supplier = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validation de base
    if (empty($name) || empty($email) || empty($phone)) {
        $error = "Tous les champs marqués d'une * sont obligatoires";
    } else {
        // Vérifier si un autre fournisseur avec le même email existe déjà
        $checkStmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $email, $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $error = "Un autre fournisseur avec cet email existe déjà";
        } else {
            // Mettre à jour le fournisseur
            $updateStmt = $conn->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $updateStmt->bind_param("sssssi", $name, $contact, $email, $phone, $address, $id);
            
            if ($updateStmt->execute()) {
                header('Location: suppliers.php?updated=1');
                exit;
            } else {
                $error = "Erreur lors de la mise à jour du fournisseur: " . $conn->error;
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Fournisseur - Système de Gestion des Stocks</title>
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
        <h1>Modifier un Fournisseur</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" class="form">
            <div class="form-group">
                <label for="name">Nom*</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="contact_person">Personne de contact</label>
                <input type="text" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($supplier['contact_person']); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Téléphone*</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($supplier['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="address">Adresse</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Mettre à jour</button>
                <a href="suppliers.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html>
