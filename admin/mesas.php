<?php
require_once "../config/admin_session.php";

function validarMesa($nombre) {
    $errores = [];
    $nombre = trim($nombre);
    if (empty($nombre)) $errores['nombre'] = "El nombre de la mesa es obligatorio";
    elseif (strlen($nombre) < 3) $errores['nombre'] = "Mínimo 3 caracteres";
    elseif (strlen($nombre) > 50) $errores['nombre'] = "Máximo 50 caracteres";
    elseif (!preg_match("/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/", $nombre)) $errores['nombre'] = "Solo letras, números y espacios";
    return ['errores' => $errores, 'nombre' => $nombre];
}

// Endpoint AJAX para verificar dependencias
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verificar_dependencias') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_mesa'];
    $clientes = $conexion->query("SELECT COUNT(*) AS total FROM clientes_mesa WHERE id_mesa = $id")->fetch_assoc();
    $pedidos = $conexion->query("SELECT COUNT(*) AS total FROM pedidos WHERE id_mesa = $id AND estado NOT IN ('entregado')")->fetch_assoc();
    echo json_encode(['tiene_dependencias' => ($clientes['total'] > 0 || $pedidos['total'] > 0)]);
    exit;
}

// Endpoint AJAX para crear mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_mesa') {
    header('Content-Type: application/json');
    $nombre = $_POST['nombre_mesa'] ?? '';
    $validacion = validarMesa($nombre);
    if (empty($validacion['errores'])) {
        $check = $conexion->prepare("SELECT id_mesa FROM mesas WHERE nombre_mesa = ?");
        $check->bind_param("s", $validacion['nombre']);
        $check->execute(); $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una mesa con ese nombre', 'errores' => ['nombre' => 'Este nombre ya está registrado']]);
            exit;
        }
        $stmt = $conexion->prepare("INSERT INTO mesas (nombre_mesa) VALUES (?)");
        $stmt->bind_param("s", $validacion['nombre']);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Mesa creada correctamente', 'mesa' => ['id_mesa' => $stmt->insert_id, 'nombre_mesa' => $validacion['nombre']]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear la mesa: ' . $conexion->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores', 'errores' => $validacion['errores']]);
    }
    exit;
}

// Endpoint AJAX para editar mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'editar_mesa') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_mesa'];
    $nombre = $_POST['nombre_mesa'] ?? '';
    $validacion = validarMesa($nombre);
    if (empty($validacion['errores'])) {
        $check = $conexion->prepare("SELECT id_mesa FROM mesas WHERE nombre_mesa = ? AND id_mesa != ?");
        $check->bind_param("si", $validacion['nombre'], $id);
        $check->execute(); $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otra mesa con ese nombre', 'errores' => ['nombre' => 'Este nombre ya está registrado']]);
            exit;
        }
        $stmt = $conexion->prepare("UPDATE mesas SET nombre_mesa = ? WHERE id_mesa = ?");
        $stmt->bind_param("si", $validacion['nombre'], $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Mesa actualizada correctamente', 'id' => $id, 'nombre' => $validacion['nombre']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la mesa: ' . $conexion->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores', 'errores' => $validacion['errores']]);
    }
    exit;
}

// Endpoint AJAX para eliminar mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'eliminar_mesa') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_mesa'];
    $check = $conexion->prepare("SELECT id_pedido FROM pedidos WHERE id_mesa = ? AND estado NOT IN ('entregado')");
    $check->bind_param("i", $id);
    $check->execute(); $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar la mesa porque tiene pedidos activos']);
        exit;
    }
    $stmt = $conexion->prepare("DELETE FROM mesas WHERE id_mesa = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Mesa eliminada correctamente', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la mesa: ' . $conexion->error]);
    }
    exit;
}

// Obtener lista actualizada (AJAX)
if (isset($_GET['ajax_list']) && $_GET['ajax_list'] == '1') {
    header('Content-Type: application/json');
    $mesas = $conexion->query("SELECT * FROM mesas ORDER BY nombre_mesa");
    $lista = [];
    while ($m = $mesas->fetch_assoc()) $lista[] = $m;
    echo json_encode($lista);
    exit;
}

