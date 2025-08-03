<?php
require_once 'config.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $email = limpiarDatos($_POST['email']);
    $password = limpiarDatos($_POST['password']);
    $confirm_password = limpiarDatos($_POST['confirm_password']);
    $direccion = limpiarDatos($_POST['direccion']);
    $telefono = limpiarDatos($_POST['telefono']);
    
    // Validaciones
    if(empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Todos los campos marcados con * son obligatorios';
    } elseif($password != $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif(strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if($stmt->rowCount() > 0) {
            $error = 'El email ya está registrado';
        } else {
            // Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar nuevo usuario
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, direccion, telefono, rol) VALUES (?, ?, ?, ?, ?, 'cliente')");
            
            if($stmt->execute([$nombre, $email, $password_hash, $direccion, $telefono])) {
                $success = 'Registro exitoso. Ahora puedes iniciar sesión.';
                header("refresh:2;url=login.php");
            } else {
                $error = 'Error al registrar. Inténtalo de nuevo.';
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
    <title>Registro - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .registration-form {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .registration-form h2 {
            margin-bottom: 30px;
            text-align: center;
            color: #0056b3;
        }
        
        .btn-register {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            font-weight: 600;
        }
        
        .btn-register:hover {
            background-color: #004494;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="registration-form">
            <h2>Crear una cuenta</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="registro.php" method="POST">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña * (mínimo 6 caracteres)</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar contraseña *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <button type="submit" class="btn btn-register">Registrarse</button>
                </form>
                <div class="mt-3 text-center">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>