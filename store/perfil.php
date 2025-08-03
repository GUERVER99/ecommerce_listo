<?php
require_once 'config.php';

// Función para limpiar datos de entrada
function limpiarDatos($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Obtener datos del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar actualización de perfil
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = limpiarDatos($_POST['nombre']);
    $direccion = limpiarDatos($_POST['direccion']);
    $telefono = limpiarDatos($_POST['telefono']);
    
    // Validaciones
    if(empty($nombre)) {
        $error = 'El nombre es obligatorio';
    } else {
        // Actualizar datos del usuario
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, direccion = ?, telefono = ? WHERE id = ?");
        
        if($stmt->execute([$nombre, $direccion, $telefono, $_SESSION['usuario_id']])) {
            $_SESSION['usuario_nombre'] = $nombre;
            $success = 'Perfil actualizado correctamente';
            header("refresh:2;url=perfil.php");
        } else {
            $error = 'Error al actualizar el perfil';
        }
    }
}

// Procesar cambio de contraseña
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = limpiarDatos($_POST['password_actual']);
    $nuevo_password = limpiarDatos($_POST['nuevo_password']);
    $confirmar_password = limpiarDatos($_POST['confirmar_password']);
    
    // Validaciones
    if(empty($password_actual) || empty($nuevo_password) || empty($confirmar_password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif($nuevo_password != $confirmar_password) {
        $error = 'Las nuevas contraseñas no coinciden';
    } elseif(strlen($nuevo_password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif(!password_verify($password_actual, $usuario['password'])) {
        $error = 'La contraseña actual es incorrecta';
    } else {
        // Hash de la nueva contraseña
        $nuevo_password_hash = password_hash($nuevo_password, PASSWORD_DEFAULT);
        
        // Actualizar contraseña
        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        
        if($stmt->execute([$nuevo_password_hash, $_SESSION['usuario_id']])) {
            $success = 'Contraseña actualizada correctamente';
            header("refresh:2;url=perfil.php");
        } else {
            $error = 'Error al actualizar la contraseña';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .profile-header {
            background-color: #0056b3;
            color: white;
            padding: 20px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .nav-pills .nav-link.active {
            background-color: #0056b3;
        }
        
        .nav-pills .nav-link {
            color: #0056b3;
        }
        
        @media (max-width: 768px) {
            .profile-info {
                text-align: center;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar_perfil.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-header text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x"></i>
                        </div>
                        <h3><?php echo $usuario['nombre']; ?></h3>
                        <span class="badge bg-<?php echo $usuario['rol'] == 'admin' ? 'danger' : 'success'; ?>">
                            <?php echo ucfirst($usuario['rol']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-envelope me-2"></i> <?php echo $usuario['email']; ?>
                            </li>
                            <?php if($usuario['telefono']): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-phone me-2"></i> <?php echo $usuario['telefono']; ?>
                                </li>
                            <?php endif; ?>
                            <?php if($usuario['direccion']): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-map-marker-alt me-2"></i> <?php echo $usuario['direccion']; ?>
                                </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <i class="fas fa-calendar-alt me-2"></i> Miembro desde: <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="card-header">
                        <ul class="nav nav-pills card-header-pills">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="pill" href="#perfil">Perfil</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#password">Contraseña</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <div class="tab-content">
                            <!-- Pestaña de perfil -->
                            <div class="tab-pane fade show active" id="perfil">
                                <form method="POST" action="perfil.php">
                                    <input type="hidden" name="actualizar_perfil" value="1">
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">Nombre completo</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $usuario['nombre']; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Correo electrónico</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo $usuario['email']; ?>" disabled>
                                        <small class="text-muted">Para cambiar tu email, contacta con soporte.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="direccion" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo $usuario['direccion'] ?? ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo $usuario['telefono'] ?? ''; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Actualizar perfil</button>
                                </form>
                            </div>
                            
                            <!-- Pestaña de contraseña -->
                            <div class="tab-pane fade" id="password">
                                <form method="POST" action="perfil.php">
                                    <input type="hidden" name="cambiar_password" value="1">
                                    <div class="mb-3">
                                        <label for="password_actual" class="form-label">Contraseña actual</label>
                                        <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="nuevo_password" class="form-label">Nueva contraseña</label>
                                        <input type="password" class="form-control" id="nuevo_password" name="nuevo_password" required>
                                        <small class="text-muted">Mínimo 6 caracteres.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmar_password" class="form-label">Confirmar nueva contraseña</label>
                                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>