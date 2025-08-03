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

// Procesar cambio de contraseña
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_password'])) {
    $usuario_id = $_POST['usuario_id'];
    $nueva_password = limpiarDatos($_POST['nueva_password']);
    $confirmar_password = limpiarDatos($_POST['confirmar_password']);
    
    // Validaciones
    if(empty($nueva_password) || empty($confirmar_password)) {
        $_SESSION['error'] = 'Ambos campos son obligatorios';
    } elseif($nueva_password != $confirmar_password) {
        $_SESSION['error'] = 'Las contraseñas no coinciden';
    } elseif(strlen($nueva_password) < 6) {
        $_SESSION['error'] = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        // Hash de la nueva contraseña
        $nueva_password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
        
        // Actualizar contraseña
        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        
        if($stmt->execute([$nueva_password_hash, $usuario_id])) {
            $_SESSION['mensaje'] = 'Contraseña actualizada correctamente';
        } else {
            $_SESSION['error'] = 'Error al actualizar la contraseña';
        }
    }
    
    header("Location: editar-usuario.php?id=$usuario_id");
    exit;
} else {
    header("Location: usuarios.php");
    exit;
}
?>