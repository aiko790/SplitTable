<?php
require_once __DIR__ . "/../../config/mesero_session.php"; // carga config.php y sesión
header('Content-Type: application/json; charset=utf-8');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;
if (!$id_mesa) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta id_mesa']);
    exit;
}

try {
    // CAMBIO: Ya NO filtramos por estado, devolvemos TODOS los pedidos (incluyendo 'entregado')
    $stmt = $conexion->prepare("SELECT id_pedido, estado, fecha_creacion, fecha_listo, fecha_entregado FROM pedidos WHERE id_mesa = ? ORDER BY fecha_creacion ASC");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $pedidos = [];
    while ($p = $res->fetch_assoc()) {
        $p['ordenes'] = [];
        $pedidos[(int)$p['id_pedido']] = $p;
    }
    $stmt->close();

    if (count($pedidos) === 0) {
        echo json_encode(['ok' => true, 'pedidos' => []]);
        exit;
    }

    // Obtener ordenes para esos pedidos
    $ids = array_keys($pedidos);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT o.id_orden, o.id_pedido, o.id_cliente, cm.nombre AS cliente_nombre, p.nombre AS platillo_nombre, cat.nombre AS categoria, o.cantidad, o.precio_unitario, o.subtotal, o.comentario
            FROM ordenes o
            JOIN clientes_mesa cm ON o.id_cliente = cm.id_cliente
            JOIN platillos p ON o.id_platillo = p.id_platillo
            JOIN categorias_platillo cat ON p.id_categoria = cat.id_categoria
            WHERE o.id_pedido IN ($placeholders)
            ORDER BY o.id_orden ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($o = $res->fetch_assoc()) {
        $pedidos[(int)$o['id_pedido']]['ordenes'][] = $o;
    }
    $stmt->close();

    // Re-index pedidos como array simple
    $out = array_values($pedidos);
    echo json_encode(['ok' => true, 'pedidos' => $out]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}