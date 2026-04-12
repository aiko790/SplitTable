<?php
require_once __DIR__ . "/../../config/mesero_session.php"; // carga config.php y sesión
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id_mesa = isset($input['id_mesa']) ? (int)$input['id_mesa'] : 0;
$id_pedido = isset($input['id_pedido']) ? (int)$input['id_pedido'] : 0;

if (!$id_mesa && !$id_pedido) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta id_mesa o id_pedido']);
    exit;
}

try {
    if ($id_pedido) {
        $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'en_preparacion' WHERE id_pedido = ? AND estado = 'capturado'");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    } else {
        $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'en_preparacion' WHERE id_mesa = ? AND estado = 'capturado'");
        $stmt->bind_param("i", $id_mesa);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    }

    echo json_encode(['ok' => true, 'updated' => $affected]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}