<?php
require_once '../config.php';

// Verificar autenticación y rol de administrador
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Obtener ID del pedido desde la URL
$pedido_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($pedido_id <= 0) {
    $_SESSION['error'] = "ID de pedido inválido";
    header("Location: pedidos.php");
    exit;
}

// Función para formatear precio
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Consulta para obtener información del pedido
$stmt = $pdo->prepare("SELECT 
    p.*, 
    u.nombre as cliente_nombre, 
    u.email as cliente_email, 
    u.telefono as cliente_telefono, 
    u.direccion as cliente_direccion
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pedido) {
    $_SESSION['error'] = "Pedido no encontrado";
    header("Location: pedidos.php");
    exit;
}

// Consulta para obtener los productos del pedido
$stmt = $pdo->prepare("SELECT 
    pi.*, 
    pr.nombre as producto_nombre, 
    pr.descripcion as producto_descripcion,
    pr.imagen as producto_imagen,
    t.nombre as talla_nombre
    FROM pedido_items pi
    JOIN productos pr ON pi.producto_id = pr.id
    LEFT JOIN tallas t ON pi.talla_id = t.id
    WHERE pi.pedido_id = ?");
$stmt->execute([$pedido_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estados disponibles para el formulario de cambio
$estados = [
    'pendiente' => 'Pendiente',
    'procesando' => 'Procesando',
    'enviado' => 'Enviado',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado'
];

// Generar mensaje para WhatsApp
$telefono_cliente = preg_replace('/[^0-9]/', '', $pedido['cliente_telefono']);
$mensaje_whatsapp = rawurlencode("Hola {$pedido['cliente_nombre']}, te contacto respecto a tu pedido #{$pedido_id}:".
                                "\n\n*Productos:*");

foreach($productos as $producto) {
    $talla = isset($producto['talla_nombre']) ? $producto['talla_nombre'] : 'Única';
    $mensaje_whatsapp .= rawurlencode("\n- {$producto['producto_nombre']} ($talla) x{$producto['cantidad']}");
}

$mensaje_whatsapp .= rawurlencode("\n\n*Total:* ".formatearPrecio($pedido['total']).
                                "\n\nPor favor confírmame si esta información es correcta.");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #<?= $pedido_id ?> - Khalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #343a40;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav {
            padding: 0;
            list-style: none;
        }
        
        .sidebar-nav li a {
            display: block;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .sidebar-nav li a:hover,
        .sidebar-nav li a.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-nav li a i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        /* Navbar toggle button */
        .navbar-toggler {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
        }
        
        /* Card styles */
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .badge-estado {
            font-size: 0.9rem;
            padding: 0.35em 0.65em;
        }
        
        .badge-pendiente { background-color: #ffc107; color: #212529; }
        .badge-procesando { background-color: #0d6efd; color: white; }
        .badge-enviado { background-color: #198754; color: white; }
        .badge-completado { background-color: #6c757d; color: white; }
        .badge-cancelado { background-color: #dc3545; color: white; }
        
        /* Product image styles */
        .producto-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            transition: transform 0.3s;
        }
        
        .producto-img-link:hover .producto-img {
            transform: scale(1.05);
        }
        
        /* Print styles */
        .print-area {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        .whatsapp-btn {
            background-color: #25D366;
            color: white;
            border: none;
        }
        
        .whatsapp-btn:hover {
            background-color: #128C7E;
            color: white;
        }
        
        @media print {
            .no-print, .no-print * {
                display: none !important;
            }
            body {
                background-color: white !important;
                padding-top: 0 !important;
            }
            .print-area {
                padding: 0;
                border: none;
            }
            .sidebar {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
        
        /* Mobile styles */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .navbar-toggler {
                display: block;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                display: none;
            }
            
            .sidebar.show + .sidebar-overlay {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
    <button class="navbar-toggler btn btn-dark d-lg-none" type="button" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay (mobile only) -->
    <div class="sidebar-overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h1 class="h4 mb-0">Khalo's Style</h1>
        </div>
        <ul class="sidebar-nav">
            <li>
                <a href="pedidos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : '' ?>">
                    <i class="fas fa-shopping-bag"></i> Pedidos
                </a>
            </li>
            <li>
                <a href="productos.php" class="<?= basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'active' : '' ?>">
                    <i class="fas fa-tshirt"></i> Productos
                </a>
            </li>
            <li>
                <a href="categorias.php" class="<?= basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i> Categorías
                </a>
            </li>
            <li>
                <a href="clientes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Clientes
                </a>
            </li>
            <li>
                <a href="reportes.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </li>
            <li>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Detalle del Pedido #<?= $pedido_id ?></h1>
                        <div class="btn-toolbar mb-2 mb-md-0 no-print">
                            <div class="btn-group me-2">
                                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-print"></i> Imprimir
                                </button>
                                <a href="pedidos.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                                <a href="https://wa.me/<?= $telefono_cliente ?>?text=<?= $mensaje_whatsapp ?>" 
                                   class="btn btn-sm whatsapp-btn" target="_blank">
                                    <i class="fab fa-whatsapp"></i> Contactar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="print-area">
                        <!-- Resumen del pedido -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-user me-2"></i> Información del Cliente</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['cliente_nombre']) ?></p>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($pedido['cliente_email']) ?></p>
                                        <p><strong>Teléfono:</strong> 
                                            <a href="https://wa.me/<?= $telefono_cliente ?>" class="text-success" target="_blank">
                                                <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($pedido['cliente_telefono']) ?>
                                            </a>
                                        </p>
                                        <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion_envio'] ?? $pedido['cliente_direccion']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i> Información del Pedido</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
                                        <p><strong>Estado:</strong> 
                                            <span class="badge rounded-pill badge-<?= $pedido['estado'] ?>">
                                                <?= $estados[$pedido['estado']] ?>
                                            </span>
                                        </p>
                                        <p><strong>Método de Pago:</strong> <?= ucfirst($pedido['metodo_pago']) ?></p>
                                        <p><strong>Total:</strong> <span class="fw-bold"><?= formatearPrecio($pedido['total']) ?></span></p>
                                        <?php if($pedido['estado'] == 'cancelado' && $pedido['motivo_cancelacion']): ?>
                                            <p><strong>Motivo cancelación:</strong> <?= htmlspecialchars($pedido['motivo_cancelacion']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Productos del pedido -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-box-open me-2"></i> Productos</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Talla</th>
                                                <th>Cantidad</th>
                                                <th>Precio Unitario</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($productos as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <a href="../assets/img/productos/<?= htmlspecialchars($producto['producto_imagen']) ?>" 
                                                               target="_blank" 
                                                               class="producto-img-link me-3"
                                                               title="Ver imagen ampliada">
                                                                <img src="../assets/img/productos/<?= htmlspecialchars($producto['producto_imagen']) ?>" 
                                                                     class="producto-img" 
                                                                     alt="<?= htmlspecialchars($producto['producto_nombre']) ?>">
                                                            </a>
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($producto['producto_nombre']) ?></h6>
                                                                <small class="text-muted"><?= htmlspecialchars($producto['producto_descripcion']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= $producto['talla_nombre'] ?? 'Única' ?></td>
                                                    <td><?= $producto['cantidad'] ?></td>
                                                    <td><?= formatearPrecio($producto['precio_unitario']) ?></td>
                                                    <td class="fw-bold"><?= formatearPrecio($producto['subtotal']) ?> + <?= formatearPrecio(15000) ?> (envío)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Total (incluye envío):</td>
                                                <td class="fw-bold"><?= formatearPrecio($pedido['total']) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formulario para cambiar estado -->
                        <div class="card no-print">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> Cambiar Estado</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="actualizar_estado_pedido.php">
                                    <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Nuevo Estado</label>
                                            <select name="nuevo_estado" class="form-select" required>
                                                <?php foreach($estados as $valor => $texto): ?>
                                                    <option value="<?= $valor ?>" <?= $pedido['estado'] == $valor ? 'selected' : '' ?>>
                                                        <?= $texto ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Guía de Envío (opcional)</label>
                                            <input type="text" name="guia_envio" class="form-control" placeholder="Número de guía">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3" id="motivo-cancelacion-container" style="<?= $pedido['estado'] == 'cancelado' ? '' : 'display:none;' ?>">
                                        <label class="form-label">Motivo de Cancelación</label>
                                        <textarea name="motivo_cancelacion" class="form-control" rows="2" 
                                            placeholder="Especifique el motivo de cancelación"><?= $pedido['motivo_cancelacion'] ?? '' ?></textarea>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" name="cambiar_estado" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Actualizar Estado
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Modal para imágenes -->
    <div class="modal fade" id="imagenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Imagen del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" class="img-fluid" id="modalImagen" style="max-height: 80vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar toggle functionality
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
        document.querySelector('.sidebar-overlay').style.display = 'block';
    });
    
    document.querySelector('.sidebar-overlay').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        this.style.display = 'none';
    });
    
    // Mostrar/ocultar campo de motivo de cancelación
    document.querySelector('select[name="nuevo_estado"]').addEventListener('change', function() {
        const motivoContainer = document.getElementById('motivo-cancelacion-container');
        if(this.value === 'cancelado') {
            motivoContainer.style.display = 'block';
        } else {
            motivoContainer.style.display = 'none';
        }
    });
    
    // Modal para imágenes de productos
    document.querySelectorAll('.producto-img-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const imgSrc = this.getAttribute('href');
            document.getElementById('modalImagen').src = imgSrc;
            const modal = new bootstrap.Modal(document.getElementById('imagenModal'));
            modal.show();
        });
    });
    </script>
</body>
</html>