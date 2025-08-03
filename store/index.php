<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

// Obtener productos destacados
$stmt = $conn->prepare("SELECT * FROM productos WHERE destacado = TRUE ORDER BY fecha_creacion DESC");
$stmt->execute();
$allProductos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar imágenes adicionales
foreach ($allProductos as &$producto) {
    if (!empty($producto['imagenes_adicionales'])) {
        $producto['imagenes_adicionales'] = json_decode($producto['imagenes_adicionales'], true);
    } else {
        $producto['imagenes_adicionales'] = [];
    }
}
unset($producto); // Romper la referencia

// Dividir productos en grupos de 8 para la rotación
$productGroups = array_chunk($allProductos, 8);
$currentProductGroup = 0;

// Lista de banners
$banners = [
    'assets/img/logo/banner1.jpg',
    'assets/img/logo/banner2.jpg',
    'assets/img/logo/banner3.jpg'
];
$currentBanner = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khalo's Style Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/asesoria.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        /* Hero Section Actualizado */
        .hero-section {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: white;
            padding: 120px 0;
            margin-bottom: 40px;
            min-height: 500px;
            display: flex;
            align-items: center;
            text-align: center;
            transition: background-image 1s ease-in-out;
        }

        .hero-section .container {
            position: relative;
            z-index: 2;
        }

        .hero-section h1 {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .hero-section p.lead {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .product-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .product-img-container {
            height: 200px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-img {
            height: 100%;
            width: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            transition: transform 0.5s ease-in-out;
        }

        .price {
            font-weight: 700;
            color: var(--primary-color);
        }

        .old-price {
            text-decoration: line-through;
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-custom:hover {
            background-color: #333333;
            color: white;
            transform: translateY(-2px);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #333333;
            border-color: #333333;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        footer {
            background-color: #f8f9fa;
            padding: 30px 0;
            margin-top: 50px;
        }

        /* Animación para productos */
        .product-group {
            transition: opacity 0.5s ease-in-out;
        }

        /* Media Queries */
        @media (max-width: 992px) {
            .hero-section {
                min-height: 400px;
                padding: 100px 0;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 20px;
                min-height: 350px;
                background-position: center center;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .hero-section p.lead {
                font-size: 1.2rem;
            }
            
            .product-card {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                min-height: 300px;
                padding: 60px 20px;
            }
            
            .hero-section h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section text-center" id="hero-banner">
        <div class="container">
            <h1 class="display-4 fw-bold" id="banner-title">ADIZERO SL</h1>
            <p class="lead" id="banner-subtitle">Zapatillas de Running de alto rendimiento</p>
            <a href="productos.php" class="btn btn-light btn-lg mt-3">Ver colección</a>
        </div>
    </section>

    <!-- Productos destacados -->
    <section class="container mb-5">
        <h2 class="text-center mb-4">Productos Destacados</h2>
        <div id="product-container">
            <?php if(count($productGroups) > 0): ?>
                <div class="row product-group" id="product-group-0">
                    <?php foreach($productGroups[0] as $producto): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="product-card card h-100">
                                <div class="product-img-container">
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                        class="product-img main-img" 
                                        alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        data-product-id="<?php echo $producto['id']; ?>"
                                        onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                    
                                    <?php if(!empty($producto['imagenes_adicionales'])): ?>
                                        <?php foreach($producto['imagenes_adicionales'] as $index => $imagen): ?>
                                            <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($imagen); ?>" 
                                                class="product-img extra-img" 
                                                alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                data-product-id="<?php echo $producto['id']; ?>"
                                                style="transform: translateX(100%); z-index: <?php echo $index + 1; ?>;"
                                                onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $producto['nombre']; ?></h5>
                                    <p class="card-text"><?php echo substr($producto['descripcion'], 0, 60) . '...'; ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price">$<?php echo number_format($producto['precio_descuento'] ?: $producto['precio_normal'], 0, ',', '.'); ?></span>
                                        <?php if($producto['precio_descuento']): ?>
                                            <span class="old-price">$<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>" class="btn btn-custom w-100">Ver detalle</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="productos.php" class="btn btn-outline-primary">Ver todos los productos</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Khalo's Style</h5>
                    <p>Tienda especializada en productos de alta calidad.</p>
                </div>
                <div class="col-md-4">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> juanvillalobos013@gmail.com</li>
                        <li><i class="fas fa-phone me-2"></i> +57 3025396418</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Cartagena, Colombia</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Khalo's Style. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>

        // Verificar y mostrar notificación de descuentos
        function checkDiscounts() {
            fetch('check-discounts.php')
                .then(response => response.json())
                .then(data => {
                    if(data.hasDiscounts) {
                        // Mostrar notificación si no está visible
                        if(!document.querySelector('.discount-alert')) {
                            const navbar = document.querySelector('.navbar .container');
                            const discountAlert = document.createElement('div');
                            discountAlert.className = 'mx-3 discount-alert';
                            discountAlert.innerHTML = `
                                <a href="ofertas.php" class="text-danger text-decoration-none">
                                    <i class="fas fa-fire me-1"></i>
                                    <span class="d-none d-md-inline">OFERTAS</span> 
                                    <span id="countdown">
                                        <span id="days">00</span>d 
                                        <span id="hours">00</span>h 
                                        <span id="minutes">00</span>m 
                                        <span id="seconds">00</span>s
                                    </span>
                                </a>
                            `;
                            navbar.insertBefore(discountAlert, navbar.querySelector('.navbar-toggler'));
                            
                            // Iniciar contador
                            startCountdown(data.endDate);
                        }
                    } else {
                        // Eliminar notificación si no hay descuentos
                        const alert = document.querySelector('.discount-alert');
                        if(alert) alert.remove();
                    }
                });
        }

        // Función para iniciar contador
        function startCountdown(endDate) {
            function update() {
                const now = new Date().getTime();
                const distance = new Date(endDate).getTime() - now;
                
                if (distance < 0) {
                    document.getElementById("countdown").innerHTML = "¡Ofertas finalizadas!";
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
                document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
                document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
                document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
            }
            
            update();
            setInterval(update, 1000);
        }

        // Verificar descuentos al cargar y cada minuto
        document.addEventListener('DOMContentLoaded', checkDiscounts);
        setInterval(checkDiscounts, 60000);
        // Datos para los banners
        const banners = [
            {
                image: "assets/img/logo/banner2.jpg",
            },
            {
                image: "assets/img/logo/banner1.jpg",
                title: "NUEVA COLECCIÓN",
                subtitle: "Descubre nuestras últimas novedades"
            },
            {
                image: "assets/img/logo/banner3.png",
                title: "OFERTAS ESPECIALES",
                subtitle: "No te pierdas nuestras promociones exclusivas"
            }
        ];
        
        // Datos para los productos (convertimos el array PHP a JavaScript)
        const productGroups = <?php echo json_encode($productGroups); ?>;
        
        let currentBannerIndex = 0;
        let currentProductGroupIndex = 0;
        
        // Función para cambiar el banner
        function changeBanner() {
            currentBannerIndex = (currentBannerIndex + 1) % banners.length;
            const banner = banners[currentBannerIndex];
            
            const heroSection = document.getElementById('hero-banner');
            heroSection.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('${banner.image}')`;
            
            document.getElementById('banner-title').textContent = banner.title;
            document.getElementById('banner-subtitle').textContent = banner.subtitle;
        }
        
        // Función para rotar los productos
        function rotateProducts() {
            if (productGroups.length <= 1) return;
            
            const productContainer = document.getElementById('product-container');
            
            // Ocultar el grupo actual
            const currentGroup = document.getElementById(`product-group-${currentProductGroupIndex}`);
            if (currentGroup) {
                currentGroup.style.opacity = '0';
            }
            
            // Calcular el siguiente grupo
            currentProductGroupIndex = (currentProductGroupIndex + 1) % productGroups.length;
            
            // Crear nuevo grupo si no existe
            let nextGroup = document.getElementById(`product-group-${currentProductGroupIndex}`);
            if (!nextGroup) {
                nextGroup = document.createElement('div');
                nextGroup.className = 'row product-group';
                nextGroup.id = `product-group-${currentProductGroupIndex}`;
                nextGroup.style.opacity = '0';
                
                const products = productGroups[currentProductGroupIndex];
                let html = '';
                
                products.forEach(product => {
                    html += `
                        <div class="col-md-3 col-sm-6">
                            <div class="product-card card h-100">
                                <div class="product-img-container">
                                    <img src="${PRODUCT_IMAGES_PATH}${product.imagen}" 
                                        class="product-img main-img" 
                                        alt="${product.nombre}"
                                        data-product-id="${product.id}"
                                        onerror="this.src='${ASSETS_PATH}img/placeholder.jpg'">
                                    
                                    ${product.imagenes_adicionales && product.imagenes_adicionales.length > 0 ? 
                                        product.imagenes_adicionales.map((img, idx) => `
                                            <img src="${PRODUCT_IMAGES_PATH}${img}" 
                                                class="product-img extra-img" 
                                                alt="${product.nombre}"
                                                data-product-id="${product.id}"
                                                style="transform: translateX(100%); z-index: ${idx + 1};"
                                                onerror="this.src='${ASSETS_PATH}img/placeholder.jpg'">
                                        `).join('') : ''}
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">${product.nombre}</h5>
                                    <p class="card-text">${product.descripcion.substring(0, 60)}...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price">$${product.precio_descuento ? product.precio_descuento.toLocaleString() : product.precio_normal.toLocaleString()}</span>
                                        ${product.precio_descuento ? `<span class="old-price">$${product.precio_normal.toLocaleString()}</span>` : ''}
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <a href="detalles-producto.php?id=${product.id}" class="btn btn-custom w-100">Ver detalle</a>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                nextGroup.innerHTML = html;
                productContainer.appendChild(nextGroup);
            }
            
            // Mostrar el nuevo grupo después de un breve retraso
            setTimeout(() => {
                if (currentGroup) {
                    currentGroup.style.display = 'none';
                }
                nextGroup.style.display = 'flex';
                nextGroup.style.opacity = '1';
            }, 500);
        }
        
        // Función para rotar las imágenes de los productos
        function rotateProductImages() {
            document.querySelectorAll('.product-img-container').forEach(container => {
                const images = container.querySelectorAll('.product-img');
                if (images.length <= 1) return;
                
                // Encontrar la imagen actualmente visible
                let currentIndex = 0;
                for (let i = 0; i < images.length; i++) {
                    if (images[i].style.transform === 'translateX(0%)') {
                        currentIndex = i;
                        break;
                    }
                }
                
                // Calcular el siguiente índice
                const nextIndex = (currentIndex + 1) % images.length;
                
                // Ocultar la imagen actual (desliza hacia la izquierda)
                images[currentIndex].style.transform = 'translateX(-100%)';
                
                // Mostrar la siguiente imagen (desliza desde la derecha)
                images[nextIndex].style.transform = 'translateX(0%)';
            });
        }
        
        // Iniciar rotación de banners cada 2 segundos
        setInterval(changeBanner, 2000);
        
        // Iniciar rotación de productos cada 3 segundos (solo si hay más de un grupo)
        if (productGroups.length > 1) {
            setInterval(rotateProducts, 3000);
        }
        
        // Iniciar rotación de imágenes de productos cada 2 segundos
        setInterval(rotateProductImages, 2000);
        
        // Cambiar el banner inmediatamente al cargar la página
        document.addEventListener('DOMContentLoaded', () => {
            changeBanner();
            
            // Si hay más de un grupo de productos, preparar la rotación
            if (productGroups.length > 1) {
                document.getElementById('product-group-0').style.opacity = '1';
            }
            
            // Inicializar las imágenes de los productos
            document.querySelectorAll('.product-img-container').forEach(container => {
                const images = container.querySelectorAll('.product-img');
                if (images.length > 0) {
                    // Mostrar solo la primera imagen inicialmente
                    images[0].style.transform = 'translateX(0%)';
                    
                    // Ocultar las demás imágenes
                    for (let i = 1; i < images.length; i++) {
                        images[i].style.transform = 'translateX(100%)';
                    }
                }
            });
        });
    </script>
</body>
</html>