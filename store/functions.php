<?php
include 'config.php';

// Función para conectar a la base de datos

// Formatear precio
function formatearPrecio($precio)
{
    return '$' . number_format($precio, 0, ',', '.');
}

// Obtener productos destacados
function obtenerProductosDestacados($limite = 4)
{
    $conexion = conectar();
    $sql = "SELECT * FROM productos WHERE destacado = 1 LIMIT ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(1, (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