$mesas = $conexion->query("SELECT * FROM mesas ORDER BY nombre_mesa");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas</title>
    <link rel="stylesheet" href="css/mesas.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div>
            <h1 class="header-title">Gestión de Mesas</h1>
            <p class="header-subtitle">Administra las áreas y mesas del restaurante</p>
        </div>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">
    <div class="dashboard-grid">
        <!-- Formulario de registro -->
        <div class="card form-card">
            <div class="card-header">
                <div class="card-header-icon"></div>
                <div><h2>Registrar nueva mesa</h2><p>Ingresa el nombre o identificador de la mesa</p></div>
            </div>
            <form id="formMesa" class="form-mesa" novalidate>
                <div class="form-group">
                    <label>Nombre de la mesa <span class="required">*</span></label>
                    <input type="text" id="nombre_mesa" name="nombre_mesa" placeholder="Ej: Mesa 1, Barra, Terraza" maxlength="50" autocomplete="off">
                    <div class="error-message" id="error-nombre"></div>
                    <div class="form-hint">Mínimo 3 caracteres, solo letras, números y espacios</div>
                </div>
                <button type="submit" class="btn-submit" id="btnSubmit"><span class="btn-icon">+</span> Crear mesa</button>
            </form>
        </div>

        <!-- Lista de mesas -->
        <div class="card list-card">
            <div class="card-header">
                <div class="card-header-icon"></div>
                <div><h2>Mesas existentes</h2><p>Total de mesas registradas</p></div>
                <span class="card-badge" id="mesasCount"><?= $mesas->num_rows ?></span>
            </div>
            <div class="table-wrapper-scroll">
                <div class="table-wrapper">
                    <table class="data-table" id="mesasTable">
                        <thead><tr><th>Nombre de mesa</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody id="mesasList">
                            <?php if ($mesas->num_rows == 0): ?>
                                <tr class="empty-row"><td colspan="3"><div class="empty-state"><div class="empty-state-icon"></div><p>No hay mesas registradas</p><small>Completa el formulario para agregar una</small></div></td></tr>
                            <?php else: ?>
                                <?php while ($m = $mesas->fetch_assoc()): ?>
                                    <tr data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>">
                                        <td class="mesa-name-cell"><div class="mesa-icon"><span class="mesa-icon-text">🍽️</span></div><span class="mesa-name" id="nombre-<?= $m['id_mesa'] ?>"><?= htmlspecialchars($m['nombre_mesa']) ?></span></td>
                                        <td class="status-cell"><span class="status-badge <?= $m['estado'] ? 'status-ocupada' : 'status-libre' ?>"><?= $m['estado'] ? 'Ocupada' : 'Libre' ?></span></td>
                                        <td class="actions-cell">
                                            <button class="action-btn edit-btn" data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>"><span class="action-icon">✎</span> Editar</button>
                                            <button class="action-btn delete-btn" data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>"><span class="action-icon">🗑</span> Eliminar</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de edición mejorado -->
<div id="editModal" class="modal">
    <div class="modal-content modal-edit">
        <div class="modal-header"><h3>Editar mesa</h3><button class="modal-close" onclick="closeEditModal()">&times;</button></div>
        <form id="editForm">
            <input type="hidden" id="edit_id" name="id_mesa">
            <div class="form-group">
                <label>Nombre de la mesa <span class="required">*</span></label>
                <input type="text" id="edit_nombre" name="nombre_mesa" placeholder="Ej: Mesa 1, Barra, Terraza" maxlength="50">
                <div class="error-message" id="edit-error-nombre"></div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-save">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de confirmación -->
<div id="confirmModal" class="modal">
    <div class="modal-content modal-confirm">
        <div class="modal-header"><h3>Confirmar acción</h3><button class="modal-close" onclick="closeConfirmModal()">&times;</button></div>
        <p class="modal-message" id="confirmMessage"></p>
        <div class="modal-buttons">
            <button class="btn-cancel" onclick="closeConfirmModal()">Cancelar</button>
            <button class="btn-save" id="confirmActionBtn">Eliminar</button>
        </div>
    </div>
</div>

<!-- Modal de notificación -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon" id="modalIcon"></div>
        <p class="modal-message" id="modalMessage"></p>
        <button class="modal-btn" onclick="closeModal()">Aceptar</button>
    </div>
