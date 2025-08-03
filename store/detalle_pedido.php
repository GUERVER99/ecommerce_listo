<?php
require_once 'config.php';

// Formatear precio
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verificar si se proporcionó un ID de pedido
if(!isset($_GET['id'])) {
    header("Location: pedidos.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// Obtener información del pedido
$stmt = $pdo->prepare("SELECT p.*, u.nombre as cliente_nombre, u.email as cliente_email, 
                       u.telefono as cliente_telefono, u.direccion as cliente_direccion
                       FROM pedidos p
                       JOIN usuarios u ON p.usuario_id = u.id
                       WHERE p.id = ? AND (p.usuario_id = ? OR ? = 'admin')");
$stmt->execute([$pedido_id, $_SESSION['usuario_id'], $_SESSION['usuario_rol']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pedido) {
    $_SESSION['error'] = "Pedido no encontrado o no tienes permiso para verlo";
    header("Location: pedidos.php");
    exit;
}

// Obtener productos del pedido con sus tallas
$stmt = $pdo->prepare("SELECT pi.*, pr.nombre, pr.imagen, pr.descripcion, t.nombre as talla_nombre
                       FROM pedido_items pi
                       JOIN productos pr ON pi.producto_id = pr.id
                       LEFT JOIN tallas t ON pi.talla_id = t.id
                       WHERE pi.pedido_id = ?");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total si no viene en el pedido (para compatibilidad)
if(!isset($pedido['total']) || $pedido['total'] == 0) {
    $pedido['total'] = array_sum(array_column($productos, 'subtotal'));
    if(isset($pedido['costo_envio'])) {
        $pedido['total'] += $pedido['costo_envio'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #<?php echo $pedido_id; ?> - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .product-img-detail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .status-badge {
            font-size: 1rem;
            padding: 8px 15px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 2px solid white;
        }
        
        .timeline-item.completed::before {
            background-color: #28a745;
        }
        
        .timeline-item.current::before {
            background-color: #ffc107;
            width: 15px;
            height: 15px;
            left: -31.5px;
            top: 4px;
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
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Detalle del Pedido</h1>
            <a href="pedidos.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver a mis pedidos
            </a>
        </div>
        
        <div class="order-header">
            <div class="row">
                <div class="col-md-6">
                    <h3>Pedido #<?php echo $pedido['id']; ?></h3>
                    <p class="text-muted mb-2">
                        <i class="far fa-calendar-alt me-2"></i>
                        <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?>
                    </p>
                    <span class="badge status-badge bg-<?php 
                        echo $pedido['estado'] == 'pendiente' ? 'warning' : 
                             ($pedido['estado'] == 'procesando' ? 'info' : 
                             ($pedido['estado'] == 'enviado' ? 'primary' : 
                             ($pedido['estado'] == 'completado' ? 'success' : 'danger'))); ?>">
                        <?php echo ucfirst($pedido['estado']); ?>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <h3 class="mb-0">Total: <?php echo formatearPrecio($pedido['total']); ?></h3>
                    <p class="text-muted"><?php echo count($productos); ?> producto(s)</p>
                    
                    <?php if($pedido['estado'] == 'pendiente' && $_SESSION['usuario_rol'] == 'cliente'): ?>
                    <div class="mt-3">
                        <a href="procesar_pago.php?pedido_id=<?php echo $pedido['id']; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-credit-card me-2"></i> Pagar ahora
                        </a>
                        <a href="cancelar_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de cancelar este pedido?')">
                            <i class="fas fa-times me-2"></i> Cancelar pedido
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Productos</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Precio unitario</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($productos as $producto): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                                     class="product-img-detail me-3"
                                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                     onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                    <small class="text-muted"><?php echo substr(htmlspecialchars($producto['descripcion']), 0, 50); ?>...</small>
                                                    <?php if(!empty($producto['talla_nombre'])): ?>
                                                    <div class="talla-info">
                                                        <i class="fas fa-ruler me-1"></i> Talla: <?php echo htmlspecialchars($producto['talla_nombre']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end"><?php echo formatearPrecio($producto['precio_unitario']); ?></td>
                                        <td class="text-center"><?php echo $producto['cantidad']; ?></td>
                                        <td class="text-end"><?php echo formatearPrecio($producto['subtotal']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-end fw-bold"><?php echo formatearPrecio(array_sum(array_column($productos, 'subtotal'))); ?></td>
                                    </tr>
                                    <?php if(isset($pedido['costo_envio']) && $pedido['costo_envio'] > 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Envío:</td>
                                        <td class="text-end fw-bold"><?php echo formatearPrecio($pedido['costo_envio']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total:</td>
                                        <td class="text-end fw-bold"><?php echo formatearPrecio($pedido['total']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if($pedido['estado'] == 'enviado' && isset($pedido['seguimiento'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Información de envío</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Transportista:</strong> <?php echo htmlspecialchars($pedido['transportista']); ?></p>
                                <p><strong>Número de seguimiento:</strong> <?php echo htmlspecialchars($pedido['seguimiento']); ?></p>
                                <a href="<?php echo htmlspecialchars($pedido['url_seguimiento']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-truck me-2"></i> Rastrear envío
                                </a>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Dirección de envío:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($pedido['direccion_envio'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Estado del pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item <?php echo $pedido['estado'] == 'completado' ? 'completed' : ''; ?>">
                                <h6 class="mb-1">Pedido realizado</h6>
                                <p class="text-muted small mb-2"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></p>
                                <p class="mb-0">Hemos recibido tu pedido correctamente.</p>
                            </div>
                            
                            <div class="timeline-item <?php 
                                echo $pedido['estado'] == 'procesando' ? 'current' : 
                                     (in_array($pedido['estado'], ['enviado', 'completado']) ? 'completed' : ''); ?>">
                                <h6 class="mb-1">En preparación</h6>
                                <?php if(in_array($pedido['estado'], ['procesando', 'enviado', 'completado'])): ?>
                                <p class="text-muted small mb-2"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_procesamiento'] ?? $pedido['fecha_pedido'])); ?></p>
                                <p class="mb-0">Estamos preparando tu pedido para el envío.</p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(in_array($pedido['estado'], ['enviado', 'completado'])): ?>
                            <div class="timeline-item <?php echo $pedido['estado'] == 'enviado' ? 'current' : 'completed'; ?>">
                                <h6 class="mb-1">Enviado</h6>
                                <p class="text-muted small mb-2"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_envio'])); ?></p>
                                <p class="mb-0">Tu pedido ha sido enviado.</p>
                                <?php if(isset($pedido['seguimiento'])): ?>
                                <p class="mt-2 mb-0">
                                    <small>Código de seguimiento: <?php echo htmlspecialchars($pedido['seguimiento']); ?></small>
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($pedido['estado'] == 'completado'): ?>
                            <div class="timeline-item completed">
                                <h6 class="mb-1">Entregado</h6>
                                <p class="text-muted small mb-2"><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_completado'])); ?></p>
                                <p class="mb-0">Pedido entregado satisfactoriamente.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Información del cliente</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3"><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></h6>
                        <p class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo htmlspecialchars($pedido['cliente_email']); ?>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($pedido['cliente_telefono']); ?>">
                                <?php echo htmlspecialchars($pedido['cliente_telefono']); ?>
                            </a>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <?php echo nl2br(htmlspecialchars($pedido['cliente_direccion'])); ?>
                        </p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Método de pago</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3 fs-4">
                                <?php if($pedido['metodo_pago'] == 'tarjeta'): ?>
                                    <i class="fas fa-credit-card text-primary"></i>
                                <?php elseif($pedido['metodo_pago'] == 'transferencia'): ?>
                                    <i class="fas fa-university text-info"></i>
                                <?php else: ?>
                                    <i class="fas fa-money-bill-wave text-success"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h6 class="mb-1"><?php echo ucfirst($pedido['metodo_pago']); ?></h6>
                                <p class="text-muted small mb-0">
                                    <?php if($pedido['metodo_pago'] == 'tarjeta'): ?>
                                        Pago con tarjeta terminada en <?php echo substr($pedido['tarjeta_numero'], -4); ?>
                                    <?php elseif($pedido['metodo_pago'] == 'transferencia'): ?>
                                        Transferencia bancaria
                                    <?php else: ?>
                                        Pago en efectivo al recibir
                                    <?php endif; ?>
                                </p>
                                <?php if($pedido['estado_pago'] == 'pendiente'): ?>
                                    <span class="badge bg-warning mt-2">Pago pendiente</span>
                                <?php elseif($pedido['estado_pago'] == 'completado'): ?>
                                    <span class="badge bg-success mt-2">Pago completado</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if($pedido['estado'] == 'completado'): ?>
        <div class="text-center mt-4">
            <a href="#" class="btn btn-outline-primary me-2">
                <i class="fas fa-redo me-2"></i> Volver a pedir
            </a>
            <a href="#" class="btn btn-primary">
                <i class="fas fa-star me-2"></i> Valorar productos
            </a>
        </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para imprimir el comprobante
        function imprimirComprobante() {
            window.print();
        }
        
        // Si hay parámetros de éxito en la URL, mostrar alerta
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.has('pago_exitoso')) {
                alert('¡Pago realizado con éxito!');
            }
        });
    </script>
</body>
</html>