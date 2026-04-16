<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id_mesa = isset($input['id_mesa']) ? (int)$input['id_mesa'] : 0;

if (!$id_mesa) {
    echo json_encode(['ok' => false, 'message' => 'ID de mesa no válido']);
    exit;
}

// Verificar que la mesa esté asignada al mesero actual
$stmt = $conexion->prepare("SELECT id_mesero_actual FROM mesas WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$mesa = $res->fetch_assoc();
$stmt->close();

if (!$mesa || $mesa['id_mesero_actual'] != $_SESSION['id_mesero']) {
    echo json_encode(['ok' => false, 'message' => 'No tienes permiso para liberar esta mesa']);
    exit;
}

// Liberar mesa
$stmt = $conexion->prepare("UPDATE mesas SET estado = 0, id_mesero_actual = NULL WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Mesa liberada correctamente']);