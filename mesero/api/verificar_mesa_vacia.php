<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;
if (!$id_mesa) {
    echo json_encode(['ok' => false, 'msg' => 'ID de mesa requerido']);
    exit;
}

// Verificar si hay clientes
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM clientes_mesa WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$clientes = (int)$res->fetch_assoc()['total'];
$stmt->close();

// Verificar si hay pedidos (cualquier estado)
$stmt = $conexion->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$pedidos = (int)$res->fetch_assoc()['total'];
$stmt->close();

$vacia = ($clientes === 0 && $pedidos === 0);

echo json_encode(['ok' => true, 'vacia' => $vacia]);