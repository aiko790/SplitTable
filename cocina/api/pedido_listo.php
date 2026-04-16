<?php
require_once __DIR__ . "/../../config/cocina_session.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'cocinero') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
    exit();
}

$id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;

if ($id_pedido <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID de pedido no válido']);
    exit();
}

$sql = "
    UPDATE pedidos
    SET estado = 'listo',
        fecha_listo = NOW()
    WHERE id_pedido = ?
      AND estado = 'en_preparacion'
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$afectadas = $stmt->affected_rows;
$stmt->close();

if ($afectadas > 0) {
    echo json_encode(['ok' => true, 'message' => 'Pedido marcado como listo']);
} else {
    echo json_encode(['ok' => false, 'message' => 'El pedido ya no está en preparación']);
}
exit();