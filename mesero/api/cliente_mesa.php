<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;

if (!$id_mesa) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Falta id_mesa']);
    exit;
}

// Validar que la mesa pertenezca al mesero actual
$id_mesero = (int)$_SESSION['id_mesero'];
$stmt = $conexion->prepare("SELECT id_mesero_actual FROM mesas WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$result = $stmt->get_result();
$mesa = $result->fetch_assoc();
$stmt->close();

if (!$mesa || $mesa['id_mesero_actual'] != $id_mesero) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tienes permiso para acceder a esta mesa']);
    exit;
}

// Obtener clientes de la mesa
$stmt = $conexion->prepare("SELECT id_cliente, nombre FROM clientes_mesa WHERE id_mesa = ? ORDER BY id_cliente");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$clientes = [];
while ($row = $res->fetch_assoc()) {
    $clientes[] = [
        'id_cliente' => (int)$row['id_cliente'],
        'nombre' => $row['nombre']
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'clientes' => $clientes]);
?>