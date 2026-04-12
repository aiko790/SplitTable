<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;
if (!$id_mesa) {
    echo json_encode(['ok' => false, 'msg' => 'ID mesa requerido']);
    exit;
}

$stmt = $conexion->prepare("SELECT COUNT(*) as pendientes FROM pedidos WHERE id_mesa = ? AND estado != 'entregado'");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$pendientes = $res->fetch_assoc()['pendientes'];
$stmt->close();

echo json_encode([
    'ok' => true,
    'todos_entregados' => ($pendientes == 0),
    'pendientes' => $pendientes
]);