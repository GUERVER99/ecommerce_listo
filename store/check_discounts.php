<?php
require_once 'config.php';

header('Content-Type: application/json');

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as count, MIN(descuento_fin) as min_fin 
                       FROM productos 
                       WHERE precio_descuento IS NOT NULL 
                       AND descuento_inicio <= ? 
                       AND descuento_fin >= ?");
$stmt->execute([$now, $now]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'hasDiscounts' => $result['count'] > 0,
    'endDate' => $result['min_fin']
]);
?>