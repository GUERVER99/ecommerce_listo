<?php
require_once '../config.php';

// Función para limpiar datos de entrada
function limpiarDatos($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

// Verificar si el usuario es admin
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
} elseif($_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Obtener ID del usuario a editar
if(!isset($_GET['id'])) {
    header("Location: usuarios.php");
    exit;
}

$usuario_id = $_GET['id'];

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$usuario) {
    header("Location: usuarios.php");
    exit;
}

$error = '';
$success = '';

// Procesar actualización de usuario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $email = limpiarDatos($_POST['email']);
    $direccion = limpiarDatos($_POST['direccion']);
    $telefono = limpiarDatos($_POST['telefono']);
    $rol = limpiarDatos($_POST['rol']);
    
    // Validaciones
    if(empty($nombre) || empty($email)) {
        $error = 'Nombre y email son obligatorios';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no tiene un formato válido';
    } else {
        // Verificar si el email ya existe (excluyendo al usuario actual)
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $usuario_id]);
        
        if($stmt->rowCount() > 0) {
            $error = 'El email ya está registrado por otro usuario';
        } else {
            // Actualizar usuario
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, direccion = ?, telefono = ?, rol = ? WHERE id = ?");
            
            if($stmt->execute([$nombre, $email, $direccion, $telefono, $rol, $usuario_id])) {
                $success = 'Usuario actualizado correctamente';
                header("refresh:2;url=usuarios.php");
            } else {
                $error = 'Error al actualizar el usuario';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Kalo's Style</title>
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
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Editar Usuario</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="usuarios.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nombre" class="form-label">Nombre completo *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $usuario['nombre']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Correo electrónico *</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario['email']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="rol" class="form-label">Rol *</label>
                                            <select class="form-select" id="rol" name="rol" required>
                                                <option value="admin" <?php echo $usuario['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                                <option value="cliente" <?php echo $usuario['rol'] == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="direccion" class="form-label">Dirección</label>
                                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo $usuario['direccion']; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo $usuario['telefono']; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Fecha de registro</label>
                                            <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Cambiar contraseña</h5>
                            <form method="POST" action="cambiar-password.php">
                                <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nueva_password" class="form-label">Nueva contraseña</label>
                                            <input type="password" class="form-control" id="nueva_password" name="nueva_password">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirmar_password" class="form-label">Confirmar nueva contraseña</label>
                                            <input type="password" class="form-control" id="confirmar_password" name="confirmar_password">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="cambiar_password" class="btn btn-warning">Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>