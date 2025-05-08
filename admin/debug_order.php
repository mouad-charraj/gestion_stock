<?php
// Fichier temporaire debug_orders.php à placer à côté de orders.php

require_once '../config.php';
$conn = connectDB();


if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Afficher toutes les commandes de la table orders pour diagnostiquer
echo "<h2>Debugging des commandes</h2>";
echo "<h3>Toutes les commandes</h3>";

$result = $conn->query("SELECT * FROM orders ORDER BY id DESC");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>ID</th>
        <th>sender_id</th>
        <th>receiver_id</th>
        <th>sender_type</th>
        <th>receiver_type</th>
        <th>total_amount</th>
        <th>status</th>
        <th>created_at</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['sender_id'] . "</td>";
    echo "<td>" . $row['receiver_id'] . "</td>";
    echo "<td>" . $row['sender_type'] . "</td>";
    echo "<td>" . $row['receiver_type'] . "</td>";
    echo "<td>" . $row['total_amount'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les commandes clients spécifiquement
echo "<h3>Commandes clients</h3>";
$result = $conn->query("
    SELECT o.*, 
           u.username AS client_name,
           p.name AS product_name, 
           p.price AS unit_price,
           c.name AS category_name, 
           oi.quantity,
           (p.price * oi.quantity) AS line_total
    FROM orders o
    LEFT JOIN users u ON o.sender_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE o.sender_type = 'client' AND o.receiver_type = 'admin'
    ORDER BY o.created_at DESC
");

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th>ID</th>
            <th>Client</th>
            <th>Product</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['client_name'] ?? 'Client #'.$row['sender_id']) . "</td>";
        echo "<td>" . $row['product_name'] . "</td>";
        echo "<td>" . $row['category_name'] . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "<td>" . $row['unit_price'] . "</td>";
        echo "<td>" . $row['total_amount'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucune commande client trouvée.</p>";
}

// Vérifier si des order_items existent
echo "<h3>Vérification des order_items</h3>";
$result = $conn->query("SELECT * FROM order_items ORDER BY order_id DESC");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>ID</th>
        <th>order_id</th>
        <th>product_id</th>
        <th>quantity</th>
        <th>price (si existe)</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . ($row['id'] ?? 'N/A') . "</td>";
    echo "<td>" . $row['order_id'] . "</td>";
    echo "<td>" . $row['product_id'] . "</td>";
    echo "<td>" . $row['quantity'] . "</td>";
    echo "<td>" . ($row['price'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";
?>