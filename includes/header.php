<!-- Modern Bootstrap-styled header -->
<nav class="navbar navbar-expand-md navbar-dark bg-primary">
    <div class="container">
        <!-- Brand / Logo -->
        <a class="navbar-brand fw-bold" href="index.php">Gestion des Stocks</a>

        <!-- Toggle button for mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar links -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? ' active' : '' ?>" href="index.php">Accueil</a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? ' active' : '' ?>" href="cart.php">Panier</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'my_orders.php' ? ' active' : '' ?>" href="my_orders.php">Mes commandes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? ' active' : '' ?>" href="profile.php">Mon profil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout.php">Déconnexion</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'login.php' ? ' active' : '' ?>" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= basename($_SERVER['PHP_SELF']) === 'register.php' ? ' active' : '' ?>" href="register.php">Inscription</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
