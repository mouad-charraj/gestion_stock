<footer style="
  background-color: #212529;
  color: white;
  padding: 15px 20px;
  width: 100%;
  margin-top : 20px;
  box-sizing: border-box;
  position: absolute;
  bottom: 0;
  left: 0;
">
  <div style="
    display: flex;
    justify-content: space-between;
    align-items: center;
  ">
    <p style="margin: 0;">© 2025 | ENSA Marrakech - Filière GCDSTE - Promo 2025</p>
    <p style="margin: 0;">Module : Ingénierie Web et Systèmes d'Information</p>
  </div>
</footer>

<script>
// Script pour ajuster la position du footer
document.addEventListener('DOMContentLoaded', function() {
  const footer = document.querySelector('footer');
  const body = document.body;
  const html = document.documentElement;
  
  function adjustFooter() {
    // Obtenir la hauteur de la page
    const pageHeight = Math.max(
      body.scrollHeight, body.offsetHeight,
      html.clientHeight, html.scrollHeight, html.offsetHeight
    );
    
    // Obtenir la hauteur de la fenêtre
    const windowHeight = window.innerHeight;
    
    // Si la page est plus haute que la fenêtre, désactiver la position absolue
    if (pageHeight > windowHeight) {
      footer.style.position = 'relative';
      body.style.paddingBottom = '0';
    } else {
      footer.style.position = 'absolute';
      body.style.paddingBottom = footer.offsetHeight + 'px';
    }
  }
  
  // Exécuter au chargement et au redimensionnement
  adjustFooter();
  window.addEventListener('resize', adjustFooter);
  
  // Vérifier périodiquement si le contenu change
  setInterval(adjustFooter, 500);
});
</script>