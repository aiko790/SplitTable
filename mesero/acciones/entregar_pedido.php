<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Obtener parámetros (pueden venir por POST form-data o JSON)
$id_pedido = null;
$id_mesa = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si es form-data (desde el frontend actual)
    if (isset($_POST['id_pedido'])) {
        $id_pedido = (int)$_POST['id_pedido'];
    }
    if (isset($_POST['id_mesa'])) {
        $id_mesa = (int)$_POST['id_mesa'];
    }
    
    // Si no, probar con JSON
    if (!$id_pedido && !$id_mesa) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id_pedido = isset($input['id_pedido']) ? (int)$input['id_pedido'] : 0;
        $id_mesa = isset($input['id_mesa']) ? (int)$input['id_mesa'] : 0;
    }
}

if (!$id_pedido && !$id_mesa) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta id_pedido o id_mesa']);
    exit;
}

try {
    if ($id_pedido) {
        // Cambiar estado de 'listo' a 'entregado'
        $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'entregado', fecha_entregado = NOW() WHERE id_pedido = ? AND estado = 'listo'");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    } else {
        // Opcional: entregar todos los pedidos listos de la mesa
        $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'entregado', fecha_entregado = NOW() WHERE id_mesa = ? AND estado = 'listo'");
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