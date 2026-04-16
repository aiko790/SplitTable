<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id_platillo = isset($input['id_platillo']) ? (int)$input['id_platillo'] : 0;
$disponible  = isset($input['disponible']) ? (int)$input['disponible'] : 0;

if (!$id_platillo || ($disponible !== 0 && $disponible !== 1)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
    exit;
}

$stmt = $conexion->prepare("UPDATE platillos SET disponible = ? WHERE id_platillo = ?");
$stmt->bind_param("ii", $disponible, $id_platillo);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['ok' => true, 'disponible' => $disponible]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'No se realizó ningún cambio']);
}
$stmt->close();
?>