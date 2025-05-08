<?php
/**
 * En-tête pour l'interface fournisseur
 */
if (!isset($page_title)) {
    $page_title = "Tableau de bord fournisseur";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Gestion Stock</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background-color: var(--secondary-color);
            min-height: 100vh;
            color: white;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .navbar-brand {
            font-weight: bold;
        }
        
        .card-header {
            font-weight: 600;
        }
        
        .order-card {
            transition: transform 0.2s;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar d-none d-md-block col-md-3 col-lg-2 p-0">
            <div class="p-3">
                <h4 class="text-center mb-4"><?= htmlspecialchars($_SESSION['username'] ?? 'Fournisseur') ?></h4>
                <hr>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'fournisseur.php' ? 'active' : '' ?>" href="fournisseur.php">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'orders_history.php' ? 'active' : '' ?>" href="orders_history.php">
                            <i class="fas fa-history"></i> Historique commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="myproducts.php">
                            <i class="fas fa-boxes"></i> Mes produits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="supplier_profile.php">
                            <i class="fas fa-user"></i> Mon profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-md navbar-dark bg-primary">
                <div class="container-fluid">
                    <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <a class="navbar-brand" href="supplier.php">
                        <i class="fas fa-store-alt"></i> Espace Fournisseur
                    </a>
                    
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Fournisseur') ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Mobile Sidebar (hidden by default) -->
            <div class="collapse d-md-none" id="sidebarCollapse">
                <div class="bg-dark p-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'supplier.php' ? 'active' : '' ?>" href="supplier.php">
                                <i class="fas fa-tachometer-alt"></i> Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'orders_history.php' ? 'active' : '' ?>" href="orders_history.php">
                                <i class="fas fa-history"></i> Historique commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="products.php">
                                <i class="fas fa-boxes"></i> Mes produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="profile.php">
                                <i class="fas fa-user"></i> Mon profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <main class="container-fluid py-4">
                <!-- Les alertes seront affichées ici -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>