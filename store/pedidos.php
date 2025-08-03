<?php
require_once 'config.php';

// Formatear precio
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Verificar si el usuario está logueado como cliente
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
    header("Location: login.php");
    exit;
}

// Obtener todos los pedidos del usuario
$stmt = $pdo->prepare("SELECT p.*, 
                       (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) as total_productos,
                       (SELECT SUM(pi.subtotal) FROM pedido_items pi WHERE pi.pedido_id = p.id) as subtotal
                       FROM pedidos p
                       WHERE p.usuario_id = ?
                       ORDER BY p.fecha_pedido DESC");
$stmt->execute([$_SESSION['usuario_id']]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separar pedidos completados de pendientes
$pedidos_pendientes = array_filter($pedidos, function($pedido) {
    return $pedido['estado'] != 'completado';
});

$pedidos_completados = array_filter($pedidos, function($pedido) {
    return $pedido['estado'] == 'completado';
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-card {
            border-left: 4px solid;
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .order-card.pendiente {
            border-left-color: #fd7e14;
        }
        
        .order-card.procesando {
            border-left-color: #17a2b8;
        }
        
        .order-card.enviado {
            border-left-color: #007bff;
        }
        
        .order-card.completado {
            border-left-color: #28a745;
        }
        
        .order-card.cancelado {
            border-left-color: #dc3545;
        }
        
        .badge-estado {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        
        .tab-content {
            padding-top: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .talla-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar_perfil.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Mis Pedidos</h1>
            <a href="productos.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Nuevo Pedido
            </a>
        </div>
        
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pendientes-tab" data-bs-toggle="tab" data-bs-target="#pendientes" type="button" role="tab">
                    Pendientes <span class="badge bg-orange ms-2"><?php echo count($pedidos_pendientes); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completados-tab" data-bs-toggle="tab" data-bs-target="#completados" type="button" role="tab">
                    Completados <span class="badge bg-success ms-2"><?php echo count($pedidos_completados); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="todos-tab" data-bs-toggle="tab" data-bs-target="#todos" type="button" role="tab">
                    Todos los Pedidos
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Pestaña de Pedidos Pendientes -->
            <div class="tab-pane fade show active" id="pendientes" role="tabpanel">
                <?php if(count($pedidos_pendientes) > 0): ?>
                    <?php foreach($pedidos_pendientes as $pedido): ?>
                        <div class="card order-card <?php echo $pedido['estado']; ?> mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title">Pedido #<?php echo $pedido['id']; ?></h5>
                                                <p class="text-muted mb-2">
                                                    <i class="far fa-calendar-alt me-2"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?>
                                                </p>
                                                <span class="badge badge-estado bg-<?php 
                                                    echo $pedido['estado'] == 'pendiente' ? 'warning' : 
                                                         ($pedido['estado'] == 'procesando' ? 'info' : 
                         ($pedido['estado'] == 'enviado' ? 'primary' : 'secondary')); ?>">
                                                    <?php echo ucfirst($pedido['estado']); ?>
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <h5><?php echo formatearPrecio($pedido['total']); ?></h5>
                                                <small class="text-muted"><?php echo $pedido['total_productos']; ?> producto(s)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end align-items-center h-100">
                                            <a href="detalle_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-outline-primary me-2">
                                                <i class="fas fa-eye me-1"></i> Ver Detalle
                                            </a>
                                            <?php if($pedido['estado'] == 'pendiente'): ?>
                                                <a href="procesar_pago.php?pedido_id=<?php echo $pedido['id']; ?>" class="btn btn-success me-2">
                                                    <i class="fas fa-credit-card me-1"></i> Pagar Ahora
                                                </a>
                                                <a href="cancelar_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de cancelar este pedido?')">
                                                    <i class="fas fa-times me-1"></i> Cancelar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Productos destacados -->
                                <div class="mt-3">
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT pi.*, pr.nombre, pr.imagen, t.nombre as talla_nombre 
                                                           FROM pedido_items pi
                                                           JOIN productos pr ON pi.producto_id = pr.id
                                                           LEFT JOIN tallas t ON pi.talla_id = t.id
                                                           WHERE pi.pedido_id = ? LIMIT 3");
                                    $stmt->execute([$pedido['id']]);
                                    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <div class="d-flex">
                                        <?php foreach($productos as $producto): ?>
                                            <div class="me-3 text-center">
                                                <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                                     class="product-thumbnail mb-1"
                                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                     onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                                <small class="d-block"><?php echo htmlspecialchars($producto['nombre']); ?></small>
                                                <small class="text-muted"><?php echo $producto['cantidad']; ?> x <?php echo formatearPrecio($producto['precio_unitario']); ?></small>
                                                <?php if(!empty($producto['talla_nombre'])): ?>
                                                    <small class="talla-info">Talla: <?php echo htmlspecialchars($producto['talla_nombre']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if($pedido['total_productos'] > 3): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-light text-dark">+<?php echo $pedido['total_productos'] - 3; ?> más</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-clipboard"></i>
                        <h3>No tienes pedidos pendientes</h3>
                        <p class="text-muted">Cuando realices un pedido, aparecerá aquí hasta que se complete.</p>
                        <a href="productos.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i> Comprar ahora
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pestaña de Pedidos Completados -->
            <div class="tab-pane fade" id="completados" role="tabpanel">
                <?php if(count($pedidos_completados) > 0): ?>
                    <?php foreach($pedidos_completados as $pedido): ?>
                        <div class="card order-card completado mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title">Pedido #<?php echo $pedido['id']; ?></h5>
                                                <p class="text-muted mb-2">
                                                    <i class="far fa-calendar-alt me-2"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?>
                                                </p>
                                                <span class="badge badge-estado bg-success">
                                                    Completado
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <h5><?php echo formatearPrecio($pedido['total']); ?></h5>
                                                <small class="text-muted"><?php echo $pedido['total_productos']; ?> producto(s)</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end align-items-center h-100">
                                            <a href="detalle_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-outline-primary me-2">
                                                <i class="fas fa-eye me-1"></i> Ver Detalle
                                            </a>
                                            <a href="#" class="btn btn-outline-secondary me-2">
                                                <i class="fas fa-redo me-1"></i> Volver a pedir
                                            </a>
                                            <a href="#" class="btn btn-outline-success">
                                                <i class="fas fa-star me-1"></i> Valorar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Productos destacados -->
                                <div class="mt-3">
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT pi.*, pr.nombre, pr.imagen, t.nombre as talla_nombre
                                                           FROM pedido_items pi
                                                           JOIN productos pr ON pi.producto_id = pr.id
                                                           LEFT JOIN tallas t ON pi.talla_id = t.id
                                                           WHERE pi.pedido_id = ? LIMIT 3");
                                    $stmt->execute([$pedido['id']]);
                                    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <div class="d-flex">
                                        <?php foreach($productos as $producto): ?>
                                            <div class="me-3 text-center">
                                                <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                                     class="product-thumbnail mb-1"
                                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                     onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                                <small class="d-block"><?php echo htmlspecialchars($producto['nombre']); ?></small>
                                                <small class="text-muted"><?php echo $producto['cantidad']; ?> x <?php echo formatearPrecio($producto['precio_unitario']); ?></small>
                                                <?php if(!empty($producto['talla_nombre'])): ?>
                                                    <small class="talla-info">Talla: <?php echo htmlspecialchars($producto['talla_nombre']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if($pedido['total_productos'] > 3): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-light text-dark">+<?php echo $pedido['total_productos'] - 3; ?> más</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-check-circle"></i>
                        <h3>No tienes pedidos completados aún</h3>
                        <p class="text-muted">Una vez que completes un pedido, aparecerá en esta sección.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pestaña de Todos los Pedidos -->
            <div class="tab-pane fade" id="todos" role="tabpanel">
                <?php if(count($pedidos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>N° Pedido</th>
                                    <th>Fecha</th>
                                    <th>Productos</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pedidos as $pedido): ?>
                                    <tr>
                                        <td>#<?php echo $pedido['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pedido['fecha_pedido'])); ?></td>
                                        <td><?php echo $pedido['total_productos']; ?></td>
                                        <td><?php echo formatearPrecio($pedido['total']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $pedido['estado'] == 'pendiente' ? 'warning' : 
                                                     ($pedido['estado'] == 'procesando' ? 'info' : 
                                                     ($pedido['estado'] == 'enviado' ? 'primary' : 
                                                     ($pedido['estado'] == 'completado' ? 'success' : 'danger'))); ?>">
                                                <?php echo ucfirst($pedido['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="detalle_pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-clipboard"></i>
                        <h3>No tienes pedidos registrados</h3>
                        <p class="text-muted">Cuando realices un pedido, aparecerá en esta sección.</p>
                        <a href="productos.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i> Comprar ahora
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activar el tab correspondiente si viene en la URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            
            if(tab) {
                const tabElement = document.querySelector(`#${tab}-tab`);
                if(tabElement) {
                    new bootstrap.Tab(tabElement).show();
                }
            }
        });
    </script>
</body>
</html>