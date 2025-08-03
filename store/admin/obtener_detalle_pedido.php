<?php
require_once '../config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

// Validar ID del pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

$pedido_id = (int)$_GET['id'];

try {
    // Obtener información básica del pedido
    $stmt = $pdo->prepare("SELECT 
        p.*, 
        u.nombre as cliente_nombre, 
        u.email as cliente_email, 
        u.telefono as cliente_telefono, 
        u.direccion as cliente_direccion
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE p.id = ?");
    
    if (!$stmt->execute([$pedido_id])) {
        throw new Exception('Error al ejecutar la consulta del pedido');
    }

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    // Obtener productos del pedido con información de tallas si existe
    $stmt = $pdo->prepare("SELECT 
        pi.*, 
        pr.nombre, 
        pr.descripcion,
        pr.imagen,
        t.nombre as talla_nombre
        FROM pedido_items pi
        JOIN productos pr ON pi.producto_id = pr.id
        LEFT JOIN tallas t ON pi.talla_id = t.id
        WHERE pi.pedido_id = ?");
    
    if (!$stmt->execute([$pedido_id])) {
        throw new Exception('Error al ejecutar la consulta de productos');
    }

    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear respuesta con todos los datos relevantes
    $response = [
        'success' => true,
        'pedido' => [
            'id' => (int)$pedido['id'],
            'fecha_pedido' => $pedido['fecha_pedido'],
            'estado' => $pedido['estado'],
            'subtotal' => (float)$pedido['subtotal'],
            'costo_envio' => (float)$pedido['costo_envio'],
            'total' => (float)$pedido['total'],
            'metodo_pago' => $pedido['metodo_pago'],
            'notas' => $pedido['notas'] ?? null,
            'fecha_cancelacion' => $pedido['fecha_cancelacion'] ?? null,
            'motivo_cancelacion' => $pedido['motivo_cancelacion'] ?? null,
            'cliente_nombre' => $pedido['cliente_nombre'],
            'cliente_email' => $pedido['cliente_email'],
            'cliente_telefono' => $pedido['cliente_telefono'],
            'cliente_direccion' => $pedido['cliente_direccion'],
            'direccion_envio' => $pedido['direccion_envio'] ?? $pedido['cliente_direccion'],
            'productos' => array_map(function($producto) {
                return [
                    'id' => (int)$producto['id'],
                    'producto_id' => (int)$producto['producto_id'],
                    'nombre' => $producto['nombre'],
                    'descripcion' => $producto['descripcion'] ?? '',
                    'imagen' => $producto['imagen'] ? '../assets/img/productos/' . $producto['imagen'] : '../assets/img/placeholder.jpg',
                    'talla_id' => isset($producto['talla_id']) ? (int)$producto['talla_id'] : null,
                    'talla_nombre' => $producto['talla_nombre'] ?? 'Única',
                    'cantidad' => (int)$producto['cantidad'],
                    'precio_unitario' => (float)$producto['precio_unitario'],
                    'subtotal' => (float)($producto['precio_unitario'] * $producto['cantidad'])
                ];
            }, $productos)
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}