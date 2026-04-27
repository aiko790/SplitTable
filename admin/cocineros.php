<?php
require_once "../config/admin_session.php";

// Función de validaciones
function validarCocinero($datos, $es_actualizacion = false) {
    $errores = [];
    
    $nombre = trim($datos['nombre'] ?? '');
    if (empty($nombre)) {
        $errores['nombre'] = "El nombre es obligatorio";
    } elseif (strlen($nombre) < 3) {
        $errores['nombre'] = "Mínimo 3 caracteres";
    } elseif (strlen($nombre) > 100) {
        $errores['nombre'] = "Máximo 100 caracteres";
    } elseif (!preg_match("/^[a-zA-ZáéíóúñÑáéíóúÁÉÍÓÚ\s]+$/", $nombre)) {
        $errores['nombre'] = "Solo letras y espacios";
    }
    
    $correo = trim($datos['correo'] ?? '');
    if (empty($correo)) {
        $errores['correo'] = "El correo es obligatorio";
    } elseif (strlen($correo) > 100) {
        $errores['correo'] = "Máximo 100 caracteres";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = "Formato de correo no válido";
    }
    
    $telefono = isset($datos['telefono']) ? trim($datos['telefono']) : '';
    if (!empty($telefono)) {
        $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
        if (strlen($telefono_limpio) != 10) {
            $errores['telefono'] = "Debe tener 10 dígitos";
        }
    }
    
    $fecha_nacimiento = $datos['fecha_nacimiento'] ?? '';
    if (!empty($fecha_nacimiento)) {
        $fecha = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fecha_nacimiento) {
            $errores['fecha_nacimiento'] = "Formato inválido";
        } else {
            $edad = date_diff($fecha, new DateTime())->y;
            if ($edad < 18) {
                $errores['fecha_nacimiento'] = "Debe ser mayor de 18 años";
            }
        }
    }
    
    if (!$es_actualizacion) {
        $password = $datos['password'] ?? '';
        if (empty($password)) {
            $errores['password'] = "La contraseña es obligatoria";
        } elseif (strlen($password) < 8) {
            $errores['password'] = "Mínimo 8 caracteres";
        } elseif (!preg_match("/[A-Z]/", $password)) {
            $errores['password'] = "Al menos una mayúscula";
        } elseif (!preg_match("/[a-z]/", $password)) {
            $errores['password'] = "Al menos una minúscula";
        } elseif (!preg_match("/\d/", $password)) {
            $errores['password'] = "Al menos un número";
        }
    }
    
    return ['errores' => $errores, 'datos' => ['nombre' => $nombre, 'correo' => $correo, 'telefono' => $telefono, 'fecha_nacimiento' => $fecha_nacimiento]];
}

// Endpoint AJAX para crear cocinero
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_cocinero') {
    header('Content-Type: application/json');
    
    $validacion = validarCocinero($_POST);
    
    if (empty($validacion['errores'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $check = $conexion->prepare("SELECT id_cocinero FROM cocineros WHERE correo = ?");
        $check->bind_param("s", $validacion['datos']['correo']);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado', 'errores' => ['correo' => 'Este correo ya existe']]);
            exit;
        }
        
        $sql = "INSERT INTO cocineros (nombre, correo, telefono, fecha_nacimiento, password) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssss", $validacion['datos']['nombre'], $validacion['datos']['correo'], $validacion['datos']['telefono'], $validacion['datos']['fecha_nacimiento'], $hash);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            
            $select = $conexion->prepare("SELECT * FROM cocineros WHERE id_cocinero = ?");
            $select->bind_param("i", $id);
            $select->execute();
            $resultado = $select->get_result();
            $cocinero = $resultado->fetch_assoc();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Cocinero registrado correctamente',
                'cocinero' => $cocinero
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . $conexion->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores', 'errores' => $validacion['errores']]);
        exit;
    }
}

