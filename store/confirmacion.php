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
    $_SESSION['redirect_to'] = "confirmacion.php?id=".$_GET['id'] ?? '';
    header("Location: login.php");
    exit;
}

// Verificar si se proporcionó un ID de pedido
if(!isset($_GET['id'])) {
    $_SESSION['error'] = "No se especificó un número de pedido";
    header("Location: index.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// Obtener información del pedido con más detalles
$stmt = $pdo->prepare("SELECT p.*, 
                       u.nombre as cliente_nombre, u.email as cliente_email, 
                       u.telefono as cliente_telefono, u.direccion as cliente_direccion,
                       (SELECT SUM(subtotal) FROM pedido_items WHERE pedido_id = p.id) as subtotal
                       FROM pedidos p
                       JOIN usuarios u ON p.usuario_id = u.id
                       WHERE p.id = ? AND p.usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pedido) {
    $_SESSION['error'] = "Pedido no encontrado";
    header("Location: index.php");
    exit;
}

// Calcular total si no viene en el pedido
if(!isset($pedido['total']) || $pedido['total'] == 0) {
    $pedido['total'] = $pedido['subtotal'] + ($pedido['costo_envio'] ?? 0);
}

// Obtener productos del pedido con tallas y descuentos
$stmt = $pdo->prepare("SELECT pi.*, 
                       pr.nombre, pr.imagen, pr.descripcion, 
                       pr.precio_normal as precio_original,
                       t.nombre as talla_nombre
                       FROM pedido_items pi
                       JOIN productos pr ON pi.producto_id = pr.id
                       LEFT JOIN tallas t ON pi.talla_id = t.id
                       WHERE pi.pedido_id = ?");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determinar el estado del pedido para la barra de progreso
$estados = [
    'pendiente' => 1,
    'procesando' => 2,
    'enviado' => 3,
    'completado' => 4,
    'cancelado' => 0
];
$estado_actual = $estados[$pedido['estado']] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido #<?php echo $pedido_id; ?> - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="includes/asesoria.css">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #6c757d;
            --accent-color: #ff6b6b;
        }
        
        .confirmation-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .product-img-confirm {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .whatsapp-link {
            color: #25D366;
            text-decoration: none;
            font-weight: 600;
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
        
        .stepper-wrapper {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .stepper-item {
            position: relative;
            flex: 1;
            text-align: center;
        }
        
        .stepper-item.completed .step-counter {
            background-color: #28a745;
            color: white;
        }
        
        .stepper-item.active .step-counter {
            background-color: var(--primary-color);
            color: white;
        }
        
        .stepper-item.active.completed .step-counter {
            background-color: #28a745;
            color: white;
        }
        
        .step-counter {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        
        .step-name {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .stepper-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60%;
            width: 80%;
            height: 2px;
            background-color: #e9ecef;
            z-index: -1;
        }
        
        .stepper-item.completed:not(:last-child)::after,
        .stepper-item.active:not(:last-child)::after {
            background-color: #28a745;
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
        
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
        
        .bank-info {
            border-left: 3px solid var(--primary-color);
            padding-left: 15px;
        }
        
        @media (max-width: 768px) {
            .stepper-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stepper-item {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
                text-align: left;
                width: 100%;
            }
            
            .stepper-item:not(:last-child)::after {
                top: auto;
                left: 20px;
                width: 2px;
                height: 100%;
                margin-left: -1px;
            }
            
            .step-counter {
                margin: 0 15px 0 0;
            }
            
            .confirmation-icon {
                font-size: 3.5rem;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                font-size: 12pt;
                background: none;
                color: #000;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
            }
            
            .card {
                border: none;
                box-shadow: none;
            }
            
            .card-header {
                background-color: transparent !important;
                border-bottom: 2px solid #000;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="text-center mb-5">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>¡Pedido Confirmado!</h1>
            <p class="lead">Gracias por tu compra en Kalo's Style. Tu pedido ha sido recibido y está siendo procesado.</p>
            <p>Número de pedido: <strong class="h4">#<?php echo $pedido_id; ?></strong></p>
            
            <?php if($pedido['estado'] == 'pendiente'): ?>
                <div class="alert alert-info d-inline-block">
                    <i class="fas fa-info-circle me-2"></i> 
                    <?php if($pedido['metodo_pago'] == 'transferencia'): ?>
                        Por favor completa el pago para procesar tu pedido.
                    <?php else: ?>
                        Estamos preparando tu pedido.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Detalles del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6><i class="fas fa-shopping-bag me-2"></i> Información del Pedido</h6>
                                <p><strong>Número:</strong> #<?php echo $pedido_id; ?></p>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></p>
                                <p><strong>Total:</strong> <?php echo formatearPrecio($pedido['total']); ?></p>
                                <p><strong>Método de pago:</strong> <?php echo ucfirst($pedido['metodo_pago']); ?></p>
                                <p><strong>Estado:</strong> 
                                    <span class="status-badge bg-<?php 
                                        echo $pedido['estado'] == 'pendiente' ? 'warning' : 
                                             ($pedido['estado'] == 'procesando' ? 'info' : 
                                             ($pedido['estado'] == 'enviado' ? 'primary' : 
                                             ($pedido['estado'] == 'completado' ? 'success' : 'danger'))); ?>">
                                        <i class="fas fa-<?php 
                                            echo $pedido['estado'] == 'pendiente' ? 'clock' : 
                                                 ($pedido['estado'] == 'procesando' ? 'cog' : 
                                                 ($pedido['estado'] == 'enviado' ? 'shipping-fast' : 
                                                 ($pedido['estado'] == 'completado' ? 'check-circle' : 'times-circle'))); ?> me-1"></i>
                                        <?php echo ucfirst($pedido['estado']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-truck me-2"></i> Información de Envío</h6>
                                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['cliente_nombre']); ?></p>
                                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($pedido['direccion_envio']); ?></p>
                                <p><strong>Teléfono:</strong> 
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $pedido['cliente_telefono']); ?>" 
                                       class="whatsapp-link" target="_blank">
                                        <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($pedido['cliente_telefono']); ?>
                                    </a>
                                </p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                                
                                <?php if(!empty($pedido['notas'])): ?>
                                    <p class="mt-2"><strong>Notas:</strong> <?php echo htmlspecialchars($pedido['notas']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($pedido['estado'] == 'pendiente' && $pedido['metodo_pago'] == 'transferencia'): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-university me-2"></i> Instrucciones para Transferencia</h5>
                    </div>
                    <div class="card-body">
                        <p>Por favor realiza la transferencia a la siguiente cuenta bancaria:</p>
                        <div class="bank-info alert alert-light">
                            <h6 class="fw-bold">Datos Bancarios</h6>
                            <p><strong>Banco:</strong> Bancolombia</p>
                            <p><strong>Tipo de cuenta:</strong> Cuenta de Ahorros</p>
                            <p><strong>Número de cuenta:</strong> 123-456789-01</p>
                            <p><strong>Titular:</strong> Kalo's Style S.A.S.</p>
                            <p><strong>NIT:</strong> 123.456.789-0</p>
                            <p><strong>Monto a transferir:</strong> <?php echo formatearPrecio($pedido['total']); ?></p>
                            <p><strong>Referencia:</strong> Pedido #<?php echo $pedido_id; ?></p>
                        </div>
                        <p class="mb-0">Una vez realizada la transferencia, por favor envíanos el comprobante al número de WhatsApp indicado arriba.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i> Productos</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($productos as $producto): ?>
                            <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                                <div class="d-flex">
                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                         class="product-img-confirm me-3" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                        <div>
                                            <span class="current-price"><?php echo $producto['cantidad']; ?> x <?php echo formatearPrecio($producto['precio_unitario']); ?></span>
                                            <?php if($producto['precio_unitario'] < $producto['precio_original']): ?>
                                                <span class="original-price"><?php echo formatearPrecio($producto['precio_original']); ?></span>
                                                <span class="discount-percentage">
                                                    <?php echo round((($producto['precio_original'] - $producto['precio_unitario']) / $producto['precio_original'] * 100)); ?>% OFF
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(!empty($producto['talla_nombre'])): ?>
                                            <div class="talla-info">
                                                <i class="fas fa-ruler me-1"></i> Talla: <?php echo htmlspecialchars($producto['talla_nombre']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="fw-bold"><?php echo formatearPrecio($producto['subtotal']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo formatearPrecio($pedido['subtotal']); ?></span>
                        </div>
                        
                        <?php if(isset($pedido['costo_envio']) && $pedido['costo_envio'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío:</span>
                            <span><?php echo formatearPrecio($pedido['costo_envio']); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío:</span>
                            <span class="text-success fw-bold">$15000</span>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <h5>Total:</h5>
                            <h5><?php echo formatearPrecio($pedido['total']); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-truck-loading me-2"></i> Seguimiento del Pedido</h5>
            </div>
            <div class="card-body">
                <div class="stepper-wrapper">
                    <div class="stepper-item <?php echo $estado_actual >= 1 ? 'completed' : ''; ?>">
                        <div class="step-counter">
                            <i class="fas fa-<?php echo $estado_actual >= 1 ? 'check' : 'clock'; ?>"></i>
                        </div>
                        <div class="step-name">Pedido recibido</div>
                    </div>
                    <div class="stepper-item <?php echo $estado_actual > 2 ? 'completed' : ($estado_actual == 2 ? 'active' : ''); ?>">
                        <div class="step-counter">
                            <i class="fas fa-<?php echo $estado_actual >= 2 ? ($estado_actual > 2 ? 'check' : 'cog') : 'clock'; ?>"></i>
                        </div>
                        <div class="step-name">En preparación</div>
                    </div>
                    <div class="stepper-item <?php echo $estado_actual > 3 ? 'completed' : ($estado_actual == 3 ? 'active' : ''); ?>">
                        <div class="step-counter">
                            <i class="fas fa-<?php echo $estado_actual >= 3 ? ($estado_actual > 3 ? 'check' : 'shipping-fast') : 'clock'; ?>"></i>
                        </div>
                        <div class="step-name">En camino</div>
                    </div>
                    <div class="stepper-item <?php echo $estado_actual == 4 ? 'active completed' : ''; ?>">
                        <div class="step-counter">
                            <i class="fas fa-<?php echo $estado_actual == 4 ? 'check-circle' : 'clock'; ?>"></i>
                        </div>
                        <div class="step-name">Entregado</div>
                    </div>
                </div>
                
                <?php if($pedido['estado'] == 'enviado' && !empty($pedido['guia_envio'])): ?>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-truck me-2"></i> 
                        <strong>Tu pedido ha sido enviado!</strong> Número de guía: <?php echo htmlspecialchars($pedido['guia_envio']); ?>
                        <?php if(!empty($pedido['url_seguimiento'])): ?>
                            <a href="<?php echo htmlspecialchars($pedido['url_seguimiento']); ?>" target="_blank" class="ms-2">
                                <i class="fas fa-external-link-alt me-1"></i> Rastrear envío
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4 no-print">
            <a href="productos.php" class="btn btn-custom me-2">
                <i class="fas fa-shopping-bag me-2"></i> Seguir comprando
            </a>
            <a href="pedidos.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-clipboard-list me-2"></i> Ver mis pedidos
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i> Imprimir comprobante
            </button>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Actualizar estado del pedido cada 5 minutos (solo si está pendiente o procesando)
        <?php if(in_array($pedido['estado'], ['pendiente', 'procesando'])): ?>
        function verificarEstadoPedido() {
            $.ajax({
                url: 'ajax/verificar-estado-pedido.php',
                method: 'GET',
                data: { id: <?php echo $pedido_id; ?> },
                success: function(response) {
                    if(response.actualizado && response.estado != '<?php echo $pedido['estado']; ?>') {
                        location.reload();
                    }
                }
            });
        }
        
        // Verificar cada 5 minutos
        setInterval(verificarEstadoPedido, 300000);
        <?php endif; ?>
    </script>
</body>
</html>