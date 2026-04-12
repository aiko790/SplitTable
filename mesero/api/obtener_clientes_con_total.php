<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json');

$id_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : 0;
if (!$id_mesa) {
    echo json_encode(['ok' => false, 'msg' => 'ID mesa requerido']);
    exit;
}

$sql = "SELECT cm.id_cliente, cm.nombre, SUM(o.subtotal) AS total
        FROM ordenes o
        JOIN pedidos p ON o.id_pedido = p.id_pedido
        JOIN clientes_mesa cm ON o.id_cliente = cm.id_cliente
        WHERE p.id_mesa = ? AND p.estado = 'entregado'
        GROUP BY cm.id_cliente, cm.nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_mesa);
$stmt->execute();
$res = $stmt->get_result();
$clientes = [];
$totalGeneral = 0;
while ($row = $res->fetch_assoc()) {
    $clientes[] = [
        'id_cliente' => (int)$row['id_cliente'],
        'nombre' => $row['nombre'],
        'total' => (float)$row['total']
    ];
    $totalGeneral += (float)$row['total'];
}
$stmt->close();

echo json_encode([
    'ok' => true,
    'clientes' => $clientes,
    'total_general' => $totalGeneral
]);