<?php
require_once 'config.php';
require_once __DIR__ . '/includes/whatsapp-widget.php';

date_default_timezone_set('America/Bogota');

// Formatear precio
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Verificar si el usuario está logueado como cliente
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
    $_SESSION['redirect_to'] = "checkout.php";
    $_SESSION['error'] = "Debes iniciar sesión como cliente para realizar un pedido";
    header("Location: login.php");
    exit;
}

// Función para obtener items del carrito desde la base de datos
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

// Obtener items del carrito
$items_carrito = obtenerCarrito($_SESSION['usuario_id']);

// Verificar que el carrito no esté vacío
if(empty($items_carrito)) {
    $_SESSION['error'] = "Tu carrito está vacío";
    header("Location: carrito.php");
    exit;
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular totales para mostrar
$subtotal = 0;
$total_items = 0;

foreach($items_carrito as $item) {
    // Usar precio con descuento si está disponible y vigente
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

// Definir costo de envío
$envio_minimo = 300000;
$envio = $subtotal > $envio_minimo ? 0 : 15000;
$total = $subtotal + $envio;

// Procesar el pedido
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar dirección de envío
    $direccion = trim($_POST['direccion']);
    if(empty($direccion)) {
        $_SESSION['error'] = "La dirección de envío es requerida";
        header("Location: checkout.php");
        exit;
    }
    
    // Validar método de pago
    $metodo_pago = $_POST['metodo_pago'];
    if(!in_array($metodo_pago, ['efectivo','transferencia','tarjeta','pse'])) {
        $_SESSION['error'] = "Método de pago no válido";
        header("Location: checkout.php");
        exit;
    }
    
    // Validar notas (opcional)
    $notas = trim($_POST['notas'] ?? '');
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // Insertar el pedido
        $stmt = $pdo->prepare("INSERT INTO pedidos (
                                usuario_id, 
                                total, 
                                direccion_envio, 
                                metodo_pago, 
                                notas,
                                estado
                              ) VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([
            $_SESSION['usuario_id'],
            $total,
            $direccion,
            $metodo_pago,
            $notas
        ]);
        $pedido_id = $pdo->lastInsertId();
        
        // Insertar detalles del pedido
        foreach($items_carrito as $item) {
            // Determinar precio final
            $precio_final = $item['precio_normal'];
            if($item['precio_descuento'] && $item['descuento_fin']) {
                $now = new DateTime();
                $fin_descuento = new DateTime($item['descuento_fin']);
                if($now < $fin_descuento) {
                    $precio_final = $item['precio_descuento'];
                }
            }
            
            $subtotal_item = $item['cantidad'] * $precio_final;
            
            $stmt = $pdo->prepare("INSERT INTO pedido_items (
                                    pedido_id, 
                                    producto_id, 
                                    cantidad, 
                                    precio_unitario, 
                                    subtotal,
                                    talla_id
                                ) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $pedido_id,
                $item['producto_id'],
                $item['cantidad'],
                $precio_final,
                $subtotal_item,
                $item['talla_id'] ?? null
            ]);
            
            // Actualizar stock
            if(isset($item['talla_id'])) {
                $stmt = $pdo->prepare("UPDATE producto_tallas SET stock = stock - ? 
                                      WHERE producto_id = ? AND talla_id = ?");
                $stmt->execute([$item['cantidad'], $item['producto_id'], $item['talla_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['cantidad'], $item['producto_id']]);
            }
        }
        
        // Vaciar carrito
        $stmt = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        
        // Confirmar transacción
        $pdo->commit();
        
        // Redirigir a confirmación
        $_SESSION['mensaje'] = "Pedido realizado con éxito. Número de pedido: #".$pedido_id;
        header("Location: confirmacion.php?id=".$pedido_id);
        exit;
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al procesar el pedido: ".$e->getMessage();
        header("Location: checkout.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/asesoria.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
        }
        
        .product-img-checkout {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .payment-method {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background-color: #f0f0f0;
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .talla-info {
            display: inline-block;
            padding: 3px 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-top: 5px;
            border: 1px solid #dee2e6;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
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
        
        .free-shipping {
            color: #28a745;
            font-weight: bold;
        }
        
        .current-price {
            font-size: 1rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .original-price {
            font-size: 0.8rem;
            text-decoration: line-through;
            color: var(--secondary-color);
            margin-left: 5px;
        }
        
        .discount-percentage {
            background-color: var(--accent-color);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .summary-card {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Finalizar Compra</h2>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Información de Envío</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre completo</label>
                                    <input type="text" class="form-control" id="nombre" 
                                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" 
                                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="ciudad" class="form-label">Ciudad</label>
                                    <input type="text" class="form-control" id="ciudad" 
                                           value="Cartagena" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección de envío *</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="3" required><?php 
                                    echo htmlspecialchars($usuario['direccion'] ?? ''); 
                                ?></textarea>
                                <small class="text-muted">Ej: Barrio, Calle, Número, Edificio, Apartamento</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notas" class="form-label">Notas adicionales (opcional)</label>
                                <textarea class="form-control" id="notas" name="notas" rows="2" 
                                          placeholder="Ej: Piso, color de puerta, referencias..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i> Método de Pago</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="payment-method selected">
                                        <input type="radio" name="metodo_pago" value="efectivo" checked>
                                        <div>
                                            <h6><i class="fas fa-money-bill-wave me-2"></i> Efectivo</h6>
                                            <p class="small text-muted mb-0">Paga en efectivo al recibir tu pedido</p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="payment-method">
                                        <input type="radio" name="metodo_pago" value="transferencia">
                                        <div>
                                            <h6><i class="fas fa-university me-2"></i> Transferencia</h6>
                                            <p class="small text-muted mb-0">Transferencia bancaria o Nequi</p>
                                        </div>
                                    </label>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="carrito.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Volver al carrito
                        </a>
                        <button type="submit" class="btn btn-custom">
                            <i class="fas fa-check-circle me-2"></i> Confirmar Pedido
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Productos (<?php echo $total_items; ?>)</h6>
                        
                        <?php foreach($items_carrito as $item): 
                            // Determinar precio a mostrar
                            $precio_mostrar = $item['precio_normal'];
                            $show_discount = false;
                            
                            if($item['precio_descuento'] && $item['descuento_fin']) {
                                $now = new DateTime();
                                $fin_descuento = new DateTime($item['descuento_fin']);
                                if($now < $fin_descuento) {
                                    $precio_mostrar = $item['precio_descuento'];
                                    $show_discount = true;
                                }
                            }
                        ?>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="d-flex">
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($item['imagen']); ?>" 
                                         class="product-img-checkout me-3" 
                                         alt="<?php echo htmlspecialchars($item['nombre']); ?>"
                                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                        <div>
                                            <span class="current-price"><?php echo $item['cantidad']; ?> x <?php echo formatearPrecio($precio_mostrar); ?></span>
                                            <?php if($show_discount): ?>
                                                <span class="original-price"><?php echo formatearPrecio($item['precio_normal']); ?></span>
                                                <span class="discount-percentage">
                                                    <?php echo round((($item['precio_normal'] - $item['precio_descuento']) / $item['precio_normal'] * 100)); ?>% OFF
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(!empty($item['talla_nombre'])): ?>
                                            <div class="talla-info">
                                                <i class="fas fa-ruler me-1"></i> Talla: <?php echo htmlspecialchars($item['talla_nombre']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span><?php echo formatearPrecio($precio_mostrar * $item['cantidad']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span><?php echo formatearPrecio($subtotal); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span>Envío:</span>
                            <span>
                                <?php if($envio == 0): ?>
                                    <span class="free-shipping">¡GRATIS!</span>
                                <?php else: ?>
                                    <?php echo formatearPrecio($envio); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if($envio > 0 && $subtotal < $envio_minimo): ?>
                            <div class="alert alert-info py-2 px-3 small">
                                <i class="fas fa-info-circle me-2"></i> Agrega <?php echo formatearPrecio($envio_minimo - $subtotal); ?> más para envío gratis
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="summary-item">
                            <h5>Total:</h5>
                            <h5><?php echo formatearPrecio($total); ?></h5>
                        </div>
                        
                        <?php if($envio == 0): ?>
                            <div class="alert alert-success py-2 px-3 small mt-2">
                                <i class="fas fa-check-circle me-2"></i> ¡Envío gratis aplicado!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Selección de método de pago
        document.addEventListener('DOMContentLoaded', function() {
            // Payment method selection
            document.querySelectorAll('.payment-method').forEach(method => {
                method.addEventListener('click', function() {
                    document.querySelectorAll('.payment-method').forEach(m => {
                        m.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Auto-select payment method if one is already selected
            const selectedMethod = document.querySelector('input[name="metodo_pago"]:checked');
            if(selectedMethod) {
                selectedMethod.closest('.payment-method').classList.add('selected');
            }
        });
    </script>
</body>
</html>