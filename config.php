<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db');

// Connexion à la base de données
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Vérification de la connexion
    if ($conn->connect_error) {
        die("Connexion échouée: " . $conn->connect_error);
    }
    
    // Définir le jeu de caractères
    $conn->set_charset("utf8");
    
    return $conn;
}

// Démarrage de la session
session_start();

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier si l'utilisateur est un administrateur
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

// Fonction pour rediriger vers une page
function redirect($url) {
    header("Location: $url");
    exit();
}

// Fonction pour échapper les données
function escape($data) {
    $conn = connectDB();
    if (is_array($data)) {
        $escaped = [];
        foreach ($data as $key => $value) {
            $escaped[$key] = $conn->real_escape_string($value);
        }
        return $escaped;
    } else {
        return $conn->real_escape_string($data);
    }
}

// Fonction pour afficher les messages d'alerte
function displayAlert($message, $type = 'success') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Fonction pour récupérer et effacer les messages d'alerte
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Fonction pour vérifier les niveaux de stock et générer des alertes
function checkStockAlerts() {
    $conn = connectDB();
    $query = "SELECT id, nom, quantite, seuil_alerte FROM articles WHERE quantite <= seuil_alerte";
    $result = $conn->query($query);
    
    $alerts = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
    }
    
    return $alerts;
}
?>