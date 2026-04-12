<?php
require_once "../config/admin_session.php";

// Función de validaciones para mesa
function validarMesa($nombre) {
    $errores = [];
    
    $nombre = trim($nombre);
    if (empty($nombre)) {
        $errores['nombre'] = "El nombre de la mesa es obligatorio";
    } elseif (strlen($nombre) < 3) {
        $errores['nombre'] = "Mínimo 3 caracteres";
    } elseif (strlen($nombre) > 50) {
        $errores['nombre'] = "Máximo 50 caracteres";
    } elseif (!preg_match("/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/", $nombre)) {
        $errores['nombre'] = "Solo letras, números y espacios";
    }
    
    return ['errores' => $errores, 'nombre' => $nombre];
}

// Endpoint AJAX para crear mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_mesa') {
    header('Content-Type: application/json');
    
    $nombre = $_POST['nombre_mesa'] ?? '';
    $validacion = validarMesa($nombre);
    
    if (empty($validacion['errores'])) {
        // Verificar si ya existe una mesa con el mismo nombre
        $check = $conexion->prepare("SELECT id_mesa FROM mesas WHERE nombre_mesa = ?");
        $check->bind_param("s", $validacion['nombre']);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una mesa con ese nombre', 'errores' => ['nombre' => 'Este nombre ya está registrado']]);
            exit;
        }
        
        $sql = "INSERT INTO mesas (nombre_mesa) VALUES (?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $validacion['nombre']);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Mesa creada correctamente',
                'mesa' => ['id_mesa' => $id, 'nombre_mesa' => $validacion['nombre']]
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear la mesa: ' . $conexion->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores', 'errores' => $validacion['errores']]);
        exit;
    }
}

// Endpoint AJAX para editar mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'editar_mesa') {
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id_mesa'];
    $nombre = $_POST['nombre_mesa'] ?? '';
    $validacion = validarMesa($nombre);
    
    if (empty($validacion['errores'])) {
        // Verificar si ya existe otra mesa con el mismo nombre
        $check = $conexion->prepare("SELECT id_mesa FROM mesas WHERE nombre_mesa = ? AND id_mesa != ?");
        $check->bind_param("si", $validacion['nombre'], $id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otra mesa con ese nombre', 'errores' => ['nombre' => 'Este nombre ya está registrado']]);
            exit;
        }
        
        $sql = "UPDATE mesas SET nombre_mesa = ? WHERE id_mesa = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("si", $validacion['nombre'], $id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Mesa actualizada correctamente',
                'id' => $id,
                'nombre' => $validacion['nombre']
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la mesa: ' . $conexion->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores', 'errores' => $validacion['errores']]);
        exit;
    }
}

// Endpoint AJAX para eliminar mesa
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'eliminar_mesa') {
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id_mesa'];
    
    // Verificar si la mesa tiene pedidos activos
    $check = $conexion->prepare("SELECT id_pedido FROM pedidos WHERE id_mesa = ? AND estado NOT IN ('entregado')");
    $check->bind_param("i", $id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar la mesa porque tiene pedidos activos']);
        exit;
    }
    
    $sql = "DELETE FROM mesas WHERE id_mesa = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Mesa eliminada correctamente', 'id' => $id]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la mesa: ' . $conexion->error]);
        exit;
    }
}

