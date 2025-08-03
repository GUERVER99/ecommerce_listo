<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Khalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/asesoria.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #000000;
            --color-secondary: #D4AF37; /* Dorado */
            --color-light: #f8f9fa;
            --color-dark: #212529;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--color-primary);
            color: var(--color-light);
        }
        
        .gold-text {
            color: var(--color-secondary);
        }
        
        .gold-border {
            border-color: var(--color-secondary) !important;
        }
        
        .gold-bg {
            background-color: var(--color-secondary);
        }
        
        .contact-card {
            background-color: var(--color-dark);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
            transition: transform 0.3s ease;
            border: 2px solid var(--color-secondary);
        }
        
        .contact-card:hover {
            transform: translateY(-10px);
        }
        
        .developer-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--color-secondary);
            margin: 0 auto;
            display: block;
        }
        
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--color-secondary);
            color: var(--color-primary);
            font-size: 1.2rem;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.7);
        }
        
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 30px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            width: 50%;
            height: 3px;
            background: var(--color-secondary);
            bottom: -10px;
            left: 25%;
        }
        
        .btn-gold {
            background-color: var(--color-secondary);
            color: var(--color-primary);
            font-weight: 600;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        
        .btn-gold:hover {
            background-color: #c9a227;
            color: var(--color-primary);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }
        
        .contact-form input,
        .contact-form textarea {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            color: white;
        }
        
        .contact-form input:focus,
        .contact-form textarea:focus {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: var(--color-secondary);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(212, 175, 55, 0.25);
        }
        
        .contact-form label {
            color: var(--color-secondary);
        }
        
        @media (max-width: 768px) {
            .developer-img {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="py-5" style="background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/img/logo/banner1.jpg') no-repeat center center; background-size: cover;">
        <div class="container py-5 text-center">
            <h1 class="display-4 fw-bold gold-text">Contacto</h1>
            <p class="lead">Conéctate con nuestro equipo de desarrollo</p>
        </div>
    </section>

    <!-- Developer Contact Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-5">
                    <h2 class="section-title gold-text">Desarrollador del E-commerce</h2>
                    <p class="lead">Detrás de Khalo's Style hay un profesional apasionado por crear experiencias digitales excepcionales.</p>
                </div>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="contact-card p-5 text-center">
                        <!-- Foto del desarrollador -->
                        <img src="assets/img/logo/developer.png" alt="Gerlin Victor - Desarrollador" class="developer-img mb-4" onerror="this.src='assets/img/placeholder.jpg'">
                        
                        <!-- Nombre y título -->
                        <h3 class="gold-text mb-3">GERLIN VICTOR</h3>
                        <p class="text-muted mb-4">INGENIERO INFORMATICO</p>
                        
                        <!-- Redes sociales -->
                        <div class="social-links mb-4">
                            <a href="https://www.linkedin.com/in/ingerlin-victor/" target="_blank" class="social-icon" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="https://www.instagram.com/dev_cartacho/" target="_blank" class="social-icon" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="mailto:gerlinbarcasnegras@gmail.com" class="social-icon" title="Email">
                                <i class="fas fa-envelope"></i>
                            </a>
                        </div>
                        
                        <!-- Información de contacto -->
                        <div class="contact-info mb-4">
                            <p><i class="fas fa-phone gold-text me-2"></i> +57 3228495116</p>
                            <p><i class="fas fa-map-marker-alt gold-text me-2"></i> Cartagena, Colombia</p>
                        </div>
                        
                        <!-- Botón de contacto -->
                        <a href="mailto:gerlinbarcasnegras@gmail.com" class="btn btn-gold mt-3">
                            <i class="fas fa-paper-plane me-2"></i> Enviar mensaje
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-center">
        <div class="container">
            <p class="mb-0 gold-text">&copy; <?php echo date('Y'); ?> Khalo's Style. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efecto hover mejorado para los iconos sociales
        document.querySelectorAll('.social-icon').forEach(icon => {
            icon.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.2) translateY(-5px)';
                this.style.boxShadow = '0 0 20px rgba(212, 175, 55, 0.8)';
            });
            
            icon.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        });
        
        // Validación básica del formulario
        document.querySelector('.contact-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Gracias por tu mensaje. Nos pondremos en contacto contigo pronto.');
            this.reset();
        });
    </script>
</body>
</html>