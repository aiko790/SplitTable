<?php
require_once __DIR__ . "/../../config/admin_session.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? 'dashboard';

// -------------------- DASHBOARD --------------------
if ($action === 'dashboard') {
    $periodo = $_GET['periodo'] ?? '7d';
    $fecha_inicio = $_GET['inicio'] ?? null;
    $fecha_fin = $_GET['fin'] ?? null;

    switch ($periodo) {
        case 'hoy': $inicio = $fin = date('Y-m-d'); break;
        case 'semana':
            $inicio = date('Y-m-d', strtotime('monday this week'));
            $fin    = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'mes':
            $inicio = date('Y-m-01');
            $fin    = date('Y-m-t');
            break;
        case 'personalizado':
            $inicio = $fecha_inicio ? date('Y-m-d', strtotime($fecha_inicio)) : date('Y-m-d', strtotime('-7 days'));
            $fin    = $fecha_fin   ? date('Y-m-d', strtotime($fecha_fin))   : date('Y-m-d');
            break;
        default: // 7d
            $inicio = date('Y-m-d', strtotime('-7 days'));
            $fin    = date('Y-m-d');
    }

    // KPIs
    $stmt = $conexion->prepare("SELECT COUNT(*) as total_cuentas, SUM(total_general) as ingresos_totales, AVG(total_general) as ticket_promedio FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $kpis = $stmt->get_result()->fetch_assoc();

    // Mejor mesero
    $stmt = $conexion->prepare("SELECT nombre_mesero, SUM(total_general) as total FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_mesero ORDER BY total DESC LIMIT 1");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $mejor_mesero = $stmt->get_result()->fetch_assoc();

    // Gráfico de ingresos por día (rellenar ceros)
    $grafico = ['labels' => [], 'valores' => []];
    $period = new DatePeriod(new DateTime($inicio), new DateInterval('P1D'), (new DateTime($fin))->modify('+1 day'));
    foreach ($period as $date) {
        $grafico['labels'][] = $date->format('d/m');
        $grafico['valores'][$date->format('Y-m-d')] = 0;
    }
    $stmt = $conexion->prepare("SELECT DATE(fecha_cierre) as fecha, SUM(total_general) as ingresos FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(fecha_cierre)");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $grafico['valores'][$row['fecha']] = (float)$row['ingresos'];
    }
    $grafico['valores'] = array_values($grafico['valores']);

    // Top 5 platillos
    $stmt = $conexion->prepare("SELECT hd.nombre_platillo, SUM(hd.cantidad) as total_vendido FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY hd.nombre_platillo ORDER BY total_vendido DESC LIMIT 5");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $top_platillos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Top 3 meseros
    $stmt = $conexion->prepare("SELECT nombre_mesero, SUM(total_general) as total, COUNT(*) as cuentas FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_mesero ORDER BY total DESC LIMIT 3");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $top_meseros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Últimas 5 cuentas
    $stmt = $conexion->prepare("SELECT id_historial, nombre_mesa, nombre_mesero, total_general, fecha_cierre FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? ORDER BY fecha_cierre DESC LIMIT 5");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $ultimas_cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'ok' => true,
        'periodo' => ['inicio' => $inicio, 'fin' => $fin],
        'kpis' => [
            'total_cuentas' => (int)($kpis['total_cuentas'] ?? 0),
            'ingresos_totales' => round((float)($kpis['ingresos_totales'] ?? 0), 2),
            'ticket_promedio' => round((float)($kpis['ticket_promedio'] ?? 0), 2)
        ],
        'mejor_mesero' => $mejor_mesero,
        'grafico' => $grafico,
        'top_platillos' => $top_platillos,
        'top_meseros' => $top_meseros,
        'ultimas_cuentas' => $ultimas_cuentas
    ]);
    exit;
}

// -------------------- DONUT (PLATILLOS Y CATEGORÍAS) --------------------
if ($action === 'donut') {
    $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fin    = $_GET['fin']    ?? date('Y-m-d');

    $stmt = $conexion->prepare("SELECT hd.nombre_platillo, SUM(hd.cantidad) as total FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY hd.nombre_platillo ORDER BY total DESC");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $platillos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conexion->prepare("SELECT hd.categoria, SUM(hd.cantidad) as total FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY hd.categoria ORDER BY total DESC");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'platillos' => $platillos, 'categorias' => $categorias]);
    exit;
}

