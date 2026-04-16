<?php
require_once __DIR__ . "/../config/cocina_session.php";

$nombre_cocinero = $_SESSION['nombre_cocinero'] ?? 'Cocinero';

$cssVer = file_exists(__DIR__ . '/dashboard.css') ? filemtime(__DIR__ . '/dashboard.css') : time();
$jsVer  = file_exists(__DIR__ . '/dashboard.js')   ? filemtime(__DIR__ . '/dashboard.js')   : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina | Panel de pedidos</title>
    <link rel="stylesheet" href="dashboard.css?v=<?= $cssVer ?>">
</head>
<body>

<header class="header">
    <div class="header-content">
        <div>
            <div class="header-title">🍳 Panel de Cocina</div>
            <div class="header-subtitle">Pedidos en preparación</div>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <span style="color: #e9ecef; font-size: 0.875rem;">👨‍🍳 <?= htmlspecialchars($nombre_cocinero) ?></span>
            <a href="../auth/logout.php" class="btn-back">Salir</a>
        </div>
    </div>
</header>

<main class="container">
    <div id="orders-container" class="orders-grid">
        <div class="empty-state">
            <p>Cargando pedidos...</p>
        </div>
    </div>
    <div class="auto-refresh">
        Actualización automática cada 5 segundos
    </div>
</main>

<!-- Modal de confirmación personalizado -->
<div id="modalConfirmacion" class="modal hidden">
    <div class="modal-box">
        <h3 id="modalTitulo">Confirmar acción</h3>
        <p id="modalMensaje">¿Estás seguro?</p>
        <div class="modal-actions">
            <button class="btn-cancelar" id="btnCancelarModal">Cancelar</button>
            <button class="btn-confirmar" id="btnConfirmarModal">Aceptar</button>
        </div>
    </div>
</div>

<script src="dashboard.js?v=<?= $jsVer ?>"></script>
</body>
</html>