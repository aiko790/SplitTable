<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

// === CORRECCIÓN: Leer el JSON ANTES de usar $input ===
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST; // fallback por si acaso
}

$id_mesa     = isset($input['id_mesa']) ? (int)$input['id_mesa'] : 0;
$id_cliente  = isset($input['id_cliente']) ? (int)$input['id_cliente'] : 0;
$id_platillo = isset($input['id_platillo']) ? (int)$input['id_platillo'] : 0;
$cantidad    = isset($input['cantidad']) ? (int)$input['cantidad'] : 0;
$comentario  = isset($input['comentario']) ? trim($input['comentario']) : '';

if (!$id_mesa || !$id_cliente || !$id_platillo || $cantidad <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Datos incompletos']);
    exit;
}

// ... resto del código igual ...

$conexion->begin_transaction();

try {
    // 1) Obtener o crear pedido activo (capturado)
    $stmt = $conexion->prepare("SELECT id_pedido FROM pedidos WHERE id_mesa = ? AND estado = 'capturado' LIMIT 1 FOR UPDATE");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $id_pedido = null;
    if ($row = $res->fetch_assoc()) {
        $id_pedido = (int)$row['id_pedido'];
    }
    $stmt->close();

    if (!$id_pedido) {
        $stmt = $conexion->prepare("INSERT INTO pedidos (id_mesa, estado, fecha_creacion) VALUES (?, 'capturado', NOW())");
        $stmt->bind_param("i", $id_mesa);
        $stmt->execute();
        $id_pedido = $conexion->insert_id;
        $stmt->close();
    }

    // 2) Obtener precio y nombre del platillo
    $stmt = $conexion->prepare("SELECT precio, nombre FROM platillos WHERE id_platillo = ? AND activo = 1 AND disponible = 1 LIMIT 1");
    $stmt->bind_param("i", $id_platillo);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$plat = $res->fetch_assoc()) {
        throw new Exception("Platillo no disponible");
    }
    $precio_unitario = (float)$plat['precio'];
    $nombre_platillo = $plat['nombre'];
    $stmt->close();

    // 3) Buscar orden existente (mismo pedido, cliente, platillo, y comentario normalizado)
    // Normalizar comentario: convertir NULL a cadena vacía para comparación
    $comentario_busqueda = $comentario === '' ? '' : $comentario;
    $stmt = $conexion->prepare("SELECT id_orden, cantidad FROM ordenes WHERE id_pedido = ? AND id_cliente = ? AND id_platillo = ? AND COALESCE(comentario, '') = ? FOR UPDATE");
    $stmt->bind_param("iiis", $id_pedido, $id_cliente, $id_platillo, $comentario_busqueda);
    $stmt->execute();
    $res = $stmt->get_result();
    $orden_existente = $res->fetch_assoc();
    $stmt->close();

    if ($orden_existente) {
        // Actualizar cantidad y subtotal
        $id_orden = $orden_existente['id_orden'];
        $nueva_cantidad = $orden_existente['cantidad'] + $cantidad;
        $nuevo_subtotal = round($precio_unitario * $nueva_cantidad, 2);
        $stmt = $conexion->prepare("UPDATE ordenes SET cantidad = ?, subtotal = ? WHERE id_orden = ?");
        $stmt->bind_param("idi", $nueva_cantidad, $nuevo_subtotal, $id_orden);
        $stmt->execute();
        $stmt->close();
        $mensaje = 'ok';
        $respuesta_cantidad = $nueva_cantidad;
        $respuesta_subtotal = $nuevo_subtotal;
    } else {
        // Insertar nueva orden
        $subtotal = round($precio_unitario * $cantidad, 2);
        $stmt = $conexion->prepare("INSERT INTO ordenes (id_pedido, id_cliente, id_platillo, cantidad, precio_unitario, subtotal, comentario) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiidds", $id_pedido, $id_cliente, $id_platillo, $cantidad, $precio_unitario, $subtotal, $comentario);
        $stmt->execute();
        $id_orden = $conexion->insert_id;
        $stmt->close();
        $mensaje = 'ok';
        $respuesta_cantidad = $cantidad;
        $respuesta_subtotal = $subtotal;
    }

    $conexion->commit();

    echo json_encode([
        'ok' => true,
        'msg' => $mensaje,
        'data' => [
            'id_pedido' => $id_pedido,
            'id_orden'  => $id_orden,
            'id_cliente' => $id_cliente,
            'id_platillo' => $id_platillo,
            'platillo'  => $nombre_platillo,
            'cantidad'  => $respuesta_cantidad,
            'precio'    => $precio_unitario,
            'subtotal'  => $respuesta_subtotal,
            'comentario' => $comentario
        ]
    ]);
} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}