// -------------------- EVOLUCIÓN DE PLATILLO --------------------
if ($action === 'evolucion_platillo') {
    $nombre = $_GET['nombre'] ?? '';
    $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fin    = $_GET['fin']    ?? date('Y-m-d');

    if (!$nombre) { echo json_encode(['error' => 'Falta nombre']); exit; }

    $labels = []; $valores = [];
    $period = new DatePeriod(new DateTime($inicio), new DateInterval('P1D'), (new DateTime($fin))->modify('+1 day'));
    foreach ($period as $date) {
        $labels[] = $date->format('d/m');
        $valores[$date->format('Y-m-d')] = 0;
    }

    $stmt = $conexion->prepare("SELECT DATE(hv.fecha_cierre) as fecha, SUM(hd.cantidad) as total FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE hd.nombre_platillo = ? AND DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(hv.fecha_cierre)");
    $stmt->bind_param("sss", $nombre, $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $valores[$row['fecha']] = (int)$row['total'];
    }

    echo json_encode(['ok' => true, 'labels' => $labels, 'valores' => array_values($valores)]);
    exit;
}

// -------------------- MESEROS BARRAS --------------------
if ($action === 'meseros_barras') {
    $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fin    = $_GET['fin']    ?? date('Y-m-d');

    $stmt = $conexion->prepare("SELECT nombre_mesero, SUM(total_general) as total FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_mesero ORDER BY total DESC");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $meseros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['ok' => true, 'meseros' => $meseros]);
    exit;
}

// -------------------- EVOLUCIÓN DE MESERO --------------------
if ($action === 'evolucion_mesero') {
    $nombre = $_GET['nombre'] ?? '';
    $inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fin    = $_GET['fin']    ?? date('Y-m-d');

    if (!$nombre) { echo json_encode(['error' => 'Falta nombre']); exit; }

    $labels = []; $valores = [];
    $period = new DatePeriod(new DateTime($inicio), new DateInterval('P1D'), (new DateTime($fin))->modify('+1 day'));
    foreach ($period as $date) {
        $labels[] = $date->format('d/m');
        $valores[$date->format('Y-m-d')] = 0;
    }

    $stmt = $conexion->prepare("SELECT DATE(fecha_cierre) as fecha, SUM(total_general) as total FROM historial_ventas WHERE nombre_mesero = ? AND DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(fecha_cierre)");
    $stmt->bind_param("sss", $nombre, $inicio, $fin);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $valores[$row['fecha']] = (float)$row['total'];
    }

    echo json_encode(['ok' => true, 'labels' => $labels, 'valores' => array_values($valores)]);
    exit;
}

// -------------------- CUENTAS PAGINADAS --------------------
if ($action === 'cuentas_paginadas') {
    $pagina = (int)($_GET['pagina'] ?? 1);
    $por_pagina = 20;
    $offset = ($pagina - 1) * $por_pagina;
    
    $stmt = $conexion->prepare("
        SELECT 
            id_historial,
            nombre_mesa,
            nombre_mesero,
            cantidad_personas,
            total_productos,
            total_general,
            fecha_cierre
        FROM historial_ventas
        ORDER BY fecha_cierre DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $por_pagina, $offset);
    $stmt->execute();
    $cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($cuentas as &$c) {
        $fecha = new DateTime($c['fecha_cierre'], new DateTimeZone('UTC'));
        $fecha->setTimezone(new DateTimeZone('America/Mexico_City'));
        $c['fecha_cierre'] = $fecha->format('Y-m-d H:i:s');
    }
    
    $total = $conexion->query("SELECT COUNT(*) as total FROM historial_ventas")->fetch_assoc()['total'];
    $hay_mas = ($offset + $por_pagina) < $total;
    
    echo json_encode(['ok' => true, 'cuentas' => $cuentas, 'hay_mas' => $hay_mas, 'pagina' => $pagina]);
    exit;
}

