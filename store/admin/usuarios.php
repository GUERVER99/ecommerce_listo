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

// Obtener todos los usuarios con paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$inicio = ($pagina > 1) ? ($pagina * $por_pagina - $por_pagina) : 0;

$stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM usuarios ORDER BY fecha_registro DESC LIMIT $inicio, $por_pagina");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $conn->query("SELECT FOUND_ROWS() as total")->fetch()['total'];
$paginas = ceil($total / $por_pagina);

// Procesar cambios de rol
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_rol'])) {
    $usuario_id = $_POST['usuario_id'];
    $nuevo_rol = $_POST['nuevo_rol'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
    $stmt->execute([$nuevo_rol, $usuario_id]);
    
    $_SESSION['mensaje'] = "Rol actualizado correctamente";
    header("Location: usuarios.php");
    exit;
}

// Procesar eliminación de usuario
if(isset($_GET['eliminar'])) {
    $usuario_id = $_GET['eliminar'];
    
    // No permitir eliminarse a sí mismo
    if($usuario_id != $_SESSION['usuario_id']) {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        
        $_SESSION['mensaje'] = "Usuario eliminado correctamente";
        header("Location: usuarios.php");
        exit;
    } else {
        $_SESSION['error'] = "No puedes eliminarte a ti mismo";
        header("Location: usuarios.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Kalo's Style</title>
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
        
        .badge-admin {
            background-color: #dc3545;
        }
        
        .badge-cliente {
            background-color: #28a745;
        }
        
        .search-form {
            max-width: 300px;
        }
        
        .navbar-toggler {
            color: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
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
                <li class="nav-item">
                    <a href="productos.php" class="nav-link">
                        <i class="fas fa-box-open"></i> Productos
                    </a>
                </li>
                <li class="nav-item active">
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
                    <h1 class="h2">Gestión de Usuarios</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../registro.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-user-plus"></i> Crear nuevo usuario
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
                                <h5 class="card-title mb-0">Listado de usuarios</h5>
                            </div>
                            <div class="col-md-6">
                                <form class="search-form float-md-end">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Buscar usuarios...">
                                        <button class="btn btn-outline-secondary" type="button">
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
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo $usuario['id']; ?></td>
                                            <td><?php echo $usuario['nombre']; ?></td>
                                            <td><?php echo $usuario['email']; ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?php echo $usuario['rol'] == 'admin' ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo ucfirst($usuario['rol']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalRol<?php echo $usuario['id']; ?>">
                                                        <i class="fas fa-user-edit"></i> Rol
                                                    </button>
                                                    <a href="editar-usuario.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </a>
                                                    <?php if($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                        <a href="usuarios.php?eliminar=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
                                                            <i class="fas fa-trash-alt"></i> Eliminar
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Modal para cambiar rol -->
                                                <div class="modal fade" id="modalRol<?php echo $usuario['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Cambiar rol de <?php echo $usuario['nombre']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Seleccionar nuevo rol</label>
                                                                        <select class="form-select" name="nuevo_rol" required>
                                                                            <option value="admin" <?php echo $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                                                            <option value="cliente" <?php echo $usuario['rol'] == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <button type="submit" name="cambiar_rol" class="btn btn-primary">Guardar cambios</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
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
                                        <a class="page-link" href="usuarios.php?pagina=<?php echo $pagina - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for($i = 1; $i <= $paginas; $i++): ?>
                                    <li class="page-item <?php echo $pagina == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="usuarios.php?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if($pagina < $paginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="usuarios.php?pagina=<?php echo $pagina + 1; ?>" aria-label="Next">
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