// Endpoint AJAX para actualizar cocinero
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'update_cocinero') {
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $estado = (isset($_POST['estado']) && $_POST['estado'] == '1') ? 1 : 0;
    $password = $_POST['password'] ?? '';
    
    $errores = [];
    
    if (empty($nombre) || strlen($nombre) < 3) {
        $errores['nombre'] = "Nombre inválido (mínimo 3 caracteres)";
    }
    if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = "Correo electrónico inválido";
    }
    
    if (empty($errores['correo'])) {
        $check = $conexion->prepare("SELECT id_cocinero FROM cocineros WHERE correo = ? AND id_cocinero != ?");
        $check->bind_param("si", $correo, $id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errores['correo'] = "Este correo ya está registrado por otro cocinero";
        }
        $check->close();
    }
    
    if (!empty($password)) {
        if (strlen($password) < 8 || !preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/\d/", $password)) {
            $errores['password'] = "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número";
        }
    }
    
    if (!empty($errores)) {
        echo json_encode(['success' => false, 'message' => 'Errores de validación', 'errores' => $errores]);
        exit;
    }
    
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE cocineros SET nombre=?, correo=?, telefono=?, fecha_nacimiento=?, password=?, estado=? WHERE id_cocinero=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssssii", $nombre, $correo, $telefono, $fecha_nacimiento, $hash, $estado, $id);
    } else {
        $sql = "UPDATE cocineros SET nombre=?, correo=?, telefono=?, fecha_nacimiento=?, estado=? WHERE id_cocinero=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ssssii", $nombre, $correo, $telefono, $fecha_nacimiento, $estado, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cocinero actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conexion->error]);
    }
    $stmt->close();
    exit;
}

// Obtener lista actualizada (para AJAX)
if (isset($_GET['ajax_list']) && $_GET['ajax_list'] == '1') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    $lista = $conexion->query("SELECT * FROM cocineros ORDER BY nombre");
    $cocineros = [];
    while ($c = $lista->fetch_assoc()) {
        $cocineros[] = $c;
    }
    echo json_encode($cocineros);
    exit;
}

