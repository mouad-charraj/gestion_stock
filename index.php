<?php
require_once 'config.php';


// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login_form.php');
}

$conn = connectDB();

// Récupérer les catégories pour le filtre
$cat_stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->get_result();

// Préparer les paramètres de recherche
$search     = $_GET['search'] ?? '';
$cat_filter = intval($_GET['category'] ?? 0);

// Construire la requête dynamique
$sql    = "SELECT p.*, s.name AS supplier_name, c.name AS category_name
           FROM products p
           LEFT JOIN suppliers s ON p.supplier_id = s.id
           LEFT JOIN categories c ON p.category_id = c.id
           WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql      .= " AND p.name LIKE ?";
    $params[]  = "%{$search}%";
    $types    .= 's';
}
if ($cat_filter > 0) {
    $sql      .= " AND p.category_id = ?";
    $params[]  = $cat_filter;
    $types    .= 'i';
}
$sql .= " ORDER BY p.name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

$page_title = "Catalogue d'articles";
include 'includes/user_header.php';
?>

<div class="container-fluid mt-4">
  <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type']) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
  <?php endif; ?>

  <!-- Barre de recherche et filtre catégorie -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-6">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                 class="form-control" placeholder="Rechercher un article...">
        </div>
        <div class="col-md-4">
          <select name="category" class="form-select">
            <option value="0">Toutes catégories</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
              <option value="<?= $cat['id'] ?>" <?= $cat_filter === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-secondary w-100">
            <i class="fas fa-search"></i> Filtrer
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Grille des produits -->
  <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
    <?php if ($products->num_rows > 0): ?>
      <?php while ($product = $products->fetch_assoc()): ?>
        <div class="col" id="product-<?= $product['id'] ?>">
          <div class="card h-100 shadow-sm">
          <?php if (!empty($product['image'])): ?>
            <a href="images/<?= htmlspecialchars($product['image']) ?>" target="_blank" id="product-image-url">
              <img src="images/<?= htmlspecialchars($product['image']) ?>"
                  class="card-img-top p-3"
                   id="product-image-src"
                  alt="<?= htmlspecialchars($product['name']) ?>"
                  style="height: 200px; object-fit: contain;">
            </a>
            <?php else: ?>
              <div class="text-center p-3 bg-light" style="height: 200px;">
                <img src="assets/img/no-image.png" alt="Pas d'image" class="img-fluid">
              </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title" id="product-name"><?= htmlspecialchars($product['name']) ?></h5>
              <p class="card-text text-muted"  id="product-description">
                <?= substr(htmlspecialchars($product['description']), 0, 50) ?>
                <?= strlen($product['description']) > 50 ? '...' : '' ?>
              </p>
              <div class="mt-auto">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="h4 text-primary" id="product-price"><?= number_format($product['price'], 2) ?> €</span>
                  <span id="product-quantity" class="badge bg-<?= $product['quantity'] <= $product['min_quantity'] ? 'danger' : 'success' ?>">
                    Stock: <?= $product['quantity'] ?>
                  </span>
                </div>
                <form method="POST" action="add_to_cart.php">
                  <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                  <button type="submit" class="btn btn-primary w-100"
                          <?= $product['quantity'] <= 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-cart-plus"></i>
                    <?= $product['quantity'] > 0 ? 'Ajouter au panier' : 'Rupture de stock' ?>
                  </button>
                </form>
                <a href="produit.php?id=<?= $product['id'] ?>" class="btn btn-outline-secondary w-100 mt-2">
                  <i class="fas fa-info-circle"></i> Plus d'infos
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info text-center">Aucun article trouvé.</div>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
  const socket = new WebSocket('ws://localhost:8080');

  socket.onopen = function() {
      console.log('Connection established');
  };

  socket.onmessage = function(event) {
      console.log('Message received:', event.data);

      if(JSON.parse(event.data).type == 'product_created') {
        // Parse the product data from the WebSocket message
      const product = JSON.parse(event.data).content;

      // Create a new product card element
      const productCard = document.createElement('div');
      productCard.classList.add('col');
      productCard.id = `product-${product.id}`;
      productCard.innerHTML = `
        <div class="card h-100 shadow-sm">
          ${product.image ? 
            `<a href="images/${product.image}" target="_blank">
              <img src="images/${product.image}" class="card-img-top p-3" alt="${product.name}" style="height: 200px; object-fit: contain;">
            </a>` :
            `<div class="text-center p-3 bg-light" style="height: 200px;">
              <img src="assets/img/no-image.png" alt="Pas d'image" class="img-fluid">
            </div>`
          }
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">${product.name}</h5>
            <p class="card-text text-muted">
              <p class="card-text text-muted">
                ${product.description && product.description.length > 50 ? product.description.substring(0, 50) + '...' : product.description || 'Aucune description disponible'}
              </p>

            </p>
            <div class="mt-auto">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="h4 text-primary">${product.price} €</span>
                <span class="badge bg-${product.quantity <= 5 ? 'danger' : 'success'}">
                  Stock: ${product.quantity}
                </span>
              </div>
              <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="${product.id}">
                <button type="submit" class="btn btn-primary w-100" ${product.quantity <= 0 ? 'disabled' : ''}>
                  <i class="fas fa-cart-plus"></i> ${product.quantity > 0 ? 'Ajouter au panier' : 'Rupture de stock'}
                </button>
              </form>
              <a href="produit.php?id=${product.id}" class="btn btn-outline-secondary w-100 mt-2">
                <i class="fas fa-info-circle"></i> Plus d'infos
              </a>
            </div>
          </div>
        </div>
      `;

      // Append the new product card to the products grid
      const productsGrid = document.querySelector('.row.row-cols-1.row-cols-md-3.row-cols-lg-4.g-4');
      productsGrid.appendChild(productCard);
      }
      else if(JSON.parse(event.data).type == 'product_deleted') {
        const data = JSON.parse(event.data);
        const productElement = document.getElementById(`product-${data.product_id}`);
        if (productElement) {
            productElement.remove(); // This completely removes it from the DOM
        }
      }
      else if (JSON.parse(event.data).type === 'product_updated') {
        let data = JSON.parse(event.data);
        const productElement = document.getElementById(`product-${data.product_id}`);
        console.log(productElement);
        data = data.content;
        if (productElement) {
          // Update name
          const nameEl = productElement.querySelector('#product-name');
          if (nameEl) nameEl.textContent = data.name;

          // Update description
          const descEl = productElement.querySelector('#product-description');
          if (descEl) {
            const desc = data.description;
            descEl.textContent = desc.length > 50 ? desc.substring(0, 50) + '...' : desc;
          }

          // Update price
          const priceEl = productElement.querySelector('#product-price');
          if (priceEl) priceEl.textContent = `${parseFloat(data.price).toFixed(2)} €`;

          // Update quantity
          const quantityEl = productElement.querySelector('#product-quantity');
          if (quantityEl) {
            quantityEl.textContent = `Stock: ${data.quantity}`;
            quantityEl.className = `badge bg-${data.quantity <= data.min_quantity ? 'danger' : 'success'}`;
          }

          // Update image (if needed)
          if (data.image) {
            const imgLink = productElement.querySelector('#product-image-url');
            const imgEl = productElement.querySelector('#product-image-src');
            if (imgLink && imgEl) {
              imgLink.href = `images/${data.image}`;
              imgEl.src = `images/${data.image}`;
              imgEl.alt = data.name;
            }
          }

          // Update "Add to cart" button
          const addToCartBtn = productElement.querySelector('form button[type="submit"]');
          if (addToCartBtn) {
            if (data.quantity <= 0) {
              addToCartBtn.disabled = true;
              addToCartBtn.innerHTML = `<i class="fas fa-cart-plus"></i> Rupture de stock`;
            } else {
              addToCartBtn.disabled = false;
              addToCartBtn.innerHTML = `<i class="fas fa-cart-plus"></i> Ajouter au panier`;
            }
          }
        }
      }

      
  };

  socket.onerror = function(error) {
      console.log('WebSocket error:', error);
  };

  socket.onclose = function() {
      console.log('Connection closed');
  };
</script>






