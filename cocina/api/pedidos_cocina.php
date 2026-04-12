<?php
require_once __DIR__ . "/../../config/cocina_session.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'cocinero') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// Obtener pedidos en estado 'en_preparacion'
$sql = "
    SELECT p.id_pedido, p.fecha_creacion, m.nombre_mesa
    FROM pedidos p
    INNER JOIN mesas m ON p.id_mesa = m.id_mesa
    WHERE p.estado = 'en_preparacion'
    ORDER BY p.fecha_creacion ASC
";
$result = $conexion->query($sql);
$pedidos = [];

while ($row = $result->fetch_assoc()) {
    $id_pedido = (int)$row['id_pedido'];
    
    // Obtener detalles del pedido
    $sql_detalle = "
        SELECT
            cm.nombre AS cliente,
            pl.nombre AS platillo,
            cat.nombre AS categoria,
            o.cantidad,
            o.comentario
        FROM ordenes o
        INNER JOIN clientes_mesa cm ON o.id_cliente = cm.id_cliente
        INNER JOIN platillos pl ON o.id_platillo = pl.id_platillo
        INNER JOIN categorias_platillo cat ON pl.id_categoria = cat.id_categoria
        WHERE o.id_pedido = ?
        ORDER BY cm.nombre ASC, o.id_orden ASC
    ";
    $stmt = $conexion->prepare($sql_detalle);
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    $detalle = $stmt->get_result();
    
    $items = [];
    while ($item = $detalle->fetch_assoc()) {
        $items[] = [
            'cliente' => $item['cliente'],
            'platillo' => $item['platillo'],
            'categoria' => $item['categoria'],
            'cantidad' => (int)$item['cantidad'],
            'comentario' => $item['comentario'] ?? ''
        ];
    }
    $stmt->close();
    
    $pedidos[] = [
        'id_pedido' => $id_pedido,
        'nombre_mesa' => $row['nombre_mesa'],
        'fecha_creacion' => $row['fecha_creacion'],
        'items' => $items
    ];
}

echo json_encode($pedidos);
?>