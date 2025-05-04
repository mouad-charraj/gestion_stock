<?php
// logout.php - Déconnexion
require_once 'config.php';

// Destruction de la session
session_unset();
session_destroy();

displayAlert('Vous avez été déconnecté avec succès', 'success');
redirect('login_form.php');
?>