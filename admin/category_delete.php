<?php
require_once '../config.php';

// Connexion à la base de données
$conn = connectDB();

// Vérifier si un ID est fourni et s’il est valide
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de la catégorie invalide.");
}

$id = intval($_GET['id']);

// Vérifier si la catégorie existe
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    die("Catégorie introuvable.");
}

// Supprimer la catégorie
$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    // Redirection après suppression
    header("Location: categories.php?success=Catégorie supprimée avec succès.");
    exit();
} else {
    die("Erreur lors de la suppression : " . $conn->error);
}
$stmt->close();
$conn->close();
?>
