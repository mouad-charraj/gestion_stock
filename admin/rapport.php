<?php
require '../config.php';
$conn = connectDB();


if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

function getValue($conn, $query) {
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    return $row ? $row['total'] : 0;
}

function getTopProducts($conn) {
    return $conn->query("SELECT p.name, SUM(oi.quantity) AS total_qte FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY oi.product_id ORDER BY total_qte DESC LIMIT 10");
}

function getStockStatus($conn) {
    return $conn->query("SELECT name, quantity, min_quantity FROM products ORDER BY name ASC");
}

function getClientOrders($conn) {
    return $conn->query("SELECT * FROM orders WHERE sender_type='user' AND receiver_type='admin'");
}

function getSupplierOrders($conn) {
    return $conn->query("SELECT * FROM orders WHERE sender_type='admin' AND receiver_type='supplier'");
}

$revenus = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE sender_type='user'");
$depenses = getValue($conn, "SELECT SUM(total_amount) AS total FROM orders WHERE receiver_type='supplier'");
$benefice = $revenus - $depenses;
$topProducts = getTopProducts($conn);
$stockStatus = getStockStatus($conn);
$clientOrders = getClientOrders($conn);
$supplierOrders = getSupplierOrders($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Complet</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            max-width: 1000px;
            margin: auto;
        }
        h1, h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        .stat-box {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .box {
            flex: 1;
            background: #e3f2fd;
            margin: 0 10px;
            padding: 15px;
            border-left: 5px solid #2196F3;
            border-radius: 4px;
        }
        .export-buttons {
            margin-top: 30px;
            text-align: center;
        }
        .export-buttons a {
            text-decoration: none;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 0 10px;
        }
        .export-buttons a.pdf {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Rapport Complet des Transactions</h1>

    <div class="export-buttons">
        <a href="rapport_pdf.php" class="pdf">📄 Exporter en PDF</a>
        <a href="rapport_excel.php" class="excel">📊 Exporter en Excel</a>
    </div>

    <div class="stat-box">
        <div class="box"><strong>Revenus Totaux:</strong> <?= number_format($revenus, 2) ?> €</div>
        <div class="box"><strong>Dépenses Totales:</strong> <?= number_format($depenses, 2) ?> €</div>
        <div class="box"><strong>Bénéfice:</strong> <?= number_format($benefice, 2) ?> €</div>
    </div>

    <h2>Commandes Clients (Utilisateur → Admin)</h2>
    <table>
        <tr><th>ID</th><th>Nom</th><th>Montant Total</th><th>Status</th><th>Date</th></tr>
        <?php while($row = $clientOrders->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['name'] ?></td>
                <td><?= number_format($row['total_amount'], 2) ?>€</td>
                <td><?= $row['status'] ?></td>
                <td><?= $row['created_at'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Commandes Fournisseurs (Admin → Fournisseur)</h2>
    <table>
        <tr><th>ID</th><th>Nom</th><th>Montant Total</th><th>Status</th><th>Date</th></tr>
        <?php while($row = $supplierOrders->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= $row['name'] ?></td>
                <td><?= number_format($row['total_amount'], 2) ?>€</td>
                <td><?= $row['status'] ?></td>
                <td><?= $row['created_at'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Top 10 des Produits Vendus</h2>
    <table>
        <thead>
            <tr><th>Produit</th><th>Quantité Vendue</th></tr>
        </thead>
        <tbody>
        <?php while ($row = $topProducts->fetch_assoc()): ?>
            <tr><td><?= $row['name'] ?></td><td><?= $row['total_qte'] ?></td></tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h2>État des Stocks</h2>
    <table>
        <thead>
            <tr><th>Produit</th><th>Quantité Actuelle</th><th>Quantité Minimale</th><th>Statut</th></tr>
        </thead>
        <tbody>
        <?php while ($row = $stockStatus->fetch_assoc()): 
            $statut = ($row['quantity'] <= $row['min_quantity']) ? 'Stock faible' : 'OK'; ?>
            <tr><td><?= $row['name'] ?></td><td><?= $row['quantity'] ?></td><td><?= $row['min_quantity'] ?></td><td><?= $statut ?></td></tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
