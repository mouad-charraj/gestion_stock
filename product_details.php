<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>

<?php
session_start();
require_once 'config.php';

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = $_GET['id'];

// Récupérer les détails du produit
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, s.name as supplier_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

// Message d'ajout au panier
if (isset($_SESSION['cart_message'])) {
    $cart_message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Système de Gestion des Stocks</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Accueil</a> &gt; 
            <?php if ($product['category_id']): ?>
                <a href="index.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> &gt; 
            <?php endif; ?>
            <?php echo htmlspecialchars($product['name']); ?>
        </div>
        
        <?php if (isset($cart_message)): ?>
            <div class="alert alert-success"><?php echo $cart_message; ?></div>
        <?php endif; ?>
        
        <div class="product-details">
            <div class="product-image-large">
                <?php if ($product['image']): ?>
                    <img src="uploads/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php else: ?>
                    <img src="assets/images/no-image.jpg" alt="No image available">
                <?php endif; ?>
            </div>
            
            <div class="product-info-details">
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <?php if ($product['category_name']): ?>
                    <p class="product-category">Catégorie: <?php echo htmlspecialchars($product['category_name']); ?></p>
                <?php endif; ?>
                
                <p class="product-price">Prix: <?php echo number_format($product['price'], 2); ?> €</p>
                
                <?php if ($product['quantity'] > 0): ?>
                    <p class="stock-status in-stock">En stock: <?php echo $product['quantity']; ?> unités</p>
                <?php else: ?>
                    <p class="stock-status out-of-stock">Rupture de stock</p>
                <?php endif; ?>
                
                <div class="product-description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'Aucune description disponible.')); ?></p>
                </div>
                
                <?php if (isset($_SESSION['user_id']) && $product['quantity'] > 0): ?>
                    <form action="cart_add.php" method="post" class="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <div class="form-group">
                            <label for="quantity">Quantité:</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Ajouter au panier</button>
                    </form>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn btn-secondary">Connectez-vous pour commander</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
</body>
</html>