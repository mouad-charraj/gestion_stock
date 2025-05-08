<?php
require_once '../config.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Récupérer la liste des clients pour le formulaire
$conn = connectDB();
$clientsQuery = "SELECT id, name, email FROM clients";
$clientsResult = $conn->query($clientsQuery);

// Traitement lors de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $email = $_POST['email'];
    $montant_total = $_POST['montant_total'];
    $statut = $_POST['statut'];

    // Validation de base
    if (empty($client_id) || empty($email) || empty($montant_total) || empty($statut)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Préparer et exécuter la requête d'insertion
        $stmt = $conn->prepare("INSERT INTO commandes (client_id, email, montant_total, statut) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $client_id, $email, $montant_total, $statut);

        if ($stmt->execute()) {
            // Rediriger vers la page des commandes avec un message de succès
            header('Location: commandes.php?success=1');
            exit;
        } else {
            $error = "Erreur lors de l'ajout de la commande: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Commande - Système de Gestion des Stocks</title>
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
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
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
        .alert-success {
            background-color: #4CAF50;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <h1>Ajouter une Commande</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success">La commande a été ajoutée avec succès.</div>
        <?php endif; ?>

        <form method="post" class="form">
            <div class="form-group">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                    <option value="">Sélectionner un client</option>
                    <?php while ($client = $clientsResult->fetch_assoc()): ?>
                        <option value="<?php echo $client['id']; ?>"><?php echo $client['name']; ?> (<?php echo $client['email']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="montant_total">Montant Total</label>
                <input type="number" id="montant_total" name="montant_total" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut" required>
                    <option value="En cours">En cours</option>
                    <option value="Expédiée">Expédiée</option>
                    <option value="Livrée">Livrée</option>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Ajouter la Commande</button>
                <a href="commandes.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html>
