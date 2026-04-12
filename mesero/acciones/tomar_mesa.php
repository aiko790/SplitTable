<?php
require_once __DIR__ . "/../../config/mesero_session.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_mesa = isset($_POST['id_mesa']) ? (int)$_POST['id_mesa'] : 0;
$id_mesero = (int)$_SESSION['id_mesero'];

if ($id_mesa <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de mesa inválido']);
    exit;
}

// Verificar si la mesa está libre
$stmt = $conexion->prepare("SELECT estado FROM mesas WHERE id_mesa = ?");
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$resultado = $stmt->get_result();
$mesa = $resultado->fetch_assoc();

if(!$mesa) {
    echo json_encode(['success' => false, 'message' => 'Mesa no encontrada']);
    exit;
}

if($mesa['estado'] == 1) {
    echo json_encode(['success' => false, 'message' => 'La mesa ya está ocupada']);
    exit;
}

// Tomar la mesa
$stmt = $conexion->prepare("UPDATE mesas SET estado = 1, id_mesero_actual = ? WHERE id_mesa = ?");
$stmt->bind_param("ii", $id_mesero, $id_mesa);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Mesa tomada correctamente']);