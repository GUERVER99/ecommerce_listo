<?php
require_once '../config.php';

// Formatear precio
function formatearPrecio($precio) {
    return '$' . number_format($precio, 0, ',', '.');
}

// Verificar si el usuario es admin
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    $_SESSION['error'] = "Acceso denegado. Debes ser administrador.";
    header("Location: ../login.php");
    exit;
}

// Configuración de paginación
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 10;
$inicio = ($pagina - 1) * $por_pagina;

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Consulta base con filtros
$query = "SELECT SQL_CALC_FOUND_ROWS p.*, u.nombre as cliente_nombre, u.email as cliente_email, 
          u.telefono as cliente_telefono, u.direccion as cliente_direccion
          FROM pedidos p
          JOIN usuarios u ON p.usuario_id = u.id
          WHERE 1=1";

$params = [];
$types = [];

// Aplicar filtros
if(!empty($filtro_estado)) {
    $query .= " AND p.estado = ?";
    $params[] = $filtro_estado;
    $types[] = PDO::PARAM_STR;
}

if(!empty($filtro_busqueda)) {
    $query .= " AND (p.id = ? OR u.nombre LIKE ? OR u.email LIKE ?)";
    $params[] = is_numeric($filtro_busqueda) ? $filtro_busqueda : 0;
    $types[] = is_numeric($filtro_busqueda) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $params[] = "%$filtro_busqueda%";
    $types[] = PDO::PARAM_STR;
    $params[] = "%$filtro_busqueda%";
    $types[] = PDO::PARAM_STR;
}

$query .= " ORDER BY p.fecha_pedido DESC LIMIT ?, ?";
$params[] = $inicio;
$types[] = PDO::PARAM_INT;
$params[] = $por_pagina;
$types[] = PDO::PARAM_INT;

$stmt = $pdo->prepare($query);

// Vincular parámetros dinámicamente
foreach($params as $i => &$param) {
    $stmt->bindParam($i+1, $param, $types[$i]);
}

$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $pdo->query("SELECT FOUND_ROWS() as total")->fetch()['total'];
$paginas = ceil($total / $por_pagina);

// Estados disponibles
$estados = [
    'pendiente' => 'Pendiente',
    'procesando' => 'Procesando',
    'enviado' => 'Enviado',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado'
];

// Procesar cambio de estado
if(isset($_POST['cambiar_estado'])) {
    $pedido_id = (int)$_POST['pedido_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $motivo_cancelacion = isset($_POST['motivo_cancelacion']) ? trim($_POST['motivo_cancelacion']) : null;
    
    try {
        $pdo->beginTransaction();
        
        if($nuevo_estado == 'cancelado') {
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, fecha_cancelacion = NOW(), motivo_cancelacion = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $motivo_cancelacion, $pedido_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $pedido_id]);
        }
        
        // Si se cancela, devolver stock
        if($nuevo_estado == 'cancelado') {
            $stmt = $pdo->prepare("SELECT producto_id, talla_id, cantidad FROM pedido_items WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($items as $item) {
                if($item['talla_id']) {
                    $stmt = $pdo->prepare("UPDATE producto_tallas SET stock = stock + ? WHERE producto_id = ? AND talla_id = ?");
                    $stmt->execute([$item['cantidad'], $item['producto_id'], $item['talla_id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                    $stmt->execute([$item['cantidad'], $item['producto_id']]);
                }
            }
        }
        
        $pdo->commit();
        $_SESSION['mensaje'] = "Estado del pedido #$pedido_id actualizado correctamente a " . $estados[$nuevo_estado];
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error al actualizar el estado: " . $e->getMessage();
    }
    
    header("Location: pedidos.php" . (!empty($filtro_estado) ? "?estado=$filtro_estado" : ""));
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Panel Admin</title>
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
            z-index: 1050;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1rem;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
            min-height: 100vh;
            background: #f8f9fa;
            padding: 20px;
        }
        
        /* Navbar toggle button */
        .navbar-toggler {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            background: #343a40;
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
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
        
        .filtros-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Sidebar Mobile */
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
                background: rgba(0,0,0,0.5);
                z-index: 1040;
                display: none;
            }
            
            .sidebar.show + .sidebar-overlay {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Botón Hamburguesa (solo visible en móviles) -->
    <button class="navbar-toggler" type="button" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay para cerrar el menú (solo en móviles) -->
    <div class="sidebar-overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h1 class="h4 mb-0">Khalo's Style</h1>
        </div>
        <?php include 'includes/sidebar.php'; ?>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestión de Pedidos</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="exportar_pedidos.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-export me-1"></i> Exportar
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filtros-container mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Filtrar por estado</label>
                        <select id="estado" name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <?php foreach($estados as $valor => $texto): ?>
                                <option value="<?= $valor ?>" <?= $filtro_estado == $valor ? 'selected' : '' ?>>
                                    <?= $texto ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="busqueda" class="form-label">Buscar (ID, Nombre o Email)</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" 
                               value="<?= htmlspecialchars($filtro_busqueda) ?>" placeholder="Buscar...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="pedidos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Resumen estadístico -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h6 class="card-title">Total Pedidos</h6>
                            <h4 class="card-text"><?= number_format($total) ?></h4>
                        </div>
                    </div>
                </div>
                <?php 
                $resumen = $pdo->query("SELECT estado, COUNT(*) as total FROM pedidos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach($estados as $estado => $nombre): 
                    $total_estado = $resumen[$estado] ?? 0;
                ?>
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><?= $nombre ?></h6>
                            <h4 class="card-text"><?= number_format($total_estado) ?></h4>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Tabla de pedidos -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($pedidos)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No se encontraron pedidos</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($pedidos as $pedido): ?>
                                        <tr>
                                            <td>#<?= $pedido['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                            <td>
                                                <div><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($pedido['cliente_email']) ?></small>
                                            </td>
                                            <td><?= formatearPrecio($pedido['total']) ?></td>
                                            <td>
                                                <span class="badge rounded-pill badge-<?= $pedido['estado'] ?>">
                                                    <?= $estados[$pedido['estado']] ?>
                                                </span>
                                                <?php if($pedido['estado'] == 'cancelado' && !empty($pedido['motivo_cancelacion'])): ?>
                                                    <div class="text-danger small mt-1"><?= htmlspecialchars($pedido['motivo_cancelacion']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="pedido_detalle.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Detalle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if($paginas > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $pagina-1 ?>&estado=<?= $filtro_estado ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                                    &laquo; Anterior
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $paginas; $i++): ?>
                                <li class="page-item <?= $pagina == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?pagina=<?= $i ?>&estado=<?= $filtro_estado ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $pagina+1 ?>&estado=<?= $filtro_estado ?>&busqueda=<?= urlencode($filtro_busqueda) ?>">
                                    Siguiente &raquo;
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle del sidebar en móviles
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
        document.querySelector('.sidebar-overlay').style.display = 'block';
    });

    // Cerrar sidebar al hacer clic en el overlay
    document.querySelector('.sidebar-overlay').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show');
        this.style.display = 'none';
    });
    </script>
</body>
</html>