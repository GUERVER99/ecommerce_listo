<?php
require_once 'config.php';

// Verificar si el usuario está logueado como cliente
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
    header("Location: login.php");
    exit;
}

// Obtener el ID del pedido desde la URL
$pedido_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar que el pedido existe y pertenece al usuario
$stmt = $conn->prepare("SELECT p.*, 
                       (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) as total_productos
                       FROM pedidos p
                       WHERE p.id = ? AND p.usuario_id = ? AND p.estado = 'pendiente'");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe el pedido o no es cancelable, redirigir
if(!$pedido) {
    $_SESSION['error'] = "El pedido no existe o no se puede cancelar";
    header("Location: mis_pedidos.php");
    exit;
}

// Procesar la cancelación si se confirmó
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        $conn->beginTransaction();
        
        // Actualizar estado del pedido
        $stmt = $conn->prepare("UPDATE pedidos SET estado = 'cancelado', fecha_cancelacion = NOW() WHERE id = ?");
        $stmt->execute([$pedido_id]);
        
        // Registrar la cancelación (opcional)
        // $stmt = $conn->prepare("INSERT INTO pedido_cancelaciones (...) VALUES (...)");
        // $stmt->execute([...]);
        
        $conn->commit();
        
        $_SESSION['exito'] = "El pedido #$pedido_id ha sido cancelado correctamente";
        header("Location: pedidos.php");
        exit;
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al cancelar el pedido: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelar Pedido - Kalos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-cancelacion {
            border-left: 4px solid #dc3545;
            max-width: 600px;
            margin: 2rem auto;
        }
        
        .producto-cancelacion {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .producto-cancelacion:last-child {
            border-bottom: none;
        }
        
        .producto-imagen {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .resumen-cancelacion {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar_perfil.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="card card-cancelacion">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Cancelar Pedido #<?php echo $pedido_id; ?>
                </h4>
            </div>
            
            <div class="card-body">
                <!-- Mensajes de error/success -->
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-warning">
                    <h5 class="alert-heading">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ¿Estás seguro que deseas cancelar este pedido?
                    </h5>
                    <p class="mb-0">Esta acción no se puede deshacer. Al cancelar:</p>
                    <ul class="mb-0">
                        <li>El pedido será marcado como cancelado</li>
                        <li>No podrás volver a pagar este pedido</li>
                        <li>Si ya realizaste el pago, contacta con soporte</li>
                    </ul>
                </div>
                
                <!-- Detalles del pedido -->
                <h5 class="mt-4">Detalles del Pedido</h5>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Fecha del pedido:</span>
                        <span><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Productos:</span>
                        <span><?php echo $pedido['total_productos']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Total:</span>
                        <span class="fw-bold">$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></span>
                    </div>
                </div>
                
                <!-- Productos del pedido -->
                <h5 class="mt-4">Productos incluidos</h5>
                <div class="mb-4">
                    <?php 
                    $stmt = $conn->prepare("SELECT pi.*, pr.nombre, pr.imagen 
                                           FROM pedido_items pi
                                           JOIN productos pr ON pi.producto_id = pr.id
                                           WHERE pi.pedido_id = ?");
                    $stmt->execute([$pedido_id]);
                    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach($productos as $producto): ?>
                        <div class="producto-cancelacion">
                            <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                 class="producto-imagen"
                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                 onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                <small class="text-muted">
                                    <?php echo $producto['cantidad']; ?> x $<?php echo number_format($producto['precio_unitario'], 0, ',', '.'); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">$<?php echo number_format($producto['subtotal'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Formulario de confirmación -->
                <form method="post" class="mt-4">
                    <div class="resumen-cancelacion mb-4">
                        <h5 class="mb-3">Resumen de Cancelación</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Descuentos:</span>
                            <span>$0</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total a devolver:</span>
                            <span>$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="mis_pedidos.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i> Volver atrás
                        </a>
                        <button type="submit" name="confirmar" class="btn btn-danger">
                            <i class="fas fa-times-circle me-1"></i> Confirmar Cancelación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirmación adicional para evitar cancelaciones accidentales
        document.querySelector('button[name="confirmar"]').addEventListener('click', function(e) {
            if(!confirm('¿Estás completamente seguro de cancelar este pedido? Esta acción no se puede deshacer.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>