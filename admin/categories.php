<?php

include '../config.php';
$conn = connectDB();

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include '../includes/admin_header.php';
?>

<div class="container mt-5">
    <h1>Gestion des Catégories</h1>
    <a href="category_add.php" class="btn btn-success mb-3">
        <i class="fas fa-plus"></i> Ajouter une Catégorie
    </a>

    <?php
    $categories = $conn->query("SELECT * FROM categories");

    while ($cat = $categories->fetch_assoc()) {
        echo "<div class='card mb-3'>";
        echo "<div class='card-header bg-dark text-white'>";
        echo "<strong>" . htmlspecialchars($cat['name']) . "</strong>";
        echo "</div>";
        echo "<div class='card-body'>";

        // Articles liés
        $cat_id = $cat['id'];
        $articles = $conn->query("SELECT * FROM products WHERE category_id = $cat_id");

        if ($articles->num_rows > 0) {
            echo "<ul>";
            while ($art = $articles->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($art['name']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<em>Aucun article dans cette catégorie.</em>";
        }

        echo "<div class='btn-group btn-group-sm mt-2'>";
        echo "<a href='category_edit.php?id=" . $cat['id'] . "' class='btn btn-outline-primary' title='Modifier'>";
        echo "<i class='fas fa-edit'></i>";
        echo "</a> ";
        echo "<a href='category_delete.php?id=" . $cat['id'] . "' class='btn btn-outline-danger' title='Supprimer' onclick=\"return confirm('Confirmer la suppression ?')\">";
        echo "<i class='fas fa-trash-alt'></i>";
        echo "</a>";
        echo "</div>";

        echo "</div></div>";
    }
    ?>


</div>
</body>
</html>
