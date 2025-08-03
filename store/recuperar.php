<?php
require_once 'config.php';

$error = '';
$success = '';

// Procesar solicitud de recuperación
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = limpiarDatos($_POST['email']);
    
    if(empty($email)) {
        $error = 'El correo electrónico es obligatorio';
    } else {
        // Verificar si el email existe
        $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($usuario) {
            // Generar token único
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Guardar token en la base de datos
            $stmt = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_expiracion = ? WHERE id = ?");
            $stmt->execute([$token, $expiracion, $usuario['id']]);
            
            // Enviar email (simulado en este ejemplo)
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset.php?token=$token";
            $mensaje = "Hola " . $usuario['nombre'] . ",\n\n";
            $mensaje .= "Para restablecer tu contraseña, haz clic en el siguiente enlace:\n";
            $mensaje .= $reset_link . "\n\n";
            $mensaje .= "El enlace expirará en 1 hora.\n";
            $mensaje .= "Si no solicitaste este cambio, ignora este mensaje.\n\n";
            $mensaje .= "Saludos,\nEl equipo de Kalo's Style";
            
            // En un entorno real, usarías mail() o una librería de email
            // mail($email, 'Restablecer contraseña - Ubraun', $mensaje);
            
            $success = 'Se ha enviado un enlace de recuperación a tu correo electrónico.';
        } else {
            $error = 'No existe una cuenta con ese correo electrónico';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .recovery-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .recovery-form h2 {
            margin-bottom: 30px;
            text-align: center;
            color: #0056b3;
        }
        
        .btn-recovery {
            background-color: #0056b3;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            font-weight: 600;
        }
        
        .btn-recovery:hover {
            background-color: #004494;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="recovery-form">
            <h2>Recuperar contraseña</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form action="recuperar.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <small class="text-muted">Ingresa el correo electrónico asociado a tu cuenta.</small>
                    </div>
                    <button type="submit" class="btn btn-recovery">Enviar enlace de recuperación</button>
                </form>
                <div class="mt-3 text-center">
                    <p><a href="login.php">Volver al inicio de sesión</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>