// -------------------- NUEVO: COMPARATIVA DE INGRESOS --------------------
if ($action === 'comparativa_ingresos') {
    $periodo = $_GET['periodo'] ?? 'semana';
    $inicio = $_GET['inicio'] ?? null;
    $fin = $_GET['fin'] ?? null;
    
    // Determinar período actual y anterior
    if ($periodo === 'personalizado' && $inicio && $fin) {
        $inicio_actual = date('Y-m-d', strtotime($inicio));
        $fin_actual = date('Y-m-d', strtotime($fin));
        $diff = (strtotime($fin_actual) - strtotime($inicio_actual)) / 86400;
        $inicio_anterior = date('Y-m-d', strtotime($inicio_actual . " - {$diff} days"));
        $fin_anterior = date('Y-m-d', strtotime($inicio_actual . ' -1 day'));
    } else {
        switch ($periodo) {
            case 'semana':
                $inicio_actual = date('Y-m-d', strtotime('monday this week'));
                $fin_actual = date('Y-m-d', strtotime('sunday this week'));
                $inicio_anterior = date('Y-m-d', strtotime('monday last week'));
                $fin_anterior = date('Y-m-d', strtotime('sunday last week'));
                break;
            case 'mes':
                $inicio_actual = date('Y-m-01');
                $fin_actual = date('Y-m-t');
                $inicio_anterior = date('Y-m-01', strtotime('first day of last month'));
                $fin_anterior = date('Y-m-t', strtotime('last day of last month'));
                break;
            default: // 7d
                $fin_actual = date('Y-m-d');
                $inicio_actual = date('Y-m-d', strtotime('-7 days'));
                $fin_anterior = date('Y-m-d', strtotime('-8 days'));
                $inicio_anterior = date('Y-m-d', strtotime('-15 days'));
        }
    }
    
    // Datos período actual
    $labels = []; $valores_actual = []; $valores_anterior = [];
    $period = new DatePeriod(new DateTime($inicio_actual), new DateInterval('P1D'), (new DateTime($fin_actual))->modify('+1 day'));
    foreach ($period as $date) {
        $labels[] = $date->format('d/m');
        $valores_actual[$date->format('Y-m-d')] = 0;
    }
    
    $stmt = $conexion->prepare("SELECT DATE(fecha_cierre) as fecha, SUM(total_general) as ingresos FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(fecha_cierre)");
    $stmt->bind_param("ss", $inicio_actual, $fin_actual);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $valores_actual[$row['fecha']] = (float)$row['ingresos'];
    }
    $total_actual = array_sum($valores_actual);
    
    // Datos período anterior
    $period_ant = new DatePeriod(new DateTime($inicio_anterior), new DateInterval('P1D'), (new DateTime($fin_anterior))->modify('+1 day'));
    foreach ($period_ant as $date) {
        $valores_anterior[$date->format('Y-m-d')] = 0;
    }
    
    $stmt = $conexion->prepare("SELECT DATE(fecha_cierre) as fecha, SUM(total_general) as ingresos FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(fecha_cierre)");
    $stmt->bind_param("ss", $inicio_anterior, $fin_anterior);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $valores_anterior[$row['fecha']] = (float)$row['ingresos'];
    }
    $total_anterior = array_sum($valores_anterior);
    
    // Calcular variación
    $variacion = $total_anterior > 0 ? (($total_actual - $total_anterior) / $total_anterior) * 100 : 0;
    
    echo json_encode([
        'ok' => true,
        'labels' => $labels,
        'valores_actual' => array_values($valores_actual),
        'valores_anterior' => array_values($valores_anterior),
        'total_actual' => round($total_actual, 2),
        'total_anterior' => round($total_anterior, 2),
        'variacion' => round($variacion, 1),
        'periodo' => ['actual' => ['inicio' => $inicio_actual, 'fin' => $fin_actual], 'anterior' => ['inicio' => $inicio_anterior, 'fin' => $fin_anterior]]
    ]);
    exit;
}

// -------------------- NUEVO: EVOLUCIÓN DE TODOS LOS MESEROS --------------------
if ($action === 'evolucion_meseros_todos') {
    $periodo = $_GET['periodo'] ?? '7d';
    $inicio = $_GET['inicio'] ?? null;
    $fin = $_GET['fin'] ?? null;
    
    switch ($periodo) {
        case 'semana':
            $inicio = date('Y-m-d', strtotime('monday this week'));
            $fin = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'mes':
            $inicio = date('Y-m-01');
            $fin = date('Y-m-t');
            break;
        case 'personalizado':
            $inicio = $inicio ? date('Y-m-d', strtotime($inicio)) : date('Y-m-d', strtotime('-7 days'));
            $fin = $fin ? date('Y-m-d', strtotime($fin)) : date('Y-m-d');
            break;
        default: // 7d
            $inicio = date('Y-m-d', strtotime('-7 days'));
            $fin = date('Y-m-d');
    }
    
    // Obtener top 5 meseros del período
    $stmt = $conexion->prepare("SELECT nombre_mesero FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_mesero ORDER BY SUM(total_general) DESC LIMIT 5");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $meseros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $labels = [];
    $period = new DatePeriod(new DateTime($inicio), new DateInterval('P1D'), (new DateTime($fin))->modify('+1 day'));
    foreach ($period as $date) {
        $labels[] = $date->format('d/m');
    }
    
    $datasets = [];
    $colores = ['#8b0000', '#2c7be5', '#16a085', '#e67e22', '#8e44ad'];
    
    foreach ($meseros as $index => $m) {
        $valores = [];
        foreach ($period as $date) {
            $valores[$date->format('Y-m-d')] = 0;
        }
        
        $stmt = $conexion->prepare("SELECT DATE(fecha_cierre) as fecha, SUM(total_general) as total FROM historial_ventas WHERE nombre_mesero = ? AND DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY DATE(fecha_cierre)");
        $stmt->bind_param("sss", $m['nombre_mesero'], $inicio, $fin);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $valores[$row['fecha']] = (float)$row['total'];
        }
        
        $datasets[] = [
            'label' => $m['nombre_mesero'],
            'data' => array_values($valores),
            'borderColor' => $colores[$index % count($colores)],
            'backgroundColor' => 'transparent',
            'tension' => 0.2,
            'borderWidth' => 2
        ];
    }
    
    echo json_encode(['ok' => true, 'labels' => $labels, 'datasets' => $datasets]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);