</div>

<script>
let submitLock = false;
const editModal = document.getElementById('editModal');
const confirmModal = document.getElementById('confirmModal');
const confirmMessage = document.getElementById('confirmMessage');
const confirmActionBtn = document.getElementById('confirmActionBtn');

// Notificación
function showModal(message, isSuccess = true) {
    const modal = document.getElementById('notificationModal');
    document.getElementById('modalIcon').className = 'modal-icon ' + (isSuccess ? 'modal-success' : 'modal-error');
    document.getElementById('modalMessage').textContent = message;
    modal.classList.add('show');
    setTimeout(closeModal, 3000);
}
function closeModal() { document.getElementById('notificationModal').classList.remove('show'); }

// Confirmación personalizada
function openConfirmModal(mensaje, callback) {
    confirmMessage.textContent = mensaje;
    confirmModal.classList.add('show');
    confirmActionBtn.onclick = () => { callback(); closeConfirmModal(); };
}
function closeConfirmModal() { confirmModal.classList.remove('show'); }

// Edición
function openEditModal(id, nombre) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit-error-nombre').textContent = '';
    editModal.classList.add('show');
}
function closeEditModal() { editModal.classList.remove('show'); }

// Actualizar tabla
async function refreshTable() {
    try {
        const response = await fetch('mesas.php?ajax_list=1');
        const mesas = await response.json();
        const tbody = document.getElementById('mesasList');
        const countBadge = document.getElementById('mesasCount');
        if (mesas.length === 0) {
            tbody.innerHTML = `<tr class="empty-row"><td colspan="3"><div class="empty-state"><div class="empty-state-icon"></div><p>No hay mesas registradas</p><small>Completa el formulario para agregar una</small></div></td></tr>`;
            countBadge.textContent = '0';
            return;
        }
        countBadge.textContent = mesas.length;
        tbody.innerHTML = mesas.map(m => {
            const sc = m.estado ? 'status-ocupada' : 'status-libre';
            const st = m.estado ? 'Ocupada' : 'Libre';
            return `<tr data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}">
                <td class="mesa-name-cell"><div class="mesa-icon"><span class="mesa-icon-text">🍽️</span></div><span class="mesa-name" id="nombre-${m.id_mesa}">${escapeHtml(m.nombre_mesa)}</span></td>
                <td class="status-cell"><span class="status-badge ${sc}">${st}</span></td>
                <td class="actions-cell">
                    <button class="action-btn edit-btn" data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}"><span class="action-icon">✎</span> Editar</button>
                    <button class="action-btn delete-btn" data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}"><span class="action-icon">🗑</span> Eliminar</button>
                </td></tr>`;
        }).join('');
        document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', handleEditClick));
        document.querySelectorAll('.delete-btn').forEach(b => b.addEventListener('click', handleDeleteClick));
    } catch (error) { console.error('Error:', error); }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
}

// Manejar clic en Editar
async function handleEditClick(event) {
    const btn = event.currentTarget;
    const id = btn.dataset.id, nombre = btn.dataset.nombre;
    try {
        const fd = new FormData();
        fd.append('ajax_action', 'verificar_dependencias');
        fd.append('id_mesa', id);
        const resp = await fetch('mesas.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.tiene_dependencias) {
            showModal('No se puede editar la mesa porque está en uso (tiene clientes o pedidos activos).', false);
            return;
        }
        openEditModal(id, nombre);
    } catch (err) { showModal('Error al verificar dependencias.', false); }
}

// Manejar clic en Eliminar
async function handleDeleteClick(event) {
    const btn = event.currentTarget;
    const id = btn.dataset.id, nombre = btn.dataset.nombre;
    try {
        const fd = new FormData();
        fd.append('ajax_action', 'verificar_dependencias');
        fd.append('id_mesa', id);
        const resp = await fetch('mesas.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.tiene_dependencias) {
            showModal('No se puede eliminar la mesa porque tiene clientes o pedidos asociados.', false);
            return;
        }
    } catch (err) { showModal('Error al verificar dependencias.', false); return; }

    openConfirmModal(`¿Eliminar la mesa "${nombre}"?\nEsta acción no se puede deshacer.`, async () => {
        try {
            const fd2 = new FormData();
            fd2.append('ajax_action', 'eliminar_mesa');
            fd2.append('id_mesa', id);
            const resp = await fetch('mesas.php', { method: 'POST', body: fd2 });
            const result = await resp.json();
            showModal(result.message, result.success);
            if (result.success) refreshTable();
        } catch (e) { showModal('Error al procesar la solicitud', false); }
    });
}

