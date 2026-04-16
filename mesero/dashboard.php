<?php
require_once __DIR__ . "/../config/mesero_session.php";

$id_mesero = (int)$_SESSION['id_mesero'];
$nombre_mesero = $_SESSION['nombre_mesero'] ?? 'Mesero';

/* Obtener lista de mesas */
$sql = "SELECT m.id_mesa, m.nombre_mesa, m.estado, m.id_mesero_actual, me.nombre AS nombre_mesero
        FROM mesas m
        LEFT JOIN meseros me ON m.id_mesero_actual = me.id_mesero
        ORDER BY m.id_mesa";
$res = $conexion->query($sql);

$mesas = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $mesas[] = $row;
    }
}

/* Preparar statement para contar pedidos por mesa (en_preparacion y listo) */
$countStmt = $conexion->prepare("
    SELECT 
        SUM(estado = 'en_preparacion') AS en_preparacion,
        SUM(estado = 'listo') AS listo
    FROM pedidos
    WHERE id_mesa = ?
");
if (!$countStmt) {
    $countStmt = null;
}

/* Mesa seleccionada (por GET) */
$selected_mesa = isset($_GET['id_mesa']) ? (int)$_GET['id_mesa'] : null;

// ========== VALIDACIÓN: la mesa debe pertenecer al mesero actual ==========
$mesa_valida = false;
if ($selected_mesa) {
    $stmt = $conexion->prepare("SELECT id_mesero_actual FROM mesas WHERE id_mesa = ?");
    $stmt->bind_param("i", $selected_mesa);
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($row = $resultado->fetch_assoc()) {
        if ($row['id_mesero_actual'] == $id_mesero) {
            $mesa_valida = true;
        }
    }
    $stmt->close();

    if (!$mesa_valida) {
        header("Location: dashboard.php");
        exit;
    }
}
// ==========================================================================

/* Cargar clientes de mesa (solo si es válida) */
$clients = [];
if ($selected_mesa && $mesa_valida) {
    $stmt = $conexion->prepare("SELECT id_cliente, nombre FROM clientes_mesa WHERE id_mesa = ? ORDER BY id_cliente");
    if ($stmt) {
        $stmt->bind_param("i", $selected_mesa);
        $stmt->execute();
        $resc = $stmt->get_result();
        while ($rc = $resc->fetch_assoc()) {
            $clients[] = $rc;
        }
        $stmt->close();
    }
}

/* Datos iniciales */
$init = [
    'clients' => $clients,
    'selected_mesa' => $selected_mesa,
    'mesero' => ['id' => $id_mesero, 'nombre' => $nombre_mesero]
];

$cssPath = __DIR__ . '/dashboard.css';
$cssVer = file_exists($cssPath) ? filemtime($cssPath) : time();

$server_time = date('H:i:s');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Mesero — Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="css/base.css?v=<?= filemtime('css/base.css') ?>">
  <link rel="stylesheet" href="css/dashboard.css?v=<?= filemtime('css/dashboard.css') ?>">
  <link rel="stylesheet" href="css/modales.css?v=<?= filemtime('css/modales.css') ?>">
</head>
<body>

<script>
console.log('Inline script: DOM listo para ejecución');
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔥 DOMContentLoaded disparado (inline)');
});
</script>
  <header class="topbar">
    <div class="brand">
      <div class="logo">🍽️</div>
      <div class="title">
        <div class="restaurant-name">Restaurante [Name]</div>
        <div class="subtitle">Panel Mesero</div>
      </div>
    </div>

    <div class="topcenter">
      <div class="welcome">Bienvenido, <strong><?= htmlspecialchars($nombre_mesero) ?></strong></div>
      <div class="clock" id="clock"><?= $server_time ?></div>
    </div>

    <nav class="top-actions">
      <a class="btn small danger" href="../auth/logout.php">Salir</a>
    </nav>
  </header>

  <main class="dashboard">

    <!-- PANEL IZQUIERDO: MESAS -->
    <section class="panel panel-mesas" aria-label="Mesas disponibles">
      <h2 class="panel-title">Mesas</h2>
      <div class="mesas-grid" id="mesasGrid">
        <?php
        foreach ($mesas as $m):
            $mesaId = (int)$m['id_mesa'];
            $isMine = ($m['id_mesero_actual'] == $id_mesero);

            $inPrep = 0;
            $readyCount = 0;
            if ($countStmt) {
                $countStmt->bind_param("i", $mesaId);
                $countStmt->execute();
                $countRes = $countStmt->get_result();
                if ($countRes && ($cntRow = $countRes->fetch_assoc())) {
                    $inPrep = (int)$cntRow['en_preparacion'];
                    $readyCount = (int)$cntRow['listo'];
                }
                if (isset($countRes) && $countRes) $countRes->free();
            }

            if ($m['estado'] == 0) {
                $visualState = 'libre';
            } else {
                if (!$isMine) {
                    $visualState = 'ocupada-otro';
                } else {
                    if ($readyCount > 0) {
                        $visualState = 'listo';
                    } elseif ($inPrep > 0) {
                        $visualState = 'cocina';
                    } else {
                        $visualState = 'ocupada-tu';
                    }
                }
            }

            $selectedClass = ($selected_mesa && $selected_mesa == $mesaId) ? 'mesa-selected' : '';

            $estadoClase = '';
            $textoBarra = '';
            $textoEstado = '';
            $textoAccion = '';
            $iconoBarra = '';

            switch ($visualState) {
                case 'libre':
                    $estadoClase = 'libre';
                    $textoBarra = 'Libre';
                    $textoEstado = 'Disponible';
                    $textoAccion = 'Tomar mesa';
                    $iconoBarra = 'mesa_libre.png';
                    break;
                case 'listo':
                    $estadoClase = 'listo';
                    $textoBarra = 'Listo';
                    $textoEstado = 'Pedido listo';
                    $textoAccion = 'Abrir mesa';
                    $iconoBarra = 'listo.png';
                    break;
                case 'cocina':
                    $estadoClase = 'cocina';
                    $textoBarra = 'En cocina';
                    $textoEstado = 'Pedido en cocina';
                    $textoAccion = 'Abrir mesa';
                    $iconoBarra = 'en_cocina.png';
                    break;
                case 'ocupada-tu':
                    $estadoClase = 'tu';
                    $textoBarra = 'Ocupada';
                    $textoEstado = 'Ocupada';
                    $textoAccion = 'Abrir mesa';
                    $iconoBarra = 'listo.png';
                    break;
                case 'ocupada-otro':
                    $estadoClase = 'otro';
                    $textoBarra = 'Ocupada';
                    $textoEstado = 'Ocupada';
                    $textoAccion = 'Mesero: ' . htmlspecialchars($m['nombre_mesero'] ?? '');
                    $iconoBarra = 'ocupado.png';
                    break;
                default:
                    $estadoClase = '';
                    $textoBarra = '';
                    $textoEstado = '';
                    $textoAccion = '';
                    $iconoBarra = '';
            }
        ?>
          <button class="mesa-card <?= $estadoClase ?> <?= $selectedClass ?>" 
                  data-mesa="<?= $mesaId ?>" 
                  data-estado="<?= $visualState ?>" 
                  data-ismine="<?= $isMine ? '1' : '0' ?>" 
                  data-nombre-mesa="<?= htmlspecialchars($m['nombre_mesa']) ?>"
                  data-accion="<?= htmlspecialchars($textoAccion) ?>">
            <div class="barra">
                <?php if ($iconoBarra): ?>
                    <img src="iconos/<?= $iconoBarra ?>" alt="" class="icono-barra">
                <?php endif; ?>
                <span><?= htmlspecialchars($textoBarra) ?></span>
            </div>
            <div class="cuerpo">
              <div class="info">
                <div class="mesa-nombre"><?= htmlspecialchars($m['nombre_mesa']) ?></div>
                <div class="estado"><?= htmlspecialchars($textoEstado) ?></div>
              </div>
              <div class="accion"><?= htmlspecialchars($textoAccion) ?></div>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- PANEL CENTRAL: CLIENTES + CATEGORÍAS -->
    <section class="panel panel-gestion" aria-label="Gestión de mesa y toma de pedidos">
      <div class="mesa-meta-compact" style="margin-bottom:10px;">
        <div>Mesero: <strong id="mesaMesero"><?= htmlspecialchars($nombre_mesero) ?></strong></div>
        <div>Mesa: <strong id="mesaNumero"><?= $selected_mesa ? htmlspecialchars($selected_mesa) : '—' ?></strong></div>
      </div>

      <div id="contenidoNormalGestion" class="contenido-gestion" style="<?= $selected_mesa ? '' : 'display: none;' ?>">
        <!-- Clientes -->
        <div class="clientes-card">
          <h4>Clientes</h4>
          <div class="add-client">
            <form id="add-client-form" method="post" action="acciones/agregar_cliente.php">
              <input type="hidden" name="id_mesa" id="form_id_mesa" value="<?= $selected_mesa ?: '' ?>">
              <input type="text" name="nombre" id="new_client_name" placeholder="Nombre cliente" required>
              <button type="submit" class="btn">Añadir</button>
            </form>
          </div>
          <ul class="clientes-list" id="clients-list">
            <?php if (count($clients) === 0): ?>
              <li class="hint">Agrega un cliente para comenzar</li>
            <?php else: ?>
              <?php foreach ($clients as $c): ?>
                <li class="cliente-item" data-client="<?= (int)$c['id_cliente'] ?>">
                  <div class="cliente-left">
                    <span class="cliente-nombre"><?= htmlspecialchars($c['nombre']) ?></span>
                  </div>
                  <div class="cliente-right">
                    <span class="cliente-total" data-total="0">$0.00</span>
                    <button type="button" class="btn small btn-ver-pedidos" data-client-id="<?= (int)$c['id_cliente'] ?>" data-client-name="<?= htmlspecialchars($c['nombre']) ?>">Pedidos</button>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Categorías -->
        <div class="categorias-card">
          <h4>Categorías</h4>
          <div id="categorias-botones" class="categorias-grid">
            <div class="hint">Cargando categorías...</div>
          </div>
        </div>
      </div>

      <div id="mensajeSeleccionMesa" style="<?= $selected_mesa ? 'display: none;' : 'display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; color: #6c757d; flex-direction: column;' ?>">
        <div>
          <img src="iconos/plato.png" alt="Seleccionar mesa" style="width: 64px; margin-bottom: 12px;">
          <div style="font-size: 18px; font-weight: 500;">Selecciona una mesa para comenzar</div>
          <div style="font-size: 14px; margin-top: 8px;">Haz clic en cualquier mesa disponible</div>
        </div>
      </div>
    </section>

    <!-- PANEL DERECHO: DETALLE DEL PEDIDO -->
    <aside class="panel panel-detalle" aria-label="Detalle del pedido y cobro">
      <div class="detalle-header">
        <h4>Detalle del Pedido</h4>
      </div>

      <div class="detalle-tabs-wrapper">
        <div class="detalle-tabs-text" role="tablist">
          <span class="tab-text active" data-tab="capturado" role="tab">Capturado</span>
          <span class="separator">|</span>
          <span class="tab-text" data-tab="listo" role="tab">Listo</span>
          <span class="separator">|</span>
          <span class="tab-text" data-tab="entregado" role="tab">Entregado</span>
        </div>
      </div>

      <div id="contenidoNormalDetalle" style="<?= $selected_mesa ? 'display: flex; flex-direction: column; height: 100%;' : 'display: none;' ?>">
        <div class="detalle-list" id="detalleList">
          <div id="tabCapturado" class="tab-panel" data-tab="capturado"></div>
          <div id="tabListo" class="tab-panel hidden" data-tab="listo"></div>
          <div id="tabEntregado" class="tab-panel hidden" data-tab="entregado"></div>
        </div>

        <div class="detalle-actions">
          <button id="btnAccionPedido" class="btn-accion" disabled>Mandar a cocina</button>
        </div>

        <div class="detalle-totales">
          <div class="line">
            <span>Subtotal:</span>
            <span id="subtotalGeneral">$0.00</span>
          </div>
          <div class="line total">
            <span>Total de la cuenta:</span>
            <span id="totalGeneral">$0.00</span>
          </div>
        </div>

        <div class="detalle-cerrar">
          <button id="btnCerrarMesa" class="btn-cerrar-mesa" disabled data-open-modal="cerrarCuenta">Cerrar cuenta</button>
        </div>
      </div>

      <div id="mensajeSeleccionDetalle" style="<?= $selected_mesa ? 'display: none;' : 'display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; color: #6c757d; flex-direction: column;' ?>">
        <div>
          <img src="iconos/lista.png" alt="Sin pedidos" style="width: 48px; margin-bottom: 12px;">
          <div style="font-size: 14px;">Aquí se mostrarán los pedidos de la mesa seleccionada</div>
        </div>
      </div>
    </aside>

  </main>

  <!-- MODALES EXISTENTES -->
  <div id="modalConfirmTake" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="confirmTakeTitle">
      <h3 id="confirmTakeTitle">¿Tomar mesa?</h3>
      <p id="confirmTakeText">¿Seguro que quieres tomar esta mesa?</p>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
        <button id="btnCancelarTake" class="btn ghost">Cancelar</button>
        <button id="btnConfirmarTake" class="btn success">Confirmar</button>
      </div>
    </div>
  </div>

  <div id="modalMesaOcupada" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true">
      <h3 style="color: #e67e22;">Mesa no disponible</h3>
      <p id="modalMesaOcupadaText">Esta mesa está siendo atendida por otro mesero</p>
      <div style="display:flex;justify-content:flex-end;margin-top:16px;">
        <button id="btnCerrarModalOcupada" class="btn success">Entendido</button>
      </div>
    </div>
  </div>

  <div id="modalPlatillo" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true">
      <h3 id="modalTitulo">Agregar platillo</h3>
      <div class="modal-info" style="margin-top:8px;">
        <div id="modalCliente" style="font-size:14px;color:#333;">Cliente: <strong id="modalClienteName">—</strong></div>
        <div id="modalPlatilloNombre" style="font-weight:700;margin-top:8px;">Nombre platillo</div>
        <div id="modalPrecio" style="color:#2c7be5;margin-top:6px;">$0.00</div>
      </div>
      <div class="modal-cantidad" style="display:flex;align-items:center;gap:10px;margin-top:12px;">
        <button id="qtyMinus" class="btn small">-</button>
        <div id="modalCantidad" style="min-width:36px;text-align:center;font-weight:700;">1</div>
        <button id="qtyPlus" class="btn small">+</button>
      </div>
      <textarea id="modalComentario" rows="3" placeholder="Comentario para cocina..." style="margin-top:10px;padding:8px;border:1px solid #dcdfe6;border-radius:6px;width:100%;"></textarea>
      <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
        <button id="btnCancelarModal" class="btn ghost">Cancelar</button>
        <button id="btnEliminarPlatillo" class="btn danger hidden">Eliminar</button>
        <button id="btnGuardarPlatillo" class="btn success">Agregar</button>
      </div>
    </div>
  </div>

  <div id="modalClientOrders" class="modal hidden" aria-hidden="true">
    <div class="modal-box modal-client-orders" role="dialog" aria-modal="true">
      <div class="modal-client-header">
        <h3 id="clientOrdersTitle">Pedidos de <span id="clientOrdersName">—</span></h3>
        <p>Mesa actual: <span id="clientOrdersMesa">—</span></p>
      </div>
      <div id="clientOrdersContent" class="modal-client-body">
        <div class="loading">Cargando pedidos...</div>
      </div>
      <div class="modal-client-footer">
        <div class="modal-client-total">Total: <span id="clientOrdersTotal">$0.00</span></div>
        <button type="button" class="btn ghost" id="btnCloseClientOrders">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- MODAL CERRAR CUENTA MODIFICADO -->
  <div id="modalCerrarCuenta" class="modal hidden" aria-hidden="true">
    <div class="modal-box modal-cerrar" role="dialog" aria-modal="true" aria-labelledby="cerrarCuentaTitle">
      <h3 class="titulo-cerrar" id="cerrarCuentaTitle">Cerrar cuenta</h3>
      <div class="cerrar-form" style="margin-top:12px;">
        <input type="hidden" name="id_mesa" id="cerrar_id_mesa" value="<?= $selected_mesa ?: '' ?>" />
        
        <div class="tipo-cuenta" role="radiogroup" aria-label="Tipo de pago">
          <label class="btn-tipo active" data-value="conjunta">
            <input type="radio" name="tipo_pago" value="conjunta" checked style="display:none;">
            Cuenta en conjunto
          </label>
          <label class="btn-tipo" data-value="separada">
            <input type="radio" name="tipo_pago" value="separada" style="display:none;">
            Cuentas separadas
          </label>
        </div>

        <div class="divider"></div>
        
        <!-- Contenedor dinámico para los métodos de pago -->
        <div id="contenedorPagos" class="pagos-container"></div>
        
        <div class="divider"></div>
        
        <div class="correo-section">
          <div class="correo-header">
            <span>Enviar ticket por correo</span>
            <button type="button" id="btnAddEmail" class="btn-add-email" title="Agregar correo">+</button>
          </div>
          <div id="emailsList" class="emails-list" aria-live="polite"></div>
          <div class="hint-small">El ticket siempre se generará y quedará guardado en el servidor. Solo agrega correos si deseas enviarlo.</div>
        </div>

        <div class="divider"></div>
        
        <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
          <button type="button" class="btn ghost" id="btnCancelarCerrar">Cancelar</button>
          <button type="button" class="btn-cerrar-mesa" id="btnConfirmarCerrar">Cerrar mesa</button>
        </div>
      </div>
    </div>
  </div>

  <div id="modalConfirmacion" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true">
      <h3 id="confirmacionTitulo">Confirmar acción</h3>
      <p id="confirmacionMensaje">¿Estás seguro?</p>
      <div class="modal-actions" style="margin-top:16px;">
        <button id="btnConfirmacionCancelar" class="btn ghost">Cancelar</button>
        <button id="btnConfirmacionAceptar" class="btn success">Aceptar</button>
      </div>
    </div>
  </div>

  <div id="modalInformativo" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true">
      <h3 id="informativoTitulo">Aviso</h3>
      <p id="informativoMensaje"></p>
      <div class="modal-actions" style="margin-top:16px; justify-content:center;">
        <button id="btnInformativoCerrar" class="btn success">Aceptar</button>
      </div>
    </div>
  </div>

  <div id="modalErrorConexion" class="modal hidden" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true">
      <h3 style="color:#e74c3c;">Error de conexión</h3>
      <p id="errorConexionMensaje">No se pudo conectar con el servidor. Verifica tu conexión.</p>
      <div class="modal-actions" style="margin-top:16px;">
        <button id="btnErrorConexionCerrar" class="btn success">Aceptar</button>
      </div>
    </div>
  </div>

  <div id="modalMenuRapido" class="modal hidden modal-grande" aria-hidden="true">
    <div class="modal-box modal-box-grande" role="dialog" aria-modal="true">
      <div class="modal-header">
        <h3 id="modalMenuClienteNombre">Armar pedido para: <span>—</span></h3>
      </div>
      <div class="modal-menu-contenedor">
        <aside class="modal-categorias">
          <h4>Categorías</h4>
          <div id="modalCategoriasList" class="modal-cats-list"><div class="hint">Cargando...</div></div>
        </aside>
        <section class="modal-platillos">
          <div class="modal-buscador">
            <input type="text" id="modalSearchPlatillo" placeholder="Buscar platillo..." style="width:100%;padding:8px;border-radius:6px;border:1px solid #d8e0ea;">
          </div>
          <div id="modalPlatillosGrid" class="modal-dishes-grid"><div class="hint">Selecciona una categoría</div></div>
        </section>
        <aside class="modal-resumen">
          <h4>Pedido de <span id="modalResumenClienteNombre">—</span></h4>
          <div id="modalResumenList" class="modal-resumen-list"><div class="hint">No hay platillos agregados</div></div>
          <div class="modal-resumen-total"><strong>Total: </strong><span id="modalResumenTotal">$0.00</span></div>
        </aside>
      </div>
      <div class="modal-footer">
        <button id="btnCerrarModalMenuFooter" class="btn ghost">Cerrar</button>
      </div>
    </div>
  </div>

  <div id="modalResumenPedido" class="modal hidden" aria-hidden="true">
    <div class="modal-box modal-resumen-pedido" role="dialog" aria-modal="true">
      <div class="modal-client-header">
        <h3 id="resumenPedidoTitulo">Resumen del pedido</h3>
      </div>
      <div id="resumenPedidoContent" class="modal-client-body">
        <div class="loading">Cargando...</div>
      </div>
      <div class="modal-client-footer">
        <div class="modal-client-total">Total: <span id="resumenPedidoTotal">$0.00</span></div>
        <div style="display: flex; gap: 8px;">
          <button type="button" class="btn ghost" id="btnCancelarResumen">Cancelar</button>
          <button type="button" class="btn success" id="btnConfirmarResumen">Confirmar</button>
        </div>
      </div>
    </div>
  </div>

  <template id="tplEmailItem">
    <div class="email-item">
      <input type="email" name="emails[]" placeholder="correo@cliente.com" class="input-email" />
      <button type="button" class="btn-remove-email" title="Remover">✕</button>
    </div>
  </template>

  <script id="init-data" type="application/json"><?= json_encode($init, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?></script>
  
  <script src="js/globales.js"></script>
  <script src="js/modales_base.js?"></script>
  <script src="js/modales_resumen.js"></script>
  <script src="js/modales_pedidos_cliente.js"></script>
  <script src="js/modales_menu_rapido.js"></script>
  <script src="js/modales_cerrar.js"></script>
  <script src="js/mesas.js"></script>
  <script src="js/clientes.js"></script>
  <script src="js/pedidos.js"></script>
  <script src="js/ordenes.js"></script>
  <script src="js/categorias.js"></script>
  <script src="js/main.js"></script>
</body>
</html>