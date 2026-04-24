<?php
require_once __DIR__ . "/../config/admin_session.php";
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.html");
    exit();
}
$nombre_admin = $_SESSION['nombre_admin'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial & Reportes | Admin</title>
    <link rel="stylesheet" href="css/cocineros.css">
    <link rel="stylesheet" href="css/historial.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="header">
    <div class="header-content">
        <div>
            <h1 class="header-title">📊 Historial & Reportes</h1>
            <p class="header-subtitle">Dashboard ejecutivo de ventas (últimos 7 días)</p>
        </div>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">
    <!-- KPIs renovados -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">📋</div>
            <div class="kpi-content">
                <span class="kpi-label">Total cuentas</span>
                <span class="kpi-value" id="kpiCuentas">--</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">💰</div>
            <div class="kpi-content">
                <span class="kpi-label">Ingresos totales</span>
                <span class="kpi-value" id="kpiIngresos">--</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">📊</div>
            <div class="kpi-content">
                <span class="kpi-label">Ticket promedio</span>
                <span class="kpi-value" id="kpiPromedio">--</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">🏆</div>
            <div class="kpi-content">
                <span class="kpi-label">Mejor mesero</span>
                <span class="kpi-value" id="kpiMejorMeseroNombre">--</span>
                <span class="kpi-sub" id="kpiMejorMeseroMonto"></span>
            </div>
        </div>
    </div>

        <!-- Gráfico y Top listas -->
    <div class="dashboard-row">
        <div class="card" style="flex:2; cursor:pointer;" id="cardIngresos" onclick="abrirModalIngresosDetalle()">
            <div class="card-header"><h2>Ingresos por día (última semana)</h2></div>
            <div style="padding:20px;">
                <canvas id="ventasChart" style="max-height:250px;"></canvas>
            </div>
        </div>
        <div class="card" style="flex:1; cursor:pointer;" id="topPlatillosCard">
            <div class="card-header"><h2>🍕 Top 5 platillos</h2></div>
            <div id="topPlatillos" class="top-list"></div>
        </div>
    </div>

    <div class="dashboard-row">
        <div class="card" style="flex:1; cursor:pointer;" id="topMeserosCard">
            <div class="card-header"><h2>👨‍🍳 Top meseros</h2></div>
            <div id="topMeseros" class="top-list"></div>
        </div>
        <!-- Tarjeta de Últimas cuentas (tabla, abre modal al hacer clic) -->
        <div class="card" style="flex:2; cursor:pointer;" id="cardUltimasCuentas">
            <div class="card-header"><h2>🕒 Últimas cuentas cerradas</h2></div>
            <div id="ultimasCuentas"></div>
        </div>
    </div>

    <!-- Botón de descarga de informe (abajo) -->
    <div style="margin-top: 30px; text-align: right;">
        <button class="btn-submit" id="mostrarModalDescarga">📄 Descargar informe PDF</button>
    </div>
</div>

<!-- Modal para drill-down (genérico) -->
<div id="detalleModal" class="modal">
    <div class="modal-content" style="max-width:900px; width:90%;">
        <span class="modal-close" onclick="closeDetalleModal()">&times;</span>
        <div id="detalleModalBody">Cargando...</div>
    </div>
</div>

<!-- Modal exclusivo para descarga de PDF -->
<div id="modalDescargaPDF" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <span class="modal-close" onclick="closeModalDescarga()">&times;</span>
        <h3>Descargar informe</h3>
        <div class="form-group">
            <label>Período</label>
            <select id="periodoDescarga">
                <option value="hoy">Hoy</option>
                <option value="7d" selected>Últimos 7 días</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="personalizado">Personalizado</option>
            </select>
        </div>
        <div id="personalizadoDescarga" style="display:none;">
            <div class="form-group">
                <label>Desde</label>
                <input type="date" id="fechaInicioDescarga" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
            </div>
            <div class="form-group">
                <label>Hasta</label>
                <input type="date" id="fechaFinDescarga" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <button class="btn-submit" id="confirmarDescarga">Descargar</button>
    </div>
</div>

<script src="js/historial.js"></script>
</body>
</html>