// Validación en tiempo real
function validarNombre() {
    const nombre = document.getElementById('nombre_mesa').value.trim();
    const err = document.getElementById('error-nombre');
    const inp = document.getElementById('nombre_mesa');
    const ok = () => { err.textContent=''; err.classList.remove('show'); inp.classList.remove('invalid'); inp.classList.add('valid'); return true; };
    if (nombre.length === 0) { err.textContent=''; err.classList.remove('show'); inp.classList.remove('valid','invalid'); return false; }
    if (nombre.length < 3) { err.textContent='Mínimo 3 caracteres'; err.classList.add('show'); inp.classList.add('invalid'); return false; }
    if (nombre.length > 50) { err.textContent='Máximo 50 caracteres'; err.classList.add('show'); inp.classList.add('invalid'); return false; }
    if (!/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) { err.textContent='Solo letras, números y espacios'; err.classList.add('show'); inp.classList.add('invalid'); return false; }
    return ok();
}
document.getElementById('nombre_mesa')?.addEventListener('input', validarNombre);

// Submit formulario crear
document.getElementById('formMesa')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (submitLock) return;
    if (!validarNombre()) { showModal('Por favor corrige los errores en el formulario', false); return; }
    submitLock = true;
    const btn = document.getElementById('btnSubmit');
    const orig = btn.innerHTML;
    btn.innerHTML = 'Creando...'; btn.disabled = true;
    const fd = new FormData(this);
    fd.append('ajax_action', 'crear_mesa');
    try {
        const resp = await fetch('mesas.php', { method: 'POST', body: fd });
        const result = await resp.json();
        if (result.success) {
            showModal(result.message, true);
            this.reset();
            document.getElementById('nombre_mesa').classList.remove('valid','invalid');
            refreshTable();
        } else {
            showModal(result.message, false);
            if (result.errores?.nombre) {
                const err = document.getElementById('error-nombre');
                err.textContent = result.errores.nombre;
                err.classList.add('show');
                document.getElementById('nombre_mesa').classList.add('invalid');
            }
        }
    } catch (err) { showModal('Error al conectar con el servidor', false); }
    finally { submitLock = false; btn.innerHTML = orig; btn.disabled = false; }
});

// Submit formulario edición
document.getElementById('editForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('edit_id').value;
    const nombre = document.getElementById('edit_nombre').value.trim();
    const err = document.getElementById('edit-error-nombre');
    if (nombre.length < 3) { err.textContent = 'Mínimo 3 caracteres'; err.classList.add('show'); return; }
    if (nombre.length > 50) { err.textContent = 'Máximo 50 caracteres'; err.classList.add('show'); return; }
    if (!/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) { err.textContent = 'Solo letras, números y espacios'; err.classList.add('show'); return; }
    err.textContent = ''; err.classList.remove('show');
    try {
        const fd = new FormData();
        fd.append('ajax_action', 'editar_mesa');
        fd.append('id_mesa', id);
        fd.append('nombre_mesa', nombre);
        const resp = await fetch('mesas.php', { method: 'POST', body: fd });
        const result = await resp.json();
        if (result.success) {
            showModal(result.message, true);
            closeEditModal();
            refreshTable();
        } else {
            showModal(result.message, false);
            if (result.errores?.nombre) { err.textContent = result.errores.nombre; err.classList.add('show'); }
        }
    } catch (err) { showModal('Error al conectar con el servidor', false); }
});

// Inicializar eventos y cierre de modales
document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', handleEditClick));
document.querySelectorAll('.delete-btn').forEach(b => b.addEventListener('click', handleDeleteClick));
window.onclick = function(event) {
    if (event.target === editModal) closeEditModal();
    if (event.target === confirmModal) closeConfirmModal();
    if (event.target === document.getElementById('notificationModal')) closeModal();
};
</script>
</body>
</html>