<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Obtener id_orden desde GET (para DELETE) o desde body (para PUT)
$id_orden = 0;
if ($method === 'DELETE') {
    $id_orden = isset($_GET['id_orden']) ? (int)$_GET['id_orden'] : 0;
} else {
    $id_orden = isset($input['id_orden']) ? (int)$input['id_orden'] : 0;
}

if (!$id_orden) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta id_orden']);
    exit;
}

try {
    // Obtener la orden actual para verificar existencia y obtener precio unitario
    $stmt = $conexion->prepare("SELECT id_pedido, id_platillo, cantidad, precio_unitario FROM ordenes WHERE id_orden = ?");
    $stmt->bind_param("i", $id_orden);
    $stmt->execute();
    $res = $stmt->get_result();
    $orden = $res->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Orden no encontrada']);
        exit;
    }

    $id_pedido = $orden['id_pedido'];

    if ($method === 'DELETE') {
        // Eliminar orden
        $stmt = $conexion->prepare("DELETE FROM ordenes WHERE id_orden = ?");
        $stmt->bind_param("i", $id_orden);
        $stmt->execute();
        $stmt->close();

        // Verificar si el pedido quedó sin órdenes
        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM ordenes WHERE id_pedido = ?");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = $res->fetch_assoc()['total'];
        $stmt->close();

        if ($count == 0) {
            // Eliminar pedido si no tiene órdenes
            $stmt = $conexion->prepare("DELETE FROM pedidos WHERE id_pedido = ?");
            $stmt->bind_param("i", $id_pedido);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['ok' => true, 'msg' => 'Orden eliminada']);
        exit;
    }

    if ($method === 'PUT') {
        // Actualizar cantidad y comentario
        $nueva_cantidad = isset($input['cantidad']) ? (int)$input['cantidad'] : $orden['cantidad'];
        $comentario = isset($input['comentario']) ? trim($input['comentario']) : null;

        if ($nueva_cantidad <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Cantidad inválida']);
            exit;
        }

        $nuevo_subtotal = round($orden['precio_unitario'] * $nueva_cantidad, 2);

        $stmt = $conexion->prepare("UPDATE ordenes SET cantidad = ?, subtotal = ?, comentario = ? WHERE id_orden = ?");
        $stmt->bind_param("idsi", $nueva_cantidad, $nuevo_subtotal, $comentario, $id_orden);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'ok' => true,
            'msg' => 'Orden actualizada',
            'data' => [
                'id_orden' => $id_orden,
                'cantidad' => $nueva_cantidad,
                'subtotal' => $nuevo_subtotal,
                'comentario' => $comentario
            ]
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>