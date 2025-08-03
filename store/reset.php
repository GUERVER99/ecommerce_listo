<?php
require_once 'config.php';

$error = '';
$success = '';

// Verificar token
if(isset($_GET['token'])) {
    $token = limpiarDatos($_GET['token']);
    
    // Buscar usuario con token válido
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE reset_token = ? AND reset_expiracion > NOW()");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$usuario) {
        $error = 'El enlace de recuperación no es válido o ha expirado';
    }
} else {
    $error = 'Enlace de recuperación no válido';
}

// Procesar cambio de contraseña
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $nuevo_password = limpiarDatos($_POST['nuevo_password']);
    $confirmar_password = limpiarDatos($_POST['confirmar_password']);
    
    if(empty($nuevo_password) || empty($confirmar_password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif($nuevo_password != $confirmar_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif(strlen($nuevo_password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Hash de la nueva contraseña
        $nuevo_password_hash = password_hash($nuevo_password, PASSWORD_DEFAULT);
        
        // Actualizar contraseña y limpiar token
        $stmt = $conn->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_expiracion = NULL WHERE id = ?");
        
        if($stmt->execute([$nuevo_password_hash, $usuario['id']])) {
            $success = 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión.';
            header("refresh:3;url=login.php");
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
    <title>Restablecer contraseña - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .reset-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .reset-form h2 {
            margin-bottom: 30px;
            text-align: center;
            color: #0056b3;
        }
        
        .btn-reset {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            font-weight: 600;
        }
        
        .btn-reset:hover {
            background-color: #004494;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="reset-form">
            <h2>Restablecer contraseña</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <div class="text-center mt-3">
                    <a href="recuperar.php" class="btn btn-outline-primary">Solicitar nuevo enlace</a>
                </div>
            <?php elseif($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="reset.php?token=<?php echo $token; ?>" method="POST">
                    <div class="mb-3">
                        <label for="nuevo_password" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="nuevo_password" name="nuevo_password" required>
                        <small class="text-muted">Mínimo 6 caracteres.</small>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_password" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-reset">Restablecer contraseña</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>