<?php
require_once 'config.php';

// Verificar si el usuario está logueado como cliente
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'cliente') {
    header("Location: login.php");
    exit;
}

// Verificar si se proporcionó un ID de pedido
if(!isset($_GET['pedido_id'])) {
    $_SESSION['error'] = "No se especificó un pedido";
    header("Location: pedidos.php");
    exit;
}

$pedido_id = (int)$_GET['pedido_id'];

// Obtener información del pedido
$stmt = $conn->prepare("SELECT p.*, u.nombre as cliente_nombre, 
                       SUM(pi.subtotal) as subtotal
                       FROM pedidos p
                       JOIN usuarios u ON p.usuario_id = u.id
                       JOIN pedido_items pi ON p.id = pi.pedido_id
                       WHERE p.id = ? AND p.usuario_id = ? AND p.estado = 'pendiente'
                       GROUP BY p.id");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pedido) {
    $_SESSION['error'] = "Pedido no encontrado o no está pendiente de pago";
    header("Location: pedidos.php");
    exit;
}

// Procesar el pago cuando se envía el formulario
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // 1. Verificar método de pago
        $metodo_pago = $_POST['metodo_pago'] ?? '';
        if(!in_array($metodo_pago, ['efectivo', 'transferencia'])) {
            throw new Exception("Método de pago no válido");
        }
        
        // 2. Actualizar el pedido
        $stmt = $conn->prepare("UPDATE pedidos SET 
                               estado = 'procesando',
                               metodo_pago = ?,
                               fecha_pago = NOW()
                               WHERE id = ?");
        
        $stmt->execute([$metodo_pago, $pedido_id]);
        
        $conn->commit();
        
        // Mensaje según método de pago
        if($metodo_pago == 'transferencia') {
            // Obtener teléfono del cliente
            $stmt = $conn->prepare("SELECT telefono FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $telefono = $stmt->fetchColumn();
            
            // Limpiar y formatear número de teléfono
            $telefono = preg_replace('/[^0-9]/', '', $telefono);
            if(substr($telefono, 0, 2) != '56') {
                $telefono = '56' . ltrim($telefono, '0');
            }
            
            // Crear mensaje para WhatsApp
            $mensaje = "¡Gracias por tu pedido #$pedido_id en Kalo's Style! 🛍️\n\n" .
                      "📌 *Instrucciones para transferencia:*\n" .
                      "🏦 Banco: Banco Nacional\n" .
                      "🔢 Cuenta: 1234567890\n" .
                      "💳 Tipo: Cuenta Corriente\n" .
                      "👤 A nombre de: Kalo's Style S.A.\n" .
                      "📝 RUT: 12.345.678-9\n" .
                      "💰 *Monto a transferir:* $" . number_format($pedido['total'], 0, ',', '.') . "\n\n" .
                      "📤 *Envía el comprobante:*\n" .
                      "1. Toma una captura de pantalla\n" .
                      "2. Responde a este mensaje adjuntando la imagen\n" .
                      "3. O envíalo al +56912345678\n\n" .
                      "⚠️ *Importante:* Tu pedido se procesará solo después de recibir el comprobante.\n\n" .
                      "¡Gracias por tu compra! ❤️";
            
            $mensaje_codificado = rawurlencode($mensaje);
            $url_whatsapp = "https://wa.me/$telefono?text=$mensaje_codificado";
            
            // Guardar en sesión para mostrar después
            $_SESSION['exito'] = "Hemos enviado las instrucciones de transferencia a tu WhatsApp. Por favor realiza el pago y envía el comprobante.";
            $_SESSION['url_whatsapp'] = $url_whatsapp;
            
            header("Location: confirmacion_whatsapp.php?pedido_id=$pedido_id");
            exit;
        } else {
            $_SESSION['exito'] = "Has seleccionado pago en efectivo. Te contactaremos por WhatsApp para coordinar la entrega.";
            header("Location: detalle_pedido.php?id=$pedido_id");
            exit;
        }
        
    } catch(Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al procesar el pago: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Pago - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .payment-card-header {
            padding: 15px;
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .payment-method input[type="radio"] {
            margin-right: 15px;
        }
        
        .order-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .whatsapp-bg {
            background-color: #25D366;
            color: white;
        }
        
        .whatsapp-bg:hover {
            background-color: #128C7E;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Procesar Pago</h1>
                    <a href="detalle_pedido.php?id=<?php echo $pedido_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Volver al pedido
                    </a>
                </div>
                
                <!-- Mostrar mensajes de error/success -->
                <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-7">
                        <form method="POST" id="payment-form">
                            <div class="payment-card mb-4">
                                <div class="payment-card-header">
                                    <h5 class="mb-0">Método de Pago</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="payment-method">
                                            <input type="radio" name="metodo_pago" value="transferencia" id="metodo_transferencia" required>
                                            <div>
                                                <h6><i class="fab fa-whatsapp me-2 text-success"></i> Transferencia bancaria</h6>
                                                <p class="small text-muted mb-0">Recibirás las instrucciones por WhatsApp</p>
                                            </div>
                                        </label>
                                        
                                        <label class="payment-method">
                                            <input type="radio" name="metodo_pago" value="efectivo" checked>
                                            <div>
                                                <h6><i class="fas fa-money-bill-wave me-2 text-success"></i> Efectivo</h6>
                                                <p class="small text-muted mb-0">Paga en efectivo al recibir tu pedido</p>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Instrucciones para transferencia -->
                                    <div id="transferencia-info" class="alert alert-info" style="display: none;">
                                        <h6><i class="fab fa-whatsapp me-2"></i> Instrucciones para transferencia</h6>
                                        <p class="mb-2">Al confirmar, recibirás un mensaje por WhatsApp con:</p>
                                        <ul class="mb-2">
                                            <li>Los datos bancarios completos</li>
                                            <li>El monto exacto a transferir</li>
                                            <li>Instrucciones para enviar el comprobante</li>
                                        </ul>
                                        <p class="mb-0"><i class="fas fa-info-circle me-2"></i> Tu pedido se procesará solo después de recibir el comprobante.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle me-2"></i> Confirmar Método de Pago
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="col-md-5">
                        <div class="order-summary">
                            <h5 class="mb-3">Resumen del Pedido</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Número de pedido:</span>
                                <span class="fw-bold">#<?php echo $pedido['id']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Fecha:</span>
                                <span><?php echo date('d/m/Y', strtotime($pedido['fecha_pedido'])); ?></span>
                            </div>
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($pedido['subtotal'], 0, ',', '.'); ?></span>
                            </div>
                            
                            <?php if(isset($pedido['costo_envio']) && $pedido['costo_envio'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span>$<?php echo number_format($pedido['costo_envio'], 0, ',', '.'); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between fw-bold fs-5 mt-3">
                                <span>Total:</span>
                                <span>$<?php echo number_format($pedido['total'], 0, ',', '.'); ?></span>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Productos:</h6>
                            <?php 
                            $stmt = $conn->prepare("SELECT pi.cantidad, pr.nombre 
                                                   FROM pedido_items pi
                                                   JOIN productos pr ON pi.producto_id = pr.id
                                                   WHERE pi.pedido_id = ? LIMIT 3");
                            $stmt->execute([$pedido_id]);
                            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <ul class="list-unstyled">
                                <?php foreach($productos as $producto): ?>
                                <li class="mb-2">
                                    <?php echo $producto['cantidad']; ?> x <?php echo htmlspecialchars($producto['nombre']); ?>
                                </li>
                                <?php endforeach; ?>
                                
                                <?php 
                                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedido_items WHERE pedido_id = ?");
                                $stmt->execute([$pedido_id]);
                                $total_productos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                ?>
                                
                                <?php if($total_productos > 3): ?>
                                <li class="text-muted">
                                    + <?php echo $total_productos - 3; ?> productos más...
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
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
        $(document).ready(function() {
            // Mostrar/ocultar instrucciones de transferencia
            $('input[name="metodo_pago"]').change(function() {
                if($(this).val() === 'transferencia') {
                    $('#transferencia-info').show();
                } else {
                    $('#transferencia-info').hide();
                }
            });
            
            // Activar el cambio inicial
            $('input[name="metodo_pago"]:checked').trigger('change');
        });
    </script>
</body>
</html>