<?php
require_once "../config/admin_session.php";

// Función de validaciones
function validarMesero($datos, $es_actualizacion = false) {
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

// Endpoint AJAX para activar/desactivar
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'toggle_estado') {
    header('Content-Type: application/json');
    
    $id = (int)$_POST['id'];
    $estado = (int)$_POST['estado'];
    $respuesta = ['success' => false, 'message' => '', 'nuevo_estado' => null];
    
    if ($estado === 0 || $estado === 1) {
        $sql = "UPDATE meseros SET estado = ? WHERE id_mesero = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $estado, $id);
        
        if ($stmt->execute()) {
            $respuesta['success'] = true;
            $respuesta['message'] = $estado ? "Mesero activado correctamente" : "Mesero desactivado correctamente";
            $respuesta['nuevo_estado'] = $estado;
        } else {
            $respuesta['message'] = "Error al actualizar el estado";
        }
        $stmt->close();
    } else {
        $respuesta['message'] = "Estado no válido";
    }
    
    echo json_encode($respuesta);
    exit;
}

// Endpoint AJAX para crear mesero
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_mesero') {
    header('Content-Type: application/json');
    
    $validacion = validarMesero($_POST);
    
    if (empty($validacion['errores'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $check = $conexion->prepare("SELECT id_mesero FROM meseros WHERE correo = ?");
        $check->bind_param("s", $validacion['datos']['correo']);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado', 'errores' => ['correo' => 'Este correo ya existe']]);
            exit;
        }
        
        $sql = "INSERT INTO meseros (nombre, correo, telefono, fecha_nacimiento, password) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssss", $validacion['datos']['nombre'], $validacion['datos']['correo'], $validacion['datos']['telefono'], $validacion['datos']['fecha_nacimiento'], $hash);
        
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            
            $select = $conexion->prepare("SELECT * FROM meseros WHERE id_mesero = ?");
            $select->bind_param("i", $id);
            $select->execute();
            $resultado = $select->get_result();
            $mesero = $resultado->fetch_assoc();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Mesero registrado correctamente',
                'mesero' => $mesero
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

// Obtener lista actualizada
if (isset($_GET['ajax_list']) && $_GET['ajax_list'] == '1') {
    header('Content-Type: application/json');
    $lista = $conexion->query("SELECT * FROM meseros ORDER BY nombre");
    $meseros = [];
    while ($m = $lista->fetch_assoc()) {
        $meseros[] = $m;
    }
    echo json_encode($meseros);
    exit;
}

$lista = $conexion->query("SELECT * FROM meseros ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Meseros</title>
    <link rel="stylesheet" href="css/meseros.css">
</head>
<body>

<div class="header">
    <div class="header-content">
        <div>
            <h1 class="header-title">Gestión de Meseros</h1>
            <p class="header-subtitle">Administra el equipo de servicio</p>
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
                    <span class="sidebar-label-small">mesero</span>
                </div>
            </div>
            
            <div class="sidebar-content" id="sidebarContent">
                <div class="card form-card">
                    <div class="card-header">
                        <div>
                            <h2>Registrar nuevo mesero</h2>
                            <p>Ingresa los datos del personal de servicio</p>
                        </div>
                    </div>
                    
                    <form id="formMesero" class="form-mesero" novalidate>
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
                        
                        <button type="submit" class="btn-submit" id="btnSubmit">Registrar mesero</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista de meseros (scroll independiente) -->
        <div class="card list-card">
            <div class="card-header">
                <div>
                    <h2>Meseros registrados <span class="card-badge" id="meserosCount"><?= $lista->num_rows ?></span></h2>
                    <p>Personal activo e inactivo</p>
                </div>
            </div>

            <div class="table-wrapper-scroll">
                <div class="table-wrapper">
                    <table class="data-table" id="meserosTable">
                        <thead>
                            <tr>
                                <th>Mesero</th>
                                <th>Contacto</th>
                                <th>Edad</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="meserosList">
                            <?php if ($lista->num_rows == 0): ?>
                                <tr class="empty-row">
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"></div>
                                            <p>No hay meseros registrados</p>
                                            <small>Completa el formulario para agregar uno</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($m = $lista->fetch_assoc()): ?>
                                    <tr data-id="<?= $m['id_mesero'] ?>">
                                        <td class="user-cell">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($m['nombre'], 0, 1)) ?>
                                            </div>
                                            <div class="user-info">
                                                <strong class="user-name"><?= htmlspecialchars($m['nombre']) ?></strong>
                                                <span class="user-email"><?= htmlspecialchars($m['correo']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($m['telefono']): ?>
                                                <span class="contact-phone"><?= htmlspecialchars($m['telefono']) ?></span>
                                            <?php else: ?>
                                                <span class="contact-empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($m['fecha_nacimiento']) {
                                                $edad = date_diff(date_create($m['fecha_nacimiento']), date_create('today'))->y;
                                                echo "<span class='age-tag'>$edad años</span>";
                                            } else {
                                                echo "<span class='contact-empty'>—</span>";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $m['estado'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $m['estado'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn toggle-status" 
                                                    data-id="<?= $m['id_mesero'] ?>"
                                                    data-estado="<?= $m['estado'] ?>">
                                                <?= $m['estado'] ? 'Desactivar' : 'Activar' ?>
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

<!-- Modal -->
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

// Toggle del sidebar (desplegable horizontal)
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

// Mostrar modal
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

// Actualizar tabla
async function refreshTable() {
    try {
        const response = await fetch('meseros.php?ajax_list=1');
        const meseros = await response.json();
        
        const tbody = document.getElementById('meserosList');
        const countBadge = document.getElementById('meserosCount');
        
        if (meseros.length === 0) {
            tbody.innerHTML = `
                <tr class="empty-row">
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>No hay meseros registrados</p>
                            <small>Completa el formulario para agregar uno</small>
                        </div>
                    </td>
                </tr>
            `;
            countBadge.textContent = '0';
            return;
        }
        
        countBadge.textContent = meseros.length;
        
        tbody.innerHTML = meseros.map(m => {
            const edad = m.fecha_nacimiento ? 
                `${new Date().getFullYear() - new Date(m.fecha_nacimiento).getFullYear()} años` : '—';
            const telefono = m.telefono || '—';
            const statusClass = m.estado ? 'status-active' : 'status-inactive';
            const statusText = m.estado ? 'Activo' : 'Inactivo';
            const actionText = m.estado ? 'Desactivar' : 'Activar';
            
            return `
                <tr data-id="${m.id_mesero}">
                    <td class="user-cell">
                        <div class="user-avatar">${m.nombre.charAt(0).toUpperCase()}</div>
                        <div class="user-info">
                            <strong class="user-name">${escapeHtml(m.nombre)}</strong>
                            <span class="user-email">${escapeHtml(m.correo)}</span>
                        </div>
                    </td>
                    <td><span class="${m.telefono ? 'contact-phone' : 'contact-empty'}">${escapeHtml(telefono)}</span></td>
                    <td>${m.fecha_nacimiento ? `<span class="age-tag">${edad}</span>` : '<span class="contact-empty">—</span>'}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="action-btn toggle-status" data-id="${m.id_mesero}" data-estado="${m.estado}">
                            ${actionText}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        document.querySelectorAll('.toggle-status').forEach(btn => {
            btn.removeEventListener('click', handleToggleStatus);
            btn.addEventListener('click', handleToggleStatus);
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

// Toggle estado
async function handleToggleStatus(event) {
    const button = event.currentTarget;
    const id = button.dataset.id;
    const estadoActual = parseInt(button.dataset.estado);
    const nuevoEstado = estadoActual === 1 ? 0 : 1;
    const nombreMesero = button.closest('tr').querySelector('.user-name')?.textContent || 'el mesero';
    
    const confirmMessage = nuevoEstado === 1 ? 
        `¿Activar a ${nombreMesero}?` : 
        `¿Desactivar a ${nombreMesero}?`;
    
    if (!confirm(confirmMessage)) return;
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'toggle_estado');
        formData.append('id', id);
        formData.append('estado', nuevoEstado);
        
        const response = await fetch('meseros.php', {
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

// Validaciones
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
    const birthDate = new Date(fecha);
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

// Event listeners
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

// Submit form
const form = document.getElementById('formMesero');
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
    formData.append('ajax_action', 'crear_mesero');
    
    try {
        const response = await fetch('meseros.php', {
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

// Inicializar eventos
document.querySelectorAll('.toggle-status').forEach(btn => {
    btn.addEventListener('click', handleToggleStatus);
});
</script>

</body>
</html>