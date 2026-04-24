<?php
require_once __DIR__ . "/../../config/admin_session.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$tipo = $_GET['tipo'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$fecha = $_GET['fecha'] ?? '';
$nombre = $_GET['nombre'] ?? '';

if ($tipo === 'cuenta') {
    $stmt = $conexion->prepare("SELECT * FROM historial_ventas WHERE id_historial = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $venta = $stmt->get_result()->fetch_assoc();
    if (!$venta) {
        echo json_encode(['error' => 'Cuenta no encontrada']);
        exit;
    }

    $stmt = $conexion->prepare("SELECT * FROM historial_detalle WHERE id_historial = ? ORDER BY nombre_cliente");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $detalle_pago = json_decode($venta['detalle_pago'], true);
    
    // NO sanitizar para mantener el nombre exacto que se usó al guardar
    $ticket = "tickets/ticket_mesa_{$venta['nombre_mesa']}_{$id}.pdf";
    $ticket_path = __DIR__ . '/../../' . $ticket;
    
    // Codificar la URL para que los espacios y caracteres especiales funcionen
    $ticket_url = "../tickets/" . rawurlencode("ticket_mesa_{$venta['nombre_mesa']}_{$id}.pdf");

    echo json_encode([
        'ok' => true,
        'venta' => $venta,
        'detalles' => $detalles,
        'pago' => $detalle_pago,
        'ticket_existe' => file_exists($ticket_path),
        'ticket_url' => $ticket_url
    ]);
    exit;
}

if ($tipo === 'dia') {
    $stmt = $conexion->prepare("SELECT * FROM historial_ventas WHERE DATE(fecha_cierre) = ? ORDER BY fecha_cierre DESC");
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['ok' => true, 'ventas' => $ventas, 'fecha' => $fecha]);
    exit;
}

if ($tipo === 'platillo' || $tipo === 'mesero') {
    $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fin    = $_GET['fin']    ?? date('Y-m-d');
    
    if ($tipo === 'platillo') {
        $stmt = $conexion->prepare("
            SELECT 
                hv.id_historial,
                hv.nombre_mesa,
                hv.fecha_cierre,
                hd.cantidad,
                hd.precio_unitario,
                hd.subtotal
            FROM historial_detalle hd
            JOIN historial_ventas hv ON hd.id_historial = hv.id_historial
            WHERE hd.nombre_platillo = ? AND DATE(hv.fecha_cierre) BETWEEN ? AND ?
            ORDER BY hv.fecha_cierre DESC
        ");
        $stmt->bind_param("sss", $nombre, $inicio, $fin);
    } else {
        $stmt = $conexion->prepare("
            SELECT * FROM historial_ventas 
            WHERE nombre_mesero = ? AND DATE(fecha_cierre) BETWEEN ? AND ?
            ORDER BY fecha_cierre DESC
        ");
        $stmt->bind_param("sss", $nombre, $inicio, $fin);
    }
    $stmt->execute();
    $ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['ok' => true, 'ventas' => $ventas, 'nombre' => $nombre]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Parámetros inválidos']);