// Obtener lista actualizada
if (isset($_GET['ajax_list']) && $_GET['ajax_list'] == '1') {
    header('Content-Type: application/json');
    $mesas = $conexion->query("SELECT * FROM mesas ORDER BY nombre_mesa");
    $lista = [];
    while ($m = $mesas->fetch_assoc()) {
        $lista[] = $m;
    }
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
        <a href="dashboard.php" class="btn-back">
            ← Volver al Dashboard
        </a>
    </div>
</div>

<div class="container">
    <div class="dashboard-grid">
        <!-- Formulario de registro -->
        <div class="card form-card">
            <div class="card-header">
                <div class="card-header-icon"></div>
                <div>
                    <h2>Registrar nueva mesa</h2>
                    <p>Ingresa el nombre o identificador de la mesa</p>
                </div>
            </div>
            
            <form id="formMesa" class="form-mesa" novalidate>
                <div class="form-group">
                    <label>Nombre de la mesa <span class="required">*</span></label>
                    <input type="text" id="nombre_mesa" name="nombre_mesa" placeholder="Ej: Mesa 1, Barra, Terraza" maxlength="50" autocomplete="off">
                    <div class="error-message" id="error-nombre"></div>
                    <div class="form-hint">Mínimo 3 caracteres, solo letras, números y espacios</div>
                </div>
                
                <button type="submit" class="btn-submit" id="btnSubmit">
                    <span class="btn-icon">+</span>
                    Crear mesa
                </button>
            </form>
        </div>

        <!-- Lista de mesas -->
        <div class="card list-card">
            <div class="card-header">
                <div class="card-header-icon"></div>
                <div>
                    <h2>Mesas existentes</h2>
                    <p>Total de mesas registradas</p>
                </div>
                <span class="card-badge" id="mesasCount"><?= $mesas->num_rows ?></span>
            </div>

            <div class="table-wrapper-scroll">
                <div class="table-wrapper">
                    <table class="data-table" id="mesasTable">
                        <thead>
                            <tr>
                                <th>Nombre de mesa</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="mesasList">
                            <?php if ($mesas->num_rows == 0): ?>
                                <tr class="empty-row">
                                    <td colspan="3">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"></div>
                                            <p>No hay mesas registradas</p>
                                            <small>Completa el formulario para agregar una</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($m = $mesas->fetch_assoc()): ?>
                                    <tr data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>">
                                        <td class="mesa-name-cell">
                                            <div class="mesa-icon">
                                                <span class="mesa-icon-text">🍽️</span>
                                            </div>
                                            <span class="mesa-name" id="nombre-<?= $m['id_mesa'] ?>"><?= htmlspecialchars($m['nombre_mesa']) ?></span>
                                        </td>
                                        <td class="status-cell">
                                            <span class="status-badge <?= $m['estado'] ? 'status-ocupada' : 'status-libre' ?>">
                                                <?= $m['estado'] ? 'Ocupada' : 'Libre' ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="action-btn edit-btn" data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>">
                                                <span class="action-icon"></span>
                                                Editar
                                            </button>
                                            <button class="action-btn delete-btn" data-id="<?= $m['id_mesa'] ?>" data-nombre="<?= htmlspecialchars($m['nombre_mesa']) ?>">
                                                <span class="action-icon"></span>
                                                Eliminar
                                            </button>
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

<!-- Modal de edición -->
<div id="editModal" class="modal">
    <div class="modal-content modal-edit">
        <div class="modal-header">
            <h3>Editar mesa</h3>
            <button class="modal-close" onclick="closeEditModal()">×</button>
        </div>
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

<!-- Modal de notificación -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon" id="modalIcon"></div>
        <p class="modal-message" id="modalMessage"></p>
        <button class="modal-btn" onclick="closeModal()">Aceptar</button>
    </div>
</div>

<script>
// Variables
let submitLock = false;

// Mostrar modal de notificación
function showModal(message, isSuccess = true) {
    const modal = document.getElementById('notificationModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalMessage = document.getElementById('modalMessage');
    
    modalIcon.className = 'modal-icon ' + (isSuccess ? 'modal-success' : 'modal-error');
    modalMessage.textContent = message;
    modal.classList.add('show');
    
    setTimeout(() => {
        closeModal();
    }, 3000);
}

function closeModal() {
    const modal = document.getElementById('notificationModal');
    modal.classList.remove('show');
}

// Modal de edición
const editModal = document.getElementById('editModal');
let currentEditId = null;

function openEditModal(id, nombre) {
    currentEditId = id;
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit-error-nombre').textContent = '';
    document.getElementById('edit-error-nombre').classList.remove('show');
    editModal.classList.add('show');
}

function closeEditModal() {
    editModal.classList.remove('show');
    currentEditId = null;
}

// Actualizar tabla
async function refreshTable() {
    try {
        const response = await fetch('mesas.php?ajax_list=1');
        const mesas = await response.json();
        
        const tbody = document.getElementById('mesasList');
        const countBadge = document.getElementById('mesasCount');
        
        if (mesas.length === 0) {
            tbody.innerHTML = `
                <tr class="empty-row">
                    <td colspan="3">
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>No hay mesas registradas</p>
                            <small>Completa el formulario para agregar una</small>
                        </div>
                    </td>
                </tr>
            `;
            countBadge.textContent = '0';
            return;
        }
        
        countBadge.textContent = mesas.length;
        
        tbody.innerHTML = mesas.map(m => {
            const statusClass = m.estado ? 'status-ocupada' : 'status-libre';
            const statusText = m.estado ? 'Ocupada' : 'Libre';
            
            return `
                <tr data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}">
                    <td class="mesa-name-cell">
                        <div class="mesa-icon">
                            <span class="mesa-icon-text">🍽️</span>
                        </div>
                        <span class="mesa-name" id="nombre-${m.id_mesa}">${escapeHtml(m.nombre_mesa)}</span>
                    </td>
                    <td class="status-cell">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </td>
                    <td class="actions-cell">
                        <button class="action-btn edit-btn" data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}">
                            <span class="action-icon">✎</span>
                            Editar
                        </button>
                        <button class="action-btn delete-btn" data-id="${m.id_mesa}" data-nombre="${escapeHtml(m.nombre_mesa)}">
                            <span class="action-icon">🗑</span>
                            Eliminar
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        // Reasignar eventos
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.removeEventListener('click', handleEditClick);
            btn.addEventListener('click', handleEditClick);
        });
        
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.removeEventListener('click', handleDeleteClick);
            btn.addEventListener('click', handleDeleteClick);
        });
        
    } catch (error) {
        console.error('Error:', error);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Manejar edición
function handleEditClick(event) {
    const button = event.currentTarget;
    const id = button.dataset.id;
    const nombre = button.dataset.nombre;
    openEditModal(id, nombre);
}

// Manejar eliminación
async function handleDeleteClick(event) {
    const button = event.currentTarget;
    const id = button.dataset.id;
    const nombre = button.dataset.nombre;
    
    if (!confirm(`¿Eliminar la mesa "${nombre}"?\nEsta acción no se puede deshacer.`)) return;
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'eliminar_mesa');
        formData.append('id_mesa', id);
        
        const response = await fetch('mesas.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showModal(result.message, true);
            refreshTable();
        } else {
            showModal(result.message, false);
        }
    } catch (error) {
        showModal('Error al procesar la solicitud', false);
    }
}

// Validación en tiempo real
function validarNombre() {
    const nombre = document.getElementById('nombre_mesa').value.trim();
    const errorDiv = document.getElementById('error-nombre');
    
    if (nombre.length === 0) {
        errorDiv.textContent = '';
        errorDiv.classList.remove('show');
        document.getElementById('nombre_mesa').classList.remove('valid', 'invalid');
        return false;
    }
    
    if (nombre.length < 3) {
        errorDiv.textContent = 'Mínimo 3 caracteres';
        errorDiv.classList.add('show');
        document.getElementById('nombre_mesa').classList.add('invalid');
        document.getElementById('nombre_mesa').classList.remove('valid');
        return false;
    }
    
    if (nombre.length > 50) {
        errorDiv.textContent = 'Máximo 50 caracteres';
        errorDiv.classList.add('show');
        document.getElementById('nombre_mesa').classList.add('invalid');
        document.getElementById('nombre_mesa').classList.remove('valid');
        return false;
    }
    
    if (!/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) {
        errorDiv.textContent = 'Solo letras, números y espacios';
        errorDiv.classList.add('show');
        document.getElementById('nombre_mesa').classList.add('invalid');
        document.getElementById('nombre_mesa').classList.remove('valid');
        return false;
    }
    
    errorDiv.textContent = '';
    errorDiv.classList.remove('show');
    document.getElementById('nombre_mesa').classList.remove('invalid');
    document.getElementById('nombre_mesa').classList.add('valid');
    return true;
}

document.getElementById('nombre_mesa')?.addEventListener('input', validarNombre);

// Submit formulario crear
const form = document.getElementById('formMesa');
form?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (submitLock) return;
    
    const isValid = validarNombre();
    if (!isValid) {
        showModal('Por favor corrige los errores en el formulario', false);
        return;
    }
    
    submitLock = true;
    const submitBtn = document.getElementById('btnSubmit');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Creando...';
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    formData.append('ajax_action', 'crear_mesa');
    
    try {
        const response = await fetch('mesas.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showModal(result.message, true);
            form.reset();
            document.getElementById('nombre_mesa').classList.remove('valid', 'invalid');
            refreshTable();
        } else {
            showModal(result.message, false);
            if (result.errores && result.errores.nombre) {
                const errorDiv = document.getElementById('error-nombre');
                errorDiv.textContent = result.errores.nombre;
                errorDiv.classList.add('show');
                document.getElementById('nombre_mesa').classList.add('invalid');
            }
        }
    } catch (error) {
        showModal('Error al conectar con el servidor', false);
    } finally {
        submitLock = false;
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});

// Submit formulario edición
const editForm = document.getElementById('editForm');
editForm?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const id = document.getElementById('edit_id').value;
    const nombre = document.getElementById('edit_nombre').value.trim();
    const errorDiv = document.getElementById('edit-error-nombre');
    
    // Validación local
    if (nombre.length < 3) {
        errorDiv.textContent = 'Mínimo 3 caracteres';
        errorDiv.classList.add('show');
        return;
    }
    if (nombre.length > 50) {
        errorDiv.textContent = 'Máximo 50 caracteres';
        errorDiv.classList.add('show');
        return;
    }
    if (!/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) {
        errorDiv.textContent = 'Solo letras, números y espacios';
        errorDiv.classList.add('show');
        return;
    }
    
    errorDiv.textContent = '';
    errorDiv.classList.remove('show');
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'editar_mesa');
        formData.append('id_mesa', id);
        formData.append('nombre_mesa', nombre);
        
        const response = await fetch('mesas.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showModal(result.message, true);
            closeEditModal();
            refreshTable();
        } else {
            showModal(result.message, false);
            if (result.errores && result.errores.nombre) {
                errorDiv.textContent = result.errores.nombre;
                errorDiv.classList.add('show');
            }
        }
    } catch (error) {
        showModal('Error al conectar con el servidor', false);
    }
});

// Inicializar eventos
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', handleEditClick);
});

document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', handleDeleteClick);
});

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === document.getElementById('notificationModal')) {
        closeModal();
    }
};
</script>

</body>
</html>