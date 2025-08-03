<?php
require_once '../config.php';

// Verificar si el usuario es admin
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
} elseif($_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Obtener todos los productos con paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$inicio = ($pagina > 1) ? ($pagina * $por_pagina - $por_pagina) : 0;

$stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS p.*, 
                       (SELECT COUNT(*) FROM producto_imagenes WHERE producto_id = p.id) as total_imagenes,
                       (SELECT SUM(stock) FROM producto_tallas WHERE producto_id = p.id) as stock_total
                       FROM productos p 
                       ORDER BY fecha_creacion DESC 
                       LIMIT $inicio, $por_pagina");
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $conn->query("SELECT FOUND_ROWS() as total")->fetch()['total'];
$paginas = ceil($total / $por_pagina);

// Procesar eliminación de producto
if(isset($_GET['eliminar'])) {
    $producto_id = $_GET['eliminar'];
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    try {
        // Eliminar imágenes adicionales primero
        $stmt = $conn->prepare("DELETE FROM producto_imagenes WHERE producto_id = ?");
        $stmt->execute([$producto_id]);
        
        // Eliminar relaciones con tallas
        $stmt = $conn->prepare("DELETE FROM producto_tallas WHERE producto_id = ?");
        $stmt->execute([$producto_id]);
        
        // Luego eliminar el producto
        $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        
        $conn->commit();
        $_SESSION['mensaje'] = "Producto eliminado correctamente";
    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error al eliminar el producto: " . $e->getMessage();
    }
    
    header("Location: productos.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar.collapsed {
            margin-left: -250px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .product-img-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .badge-destacado {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-stock {
            font-size: 0.9em;
            padding: 0.35em 0.65em;
        }
        
        .stock-bajo {
            background-color: #dc3545;
            color: white;
        }
        
        .stock-medio {
            background-color: #fd7e14;
            color: white;
        }
        
        .stock-alto {
            background-color: #28a745;
            color: white;
        }
        
        .search-form {
            max-width: 300px;
        }
        
        /* Estilos para la tabla */
        .table th, .table td {
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .table td:nth-child(2) { /* Columna de imagen */
            width: 60px;
            padding: 8px;
        }
        
        .table td:nth-child(3) { /* Columna de nombre */
            min-width: 200px;
            white-space: normal;
        }
        
        .table td:nth-child(9) { /* Columna de acciones */
            min-width: 200px;
        }
        
        .actions-column {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .navbar-toggler {
            color: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        .img-thumbnail-container {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .img-thumbnail-wrapper {
            position: relative;
        }
        
        .img-thumbnail-wrapper .badge {
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .tallas-badge {
            font-size: 0.8em;
            margin-right: 3px;
            margin-bottom: 3px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.collapsed {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }
            
            .search-form {
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .table td:nth-child(3) {
                min-width: 150px;
            }
            
            .actions-column {
                flex-direction: column;
                gap: 3px;
            }
            
            .btn-sm {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header d-flex justify-content-between align-items-center">
                <h3>Kalo's Style</h3>
                <button type="button" id="sidebarCollapse" class="btn btn-dark d-md-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="list-unstyled components">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="productos.php" class="nav-link">
                        <i class="fas fa-box-open"></i> Productos
                    </a>
                </li>
                </li>
                <li class="nav-item">
                    <a href="usuarios.php" class="nav-link">
                        <i class="fas fa-users"></i> Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a href="pedidos.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main content -->
        <div id="content" class="main-content">
            <nav class="navbar navbar-expand-md navbar-light bg-light mb-4 d-md-none">
                <div class="container-fluid">
                    <button type="button" id="sidebarToggle" class="btn btn-dark">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a class="navbar-brand ms-3" href="#">Kalo's Style</a>
                </div>
            </nav>
            
            <div class="container-fluid">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Productos</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="crear-producto.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Nuevo producto
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title mb-0">Listado de productos</h5>
                            </div>
                            <div class="col-md-6">
                                <form class="search-form float-md-end" method="GET" action="">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar productos..." value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Imagen</th>
                                        <th>Nombre</th>
                                        <th>Stock</th>
                                        <th>Tallas</th>
                                        <th>Precio</th>
                                        <th>Destacado</th>
                                        <th>Imágenes</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($productos as $producto): ?>
                                        <?php
                                        // Obtener tallas para este producto
                                        $tallas_producto = [];
                                        try {
                                            $stmt_tallas = $conn->prepare("SELECT t.nombre, pt.stock 
                                                                         FROM producto_tallas pt 
                                                                         JOIN tallas t ON pt.talla_id = t.id 
                                                                         WHERE pt.producto_id = ?");
                                            $stmt_tallas->execute([$producto['id']]);
                                            $tallas_producto = $stmt_tallas->fetchAll(PDO::FETCH_ASSOC);
                                        } catch(PDOException $e) {
                                            // No hacer nada, simplemente no mostrar tallas
                                        }
                                        
                                        // Determinar clase CSS para el stock
                                        $stock_total = $producto['stock_total'] ?? 0;
                                        $stock_class = '';
                                        if($stock_total <= 5) {
                                            $stock_class = 'stock-bajo';
                                        } elseif($stock_total <= 15) {
                                            $stock_class = 'stock-medio';
                                        } else {
                                            $stock_class = 'stock-alto';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $producto['id']; ?></td>
                                            <td>
                                                <?php if($producto['imagen']): ?>
                                                    <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                                        class="product-img-thumb" 
                                                        alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                        onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                                <?php else: ?>
                                                    <img src="<?php echo ASSETS_PATH; ?>img/placeholder.jpg" 
                                                        class="product-img-thumb" 
                                                        alt="Sin imagen">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                            <td>
                                                <span class="badge badge-stock <?php echo $stock_class; ?>">
                                                    <?php echo $stock_total; ?> unidades
                                                </span>
                                            </td>
                                            <td>
                                                <?php if(!empty($tallas_producto)): ?>
                                                    <div class="d-flex flex-wrap">
                                                        <?php foreach($tallas_producto as $talla): ?>
                                                            <span class="badge bg-secondary tallas-badge" title="<?php echo $talla['stock']; ?> unidades">
                                                                <?php echo htmlspecialchars($talla['nombre']); ?>: <?php echo $talla['stock']; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Sin tallas</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($producto['precio_descuento']): ?>
                                                    <span class="text-success fw-bold">$<?php echo number_format($producto['precio_descuento'], 0, ',', '.'); ?></span>
                                                    <small class="text-muted text-decoration-line-through">$<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?></small>
                                                <?php else: ?>
                                                    $<?php echo number_format($producto['precio_normal'], 0, ',', '.'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($producto['destacado']): ?>
                                                    <span class="badge badge-destacado rounded-pill">Destacado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary rounded-pill">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($producto['total_imagenes'] > 0): ?>
                                                    <div class="img-thumbnail-container">
                                                        <span class="badge bg-info">+<?php echo $producto['total_imagenes']; ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?></td>
                                            <td>
                                                <div class="actions-column">
                                                    <a href="editar-producto.php?id=<?php echo $producto['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="productos.php?eliminar=<?php echo $producto['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este producto?')" title="Eliminar">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                    <a href="gestion-imagenes.php?id=<?php echo $producto['id']; ?>" class="btn btn-sm btn-info" title="Gestionar imágenes">
                                                        <i class="fas fa-images"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if($pagina > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="productos.php?pagina=<?php echo $pagina - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for($i = 1; $i <= $paginas; $i++): ?>
                                    <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="productos.php?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if($pagina < $paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="productos.php?pagina=<?php echo $pagina + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            
            // Toggle sidebar on mobile
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            });
            
            // Close sidebar when clicking the close button
            sidebarCollapse.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const isClickInsideSidebar = sidebar.contains(event.target) || sidebarToggle.contains(event.target);
                
                if (!isClickInsideSidebar && window.innerWidth <= 768 && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    content.classList.add('expanded');
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('collapsed');
                    content.classList.remove('expanded');
                }
            });
        });
    </script>
</body>
</html>