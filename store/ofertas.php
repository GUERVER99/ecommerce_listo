<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

// Obtener productos con descuento activo
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT p.*, c.nombre as categoria_nombre 
                       FROM productos p 
                       LEFT JOIN categorias c ON p.categoria_id = c.categoria_id
                       WHERE p.precio_descuento IS NOT NULL 
                       AND p.descuento_inicio <= ? 
                       AND p.descuento_fin >= ?
                       ORDER BY p.descuento_fin ASC");
$stmt->execute([$now, $now]);
$productos_oferta = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar imágenes adicionales
foreach ($productos_oferta as &$producto) {
    if (!empty($producto['imagenes_adicionales'])) {
        $producto['imagenes_adicionales'] = json_decode($producto['imagenes_adicionales'], true);
    } else {
        $producto['imagenes_adicionales'] = [];
    }
}
unset($producto);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas Especiales - Khalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="includes/asesoria.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
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
        }
        
        .discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
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
        
        .countdown-badge {
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--accent-color);
            margin-top: 5px;
            display: inline-block;
        }
        
        .no-offers-banner {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin: 50px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container my-5">
        <h1 class="text-center mb-4">Ofertas Especiales</h1>
        
        <?php if(count($productos_oferta) > 0): ?>
            <div class="row">
                <?php foreach($productos_oferta as $producto): ?>
                    <div class="col-md-4 col-lg-3 mb-4">
                        <div class="product-card card h-100">
                            <div class="product-img-container">
                                <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                    class="product-img" 
                                    alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                    onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                
                                <div class="discount-badge">
                                    <?php 
                                    $descuento = round((1 - ($producto['precio_descuento'] / $producto['precio_normal'])) * 100);
                                    echo "-$descuento%";
                                    ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="price">$<?php echo number_format($producto['precio_descuento'], 0, ',', '.'); ?></span>
                                    <span class="old-price">$<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="countdown-badge" id="countdown-<?php echo $producto['id']; ?>">
                                    <i class="fas fa-clock me-1"></i>
                                    <span class="days">00</span>d 
                                    <span class="hours">00</span>h 
                                    <span class="minutes">00</span>m 
                                    <span class="seconds">00</span>s
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>" class="btn btn-custom w-100">Ver oferta</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-offers-banner">
                <h3><i class="fas fa-tag me-2"></i>No hay ofertas disponibles</h3>
                <p class="mt-3">Actualmente no tenemos promociones activas, pero vuelve pronto para descubrir nuestras próximas ofertas.</p>
                <a href="productos.php" class="btn btn-custom mt-3">Ver todos los productos</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if(count($productos_oferta) > 0): ?>
    <script>
    // Inicializar todos los contadores
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach($productos_oferta as $producto): ?>
            startCountdown(
                "<?php echo $producto['descuento_fin']; ?>", 
                "countdown-<?php echo $producto['id']; ?>"
            );
        <?php endforeach; ?>
    });
    
    // Función para iniciar contador individual
    function startCountdown(endDate, elementId) {
        function update() {
            const now = new Date().getTime();
            const distance = new Date(endDate).getTime() - now;
            const element = document.getElementById(elementId);
            
            if (distance < 0) {
                element.innerHTML = '<i class="fas fa-clock me-1"></i>Oferta finalizada';
                element.style.color = 'var(--secondary-color)';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const countdownElement = document.getElementById(elementId);
            countdownElement.querySelector('.days').textContent = days.toString().padStart(2, '0');
            countdownElement.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
            countdownElement.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
            countdownElement.querySelector('.seconds').textContent = seconds.toString().padStart(2, '0');
        }
        
        update();
        setInterval(update, 1000);
    }
    </script>
    <?php endif; ?>
</body>
</html>