$lista = $conexion->query("SELECT * FROM cocineros ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cocineros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/cocineros.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div>
            <h1 class="header-title">Gestión de Cocineros</h1>
            <p class="header-subtitle">Administra el equipo de cocina</p>
        </div>
        <a href="dashboard.php" class="btn-back">
            ← Volver al Dashboard
        </a>
    </div>
</div>

<div class="container">
    <div class="dashboard-grid">
        <!-- Formulario de registro (desplegable horizontal) -->
        <div class="form-sidebar" id="formSidebar">
            <div class="sidebar-toggle" id="toggleSidebar">
                <button type="button" class="toggle-btn-sidebar" id="toggleBtn">
                    <span class="toggle-icon-sidebar" id="toggleIcon">◀</span>
                </button>
                <div class="sidebar-label">
                    <span>Registrar</span>
                    <span class="sidebar-label-small">cocinero</span>
                </div>
            </div>
            
            <div class="sidebar-content" id="sidebarContent">
                <div class="card form-card">
                    <div class="card-header">
                        <div>
                            <h2>Registrar nuevo cocinero</h2>
                            <p>Ingresa los datos del personal de cocina</p>
                        </div>
                    </div>
                    
                    <form id="formCocinero" class="form-cocinero" novalidate>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Nombre completo <span class="required">*</span></label>
                                <input type="text" id="nombre" name="nombre" placeholder="Juan Pérez González" maxlength="100">
                                <div class="error-message" id="error-nombre"></div>
                                <div class="form-hint">Mínimo 3 caracteres, solo letras</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Correo electrónico <span class="required">*</span></label>
                                <input type="email" id="correo" name="correo" placeholder="ejemplo@restaurante.com" maxlength="100">
                                <div class="error-message" id="error-correo"></div>
                                <div class="form-hint">Usuario para iniciar sesión</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="tel" id="telefono" name="telefono" placeholder="5512345678" maxlength="10">
                                <div class="error-message" id="error-telefono"></div>
                                <div class="form-hint">10 dígitos, solo números</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de nacimiento</label>
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                <div class="error-message" id="error-fecha"></div>
                                <div class="form-hint">Debe ser mayor de 18 años</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Contraseña <span class="required">*</span></label>
                                <div class="password-field">
                                    <input type="password" id="password" name="password" placeholder="········" minlength="8" maxlength="255">
                                    <button type="button" class="password-toggle" onclick="togglePassword()">Mostrar</button>
                                </div>
                                <div class="error-message" id="error-password"></div>
                                <div class="password-requirements">
                                    <span class="requirement" id="req-length">8 caracteres mínimo</span>
                                    <span class="requirement" id="req-upper">Una mayúscula</span>
                                    <span class="requirement" id="req-lower">Una minúscula</span>
                                    <span class="requirement" id="req-number">Un número</span>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="btnSubmit">Registrar cocinero</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de cocineros (scroll independiente) -->
        <div class="card list-card">
            <div class="card-header">
                <div>
                    <h2>Cocineros registrados <span class="card-badge" id="cocinerosCount"><?= $lista->num_rows ?></span></h2>
                    <p>Personal activo e inactivo</p>
                </div>
            </div>

            <div class="table-wrapper-scroll">
                <div class="table-wrapper">
                    <table class="data-table" id="cocinerosTable">
                        <thead>
                            <tr>
                                <th>Cocinero</th>
                                <th>Contacto</th>
                                <th>Edad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="cocinerosList">
                            <?php if ($lista->num_rows == 0): ?>
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"></div>
                                            <p>No hay cocineros registrados</p>
                                            <small>Completa el formulario para agregar uno</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($c = $lista->fetch_assoc()): ?>
                                    <tr data-id="<?= $c['id_cocinero'] ?>">
                                        <td class="user-cell">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                                            </div>
                                            <div class="user-info">
                                                <strong class="user-name"><?= htmlspecialchars($c['nombre']) ?></strong>
                                                <span class="user-email"><?= htmlspecialchars($c['correo']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($c['telefono']): ?>
                                                <span class="contact-phone"><?= htmlspecialchars($c['telefono']) ?></span>
                                            <?php else: ?>
                                                <span class="contact-empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($c['fecha_nacimiento']) {
                                                $edad = date_diff(date_create($c['fecha_nacimiento']), date_create('today'))->y;
                                                echo "<span class='age-tag'>$edad años</span>";
                                            } else {
                                                echo "<span class='contact-empty'>—</span>";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $c['estado'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $c['estado'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn edit-btn" 
                                                    data-id="<?= $c['id_cocinero'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                                                    data-correo="<?= htmlspecialchars($c['correo']) ?>"
                                                    data-telefono="<?= htmlspecialchars($c['telefono'] ?? '') ?>"
                                                    data-fecha="<?= $c['fecha_nacimiento'] ?? '' ?>"
                                                    data-estado="<?= $c['estado'] ?>">
                                                <i class="fas fa-edit"></i> Editar
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

<!-- Modal de notificaciones -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-icon" id="modalIcon"></div>
        <p class="modal-message" id="modalMessage"></p>
        <button class="modal-btn" onclick="closeModal()">Aceptar</button>
    </div>
</div>

<!-- Modal de Edición -->
<div id="editModal" class="modal">
    <div class="modal-content edit-modal-content">
        <div class="modal-header">
            <h3>Editar Cocinero</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" novalidate>
            <input type="hidden" id="edit_id" name="id">
            
            <div class="form-group">
                <label>Nombre completo <span class="required">*</span></label>
                <input type="text" id="edit_nombre" name="nombre" maxlength="100" required>
                <div class="error-message" id="edit-error-nombre"></div>
            </div>
            
            <div class="form-group">
                <label>Correo electrónico <span class="required">*</span></label>
                <input type="email" id="edit_correo" name="correo" maxlength="100" required>
                <div class="error-message" id="edit-error-correo"></div>
            </div>
            
            <div class="form-group">
                <label>Teléfono</label>
                <input type="tel" id="edit_telefono" name="telefono" maxlength="10">
                <div class="error-message" id="edit-error-telefono"></div>
            </div>
            
            <div class="form-group">
                <label>Fecha de nacimiento</label>
                <input type="date" id="edit_fecha" name="fecha_nacimiento" max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                <div class="error-message" id="edit-error-fecha"></div>
            </div>
            
            <div class="form-group checkbox-group">
                <label>
                    <input type="checkbox" id="edit_estado" name="estado" value="1">
                    Usuario activo
                </label>
            </div>
            
            <div class="form-group">
                <label>Nueva contraseña (opcional)</label>
                <div class="password-field">
                    <input type="password" id="edit_password" name="password" placeholder="Dejar en blanco para no cambiar" minlength="8">
                    <button type="button" class="password-toggle" onclick="toggleEditPassword()">Mostrar</button>
                </div>
                <div class="error-message" id="edit-error-password"></div>
                <div class="form-hint">Mínimo 8 caracteres, mayúscula, minúscula y número</div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables
let submitLock = false;

// Toggle del sidebar
const toggleBtn = document.getElementById('toggleBtn');
const sidebar = document.getElementById('formSidebar');
let isExpanded = true;

if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function() {
        if (isExpanded) {
            sidebar.classList.add('collapsed');
            toggleBtn.querySelector('.toggle-icon-sidebar').textContent = '▶';
            isExpanded = false;
        } else {
            sidebar.classList.remove('collapsed');
            toggleBtn.querySelector('.toggle-icon-sidebar').textContent = '◀';
            isExpanded = true;
        }
    });
}

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

// Limpieza automática del teléfono mientras se escribe
document.addEventListener('DOMContentLoaded', function() {
    const editTelefono = document.getElementById('edit_telefono');
    if (editTelefono) {
        editTelefono.addEventListener('input', function() {
            let valor = this.value.replace(/\D/g, '');
            if (valor.length > 10) valor = valor.slice(0, 10);
            this.value = valor;
        });
    }
});

function openEditModal(cocineroData) {
    document.getElementById('edit_id').value = cocineroData.id;
    document.getElementById('edit_nombre').value = cocineroData.nombre;
    document.getElementById('edit_correo').value = cocineroData.correo;
    document.getElementById('edit_telefono').value = cocineroData.telefono || '';
    document.getElementById('edit_fecha').value = cocineroData.fecha || '';
    document.getElementById('edit_estado').checked = (cocineroData.estado == 1);
    document.getElementById('edit_password').value = '';
    
    document.querySelectorAll('#editForm .error-message').forEach(el => el.textContent = '');
    document.querySelectorAll('#editForm input').forEach(el => el.classList.remove('invalid', 'valid'));
    
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function toggleEditPassword() {
    const pwdField = document.getElementById('edit_password');
    const toggleBtn = pwdField.nextElementSibling;
    if (pwdField.type === 'password') {
        pwdField.type = 'text';
        toggleBtn.textContent = 'Ocultar';
    } else {
        pwdField.type = 'password';
        toggleBtn.textContent = 'Mostrar';
    }
}

// Validación del formulario de edición
function validarEditForm() {
    let isValid = true;
    const nombre = document.getElementById('edit_nombre').value.trim();
    const correo = document.getElementById('edit_correo').value.trim();
    const telefono = document.getElementById('edit_telefono').value.trim();
    const fecha = document.getElementById('edit_fecha').value;
    const password = document.getElementById('edit_password').value;
    
    if (nombre.length < 3 || !/^[a-zA-ZáéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) {
        document.getElementById('edit-error-nombre').textContent = 'Mínimo 3 caracteres, solo letras';
        isValid = false;
    } else {
        document.getElementById('edit-error-nombre').textContent = '';
    }
    
    if (!/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(correo)) {
        document.getElementById('edit-error-correo').textContent = 'Correo inválido';
        isValid = false;
    } else {
        document.getElementById('edit-error-correo').textContent = '';
    }
    
    if (telefono.length > 0 && telefono.length !== 10) {
        document.getElementById('edit-error-telefono').textContent = 'Debe tener 10 dígitos';
        isValid = false;
    } else {
        document.getElementById('edit-error-telefono').textContent = '';
    }
    
    if (fecha) {
        const birthDate = new Date(fecha + 'T00:00:00');
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
        if (age < 18) {
            document.getElementById('edit-error-fecha').textContent = 'Debe ser mayor de 18 años';
            isValid = false;
        } else {
            document.getElementById('edit-error-fecha').textContent = '';
        }
    } else {
        document.getElementById('edit-error-fecha').textContent = '';
    }
    
    if (password.length > 0) {
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password)) {
            document.getElementById('edit-error-password').textContent = 'Al menos 8 caracteres, una mayúscula, una minúscula y un número';
            isValid = false;
        } else {
            document.getElementById('edit-error-password').textContent = '';
        }
    }
    
    return isValid;
}

// Envío del formulario de edición
document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!validarEditForm()) return;
    
    const formData = new FormData(this);
    formData.append('ajax_action', 'update_cocinero');
    
    formData.delete('estado');
    formData.append('estado', document.getElementById('edit_estado').checked ? '1' : '0');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Guardando...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('cocineros.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showModal(result.message, true);
            closeEditModal();
            location.reload();
        } else {
            showModal(result.message || 'Error al actualizar', false);
            if (result.errores) {
                if (result.errores.nombre) document.getElementById('edit-error-nombre').textContent = result.errores.nombre;
                if (result.errores.correo) document.getElementById('edit-error-correo').textContent = result.errores.correo;
                if (result.errores.password) document.getElementById('edit-error-password').textContent = result.errores.password;
                if (result.errores.telefono) document.getElementById('edit-error-telefono').textContent = result.errores.telefono;
                if (result.errores.fecha_nacimiento) document.getElementById('edit-error-fecha').textContent = result.errores.fecha_nacimiento;
            }
        }
    } catch (error) {
        showModal('Error de conexión', false);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

// Delegación de eventos para botones Editar
document.addEventListener('click', function(e) {
    if (e.target.closest('.edit-btn')) {
        const btn = e.target.closest('.edit-btn');
        const cocineroData = {
            id: btn.dataset.id,
            nombre: btn.dataset.nombre,
            correo: btn.dataset.correo,
            telefono: btn.dataset.telefono,
            fecha: btn.dataset.fecha,
            estado: btn.dataset.estado
        };
        openEditModal(cocineroData);
    }
});

// Actualizar tabla
async function refreshTable() {
    try {
        const response = await fetch('cocineros.php?ajax_list=1', {
            cache: 'no-store'
        });
        const cocineros = await response.json();
        
        const tbody = document.getElementById('cocinerosList');
        const countBadge = document.getElementById('cocinerosCount');
        
        if (cocineros.length === 0) {
            tbody.innerHTML = `
                <tr class="empty-row">
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>No hay cocineros registrados</p>
                            <small>Completa el formulario para agregar uno</small>
                        </div>
                    </td>
                </tr>
            `;
            countBadge.textContent = '0';
            return;
        }
        
        countBadge.textContent = cocineros.length;
        
        tbody.innerHTML = cocineros.map(c => {
            let edad = '—';
            if (c.fecha_nacimiento) {
                const hoy = new Date();
                const nac = new Date(c.fecha_nacimiento + 'T00:00:00');
                let anios = hoy.getFullYear() - nac.getFullYear();
                const mes = hoy.getMonth() - nac.getMonth();
                if (mes < 0 || (mes === 0 && hoy.getDate() < nac.getDate())) {
                    anios--;
                }
                edad = anios + ' años';
            }
            
            const telefono = c.telefono || '—';
            const statusClass = c.estado ? 'status-active' : 'status-inactive';
            const statusText = c.estado ? 'Activo' : 'Inactivo';
            
            return `
                <tr data-id="${c.id_cocinero}">
                    <td class="user-cell">
                        <div class="user-avatar">${c.nombre.charAt(0).toUpperCase()}</div>
                        <div class="user-info">
                            <strong class="user-name">${escapeHtml(c.nombre)}</strong>
                            <span class="user-email">${escapeHtml(c.correo)}</span>
                        </div>
                    </td>
                    <td><span class="${c.telefono ? 'contact-phone' : 'contact-empty'}">${escapeHtml(telefono)}</span></td>
                    <td>${c.fecha_nacimiento ? `<span class="age-tag">${edad}</span>` : '<span class="contact-empty">—</span>'}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="action-btn edit-btn" 
                                data-id="${c.id_cocinero}"
                                data-nombre="${escapeHtml(c.nombre)}"
                                data-correo="${escapeHtml(c.correo)}"
                                data-telefono="${c.telefono ? escapeHtml(c.telefono) : ''}"
                                data-fecha="${c.fecha_nacimiento || ''}"
                                data-estado="${c.estado}">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
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

// Validaciones del formulario de registro
function mostrarError(id, mensaje) {
    const errorDiv = document.getElementById('error-' + id);
    const input = document.getElementById(id);
    if (mensaje) {
        errorDiv.textContent = mensaje;
        errorDiv.classList.add('show');
        if (input) input.classList.add('invalid');
    } else {
        errorDiv.textContent = '';
        errorDiv.classList.remove('show');
        if (input) input.classList.remove('invalid');
    }
}

function limpiarError(id) {
    const errorDiv = document.getElementById('error-' + id);
    const input = document.getElementById(id);
    errorDiv.textContent = '';
    errorDiv.classList.remove('show');
    if (input) input.classList.remove('invalid');
}

function validarNombre() {
    const nombre = document.getElementById('nombre').value.trim();
    if (nombre.length === 0) {
        mostrarError('nombre', '');
        return false;
    }
    if (nombre.length < 3) {
        mostrarError('nombre', 'Mínimo 3 caracteres');
        return false;
    }
    if (!/^[a-zA-ZáéíóúñÑáéíóúÁÉÍÓÚ\s]+$/.test(nombre)) {
        mostrarError('nombre', 'Solo letras y espacios');
        return false;
    }
    limpiarError('nombre');
    document.getElementById('nombre').classList.add('valid');
    return true;
}

function validarCorreo() {
    const correo = document.getElementById('correo').value.trim();
    if (correo.length === 0) {
        mostrarError('correo', '');
        return false;
    }
    if (!/^[^\s@]+@([^\s@]+\.)+[^\s@]+$/.test(correo)) {
        mostrarError('correo', 'Formato de correo no válido');
        return false;
    }
    limpiarError('correo');
    document.getElementById('correo').classList.add('valid');
    return true;
}

function validarTelefono() {
    let telefono = document.getElementById('telefono').value.replace(/\D/g, '');
    if (telefono.length > 10) telefono = telefono.slice(0, 10);
    document.getElementById('telefono').value = telefono;
    
    if (telefono.length === 0) {
        limpiarError('telefono');
        return true;
    }
    if (telefono.length !== 10) {
        mostrarError('telefono', 'Debe tener 10 dígitos');
        return false;
    }
    limpiarError('telefono');
    document.getElementById('telefono').classList.add('valid');
    return true;
}

function validarFecha() {
    const fecha = document.getElementById('fecha_nacimiento').value;
    if (!fecha) {
        limpiarError('fecha');
        return true;
    }
    const birthDate = new Date(fecha + 'T00:00:00');
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
    if (age < 18) {
        mostrarError('fecha', 'Debe ser mayor de 18 años');
        return false;
    }
    limpiarError('fecha');
    document.getElementById('fecha_nacimiento').classList.add('valid');
    return true;
}

function validarPassword() {
    const password = document.getElementById('password').value;
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /\d/.test(password);
    
    if (reqLength) reqLength.classList.toggle('met', hasLength);
    if (reqUpper) reqUpper.classList.toggle('met', hasUpper);
    if (reqLower) reqLower.classList.toggle('met', hasLower);
    if (reqNumber) reqNumber.classList.toggle('met', hasNumber);
    
    if (password.length === 0) {
        mostrarError('password', '');
        return false;
    }
    if (!hasLength) {
        mostrarError('password', 'Mínimo 8 caracteres');
        return false;
    }
    if (!hasUpper) {
        mostrarError('password', 'Agrega una mayúscula');
        return false;
    }
    if (!hasLower) {
        mostrarError('password', 'Agrega una minúscula');
        return false;
    }
    if (!hasNumber) {
        mostrarError('password', 'Agrega un número');
        return false;
    }
    limpiarError('password');
    document.getElementById('password').classList.add('valid');
    return true;
}

// Event listeners para el formulario de registro
document.getElementById('nombre')?.addEventListener('input', validarNombre);
document.getElementById('correo')?.addEventListener('input', validarCorreo);
document.getElementById('telefono')?.addEventListener('input', validarTelefono);
document.getElementById('fecha_nacimiento')?.addEventListener('change', validarFecha);
document.getElementById('password')?.addEventListener('input', validarPassword);

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    const toggleBtn = document.querySelector('.password-toggle');
    toggleBtn.textContent = type === 'password' ? 'Mostrar' : 'Ocultar';
}

// Submit del formulario de registro
const form = document.getElementById('formCocinero');
form?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (submitLock) return;
    
    const isValid = validarNombre() && validarCorreo() && validarTelefono() && validarFecha() && validarPassword();
    
    if (!isValid) {
        showModal('Por favor corrige los errores en el formulario', false);
        return;
    }
    
    submitLock = true;
    const submitBtn = document.getElementById('btnSubmit');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = 'Registrando...';
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    formData.append('ajax_action', 'crear_cocinero');
    
    try {
        const response = await fetch('cocineros.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showModal(result.message, true);
            form.reset();
            document.querySelectorAll('.form-group input').forEach(input => {
                input.classList.remove('valid', 'invalid');
            });
            refreshTable();
        } else {
            showModal(result.message, false);
            if (result.errores) {
                if (result.errores.nombre) mostrarError('nombre', result.errores.nombre);
                if (result.errores.correo) mostrarError('correo', result.errores.correo);
                if (result.errores.telefono) mostrarError('telefono', result.errores.telefono);
                if (result.errores.fecha_nacimiento) mostrarError('fecha', result.errores.fecha_nacimiento);
                if (result.errores.password) mostrarError('password', result.errores.password);
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
</script>

</body>
</html>