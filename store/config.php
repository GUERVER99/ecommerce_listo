<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'store');
define('BASE_URL', 'http://localhost/store/');
define('ASSETS_PATH', BASE_URL . 'assets/');
define('PRODUCT_IMAGES_PATH', ASSETS_PATH . 'img/productos/');

// Conexión a la base de datos
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // También creamos $conn por compatibilidad con código existente
    $conn = $pdo;
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Iniciar sesión
session_start();
?>