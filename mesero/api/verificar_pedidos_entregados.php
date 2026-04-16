<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;
if (!$id_mesa) {
    echo json_encode(['ok' => false, 'msg' => 'ID mesa requerido']);
    exit;
}

// Verificar pedidos no entregados
$stmt = $conexion->prepare("SELECT COUNT(*) as pendientes FROM pedidos WHERE id_mesa = ? AND estado != 'entregado'");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$pendientes = $res->fetch_assoc()['pendientes'];
$stmt->close();

// Verificar si hay clientes
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM clientes_mesa WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$clientes = (int)$res->fetch_assoc()['total'];
$stmt->close();

// Verificar total de pedidos
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$total_pedidos = (int)$res->fetch_assoc()['total'];
$stmt->close();

$vacia = ($clientes === 0 && $total_pedidos === 0);
$todos_entregados = ($pendientes == 0);

echo json_encode([
    'ok' => true,
    'todos_entregados' => $todos_entregados,
    'pendientes' => $pendientes,
    'vacia' => $vacia,
    'total_pedidos' => $total_pedidos
]);