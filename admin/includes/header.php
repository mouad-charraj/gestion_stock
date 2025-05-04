<?php
$page_title = "Admin Panel";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? $page_title : "Admin Panel"; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Custom CSS (si nécessaire) -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header class="main-header">
        <div class="container-fluid d-flex justify-content-between align-items-center py-3 px-4 bg-dark text-white">
            <div class="logo">
                <h1 class="h4 m-0"><i class="fas fa-warehouse me-2"></i>Gestion des Stocks</h1>
            </div>
            <nav class="main-nav">
                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-chart-line me-1"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="products.php">
                            <i class="fas fa-boxes me-1"></i> Produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="categories.php">
                            <i class="fas fa-tags me-1"></i> Catégories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="suppliers.php">
                            <i class="fas fa-truck me-1"></i> Fournisseurs
                        </a>
                    </li>
                   
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="users.php">
                            <i class="fas fa-users me-1"></i> Utilisateurs
                        </a>
                        <li class="nav-item">
                        <a class="nav-link text-white" href="clients.php">
                            <i class="fas fa-user me-1"></i> Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="orders.php">
                            <i class="fas fa-receipt me-1"></i> Commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Contenu principal ici -->
    <main class="container mt-4">
        <!-- Exemple de contenu -->
        <h2>Bienvenue dans l'administration</h2>
        <p>Utilisez le menu ci-dessus pour gérer les ressources.</p>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
