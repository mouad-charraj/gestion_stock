<?php
$conn = mysqli_connect("localhost", "root", "", "db"); // ← avec "s" si nécessaire

if (!$conn) {
    die("Connexion échouée : " . mysqli_connect_error());
}
?>
