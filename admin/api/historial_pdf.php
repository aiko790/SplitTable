<?php
require_once __DIR__ . "/../../config/admin_session.php";
require_once __DIR__ . "/../../vendor/autoload.php"; // FPDF

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.html");
    exit;
}

$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fin    = $_GET['fin']    ?? date('Y-m-d');

// ==================== CONSULTAS ====================

// 1. KPIs globales + mejor mesero
$stmt = $conexion->prepare("SELECT COUNT(*) AS total_ventas, SUM(total_general) AS ingresos_totales, AVG(total_general) AS ticket_promedio FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ?");
$stmt->bind_param("ss", $inicio, $fin);
$stmt->execute();
$kpis = $stmt->get_result()->fetch_assoc();

$stmt = $conexion->prepare("SELECT nombre_mesero, SUM(total_general) as total FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_mesero ORDER BY total DESC LIMIT 1");
$stmt->bind_param("ss", $inicio, $fin);
$stmt->execute();
$mejor_mesero = $stmt->get_result()->fetch_assoc();

// 2. Ranking de platillos
$stmt = $conexion->prepare("SELECT nombre_platillo, SUM(cantidad) AS total_vendido FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_platillo ORDER BY total_vendido DESC");
$stmt->bind_param("ss", $inicio, $fin);
$stmt->execute();
$platillos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_platillos_vendidos = array_sum(array_column($platillos, 'total_vendido'));

// 3. Ranking de categorías
$stmt = $conexion->prepare("SELECT categoria, SUM(cantidad) AS total_vendido FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(hv.fecha_cierre) BETWEEN ? AND ? GROUP BY categoria ORDER BY total_vendido DESC");
$stmt->bind_param("ss", $inicio, $fin);
$stmt->execute();
$categorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_categorias_vendidos = array_sum(array_column($categorias, 'total_vendido'));

// ==================== GENERAR PDF ====================
$pdf = new FPDF();
$pdf->AddPage();

// -------------------- LOGO --------------------
$logoPath = __DIR__ . '/../img/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 26);
} else {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(139, 0, 0);
    $pdf->Cell(30, 10, '', 0, 1);
    $pdf->SetTextColor(30, 30, 30);
}

// -------------------- CABECERA INSTITUCIONAL --------------------
$pdf->SetY(15);
$pdf->SetFillColor(139, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 20);
$pdf->Cell(0, 15, 'REPORTE DE VENTAS', 0, 1, 'C', true);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, "Periodo: $inicio hasta $fin", 0, 1, 'C', true);
$pdf->Ln(12);
$pdf->SetTextColor(30, 30, 30);

// ==================== 1. INDICADORES GLOBALES ====================
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(139, 0, 0);
$pdf->Cell(0, 10, '1. INDICADORES GLOBALES', 0, 1);

$pdf->SetDrawColor(139, 0, 0);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetTextColor(30, 30, 30);
$cw = 45; $ch = 8;

// Fila de etiquetas
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Cell($cw, $ch, 'Ventas Totales', 1, 0, 'C', true);
$pdf->Cell(3, $ch, '', 0, 0);
$pdf->Cell($cw, $ch, 'Ingresos Totales', 1, 0, 'C', true);
$pdf->Cell(3, $ch, '', 0, 0);
$pdf->Cell($cw, $ch, 'Ticket Promedio', 1, 0, 'C', true);
$pdf->Cell(3, $ch, '', 0, 0);
$pdf->Cell($cw, $ch, 'Mejor Mesero', 1, 1, 'C', true);

// Fila de valores
$pdf->SetFont('Arial', '', 14);
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell($cw, 16, number_format($kpis['total_ventas'] ?? 0), 1, 0, 'C', true);
$pdf->Cell(3, 16, '', 0, 0);
$pdf->Cell($cw, 16, '$' . number_format($kpis['ingresos_totales'] ?? 0, 2), 1, 0, 'C', true);
$pdf->Cell(3, 16, '', 0, 0);
$pdf->Cell($cw, 16, '$' . number_format($kpis['ticket_promedio'] ?? 0, 2), 1, 0, 'C', true);
$pdf->Cell(3, 16, '', 0, 0);

// Mejor mesero (nombre arriba, monto abajo)
$nombreM = $mejor_mesero['nombre_mesero'] ?? 'N/A';
$totalM  = $mejor_mesero['total'] ?? 0;
$textoM  = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $nombreM);
if ($pdf->GetStringWidth($textoM) > 38) {
    $textoM = substr($textoM, 0, 14) . '...';
}
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell($cw, 8, $textoM, 1, 0, 'C', true);
$pdf->Cell(0, 8, '', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->SetX($pdf->GetX() + ($cw * 3) + 9);
$pdf->Cell($cw, 8, '$' . number_format($totalM, 2), 1, 0, 'C', true);
$pdf->Ln(16);

// ==================== 2. PLATILLOS MAS VENDIDOS ====================
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(139, 0, 0);
$pdf->Cell(0, 10, '2. PLATILLOS MAS DEMANDADOS', 0, 1);

$pdf->SetDrawColor(139, 0, 0);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(139, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(110, 8, 'Platillo', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(30, 8, '% del Total', 1, 1, 'C', true);
$pdf->SetTextColor(30, 30, 30);

$pdf->SetFont('Arial', '', 9);
$top_platillos = array_slice($platillos, 0, 10);
$fill = false;
foreach ($top_platillos as $p) {
    $porcentaje = $total_platillos_vendidos > 0 ? round(($p['total_vendido'] / $total_platillos_vendidos) * 100, 1) : 0;
    $pdf->SetFillColor($fill ? 250 : 255);
    $pdf->SetDrawColor(230, 230, 230);
    $pdf->Cell(110, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $p['nombre_platillo']), 1, 0, 'L', true);
    $pdf->Cell(30, 7, $p['total_vendido'], 1, 0, 'C', true);
    $pdf->Cell(30, 7, $porcentaje . '%', 1, 1, 'C', true);
    $fill = !$fill;
}
$pdf->Ln(16);

// ==================== 3. CATEGORIAS MAS DEMANDADAS ====================
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(139, 0, 0);
$pdf->Cell(0, 10, '3. CATEGORIAS MAS DEMANDADAS', 0, 1);

$pdf->SetDrawColor(139, 0, 0);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(139, 0, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(110, 8, 'Categoria', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(30, 8, '% del Total', 1, 1, 'C', true);
$pdf->SetTextColor(30, 30, 30);

$pdf->SetFont('Arial', '', 9);
$fill = false;
foreach ($categorias as $c) {
    $porcentaje = $total_categorias_vendidos > 0 ? round(($c['total_vendido'] / $total_categorias_vendidos) * 100, 1) : 0;
    $pdf->SetFillColor($fill ? 250 : 255);
    $pdf->SetDrawColor(230, 230, 230);
    $pdf->Cell(110, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $c['categoria']), 1, 0, 'L', true);
    $pdf->Cell(30, 7, $c['total_vendido'], 1, 0, 'C', true);
    $pdf->Cell(30, 7, $porcentaje . '%', 1, 1, 'C', true);
    $fill = !$fill;
}

$pdf->Output('D', "informe_estadistico_{$inicio}_{$fin}.pdf");
exit;