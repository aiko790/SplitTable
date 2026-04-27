<?php
require_once __DIR__ . "/../config/cocina_session.php";

$nombre_cocinero = $_SESSION['nombre_cocinero'] ?? 'Cocinero';
$server_time = date('H:i:s');

// Obtener todas las mesas para la barra lateral
$sqlMesas = "SELECT id_mesa, nombre_mesa, estado FROM mesas ORDER BY id_mesa";
$resMesas = $conexion->query($sqlMesas);
$todasMesas = [];
while ($m = $resMesas->fetch_assoc()) {
    $todasMesas[] = $m;
}

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

<header class="topbar">
    <div class="brand">
        <img src="../img_genericos/descarga.png" alt="Logo" class="logo-img">
        <span class="restaurant-name">MONOCROMO</span>
    </div>
    <div class="topcenter">
        <div class="clock" id="clock"><?= $server_time ?></div>
    </div>
    <nav class="top-actions">
        <a class="btn small danger" href="../auth/logout.php">Salir</a>
    </nav>
</header>

<main class="cocina-layout">
    <!-- Barra lateral de mesas -->
    <aside class="sidebar-mesas">
        <h4>Todas las mesas</h4>
        <ul class="lista-mesas" id="listaMesas">
            <?php foreach ($todasMesas as $mesa): ?>
            <li class="item-mesa" data-mesa="<?= $mesa['id_mesa'] ?>">
                <?= htmlspecialchars($mesa['nombre_mesa']) ?>
                <span class="estado-mesa <?= $mesa['estado'] ? 'ocupada' : 'libre' ?>"></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <!-- Contenido principal -->
    <section class="pedidos-area" id="pedidosArea">
        <div class="empty-state">
            <p>Cargando pedidos...</p>
        </div>
    </section>
</main>

<!-- Modal de confirmación genérico -->
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

<script>
// Reloj en tiempo real
(function() {
    function actualizarReloj() {
        const ahora = new Date();
        const h = String(ahora.getHours()).padStart(2, '0');
        const m = String(ahora.getMinutes()).padStart(2, '0');
        const s = String(ahora.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = h + ':' + m + ':' + s;
    }
    actualizarReloj();
    setInterval(actualizarReloj, 1000);
})();
</script>

<script src="dashboard.js?v=<?= $jsVer ?>"></script>
</body>
</html>