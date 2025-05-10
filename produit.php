<?php

require_once 'config.php';
$conn = connectDB();

// Vérifie que l'utilisateur est connecté
if ($_SESSION['user_role'] !== 'user') {
    header("Location: ../login.php");
    exit;
  }

// Vérifie l’ID du produit
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "Produit introuvable.";
    $_SESSION['message_type'] = "danger";
    redirect('index.php');
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Traitement du bouton "Acheter maintenant" redirigeant vers le panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $order_quantity = max(1, intval($_POST['order_quantity']));

    // Récupérer l'ancien panier
    $cart = &$_SESSION['cart'];
    if (!is_array($cart)) {
        $cart = [];
    }

    // Ajouter ou mettre à jour la quantité dans le panier
    if (isset($cart[$product_id])) {
        $cart[$product_id]['quantity'] += $order_quantity;
    } else {
        // Récupérer le prix unitaire du produit
        $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $unit_price = $row ? $row['price'] : 0;

        $cart[$product_id] = [
            'quantity' => $order_quantity,
            'price'    => $unit_price
        ];
    }

    // Mettre à jour le compteur global
    $_SESSION['cart_count'] = array_sum(array_column($cart, 'quantity'));

    // Message de confirmation et redirection vers le panier
    $_SESSION['message'] = "Le produit a été ajouté au panier.";
    $_SESSION['message_type'] = "success";
    redirect('panier.php');
}

// Traitement d'un nouveau commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $comment_text = trim($_POST['comment_text']);
    $rating = intval($_POST['rating']);
    if ($comment_text !== '' && $rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO comments (product_id, user_id, comment, rating) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $product_id, $user_id, $comment_text, $rating);
        $stmt->execute();
        $_SESSION['message_comment'] = "Votre commentaire a été publié.";
        $_SESSION['message_comment_type'] = "success";
    } else {
        $_SESSION['message_comment'] = "Commentaire invalide ou note manquante.";
        $_SESSION['message_comment_type'] = "danger";
    }
    redirect('produit.php?id=' . $product_id);
}

// Récupération des informations produit pour affichage
$stmt = $conn->prepare("SELECT p.*, s.name AS supplier_name, c.name AS category_name
                        FROM products p
                        LEFT JOIN suppliers s ON p.supplier_id = s.id
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = "Produit non trouvé.";
    $_SESSION['message_type'] = "warning";
    redirect('index.php');
}
$product = $result->fetch_assoc();

// Récupérer les commentaires existants
$comments_stmt = $conn->prepare("SELECT cm.comment, cm.rating, cm.created_at, u.username
                                FROM comments cm
                                JOIN users u ON cm.user_id = u.id
                                WHERE cm.product_id = ?
                                ORDER BY cm.created_at DESC");
$comments_stmt->bind_param("i", $product_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

include 'includes/user_header.php';
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['message_comment'])): ?>
        <div class="alert alert-<?= $_SESSION['message_comment_type'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message_comment']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message_comment'], $_SESSION['message_comment_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <?php if (!empty($product['image_url'])): ?>
                <img src="assets/img/<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <img src="assets/img/no-image.png" class="img-fluid" alt="Pas d'image">
            <?php endif; ?>
        </div>
        <div class="col-md-7">
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <p class="text-muted">Catégorie: <?= htmlspecialchars($product['category_name']) ?></p>
            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            <h4 class="text-primary"><?= number_format($product['price'], 2) ?> €</h4>
            <p><strong>Stock disponible:</strong> <?= $product['quantity'] ?></p>
            <p><strong>Fournisseur:</strong> <?= htmlspecialchars($product['supplier_name']) ?></p>

            <!-- Formulaire "Acheter maintenant" -->
            <form method="POST" class="d-inline">
                <div class="mb-2">
                    <label for="order_quantity">Quantité :</label>
                    <input type="number" name="order_quantity" value="1" min="1" max="<?= $product['quantity'] ?>" class="form-control w-25 d-inline-block" required>
                </div>
                <button type="submit" name="submit_order" class="btn btn-warning mb-2" <?= $product['quantity'] <= 0 ? 'disabled' : '' ?>>Acheter maintenant</button>
                <a href="panier.php" class="btn btn-outline-secondary ms-2">Voir mon panier</a>
            </form>

            <!-- Formulaire de commentaire -->
            <div class="mt-4">
                <h3>Laisser un commentaire</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label for="comment_text" class="form-label">Commentaire :</label>
                        <textarea name="comment_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="rating" class="form-label">Note :</label>
                        <select name="rating" class="form-select w-25" required>
                            <option value="">-- Choisir --</option>
                            <?php for($i=1; $i<=5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> étoile<?= $i>1?'s':'' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" name="submit_comment" class="btn btn-primary">Publier le commentaire</button>
                </form>
            </div>
        </div>
    </div>

    <hr>

    <h3>Commentaires des acheteurs</h3>
    <?php if ($comments_result->num_rows === 0): ?>
        <p class="text-muted">Pas encore de commentaires pour ce produit.</p>
    <?php else: ?>
        <?php while ($cm = $comments_result->fetch_assoc()): ?>
            <div class="mb-3">
                <p><strong><?= htmlspecialchars($cm['username']) ?></strong> 
                    <span class="text-warning"><?= str_repeat('★', $cm['rating']) ?><?= str_repeat('☆', 5-$cm['rating']) ?></span>
                    <small class="text-muted">le <?= date('d/m/Y H:i', strtotime($cm['created_at'])) ?></small>
                </p>
                <p><?= nl2br(htmlspecialchars($cm['comment'])) ?></p>
            </div>
            <hr>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include 'includes/admin_footer.php'; ?>
