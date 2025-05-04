<header class="main-header">
    <div class="container">
        <div class="logo">
            <h1><a href="index.php">Gestion des Stocks</a></h1>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.php">Accueil</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="cart.php">Panier</a></li>
                    <li><a href="my_orders.php">Mes commandes</a></li>
                    <li><a href="profile.php">Mon profil</a></li>
                    <li><a href="logout.php">Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="login.php">Connexion</a></li>
                    <li><a href="register.php">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>