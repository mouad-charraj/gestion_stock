<?php
session_start();
require_once 'config.php';
$conn = connectDB();

if (!isset($_POST['product_id']) || !is_numeric($_POST['product_id'])) {
    die("Requête invalide");
}

$product_id = $_POST['product_id'];
$quantity = $_POST['quantity'] ?? 1;

// Récupération du produit
$stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Produit introuvable");
}

// Mise à jour du panier
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$product_id] = [
        'quantity' => $quantity,
        'price' => $product['price']
    ];
}

header('Location: panier.php');
exit;
?>