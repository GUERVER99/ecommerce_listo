<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

date_default_timezone_set('America/Bogota');

// Verificar si se proporcionó un ID de producto
if(!isset($_GET['id'])) {
    header("Location: productos.php");
    exit;
}

$producto_id = (int)$_GET['id'];

// Obtener información del producto
$stmt = $conn->prepare("SELECT p.*, c.nombre as categoria_nombre 
                       FROM productos p 
                       LEFT JOIN categorias c ON p.categoria_id = c.categoria_id 
                       WHERE p.id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$producto) {
    header("Location: productos.php");
    exit;
}

// Verificar si el descuento ha expirado
$ahora = new DateTime();
if ($producto['descuento_fin']) {
    $fin_descuento = new DateTime($producto['descuento_fin']);
    if ($ahora > $fin_descuento) {
        // Desactivar el descuento si ha expirado
        $stmt = $conn->prepare("UPDATE productos SET precio_descuento = NULL, descuento_inicio = NULL, descuento_fin = NULL WHERE id = ?");
        $stmt->execute([$producto_id]);
        
        // Actualizar los datos del producto
        $producto['precio_descuento'] = null;
        $producto['descuento_inicio'] = null;
        $producto['descuento_fin'] = null;
    }
}

// Obtener imágenes adicionales del producto
$imagenes_adicionales = [];
if (!empty($producto['imagenes_adicionales'])) {
    $imagenes_adicionales = json_decode($producto['imagenes_adicionales'], true);
}

// Determinar el tipo de talla según la categoría del producto
$tipo_talla = 'camisa'; // Valor por defecto
if(!empty($producto['categoria_nombre'])) {
    $categoria_nombre = strtolower($producto['categoria_nombre']);
    
    if(strpos($categoria_nombre, 'pantalon') !== false) {
        $tipo_talla = 'pantalon';
    } elseif(strpos($categoria_nombre, 'zapato') !== false || strpos($categoria_nombre, 'calzado') !== false) {
        $tipo_talla = 'zapato';
    }
}

