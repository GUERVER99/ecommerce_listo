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

// Obtener todos los usuarios
$stmt = $conn->prepare("SELECT * FROM usuarios ORDER BY fecha_registro DESC");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar cambios de rol
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_rol'])) {
    $usuario_id = $_POST['usuario_id'];
    $nuevo_rol = $_POST['nuevo_rol'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
    $stmt->execute([$nuevo_rol, $usuario_id]);
    
    $_SESSION['mensaje'] = "Rol actualizado correctamente";
    header("Location: dashboard.php");
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
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "No puedes eliminarte a ti mismo";
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: white;
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
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar bg-dark collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="usuarios.php">
                                <i class="fas fa-users"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="productos.php">
                                <i class="fas fa-shopping-bag"></i> Productos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="pedidos.php" class="nav-link">
                                <i class="fas fa-shopping-cart"></i> Pedidos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../perfil.php">
                                <i class="fas fa-user"></i> Mi perfil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Administrador</h1>
                </div>
                
                <?php if(isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Resumen de usuarios</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $total_usuarios = count($usuarios);
                                $total_admins = 0;
                                $total_clientes = 0;
                                
                                foreach($usuarios as $usuario) {
                                    if($usuario['rol'] == 'admin') {
                                        $total_admins++;
                                    } else {
                                        $total_clientes++;
                                    }
                                }
                                ?>
                                <p>Total de usuarios: <strong><?php echo $total_usuarios; ?></strong></p>
                                <p>Administradores: <strong><?php echo $total_admins; ?></strong></p>
                                <p>Clientes: <strong><?php echo $total_clientes; ?></strong></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Acciones rápidas</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="usuarios.php" class="btn btn-primary">Gestionar usuarios</a>
                                    <a href="productos.php" class="btn btn-secondary">Ver productos</a>
                                    <a href="crear_usuario.php" class="btn btn-info">Crear nuevo usuario</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de usuarios</h5>
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
                                        <th>Fecha registro</th>
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
                                                        <i class="fas fa-user-edit"></i> Cambiar rol
                                                    </button>
                                                    <?php if($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                        <a href="dashboard.php?eliminar=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">
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
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activar el sidebar
        document.addEventListener('DOMContentLoaded', function() {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.add('show');
            }
        });
    </script>
</body>
</html>