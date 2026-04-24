<?php
require_once __DIR__ . "/../../config/admin_session.php";
require_once __DIR__ . "/../../vendor/autoload.php"; // FPDF

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.html");
    exit;
}

$tipo = $_GET['tipo'] ?? 'resumen';
$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fin = $_GET['fin'] ?? date('Y-m-d');

// Obtener datos según tipo
if ($tipo === 'resumen') {
    // KPIs
    $stmt = $conexion->prepare("SELECT COUNT(*) as cuentas, SUM(total_general) as ingresos, AVG(total_general) as promedio FROM historial_ventas WHERE DATE(fecha_cierre) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $kpis = $stmt->get_result()->fetch_assoc();

    // Top platillos
    $stmt = $conexion->prepare("SELECT nombre_platillo, SUM(cantidad) as total FROM historial_detalle hd JOIN historial_ventas hv ON hd.id_historial=hv.id_historial WHERE DATE(fecha_cierre) BETWEEN ? AND ? GROUP BY nombre_platillo ORDER BY total DESC LIMIT 10");
    $stmt->bind_param("ss", $inicio, $fin);
    $stmt->execute();
    $platillos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Generar PDF con FPDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Reporte de Ventas', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, "Periodo: $inicio al $fin", 0, 1, 'C');
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Resumen General', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Total de cuentas: ' . ($kpis['cuentas']??0), 0, 1);
    $pdf->Cell(0, 6, 'Ingresos totales: $' . number_format($kpis['ingresos']??0,2), 0, 1);
    $pdf->Cell(0, 6, 'Ticket promedio: $' . number_format($kpis['promedio']??0,2), 0, 1);
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Top 10 Platillos', 0, 1);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(120, 7, 'Platillo', 1);
    $pdf->Cell(40, 7, 'Cantidad', 1, 1);
    $pdf->SetFont('Arial', '', 9);
    foreach ($platillos as $p) {
        $pdf->Cell(120, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $p['nombre_platillo']), 1);
        $pdf->Cell(40, 6, $p['total'], 1, 1);
    }
    $pdf->Output('D', "reporte_{$inicio}_{$fin}.pdf");
    exit;
}

// Otros tipos de reporte se pueden agregar aquí...