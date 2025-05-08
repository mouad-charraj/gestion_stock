<?php
include 'includes/header.php';
include '../config.php';

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

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

        echo "<a href='category_edit.php?id=" . $cat['id'] . "' class='btn btn-primary btn-sm mt-2'>Modifier</a> ";
        echo "<a href='category_delete.php?id=" . $cat['id'] . "' class='btn btn-danger btn-sm mt-2'>Supprimer</a>";
        echo "</div></div>";
    }
    ?>

</div>
</body>
</html>
