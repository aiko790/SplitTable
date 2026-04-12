<?php
require_once __DIR__ . "/../../config/mesero_session.php";

$id_mesero = (int)$_SESSION['id_mesero'];

// Consulta principal para obtener las mesas y el mesero asignado
$sql = "SELECT m.id_mesa, m.nombre_mesa, m.estado, m.id_mesero_actual, me.nombre AS nombre_mesero
        FROM mesas m
        LEFT JOIN meseros me ON m.id_mesero_actual = me.id_mesero
        ORDER BY m.id_mesa";
$res = $conexion->query($sql);
$mesas = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $mesas[$row['id_mesa']] = $row;
    }
}

// Contar pedidos por mesa
$ids = array_keys($mesas);
$counts = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conexion->prepare("
        SELECT id_mesa,
               SUM(estado = 'en_preparacion') AS en_preparacion,
               SUM(estado = 'listo') AS listo
        FROM pedidos
        WHERE id_mesa IN ($placeholders)
        GROUP BY id_mesa
    ");
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $counts[$row['id_mesa']] = [
            'en_preparacion' => (int)$row['en_preparacion'],
            'listo' => (int)$row['listo']
        ];
    }
    $stmt->close();
}

// Armar array final con estado visual (prioridad CORREGIDA)
$result = [];
foreach ($mesas as $id => $m) {
    $isMine = ($m['id_mesero_actual'] == $id_mesero);
    $inPrep = $counts[$id]['en_preparacion'] ?? 0;
    $readyCount = $counts[$id]['listo'] ?? 0;

    // Lógica de prioridad:
    // 1. Si está libre → libre
    // 2. Si NO es mi mesa → otra (independientemente de pedidos)
    // 3. Si ES mi mesa → evaluar listo, cocina, propia
    if ($m['estado'] == 0) {
        $visualState = 'libre';
        $estadoTexto = 'Libre';
        $subTexto = 'Disponible';
    } elseif (!$isMine) {
        // Prioridad máxima: mesa de otro mesero → siempre "otra"
        $visualState = 'otra';
        $estadoTexto = 'Ocupada';
        $subTexto = 'Otro mesero';
    } else {
        // Es mi mesa, evaluar según pedidos
        if ($readyCount > 0) {
            $visualState = 'lista';
            $estadoTexto = 'Pedido listo';
            $subTexto = 'Servir';
        } elseif ($inPrep > 0) {
            $visualState = 'cocina';
            $estadoTexto = 'Pedido en cocina';
            $subTexto = 'Preparando';
        } else {
            $visualState = 'propia';
            $estadoTexto = 'Ocupada';
            $subTexto = 'Atendiendo';
        }
    }

    $result[] = [
        'id_mesa' => $id,
        'nombre_mesa' => $m['nombre_mesa'],
        'visualState' => $visualState,
        'estadoTexto' => $estadoTexto,
        'subTexto' => $subTexto,
        'nombre_mesero' => $m['nombre_mesero'] ?? null,
        'isMine' => $isMine,
        'selected' => ($_GET['id_mesa'] ?? null) == $id
    ];
}

header('Content-Type: application/json');
echo json_encode($result);