// Obtener tallas y stocks disponibles para este producto
$tallas_disponibles = [];
try {
    $stmt = $conn->prepare("SELECT t.id, t.nombre, pt.stock 
                           FROM producto_tallas pt 
                           JOIN tallas t ON pt.talla_id = t.id 
                           WHERE pt.producto_id = ? 
                           AND t.categoria_talla = ?
                           ORDER BY t.orden");
    $stmt->execute([$producto_id, $tipo_talla]);
    $tallas_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $_SESSION['error'] = "Error al obtener las tallas disponibles: " . $e->getMessage();
}

// Calcular stock total
$stock_total = array_sum(array_column($tallas_disponibles, 'stock'));

// Obtener productos relacionados (misma categoría)
$productos_relacionados = [];
if(!empty($producto['categoria_id'])) {
    $stmt_relacionados = $conn->prepare("SELECT p.*, c.nombre as categoria_nombre 
                                        FROM productos p 
                                        LEFT JOIN categorias c ON p.categoria_id = c.categoria_id 
                                        WHERE p.categoria_id = ? AND p.id != ? AND EXISTS (
                                            SELECT 1 FROM producto_tallas pt WHERE pt.producto_id = p.id AND pt.stock > 0
                                        )
                                        ORDER BY p.fecha_creacion DESC LIMIT 4");
    $stmt_relacionados->execute([$producto['categoria_id'], $producto_id]);
    $productos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
}

// Verificar si el producto está en favoritos
$en_favoritos = false;
if(isset($_SESSION['usuario_id'])) {
    $stmt_fav = $conn->prepare("SELECT id FROM favoritos WHERE usuario_id = ? AND producto_id = ?");
    $stmt_fav->execute([$_SESSION['usuario_id'], $producto_id]);
    $en_favoritos = $stmt_fav->rowCount() > 0;
}

// Procesar añadir/eliminar de favoritos
if(isset($_POST['toggle_favorite']) && isset($_SESSION['usuario_id'])) {
    if($en_favoritos) {
        $stmt = $conn->prepare("DELETE FROM favoritos WHERE usuario_id = ? AND producto_id = ?");
        $stmt->execute([$_SESSION['usuario_id'], $producto_id]);
        $_SESSION['mensaje'] = "Producto eliminado de favoritos";
        $en_favoritos = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO favoritos (usuario_id, producto_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $producto_id]);
        $_SESSION['mensaje'] = "Producto añadido a favoritos";
        $en_favoritos = true;
    }
    
    header("Location: detalles-producto.php?id=".$producto_id);
    exit;
}

// Procesar añadir al carrito (versión con base de datos)
if(isset($_POST['add_to_cart'])) {
    if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
        $_SESSION['redirect_to'] = "detalles-producto.php?id=".$producto_id;
        $_SESSION['error'] = "Debes iniciar sesión como cliente para añadir productos al carrito";
        header("Location: login.php");
        exit;
    }
    
    $cantidad = (int)$_POST['cantidad'];
    $talla_id = isset($_POST['talla_id']) ? (int)$_POST['talla_id'] : null;
    $talla_nombre = isset($_POST['talla_nombre']) ? $_POST['talla_nombre'] : null;
    
    // Validar talla seleccionada
    if(!empty($tallas_disponibles)) {
        // Producto con tallas
        if(!$talla_id) {
            $_SESSION['error'] = "Debes seleccionar una talla";
            header("Location: detalles-producto.php?id=".$producto_id);
            exit;
        } else {
            // Verificar que la talla seleccionada existe y tiene stock
            $talla_valida = false;
            foreach($tallas_disponibles as $talla) {
                if($talla['id'] == $talla_id && $talla['stock'] > 0) {
                    $talla_valida = true;
                    $stock_disponible = $talla['stock'];
                    break;
                }
            }
            
            if(!$talla_valida) {
                $_SESSION['error'] = "Talla no válida o sin stock disponible";
                header("Location: detalles-producto.php?id=".$producto_id);
                exit;
            }
        }
    } else {
        // Producto sin tallas
        $talla_id = null;
        $stock_disponible = $producto['stock'];
    }
    
    if($cantidad <= 0 || $cantidad > $stock_disponible) {
        $_SESSION['error'] = "Cantidad no válida o excede el stock disponible";
        header("Location: detalles-producto.php?id=".$producto_id);
        exit;
    }
    
    try {
        // Verificar si el producto ya está en el carrito del usuario (misma talla)
        $stmt = $conn->prepare("SELECT * FROM carrito 
                               WHERE usuario_id = ? AND producto_id = ? AND talla_id <=> ?
                               AND fecha_expiracion > NOW()");
        $stmt->execute([$_SESSION['usuario_id'], $producto_id, $talla_id]);
        $item_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($item_existente) {
            // Actualizar cantidad si ya existe
            $nueva_cantidad = $item_existente['cantidad'] + $cantidad;
            
            if($nueva_cantidad > $stock_disponible) {
                $_SESSION['error'] = "No hay suficiente stock para la cantidad solicitada";
                header("Location: detalles-producto.php?id=".$producto_id);
                exit;
            }
            
            $stmt = $conn->prepare("UPDATE carrito 
                                   SET cantidad = ?, fecha_expiracion = NOW() + INTERVAL 3 DAY 
                                   WHERE id = ?");
            $stmt->execute([$nueva_cantidad, $item_existente['id']]);
        } else {
            // Insertar nuevo item en el carrito
            $stmt = $conn->prepare("INSERT INTO carrito 
                                  (usuario_id, producto_id, talla_id, cantidad, fecha_expiracion) 
                                  VALUES (?, ?, ?, ?, NOW() + INTERVAL 3 DAY)");
            $stmt->execute([$_SESSION['usuario_id'], $producto_id, $talla_id, $cantidad]);
        }
        
        $_SESSION['mensaje'] = "Producto añadido al carrito";
        header("Location: detalles-producto.php?id=".$producto_id);
        exit;
        
    } catch(PDOException $e) {
        error_log("Error al añadir al carrito: " . $e->getMessage());
        $_SESSION['error'] = "Error al añadir el producto al carrito. Por favor intenta nuevamente.";
        header("Location: detalles-producto.php?id=".$producto_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - Khalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/asesoria.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
        }
        
        .product-header {
            margin-bottom: 15px;
        }
        
        .product-code {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .stock-alerts {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stock-alert {
            font-size: 0.8rem;
            color: var(--accent-color);
        }
        
        .free-shipping {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .size-selection {
            margin-bottom: 20px;
        }
        
        .size-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .size-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 5px;
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
        
        .size-fit-info {
            font-size: 0.8rem;
            color: var(--secondary-color);
            margin-top: 5px;
        }
        
        .add-to-cart-container {
            margin-top: 25px;
        }
        
        .add-to-cart-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 0;
            width: 100%;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .discount-banner {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .price-container {
            margin: 20px 0;
        }
        
        .current-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .original-price {
            font-size: 1.1rem;
            text-decoration: line-through;
            color: var(--secondary-color);
            margin-left: 10px;
        }
        
        .discount-percentage {
            background-color: var(--accent-color);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .countdown-timer {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: center;
        }
        
        .countdown-title {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .countdown-numbers {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        /* Estilos para la galería de imágenes */
        .product-gallery {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .main-product-image {
            width: 100%;
            height: 500px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .product-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .product-thumbnail:hover,
        .product-thumbnail.active {
            border-color: var(--primary-color);
        }
        
        .stock-info {
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-top: 5px;
        }
        
        /* Estilos para contadores de productos relacionados */
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
            .size-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .size-option {
                padding: 6px;
                font-size: 0.8rem;
            }
            
            .main-product-image {
                height: 300px;
            }
            
            .current-price {
                font-size: 1.3rem;
            }
            
            .original-price {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-product-image {
                height: 250px;
            }
            
            .product-thumbnail {
                width: 60px;
                height: 60px;
            }
            
            .price-container {
                margin: 15px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <!-- Mostrar mensajes -->
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

        <!-- Detalle del producto -->
        <div class="row">
            <div class="col-md-6">
                <div class="product-gallery">
                    <!-- Imagen principal -->
                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                         class="main-product-image" 
                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'"
                         id="mainProductImage">
                    
                    <!-- Miniaturas -->
                    <?php if(!empty($imagenes_adicionales)): ?>
                    <div class="thumbnail-container">
                        <!-- Miniatura imagen principal -->
                        <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                             class="product-thumbnail active" 
                             alt="Miniatura <?php echo htmlspecialchars($producto['nombre']); ?>"
                             onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'"
                             onclick="changeMainImage(this, '<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>')">
                        
                        <!-- Miniaturas imágenes adicionales -->
                        <?php foreach($imagenes_adicionales as $imagen): ?>
                            <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($imagen); ?>" 
                                 class="product-thumbnail" 
                                 alt="Miniatura <?php echo htmlspecialchars($producto['nombre']); ?>"
                                 onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'"
                                 onclick="changeMainImage(this, '<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($imagen); ?>')">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="product-header">
                    <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="product-code"><?php echo htmlspecialchars($producto['descripcion']); ?></div>
                </div>
                
                <!-- Mostrar precios -->
                <div class="price-container">
                    <?php if($producto['precio_descuento'] && $producto['descuento_fin'] && new DateTime() < new DateTime($producto['descuento_fin'])): ?>
                        <div class="current-price">
                            $<?php echo number_format($producto['precio_descuento'], 0, ',', '.'); ?>
                            <span class="original-price">$<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?></span>
                            <span class="discount-percentage">
                                <?php echo round((1 - ($producto['precio_descuento'] / $producto['precio_normal'])) * 100); ?>% OFF
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="current-price">
                            $<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if($producto['precio_descuento'] && $producto['descuento_fin'] && new DateTime() < new DateTime($producto['descuento_fin'])): ?>
                <!-- Contador regresivo para oferta -->
                <div class="countdown-timer">
                    <div class="countdown-title">¡Oferta termina en!</div>
                    <div class="countdown-numbers" id="countdown">
                        <span id="days">00</span>d 
                        <span id="hours">00</span>h 
                        <span id="minutes">00</span>m 
                        <span id="seconds">00</span>s
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($stock_total > 0 && $stock_total <= 5): ?>
                <div class="stock-alerts">
                    <span class="stock-alert">¡Últimas unidades!</span>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($tallas_disponibles)): ?>
                <div class="size-selection">
                    <div class="size-title">Tallas disponibles:</div>
                    <div class="size-grid">
                        <?php foreach($tallas_disponibles as $talla): ?>
                            <div class="size-option <?php echo $talla['stock'] <= 0 ? 'unavailable' : ''; ?>" 
                                 data-talla-id="<?php echo $talla['id']; ?>"
                                 data-talla-nombre="<?php echo htmlspecialchars($talla['nombre']); ?>"
                                 data-stock="<?php echo $talla['stock']; ?>"
                                 <?php echo $talla['stock'] <= 0 ? 'title="Sin stock"' : ''; ?>>
                                <?php echo htmlspecialchars($talla['nombre']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="stock-disponible" class="stock-info"></div>
                    <div class="size-fit-info">
                        Tallas de zapato/pantalon/camisas
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($stock_total > 0): ?>
                <form method="POST" class="add-to-cart-container">
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad</label>
                        <select class="form-select" id="cantidad" name="cantidad">
                            <?php 
                            $max_cantidad = min(10, $stock_total);
                            for($i = 1; $i <= $max_cantidad; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <?php if(!empty($tallas_disponibles)): ?>
                        <input type="hidden" name="talla_id" id="tallaIdInput">
                        <input type="hidden" name="talla_nombre" id="tallaNombreInput">
                    <?php endif; ?>
                    
                    <button type="submit" name="add_to_cart" class="add-to-cart-btn" id="btnAddToCart" <?php echo !empty($tallas_disponibles) ? 'disabled' : ''; ?>>
                        Añadir al carrito
                    </button>
                    
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" class="flex-grow-1">
                        </form>
                        <button type="button" class="btn btn-outline-secondary flex-grow-1" id="shareBtn">
                            <i class="fas fa-share-alt me-2"></i> Compartir
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-warning">Este producto está actualmente agotado</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Productos relacionados -->
        <?php if(!empty($productos_relacionados)): ?>
            <section class="mt-5">
                <h3 class="mb-4">Productos relacionados</h3>
                <div class="row">
                    <?php foreach($productos_relacionados as $relacionado): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <a href="detalles-producto.php?id=<?php echo $relacionado['id']; ?>">
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($relacionado['imagen']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($relacionado['nombre']); ?>"
                                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                </a>
                                
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="detalles-producto.php?id=<?php echo $relacionado['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($relacionado['nombre']); ?>
                                        </a>
                                    </h5>
                                    <p class="card-text">
                                        <?php if($relacionado['precio_descuento'] && $relacionado['descuento_fin'] && new DateTime() < new DateTime($relacionado['descuento_fin'])): ?>
                                            <span class="fw-bold">$<?php echo number_format($relacionado['precio_descuento'], 0, ',', '.'); ?></span>
                                            <span class="text-decoration-line-through text-muted ms-2">$<?php echo number_format($relacionado['precio_normal'], 0, ',', '.'); ?></span>
                                            <span class="badge bg-danger ms-2">
                                                <?php echo round((1 - ($relacionado['precio_descuento'] / $relacionado['precio_normal'])) * 100); ?>% OFF
                                            </span>
                                            <!-- Mostrar contador regresivo para productos relacionados en oferta -->
                                            <div class="countdown-timer-sm mt-2" 
                                                 data-end-date="<?php echo htmlspecialchars($relacionado['descuento_fin']); ?>">
                                                <small class="text-muted d-block">Termina en:</small>
                                                <small class="fw-bold">
                                                    <span class="days">00</span>d 
                                                    <span class="hours">00</span>h 
                                                    <span class="minutes">00</span>m
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <span class="fw-bold">$<?php echo number_format($relacionado['precio_normal'], 0, ',', '.'); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <a href="detalles-producto.php?id=<?php echo $relacionado['id']; ?>" class="btn btn-outline-dark w-100">
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
        // Manejar selección de tallas
        document.addEventListener('DOMContentLoaded', function() {
            const sizeOptions = document.querySelectorAll('.size-option:not(.unavailable)');
            const stockInfo = document.getElementById('stock-disponible');
            
            sizeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Deseleccionar todas las tallas
                    document.querySelectorAll('.size-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Seleccionar la talla clickeada
                    this.classList.add('selected');
                    
                    // Mostrar stock disponible
                    const stock = this.dataset.stock;
                    stockInfo.textContent = `Stock disponible: ${stock} unidades`;
                    
                    // Habilitar botón de añadir al carrito
                    document.getElementById('btnAddToCart').disabled = false;
                    
                    // Actualizar los campos ocultos con la talla seleccionada
                    document.getElementById('tallaIdInput').value = this.dataset.tallaId;
                    document.getElementById('tallaNombreInput').value = this.dataset.tallaNombre;
                    
                    // Actualizar cantidad máxima según stock disponible
                    const stockDisponible = parseInt(stock);
                    const selectCantidad = document.getElementById('cantidad');
                    const options = selectCantidad.querySelectorAll('option');
                    
                    // Habilitar/deshabilitar opciones según el stock
                    options.forEach(option => {
                        const value = parseInt(option.value);
                        option.disabled = value > stockDisponible;
                        
                        if (value > stockDisponible && option.selected) {
                            selectCantidad.value = Math.min(10, stockDisponible);
                        }
                    });
                });
            });
            
            // Compartir producto
            document.getElementById('shareBtn')?.addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: '<?php echo addslashes($producto['nombre']); ?>',
                        text: 'Echa un vistazo a este producto en Kalos Style',
                        url: window.location.href
                    }).catch(err => {
                        console.log('Error al compartir:', err);
                    });
                } else {
                    // Fallback para navegadores que no soportan Web Share API
                    const url = window.location.href;
                    const tempInput = document.createElement('input');
                    document.body.appendChild(tempInput);
                    tempInput.value = url;
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    alert('Enlace copiado al portapapeles: ' + url);
                }
            });
            
            <?php if($producto['precio_descuento'] && $producto['descuento_fin'] && new DateTime() < new DateTime($producto['descuento_fin'])): ?>
            // Contador regresivo para producto principal
            function updateCountdown() {
                const endDate = new Date("<?php echo $producto['descuento_fin']; ?>").getTime();
                const now = new Date().getTime();
                const distance = endDate - now;
                
                if (distance < 0) {
                    document.getElementById("countdown").innerHTML = "¡La oferta ha terminado!";
                    // Recargar la página para actualizar el estado del descuento
                    setTimeout(() => location.reload(), 2000);
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
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
            <?php endif; ?>
            
            // Contadores regresivos para productos relacionados
            function updateAllCountdowns() {
                const countdowns = document.querySelectorAll('.countdown-timer-sm');
                
                countdowns.forEach(countdown => {
                    const endDate = new Date(countdown.dataset.endDate).getTime();
                    const now = new Date().getTime();
                    const distance = endDate - now;
                    
                    if (distance < 0) {
                        countdown.innerHTML = '<small class="text-danger">Oferta terminada</small>';
                        return;
                    }
                    
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    
                    countdown.querySelector('.days').textContent = days.toString().padStart(2, '0');
                    countdown.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
                    countdown.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
                });
            }
            
            // Actualizar todos los contadores inmediatamente y luego cada minuto
            updateAllCountdowns();
            setInterval(updateAllCountdowns, 60000);
        });
        
        // Cambiar imagen principal al hacer clic en miniatura
        function changeMainImage(thumbnail, imageUrl) {
            // Remover clase active de todas las miniaturas
            document.querySelectorAll('.product-thumbnail').forEach(img => {
                img.classList.remove('active');
            });
            
            // Añadir clase active a la miniatura clickeada
            thumbnail.classList.add('active');
            
            // Cambiar imagen principal
            document.getElementById('mainProductImage').src = imageUrl;
        }
    </script>
</body>
</html>