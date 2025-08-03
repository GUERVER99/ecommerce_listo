<!-- Widget de WhatsApp -->
    <div class="whatsapp-widget">
    <div class="whatsapp-button">
        <i class="fab fa-whatsapp"></i>
    </div>
    <div class="whatsapp-bubble">
        <div class="whatsapp-header">
        <i class="fab fa-whatsapp"></i>
        <h4>Asesoría Personalizada</h4>
        <button class="close-bubble">&times;</button>
        </div>
        <div class="whatsapp-content">
        <p>Selecciona un asesor:</p>
        <div class="advisor-list">
            <a href="https://wa.me/+573025396418?text=Hola%20Juan,%20me%20interesa%20recibir%20asesoría" target="_blank" class="advisor">
            <img src="assets/img/asesores/juan.jpg" alt="Juan Villalobos">
            <span>Juan Villalobos</span>
            </a>
        </div>
        </div>
    </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const whatsappWidget = document.querySelector('.whatsapp-widget');
    const whatsappButton = document.querySelector('.whatsapp-button');
    const closeBubble = document.querySelector('.close-bubble');
    
    // Alternar la burbuja
    whatsappButton.addEventListener('click', function(e) {
        e.stopPropagation();
        whatsappWidget.classList.toggle('active');
    });
    
    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.whatsapp-widget')) {
        whatsappWidget.classList.remove('active');
        }
    });
    
    // Cerrar con el botón
    closeBubble.addEventListener('click', function() {
        whatsappWidget.classList.remove('active');
    });
    
    // Asegurar que no tape contenido importante
    function checkPosition() {
        const footer = document.querySelector('footer');
        if (footer) {
        const footerRect = footer.getBoundingClientRect();
        const widgetRect = whatsappWidget.getBoundingClientRect();
        
        if (widgetRect.bottom > footerRect.top) {
            whatsappWidget.style.bottom = `${window.innerHeight - footerRect.top + 20}px`;
        } else {
            whatsappWidget.style.bottom = '30px';
        }
        }
    }
    
    // Verificar posición al cargar y al hacer scroll
    window.addEventListener('load', checkPosition);
    window.addEventListener('scroll', checkPosition);
    window.addEventListener('resize', checkPosition);
    });
    </script>