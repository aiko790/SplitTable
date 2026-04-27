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

$data = json_decode(file_get_contents('php://input'), true);
$id_mesa = isset($data['id_mesa']) ? (int)$data['id_mesa'] : 0;

if ($id_mesa <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID de mesa no válido']);
    exit();
}

$sql = "UPDATE pedidos SET estado = 'listo', fecha_listo = NOW() WHERE id_mesa = ? AND estado = 'en_preparacion'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$afectadas = $stmt->affected_rows;
$stmt->close();

echo json_encode(['ok' => true, 'message' => "$afectadas pedido(s) marcado(s) como listo(s)"]);