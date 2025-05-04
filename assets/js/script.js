// Script principal pour l'application de gestion des stocks

document.addEventListener('DOMContentLoaded', function() {
    // Gestion des alertes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });

    // Toggle sidebar (pour responsive)
    const navToggle = document.querySelector('.nav-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    }

    // Modal
    const modalButtons = document.querySelectorAll('[data-toggle="modal"]');
    const closeButtons = document.querySelectorAll('.close, .modal-close');
    
    modalButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const target = document.querySelector(button.dataset.target);
            if (target) {
                target.style.display = 'block';
            }
        });
    });
    
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modal = button.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // Confirmation de suppression
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
            }
        });
    });

    // Validation des formulaires
    const forms = document.querySelectorAll('form.validate');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
                
                // Validation email
                if (field.type === 'email' && field.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value.trim())) {
                        valid = false;
                        field.classList.add('is-invalid');
                    }
                }
                
                // Validation confirmation mot de passe
                if (field.id === 'confirm_password') {
                    const password = document.getElementById('password');
                    if (password && field.value !== password.value) {
                        valid = false;
                        field.classList.add('is-invalid');
                    }
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Veuillez corriger les erreurs dans le formulaire.');
            }
        });
    });

    // Tableau de bord - Statistiques
    const ctx = document.getElementById('stockChart');
    if (ctx) {
        drawStockChart(ctx);
    }

    // Filtrage d'articles
    const searchInput = document.getElementById('searchArticle');
    if (searchInput) {
        searchInput.addEventListener('input', filterArticles);
    }

    // Gestion des quantités pour la commande
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(function(input) {
        const minusBtn = input.parentElement.querySelector('.quantity-minus');
        const plusBtn = input.parentElement.querySelector('.quantity-plus');
        
        minusBtn.addEventListener('click', function() {
            let value = parseInt(input.value);
            if (value > 1) {
                input.value = value - 1;
                updateOrderTotals();
            }
        });
        
        plusBtn.addEventListener('click', function() {
            let value = parseInt(input.value);
            let max = parseInt(input.dataset.max || 999);
            if (value < max) {
                input.value = value + 1;
                updateOrderTotals();
            }
        });
        
        input.addEventListener('change', function() {
            let value = parseInt(input.value);
            let max = parseInt(input.dataset.max || 999);
            
            if (isNaN(value) || value < 1) {
                input.value = 1;
            } else if (value > max) {
                input.value = max;
            }
            
            updateOrderTotals();
        });
    });
});

// Fonction pour filtrer les articles
function filterArticles() {
    const searchInput = document.getElementById('searchArticle');
    const filter = searchInput.value.toUpperCase();
    const articles = document.querySelectorAll('.article-card');
    
    articles.forEach(function(article) {
        const title = article.querySelector('.article-card-title').textContent;
        if (title.toUpperCase().indexOf(filter) > -1) {
            article.style.display = '';
        } else {
            article.style.display = 'none';
        }
    });
}

// Fonction pour mettre à jour les totaux de commande
function updateOrderTotals() {
    const items = document.querySelectorAll('.cart-item');
    let subtotal = 0;
    
    items.forEach(function(item) {
        const price = parseFloat(item.dataset.price);
        const quantity = parseInt(item.querySelector('.quantity-input').value);
        const total = price * quantity;
        
        item.querySelector('.item-total').textContent = total.toFixed(2) + ' €';
        subtotal += total;
    });
    
    const subtotalElement = document.getElementById('cart-subtotal');
    if (subtotalElement) {
        subtotalElement.textContent = subtotal.toFixed(2) + ' €';
    }
    
    const totalElement = document.getElementById('cart-total');
    if (totalElement) {
        totalElement.textContent = subtotal.toFixed(2) + ' €';
    }
}

// Fonction pour dessiner le graphique de stock
function drawStockChart(ctx) {
    // Simulation de données - à remplacer par des données réelles
    const stockData = {
        labels: ['Cat 1', 'Cat 2', 'Cat 3', 'Cat 4', 'Cat 5'],
        datasets: [{
            label: 'Niveaux de stock par catégorie',
            data: [45, 25, 60, 31, 89],
            backgroundColor: [
                'rgba(52, 152, 219, 0.6)',
                'rgba(46, 204, 113, 0.6)',
                'rgba(155, 89, 182, 0.6)',
                'rgba(241, 196, 15, 0.6)',
                'rgba(231, 76, 60, 0.6)'
            ],
            borderColor: [
                'rgba(52, 152, 219, 1)',
                'rgba(46, 204, 113, 1)',
                'rgba(155, 89, 182, 1)',
                'rgba(241, 196, 15, 1)',
                'rgba(231, 76, 60, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    // Créer un graphique simple (nécessite une bibliothèque comme Chart.js)
    // En version native sans bibliothèque, on pourrait juste simuler un graphique avec des divs
    if (window.Chart) {
        new Chart(ctx, {
            type: 'bar',
            data: stockData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    } else {
        // Version simple sans Chart.js
        const container = document.createElement('div');
        container.className = 'chart-fallback';
        
        stockData.labels.forEach((label, index) => {
            const bar = document.createElement('div');
            bar.className = 'chart-bar';
            bar.style.width = '18%';
            bar.style.marginRight = '2%';
            bar.style.backgroundColor = stockData.datasets[0].backgroundColor[index];
            bar.style.height = (stockData.datasets[0].data[index]) + 'px';
            
            const labelDiv = document.createElement('div');
            labelDiv.className = 'chart-label';
            labelDiv.textContent = label;
            
            const valueDiv = document.createElement('div');
            valueDiv.className = 'chart-value';
            valueDiv.textContent = stockData.datasets[0].data[index];
            
            bar.appendChild(valueDiv);
            container.appendChild(bar);
            container.appendChild(labelDiv);
        });
        
        ctx.parentNode.replaceChild(container, ctx);
    }
}