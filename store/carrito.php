<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

date_default_timezone_set('America/Bogota');

// Verify user is logged in as client
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
    $_SESSION['redirect_to'] = "carrito.php";
    $_SESSION['error'] = "Debes iniciar sesión como cliente para ver el carrito";
    header("Location: login.php");
    exit;
}

// Function to get cart items from database
function obtenerCarrito($usuario_id) {
    global $pdo;
    
    $sql = "SELECT c.id, c.producto_id, c.talla_id, c.cantidad, c.fecha_agregado, c.fecha_expiracion,
                   p.nombre, p.descripcion, p.precio_normal, p.precio_descuento, p.imagen, 
                   p.descuento_inicio, p.descuento_fin, p.stock,
                   t.nombre as talla_nombre
            FROM carrito c
            JOIN productos p ON c.producto_id = p.id
            LEFT JOIN tallas t ON c.talla_id = t.id
            WHERE c.usuario_id = :usuario_id AND c.fecha_expiracion > NOW()
            ORDER BY c.fecha_agregado DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process cart updates
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cart'])) {
    foreach($_POST['cantidades'] as $carrito_id => $cantidad) {
        $cantidad = (int)$cantidad;
        $carrito_id = (int)$carrito_id;
        
        // Verify item belongs to current user
        $stmt = $pdo->prepare("SELECT c.*, p.stock 
                              FROM carrito c
                              JOIN productos p ON c.producto_id = p.id
                              WHERE c.id = ? AND c.usuario_id = ?");
        $stmt->execute([$carrito_id, $_SESSION['usuario_id']]);
        $item = $stmt->fetch();
        
        if($item && $cantidad > 0 && $cantidad <= $item['stock']) {
            // Update quantity in database
            $stmt = $pdo->prepare("UPDATE carrito 
                                  SET cantidad = ?, fecha_expiracion = NOW() + INTERVAL 3 DAY 
                                  WHERE id = ?");
            $stmt->execute([$cantidad, $carrito_id]);
        }
        
        // Update size if provided
        if(isset($_POST['talla_id'][$carrito_id])) {
            $talla_id = (int)$_POST['talla_id'][$carrito_id];
            $stmt = $pdo->prepare("UPDATE carrito SET talla_id = ? WHERE id = ?");
            $stmt->execute([$talla_id, $carrito_id]);
        }
    }
    
    $_SESSION['mensaje'] = "Carrito actualizado";
    header("Location: carrito.php");
    exit;
}

// Process item removal
if(isset($_GET['remove'])) {
    $carrito_id = (int)$_GET['remove'];
    
    // Verify item belongs to current user before deleting
    $stmt = $pdo->prepare("DELETE FROM carrito WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$carrito_id, $_SESSION['usuario_id']]);
    
    if($stmt->rowCount() > 0) {
        $_SESSION['mensaje'] = "Producto eliminado del carrito";
    } else {
        $_SESSION['error'] = "No se pudo eliminar el producto";
    }
    
    header("Location: carrito.php");
    exit;
}

// Get cart items
$items_carrito = obtenerCarrito($_SESSION['usuario_id']);

// Calculate totals
$subtotal = 0;
$total_items = 0;

foreach($items_carrito as $item) {
    // Use discounted price if available and valid
    $precio = $item['precio_normal'];
    if($item['precio_descuento'] && $item['descuento_fin']) {
        $now = new DateTime();
        $fin_descuento = new DateTime($item['descuento_fin']);
        if($now < $fin_descuento) {
            $precio = $item['precio_descuento'];
        }
    }
    
    $subtotal += $precio * $item['cantidad'];
    $total_items += $item['cantidad'];
}

// Shipping cost
$envio_minimo = 199900; // Free shipping threshold
$envio = $subtotal > $envio_minimo ? 0 : 15000; // Shipping cost
$total = $subtotal + $envio;

// Function to get available sizes for a product
function obtenerTallasDisponibles($producto_id) {
    global $pdo;
    
    $sql = "SELECT pt.talla_id, t.nombre, pt.stock 
            FROM producto_tallas pt
            JOIN tallas t ON pt.talla_id = t.id
            WHERE pt.producto_id = :producto_id AND pt.stock > 0
            ORDER BY t.orden";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Format price
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Get featured products
function obtenerProductosDestacados($limite = 4) {
    global $pdo;
    $sql = "SELECT p.*, 
                   (SELECT nombre FROM categorias WHERE categoria_id = p.categoria_id) as categoria_nombre
            FROM productos p 
            WHERE p.destacado = 1 
            AND EXISTS (SELECT 1 FROM producto_tallas pt WHERE pt.producto_id = p.id AND pt.stock > 0)
            ORDER BY RAND() 
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/asesoria.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
        }
        
        .product-img-cart {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 600;
            border: none;
        }
        
        .btn-custom:hover {
            background-color: #333333;
            color: white;
        }
        
        .quantity-selector {
            width: 70px;
            text-align: center;
        }
        
        .talla-selector {
            width: 100px;
            text-align: center;
        }
        
        .summary-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .free-shipping {
            color: #28a745;
            font-weight: bold;
        }
        
        .size-option {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .size-option:hover {
            border-color: var(--primary-color);
        }
        
        .size-option.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .size-option.unavailable {
            color: #ccc;
            border-color: #eee;
            cursor: not-allowed;
            position: relative;
        }
        
        .size-option.unavailable::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 1px;
            background-color: var(--accent-color);
            transform: rotate(-15deg);
        }
        
        .current-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .original-price {
            font-size: 0.9rem;
            text-decoration: line-through;
            color: var(--secondary-color);
            margin-left: 5px;
        }
        
        .discount-percentage {
            background-color: var(--accent-color);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        
        .countdown-timer-sm {
            font-size: 0.8rem;
            color: #dc3545;
        }
        
        .countdown-timer-sm span {
            display: inline-block;
            min-width: 1.8em;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .product-img-cart {
                width: 60px;
                height: 60px;
            }
            
            .summary-card {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Tu Carrito de Compras</h2>
                
                <?php if(isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(empty($items_carrito)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-shopping-cart me-2"></i> Tu carrito está vacío
                    </div>
                    <a href="productos.php" class="btn btn-custom">
                        <i class="fas fa-arrow-left me-2"></i> Continuar comprando
                    </a>
                <?php else: ?>
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Precio</th>
                                        <th>Talla</th>
                                        <th>Cantidad</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items_carrito as $item): 
                                        $tallas_disponibles = obtenerTallasDisponibles($item['producto_id']);
                                        
                                        // Determine current price
                                        $precio_actual = $item['precio_normal'];
                                        if($item['precio_descuento'] && $item['descuento_fin']) {
                                            $now = new DateTime();
                                            $fin_descuento = new DateTime($item['descuento_fin']);
                                            if($now < $fin_descuento) {
                                                $precio_actual = $item['precio_descuento'];
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($item['imagen']); ?>" 
                                                         class="product-img-cart me-3" 
                                                         alt="<?php echo htmlspecialchars($item['nombre']); ?>"
                                                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($item['descripcion']); ?></small><br>
                                                        <small class="text-muted">Disponible: <?php echo $item['stock']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($item['precio_descuento'] && $item['descuento_fin'] && new DateTime() < new DateTime($item['descuento_fin'])): ?>
                                                    <span class="current-price"><?php echo formatearPrecio($item['precio_descuento']); ?></span>
                                                    <span class="original-price"><?php echo formatearPrecio($item['precio_normal']); ?></span>
                                                    <span class="discount-percentage">
                                                        <?php echo round((($item['precio_normal'] - $item['precio_descuento']) / $item['precio_normal'] * 100)); ?>% OFF
                                                    </span>
                                                <?php else: ?>
                                                    <span class="current-price"><?php echo formatearPrecio($item['precio_normal']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($tallas_disponibles)): ?>
                                                    <select name="talla_id[<?php echo $item['id']; ?>]" class="form-select talla-selector">
                                                        <?php foreach($tallas_disponibles as $talla): ?>
                                                            <option value="<?php echo $talla['talla_id']; ?>" 
                                                                <?php echo (!empty($item['talla_id']) && $item['talla_id'] == $talla['talla_id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($talla['nombre']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php if(!empty($item['talla_nombre'])): ?>
                                                        <small class="text-muted">Actual: <?php echo htmlspecialchars($item['talla_nombre']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">Única</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select name="cantidades[<?php echo $item['id']; ?>]" class="form-select quantity-selector">
                                                    <?php 
                                                    $max_cantidad = min(10, $item['stock']);
                                                    for($i = 1; $i <= $max_cantidad; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo $i == $item['cantidad'] ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <?php echo formatearPrecio($precio_actual * $item['cantidad']); ?>
                                            </td>
                                            <td>
                                                <a href="carrito.php?remove=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar este producto de tu carrito?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <a href="productos.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Continuar comprando
                            </a>
                            <button type="submit" name="update_cart" class="btn btn-outline-primary">
                                <i class="fas fa-sync-alt me-2"></i> Actualizar carrito
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="summary-card sticky-top" style="top: 20px;">
                    <h4 class="mb-4">Resumen del Pedido</h4>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?php echo $total_items; ?> items):</span>
                        <span><?php echo formatearPrecio($subtotal); ?></span>
                    </div>
                    
                    <?php if($subtotal > $envio_minimo): ?>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Envío:</span>
                            <span class="free-shipping">¡GRATIS!</span>
                        </div>
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-check-circle me-2"></i> ¡Felicidades! Tu compra califica para envío gratis en Cartagena.
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Envío:</span>
                            <span><?php echo formatearPrecio($envio); ?></span>
                        </div>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i> Agrega <?php echo formatearPrecio($envio_minimo - $subtotal); ?> más a tu carrito para obtener envío gratis.
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <h5>Total:</h5>
                        <h5><?php echo formatearPrecio($total); ?></h5>
                    </div>
                    
                    <?php if(!empty($items_carrito)): ?>
                        <a href="checkout.php" class="btn btn-custom w-100 mb-2">
                            <i class="fas fa-credit-card me-2"></i> Proceder al pago
                        </a>
                        <a href="https://wa.me/573123456789?text=Hola,%20quiero%20confirmar%20mi%20compra%20del%20carrito" class="btn btn-success w-100">
                            <i class="fab fa-whatsapp me-2"></i> Comprar por WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Featured Products -->
        <?php $productos_destacados = obtenerProductosDestacados(); ?>
        <?php if(!empty($productos_destacados)): ?>
            <section class="mt-5">
                <h3 class="mb-4">Productos que podrían interesarte</h3>
                <div class="row">
                    <?php foreach($productos_destacados as $producto): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>">
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                </a>
                                
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($producto['nombre']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text">
                                        <?php if($producto['precio_descuento'] && $producto['descuento_fin'] && new DateTime() < new DateTime($producto['descuento_fin'])): ?>
                                            <span class="fw-bold"><?php echo formatearPrecio($producto['precio_descuento']); ?></span>
                                            <span class="original-price"><?php echo formatearPrecio($producto['precio_normal']); ?></span>
                                            <span class="discount-percentage">
                                                <?php echo round((($producto['precio_normal'] - $producto['precio_descuento']) / $producto['precio_normal'] * 100)); ?>% OFF
                                            </span>
                                            <!-- Countdown timer for offers -->
                                            <div class="countdown-timer-sm mt-2" 
                                                 data-end-date="<?php echo htmlspecialchars($producto['descuento_fin']); ?>">
                                                <small class="text-muted d-block">Termina en:</small>
                                                <small class="fw-bold">
                                                    <span class="days">00</span>d 
                                                    <span class="hours">00</span>h 
                                                    <span class="minutes">00</span>m
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <span class="fw-bold"><?php echo formatearPrecio($producto['precio_normal']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <a href="detalles-producto.php?id=<?php echo $producto['id']; ?>" class="btn btn-outline-dark w-100">
                                        Ver detalle
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Handle size selection in cart
        document.addEventListener('DOMContentLoaded', function() {
            // Update quantity selectors when size changes
            $('.talla-selector').change(function() {
                const carritoId = $(this).attr('name').match(/\[(\d+)\]/)[1];
                const tallaId = $(this).val();
                
                // Here you could make an AJAX call to verify stock for the new size
                // and update the quantity selector if needed
                
                // Example AJAX call:
                /*
                $.ajax({
                    url: 'ajax/check-stock.php',
                    method: 'POST',
                    data: {
                        producto_id: $(this).data('producto-id'),
                        talla_id: tallaId
                    },
                    success: function(response) {
                        const select = $('select[name="cantidades[' + carritoId + ']"]');
                        select.empty();
                        
                        const max = Math.min(10, response.stock);
                        for(let i = 1; i <= max; i++) {
                            select.append(new Option(i, i));
                        }
                    }
                });
                */
            });
            
            // Update all countdown timers for featured products
            function updateAllCountdowns() {
                $('.countdown-timer-sm').each(function() {
                    const endDate = new Date($(this).data('end-date'));
                    const now = new Date();
                    const distance = endDate - now;
                    
                    if (distance < 0) {
                        $(this).html('<small class="text-danger">Oferta terminada</small>');
                        return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    
                    $(this).find('.days').text(days.toString().padStart(2, '0'));
                    $(this).find('.hours').text(hours.toString().padStart(2, '0'));
                    $(this).find('.minutes').text(minutes.toString().padStart(2, '0'));
                });
            }
            
            // Update countdowns immediately and then every minute
            updateAllCountdowns();
            setInterval(updateAllCountdowns, 60000);
        });
    </script>
</body>
</html>