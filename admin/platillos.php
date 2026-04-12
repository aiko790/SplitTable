<?php
require_once "../config/admin_session.php";

// ==================== FUNCIONES DE VALIDACIÓN ====================
function validarCategoria($nombre) {
    $nombre = trim($nombre);
    if (empty($nombre)) return "El nombre es obligatorio";
    if (strlen($nombre) < 3) return "Mínimo 3 caracteres";
    if (strlen($nombre) > 50) return "Máximo 50 caracteres";
    if (!preg_match("/^[a-zA-Z0-9áéíóúñÑáéíóúÁÉÍÓÚ\s]+$/", $nombre)) {
        return "Solo letras, números y espacios";
    }
    return null;
}

function validarPlatillo($datos) {
    $errores = [];
    $nombre = trim($datos['nombre'] ?? '');
    if (empty($nombre)) $errores['nombre'] = "El nombre es obligatorio";
    elseif (strlen($nombre) < 3) $errores['nombre'] = "Mínimo 3 caracteres";
    elseif (strlen($nombre) > 100) $errores['nombre'] = "Máximo 100 caracteres";

    $id_categoria = (int)($datos['id_categoria'] ?? 0);
    if ($id_categoria <= 0) $errores['id_categoria'] = "Selecciona una categoría";

    $precio = (float)($datos['precio'] ?? 0);
    if ($precio <= 0) $errores['precio'] = "El precio debe ser mayor a 0";
    elseif ($precio > 99999.99) $errores['precio'] = "Precio máximo 99,999.99";

    $descripcion = trim($datos['descripcion'] ?? '');
    if (strlen($descripcion) > 500) $errores['descripcion'] = "Máximo 500 caracteres";

    $imagen = trim($datos['imagen'] ?? '');
    if (!empty($imagen) && strlen($imagen) > 255) {
        $errores['imagen'] = "El nombre de la imagen no puede exceder 255 caracteres";
    }
    // Validar que el nombre de imagen solo contenga caracteres seguros (opcional)
    if (!empty($imagen) && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $imagen)) {
        $errores['imagen'] = "El nombre de la imagen solo puede contener letras, números, guiones y puntos";
    }

    return [
        'errores' => $errores,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'id_categoria' => $id_categoria,
        'precio' => $precio,
        'imagen' => $imagen
    ];
}

// ==================== ENDPOINTS PARA IMÁGENES ====================

// Listar imágenes de la carpeta img_platillos
if (isset($_GET['ajax_list_images']) && $_GET['ajax_list_images'] == '1') {
    header('Content-Type: application/json');
    $carpeta = '../img_platillos/';
    $imagenes = [];
    if (is_dir($carpeta)) {
        $archivos = scandir($carpeta);
        foreach ($archivos as $archivo) {
            $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $imagenes[] = [
                    'nombre' => $archivo,               // Solo el nombre del archivo
                    'ruta' => '../img_platillos/' . $archivo  // Ruta para mostrar en el modal
                ];
            }
        }
    }
    echo json_encode($imagenes);
    exit;
}

// Subir nueva imagen (solo guarda el archivo, retorna el nombre)
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'subir_imagen') {
    header('Content-Type: application/json');
    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        exit;
    }
    $archivo = $_FILES['imagen'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $tipos_permitidos)) {
        echo json_encode(['success' => false, 'message' => 'Formato no permitido. Usa JPG, PNG, GIF o WEBP']);
        exit;
    }
    $nombre_archivo = uniqid() . '.' . $extension;
    $ruta_destino = '../img_platillos/' . $nombre_archivo;
    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        echo json_encode([
            'success' => true,
            'message' => 'Imagen subida correctamente',
            'nombre' => $nombre_archivo   // Solo el nombre
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
    }
    exit;
}

// ==================== ENDPOINTS AJAX (CRUD) ====================

// Crear categoría
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_categoria') {
    header('Content-Type: application/json');
    $nombre = $_POST['nombre_categoria'] ?? '';
    $error = validarCategoria($nombre);
    if ($error) {
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    $check = $conexion->prepare("SELECT id_categoria FROM categorias_platillo WHERE nombre = ?");
    $check->bind_param("s", $nombre);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con ese nombre']);
        exit;
    }
    $stmt = $conexion->prepare("INSERT INTO categorias_platillo (nombre) VALUES (?)");
    $stmt->bind_param("s", $nombre);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Categoría creada', 'id' => $id, 'nombre' => $nombre]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear la categoría']);
    }
    exit;
}

// Editar categoría
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'editar_categoria') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_categoria'];
    $nombre = trim($_POST['nombre'] ?? '');
    $error = validarCategoria($nombre);
    if ($error) {
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    $check = $conexion->prepare("SELECT id_categoria FROM categorias_platillo WHERE nombre = ? AND id_categoria != ?");
    $check->bind_param("si", $nombre, $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya existe otra categoría con ese nombre']);
        exit;
    }
    $stmt = $conexion->prepare("UPDATE categorias_platillo SET nombre = ? WHERE id_categoria = ?");
    $stmt->bind_param("si", $nombre, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Categoría actualizada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    }
    exit;
}

// Eliminar categoría
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'eliminar_categoria') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_categoria'];
    $check = $conexion->prepare("SELECT COUNT(*) as total FROM platillos WHERE id_categoria = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $res = $check->get_result();
    $total = $res->fetch_assoc()['total'];
    if ($total > 0) {
        echo json_encode(['success' => false, 'message' => "No se puede eliminar la categoría porque tiene $total platillo(s). Elimina primero los platillos."]);
        exit;
    }
    $stmt = $conexion->prepare("DELETE FROM categorias_platillo WHERE id_categoria = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Categoría eliminada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
    }
    exit;
}

// Crear platillo
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'crear_platillo') {
    header('Content-Type: application/json');
    $validacion = validarPlatillo($_POST);
    if (!empty($validacion['errores'])) {
        echo json_encode(['success' => false, 'message' => 'Errores en el formulario', 'errores' => $validacion['errores']]);
        exit;
    }
    $stmt = $conexion->prepare("INSERT INTO platillos (nombre, descripcion, id_categoria, precio, activo, disponible, imagen) VALUES (?, ?, ?, ?, 1, 1, ?)");
    $stmt->bind_param("ssids", $validacion['nombre'], $validacion['descripcion'], $validacion['id_categoria'], $validacion['precio'], $validacion['imagen']);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        echo json_encode(['success' => true, 'message' => 'Platillo creado', 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear platillo']);
    }
    exit;
}

// Editar platillo
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'editar_platillo') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_platillo'];
    $validacion = validarPlatillo($_POST);
    if (!empty($validacion['errores'])) {
        echo json_encode(['success' => false, 'message' => 'Errores en el formulario', 'errores' => $validacion['errores']]);
        exit;
    }
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;
    $activo = ($activo === 1) ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE platillos SET nombre=?, descripcion=?, id_categoria=?, precio=?, activo=?, imagen=? WHERE id_platillo=?");
    $stmt->bind_param("ssidisi", $validacion['nombre'], $validacion['descripcion'], $validacion['id_categoria'], $validacion['precio'], $activo, $validacion['imagen'], $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Platillo actualizado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    }
    exit;
}

// Obtener datos actualizados
if (isset($_GET['ajax_list']) && $_GET['ajax_list'] == '1') {
    header('Content-Type: application/json');
    $categorias = $conexion->query("
        SELECT c.*, COUNT(p.id_platillo) as total_platillos
        FROM categorias_platillo c
        LEFT JOIN platillos p ON c.id_categoria = p.id_categoria
        GROUP BY c.id_categoria
        ORDER BY c.nombre
    ");
    $cats = [];
    while ($c = $categorias->fetch_assoc()) {
        $cats[] = $c;
    }
    $platillos = $conexion->query("
        SELECT p.*, c.nombre as categoria_nombre
        FROM platillos p
        JOIN categorias_platillo c ON p.id_categoria = c.id_categoria
        ORDER BY c.nombre, p.nombre
    ");
    $platos = [];
    while ($p = $platillos->fetch_assoc()) {
        $p['activo'] = (int)$p['activo'];
        $p['disponible'] = (int)$p['disponible'];
        $platos[] = $p;
    }
    echo json_encode(['categorias' => $cats, 'platillos' => $platos]);
    exit;
}

$categorias = $conexion->query("SELECT * FROM categorias_platillo ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Platillos</title>
    <link rel="stylesheet" href="css/platillos.css">
    <script>
        // Ruta base para imágenes (relativa desde la raíz del proyecto)
        const RUTA_IMG = '../img_platillos/';
    </script>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div>
            <h1 class="header-title">Gestión de Platillos</h1>
            <p class="header-subtitle">Administra el menú del restaurante</p>
        </div>
        <a href="dashboard.php" class="btn-back">← Volver al Dashboard</a>
    </div>
</div>

<div class="container">

    <!-- Tarjeta: Crear categoría -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon"></div>
            <div>
                <h2>Crear categoría</h2>
                <p>Agrupa los platillos por tipo</p>
            </div>
        </div>
        <form id="formCategoria" class="form-vertical">
            <div class="form-group">
                <label>Nombre de la categoría <span class="required">*</span></label>
                <input type="text" id="nombre_categoria" name="nombre_categoria" placeholder="Ej: Bebidas, Entradas, Platos Fuertes" maxlength="50" autocomplete="off">
                <div class="error-message" id="error-categoria"></div>
                <div class="form-hint">Mínimo 3 caracteres, solo letras, números y espacios</div>
            </div>
            <button type="submit" class="btn-primary btn-create" id="btnCrearCategoria">Crear categoría</button>
        </form>
        <div class="categorias-footer">
            <a href="#" id="editarCategoriasLink" class="edit-categorias-link">✏️ Editar categorías</a>
        </div>
        <div class="categorias-list" id="categoriasList"></div>
    </div>

    <!-- Tarjeta: Crear platillo (campo imagen solo nombre) -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon"></div>
            <div>
                <h2>Crear platillo</h2>
                <p>Agrega un nuevo platillo al menú</p>
            </div>
        </div>
        <form id="formPlatillo" class="form-vertical">
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre del platillo <span class="required">*</span></label>
                    <input type="text" name="nombre" id="platillo_nombre" placeholder="Ej: Tacos al pastor" maxlength="100">
                    <div class="error-message" id="error-platillo-nombre"></div>
                </div>
                <div class="form-group">
                    <label>Categoría <span class="required">*</span></label>
                    <select name="id_categoria" id="platillo_categoria">
                        <option value="">Selecciona categoría</option>
                        <?php while ($c = $categorias->fetch_assoc()): ?>
                            <option value="<?= $c['id_categoria'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="error-message" id="error-platillo-categoria"></div>
                </div>
                <div class="form-group">
                    <label>Precio <span class="required">*</span></label>
                    <input type="number" step="0.01" name="precio" id="platillo_precio" placeholder="0.00" min="0.01" max="99999.99">
                    <div class="error-message" id="error-platillo-precio"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="platillo_descripcion" rows="2" placeholder="Describe los ingredientes o preparación (opcional)" maxlength="500"></textarea>
                <div class="error-message" id="error-platillo-descripcion"></div>
                <div class="form-hint">Máximo 500 caracteres</div>
            </div>
            <div class="form-group">
                <label>Imagen (solo el nombre del archivo)</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" name="imagen" id="platillo_imagen" placeholder="ejemplo.jpg" style="flex:1;" maxlength="255">
                    <button type="button" class="btn-secondary" onclick="abrirSelectorImagen('platillo_imagen')">📁 Seleccionar</button>
                </div>
                <div id="preview_platillo_imagen" class="imagen-preview" style="margin-top: 10px;"></div>
                <div class="error-message" id="error-platillo-imagen"></div>
                <div class="form-hint">Escribe solo el nombre del archivo (ej. "foto123.jpg") o selecciona desde la galería. La imagen debe estar en la carpeta img_platillos.</div>
            </div>
            <button type="submit" class="btn-primary btn-create" id="btnCrearPlatillo">Crear platillo</button>
        </form>
    </div>

    <!-- Tarjeta: Lista de platillos -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon"></div>
            <div>
                <h2>Platillos registrados</h2>
                <p>Lista de platillos, puedes editar cada uno</p>
            </div>
        </div>

        <div class="filtro-categoria">
            <label>Filtrar por categoría:</label>
            <select id="filtroCategoria">
                <option value="">-- Todas las categorías --</option>
            </select>
        </div>

        <div id="platillosLista">
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <p>Cargando platillos...</p>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Editar categorías -->
<div id="modalCategorias" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Gestionar categorías</h3>
            <button class="modal-close" onclick="closeModalCategorias()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="categoriasModalList"></div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeModalCategorias()">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL: Editar platillo (campo imagen solo nombre) -->
<div id="modalPlatillo" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Editar platillo</h3>
            <button class="modal-close" onclick="closeModalPlatillo()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formEditarPlatillo" class="form-vertical">
                <input type="hidden" id="edit_id_platillo" name="id_platillo">
                <div class="form-group">
                    <label>Nombre <span class="required">*</span></label>
                    <input type="text" id="edit_nombre" name="nombre" maxlength="100">
                    <div class="error-message" id="edit-error-nombre"></div>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea id="edit_descripcion" name="descripcion" rows="3" maxlength="500"></textarea>
                    <div class="error-message" id="edit-error-descripcion"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <select id="edit_id_categoria" name="id_categoria"></select>
                        <div class="error-message" id="edit-error-categoria"></div>
                    </div>
                    <div class="form-group">
                        <label>Precio <span class="required">*</span></label>
                        <input type="number" step="0.01" id="edit_precio" name="precio" min="0.01" max="99999.99">
                        <div class="error-message" id="edit-error-precio"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Imagen (solo el nombre del archivo)</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="edit_imagen" name="imagen" style="flex:1;" maxlength="255">
                        <button type="button" class="btn-secondary" onclick="abrirSelectorImagen('edit_imagen')">📁 Seleccionar</button>
                    </div>
                    <div class="error-message" id="edit-error-imagen"></div>
                    <div id="edit_imagen_preview" class="imagen-preview"></div>
                </div>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" id="edit_activo" name="activo" value="1">
                        <span>Activo (visible en menú)</span>
                    </label>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                    <button type="button" class="btn-secondary" onclick="closeModalPlatillo()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Selector de imágenes (muestra la imagen y asigna solo el nombre) -->
<div id="modalImagenes" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Seleccionar imagen</h3>
            <button class="modal-close" onclick="cerrarModalImagenes()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                <h4>Subir nueva imagen</h4>
                <form id="formSubirImagen" enctype="multipart/form-data">
                    <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <button type="submit" class="btn-primary" style="margin-top: 8px;">Subir imagen</button>
                </form>
                <div id="mensajeSubida" style="margin-top: 10px; font-size: 14px;"></div>
            </div>
            <h4>Imágenes disponibles</h4>
            <div id="galeriaImagenes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; max-height: 400px; overflow-y: auto; padding: 5px;">
                <p>Cargando imágenes...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="cerrarModalImagenes()">Cerrar</button>
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
// Variables
let categoriasData = [];
let platillosData = [];
let filtroCategoria = '';
let campoImagenDestino = null;

function showModal(message, isSuccess = true) {
    const modal = document.getElementById('notificationModal');
    const modalIcon = document.getElementById('modalIcon');
    const modalMessage = document.getElementById('modalMessage');
    modalIcon.className = 'modal-icon ' + (isSuccess ? 'modal-success' : 'modal-error');
    modalMessage.textContent = message;
    modal.classList.add('show');
    setTimeout(() => closeModal(), 3000);
}
function closeModal() {
    document.getElementById('notificationModal').classList.remove('show');
}

async function refreshData() {
    try {
        const response = await fetch('platillos.php?ajax_list=1');
        const data = await response.json();
        categoriasData = data.categorias;
        platillosData = data.platillos;
        renderCategorias();
        updateFiltroSelect();
        renderPlatillos();
    } catch (error) {
        console.error('Error al refrescar:', error);
    }
}

function updateFiltroSelect() {
    const select = document.getElementById('filtroCategoria');
    if (!select) return;
    let options = '<option value="">-- Todas las categorías --</option>';
    categoriasData.forEach(cat => {
        options += `<option value="${cat.id_categoria}" ${filtroCategoria == cat.id_categoria ? 'selected' : ''}>${escapeHtml(cat.nombre)}</option>`;
    });
    select.innerHTML = options;
}

function renderCategorias() {
    const container = document.getElementById('categoriasList');
    if (!container) return;
    if (categoriasData.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay categorías aún.</p>';
        return;
    }
    let html = '<div class="categorias-grid">';
    categoriasData.forEach(cat => {
        html += `
            <div class="categoria-item" data-id="${cat.id_categoria}">
                <span class="categoria-nombre">${escapeHtml(cat.nombre)}</span>
                <span class="categoria-badge">${cat.total_platillos} platillo(s)</span>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderPlatillos() {
    const container = document.getElementById('platillosLista');
    if (!container) return;

    let platillosFiltrados = platillosData;
    if (filtroCategoria) {
        platillosFiltrados = platillosData.filter(p => p.id_categoria == filtroCategoria);
    }

    if (platillosFiltrados.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <p>No hay platillos ${filtroCategoria ? 'en esta categoría' : 'registrados'}</p>
                <small>Usa el formulario para agregar uno</small>
            </div>
        `;
        return;
    }

    let html = `
        <div class="platillos-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Platillo</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
    `;

    platillosFiltrados.forEach(p => {
        const estadoClase = p.activo ? 'status-active' : 'status-inactive';
        const estadoTexto = p.activo ? 'Activo' : 'Inactivo';
        // Construir ruta completa de la imagen: RUTA_IMG + nombre del archivo
        const imagenUrl = p.imagen ? RUTA_IMG + p.imagen : '';
        const imagenHtml = imagenUrl ? `<img src="${escapeHtml(imagenUrl)}" class="platillo-imagen-thumb" alt="${escapeHtml(p.nombre)}">` : '<span class="sin-imagen">Sin imagen</span>';
        html += `
            <tr>
                <td class="platillo-imagen">${imagenHtml}</td>
                <td class="platillo-nombre">${escapeHtml(p.nombre)}</td>
                <td class="platillo-descripcion">${escapeHtml(p.descripcion || '')}</td>
                <td class="platillo-categoria">${escapeHtml(p.categoria_nombre)}</td>
                <td class="platillo-precio">$${parseFloat(p.precio).toFixed(2)}</td>
                <td class="platillo-estado"><span class="estado-texto ${estadoClase}">${estadoTexto}</span></td>
                <td class="platillo-acciones">
                    <button class="btn-edit-platillo" data-id="${p.id_platillo}">Editar</button>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;
    container.innerHTML = html;

    document.querySelectorAll('.btn-edit-platillo').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const platillo = platillosData.find(p => p.id_platillo == id);
            if (platillo) {
                abrirModalEditarPlatillo(platillo);
            }
        });
    });
}

function abrirModalEditarPlatillo(platillo) {
    document.getElementById('edit_id_platillo').value = platillo.id_platillo;
    document.getElementById('edit_nombre').value = platillo.nombre;
    document.getElementById('edit_descripcion').value = platillo.descripcion || '';
    document.getElementById('edit_precio').value = platillo.precio;
    document.getElementById('edit_imagen').value = platillo.imagen || '';
    document.getElementById('edit_activo').checked = platillo.activo === 1;

    const selectCategoria = document.getElementById('edit_id_categoria');
    selectCategoria.innerHTML = '';
    categoriasData.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id_categoria;
        option.textContent = cat.nombre;
        if (cat.id_categoria == platillo.id_categoria) option.selected = true;
        selectCategoria.appendChild(option);
    });

    const previewDiv = document.getElementById('edit_imagen_preview');
    if (platillo.imagen) {
        previewDiv.innerHTML = `<img src="${RUTA_IMG + escapeHtml(platillo.imagen)}" class="imagen-preview-img" alt="Preview">`;
    } else {
        previewDiv.innerHTML = '';
    }

    document.querySelectorAll('#formEditarPlatillo .error-message').forEach(el => el.textContent = '');
    document.getElementById('modalPlatillo').classList.add('show');
}

function closeModalPlatillo() {
    document.getElementById('modalPlatillo').classList.remove('show');
}

const formEditarPlatillo = document.getElementById('formEditarPlatillo');
formEditarPlatillo.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('edit_id_platillo').value;
    const nombre = document.getElementById('edit_nombre').value.trim();
    const descripcion = document.getElementById('edit_descripcion').value;
    const id_categoria = document.getElementById('edit_id_categoria').value;
    const precio = parseFloat(document.getElementById('edit_precio').value);
    const imagen = document.getElementById('edit_imagen').value.trim();
    const activo = document.getElementById('edit_activo').checked ? 1 : 0;

    let errors = {};
    if (!nombre || nombre.length < 3) errors.nombre = 'Mínimo 3 caracteres';
    if (!id_categoria) errors.id_categoria = 'Selecciona una categoría';
    if (isNaN(precio) || precio <= 0) errors.precio = 'Precio mayor a 0';
    if (imagen.length > 255) errors.imagen = 'Máximo 255 caracteres';

    if (Object.keys(errors).length > 0) {
        for (let field in errors) {
            const errorSpan = document.getElementById(`edit-error-${field}`);
            if (errorSpan) errorSpan.textContent = errors[field];
        }
        return;
    }

    const formData = new FormData();
    formData.append('ajax_action', 'editar_platillo');
    formData.append('id_platillo', id);
    formData.append('nombre', nombre);
    formData.append('descripcion', descripcion);
    formData.append('id_categoria', id_categoria);
    formData.append('precio', precio);
    formData.append('activo', activo);
    formData.append('imagen', imagen);

    try {
        const resp = await fetch('platillos.php', { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            showModal(result.message, true);
            closeModalPlatillo();
            refreshData();
        } else {
            showModal(result.message, false);
            if (result.errores) {
                for (let field in result.errores) {
                    const errorSpan = document.getElementById(`edit-error-${field}`);
                    if (errorSpan) errorSpan.textContent = result.errores[field];
                }
            }
        }
    } catch (error) {
        showModal('Error al guardar', false);
    }
});

const modalCategorias = document.getElementById('modalCategorias');
const editarCategoriasLink = document.getElementById('editarCategoriasLink');
editarCategoriasLink.addEventListener('click', (e) => {
    e.preventDefault();
    cargarModalCategorias();
    modalCategorias.classList.add('show');
});

function cargarModalCategorias() {
    const container = document.getElementById('categoriasModalList');
    if (!container) return;
    if (categoriasData.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay categorías</p>';
        return;
    }
    let html = '<ul class="categorias-modal-list">';
    categoriasData.forEach(cat => {
        html += `
            <li class="categoria-modal-item" data-id="${cat.id_categoria}">
                <input type="text" class="categoria-edit-input" value="${escapeHtml(cat.nombre)}" data-original="${escapeHtml(cat.nombre)}">
                <div class="categoria-actions">
                    <button class="btn-save-categoria" data-id="${cat.id_categoria}">Guardar</button>
                    <button class="btn-delete-categoria-modal" data-id="${cat.id_categoria}" data-nombre="${escapeHtml(cat.nombre)}" data-platillos="${cat.total_platillos}">Eliminar</button>
                </div>
            </li>
        `;
    });
    html += '</ul>';
    container.innerHTML = html;

    document.querySelectorAll('.btn-save-categoria').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const input = btn.closest('.categoria-modal-item').querySelector('.categoria-edit-input');
            const nuevoNombre = input.value.trim();
            const originalNombre = input.dataset.original;
            if (nuevoNombre === originalNombre) return;
            if (!nuevoNombre || nuevoNombre.length < 3) {
                showModal('El nombre debe tener al menos 3 caracteres', false);
                return;
            }
            const formData = new FormData();
            formData.append('ajax_action', 'editar_categoria');
            formData.append('id_categoria', id);
            formData.append('nombre', nuevoNombre);
            try {
                const resp = await fetch('platillos.php', { method: 'POST', body: formData });
                const result = await resp.json();
                if (result.success) {
                    showModal(result.message, true);
                    refreshData();
                    modalCategorias.classList.remove('show');
                } else {
                    showModal(result.message, false);
                }
            } catch (error) {
                showModal('Error al conectar', false);
            }
        });
    });

    document.querySelectorAll('.btn-delete-categoria-modal').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const nombre = btn.dataset.nombre;
            const totalPlatillos = parseInt(btn.dataset.platillos);
            if (totalPlatillos > 0) {
                showModal(`No se puede eliminar la categoría "${nombre}" porque tiene ${totalPlatillos} platillo(s). Elimina primero los platillos.`, false);
                return;
            }
            if (!confirm(`¿Eliminar categoría "${nombre}"?`)) return;
            const formData = new FormData();
            formData.append('ajax_action', 'eliminar_categoria');
            formData.append('id_categoria', id);
            try {
                const resp = await fetch('platillos.php', { method: 'POST', body: formData });
                const result = await resp.json();
                if (result.success) {
                    showModal(result.message, true);
                    refreshData();
                    modalCategorias.classList.remove('show');
                } else {
                    showModal(result.message, false);
                }
            } catch (error) {
                showModal('Error al conectar', false);
            }
        });
    });
}

function closeModalCategorias() {
    modalCategorias.classList.remove('show');
}

const formCategoria = document.getElementById('formCategoria');
formCategoria?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const nombre = document.getElementById('nombre_categoria').value.trim();
    const errorDiv = document.getElementById('error-categoria');
    if (!nombre) {
        errorDiv.textContent = 'El nombre es obligatorio';
        errorDiv.classList.add('show');
        return;
    }
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
    errorDiv.classList.remove('show');
    const formData = new FormData();
    formData.append('ajax_action', 'crear_categoria');
    formData.append('nombre_categoria', nombre);
    try {
        const resp = await fetch('platillos.php', { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            showModal(result.message, true);
            document.getElementById('nombre_categoria').value = '';
            refreshData();
            await updateCategoriaSelect();
        } else {
            errorDiv.textContent = result.message;
            errorDiv.classList.add('show');
        }
    } catch (error) {
        showModal('Error al conectar', false);
    }
});

const formPlatillo = document.getElementById('formPlatillo');
formPlatillo?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const nombre = document.getElementById('platillo_nombre').value.trim();
    const id_categoria = document.getElementById('platillo_categoria').value;
    const precio = document.getElementById('platillo_precio').value;
    const descripcion = document.getElementById('platillo_descripcion').value;
    const imagen = document.getElementById('platillo_imagen').value.trim();

    document.querySelectorAll('#formPlatillo .error-message').forEach(el => el.classList.remove('show'));
    let hasError = false;

    if (!nombre) {
        document.getElementById('error-platillo-nombre').textContent = 'El nombre es obligatorio';
        document.getElementById('error-platillo-nombre').classList.add('show');
        hasError = true;
    } else if (nombre.length < 3) {
        document.getElementById('error-platillo-nombre').textContent = 'Mínimo 3 caracteres';
        document.getElementById('error-platillo-nombre').classList.add('show');
        hasError = true;
    }
    if (!id_categoria) {
        document.getElementById('error-platillo-categoria').textContent = 'Selecciona una categoría';
        document.getElementById('error-platillo-categoria').classList.add('show');
        hasError = true;
    }
    const precioNum = parseFloat(precio);
    if (isNaN(precioNum) || precioNum <= 0) {
        document.getElementById('error-platillo-precio').textContent = 'Precio válido mayor a 0';
        document.getElementById('error-platillo-precio').classList.add('show');
        hasError = true;
    }
    if (descripcion.length > 500) {
        document.getElementById('error-platillo-descripcion').textContent = 'Máximo 500 caracteres';
        document.getElementById('error-platillo-descripcion').classList.add('show');
        hasError = true;
    }
    if (imagen.length > 255) {
        document.getElementById('error-platillo-imagen').textContent = 'Máximo 255 caracteres';
        document.getElementById('error-platillo-imagen').classList.add('show');
        hasError = true;
    }
    if (hasError) return;

    const formData = new FormData();
    formData.append('ajax_action', 'crear_platillo');
    formData.append('nombre', nombre);
    formData.append('id_categoria', id_categoria);
    formData.append('precio', precioNum);
    formData.append('descripcion', descripcion);
    formData.append('imagen', imagen);

    try {
        const resp = await fetch('platillos.php', { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            showModal(result.message, true);
            formPlatillo.reset();
            document.getElementById('preview_platillo_imagen').innerHTML = '';
            refreshData();
        } else {
            if (result.errores) {
                for (let field in result.errores) {
                    const errorSpan = document.getElementById(`error-platillo-${field}`);
                    if (errorSpan) {
                        errorSpan.textContent = result.errores[field];
                        errorSpan.classList.add('show');
                    }
                }
            } else {
                showModal(result.message, false);
            }
        }
    } catch (error) {
        showModal('Error al conectar', false);
    }
});

async function updateCategoriaSelect() {
    try {
        const resp = await fetch('platillos.php?ajax_list=1');
        const data = await resp.json();
        const select = document.getElementById('platillo_categoria');
        if (!select) return;
        let options = '<option value="">Selecciona categoría</option>';
        data.categorias.forEach(cat => {
            options += `<option value="${cat.id_categoria}">${escapeHtml(cat.nombre)}</option>`;
        });
        select.innerHTML = options;
    } catch (e) {}
}

const filtroSelect = document.getElementById('filtroCategoria');
if (filtroSelect) {
    filtroSelect.addEventListener('change', (e) => {
        filtroCategoria = e.target.value;
        renderPlatillos();
    });
}

// Preview de imagen en tiempo real para edición
document.getElementById('edit_imagen')?.addEventListener('input', function() {
    const previewDiv = document.getElementById('edit_imagen_preview');
    const nombre = this.value.trim();
    if (nombre) {
        previewDiv.innerHTML = `<img src="${RUTA_IMG + escapeHtml(nombre)}" class="imagen-preview-img" alt="Preview" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\"error-preview\">Error al cargar imagen</span>';">`;
    } else {
        previewDiv.innerHTML = '';
    }
});

// Preview para el formulario de crear platillo
document.getElementById('platillo_imagen')?.addEventListener('input', function() {
    const previewDiv = document.getElementById('preview_platillo_imagen');
    const nombre = this.value.trim();
    if (nombre) {
        previewDiv.innerHTML = `<img src="${RUTA_IMG + escapeHtml(nombre)}" class="imagen-preview-img" alt="Preview" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\"error-preview\">Error al cargar imagen</span>';">`;
    } else {
        previewDiv.innerHTML = '';
    }
});

// ==================== FUNCIONES PARA EL SELECTOR DE IMÁGENES ====================
function abrirSelectorImagen(idCampo) {
    campoImagenDestino = idCampo;
    cargarGaleria();
    document.getElementById('modalImagenes').classList.add('show');
}

function cerrarModalImagenes() {
    document.getElementById('modalImagenes').classList.remove('show');
    campoImagenDestino = null;
}

async function cargarGaleria() {
    const contenedor = document.getElementById('galeriaImagenes');
    contenedor.innerHTML = '<p>Cargando imágenes...</p>';
    try {
        const resp = await fetch('platillos.php?ajax_list_images=1');
        const imagenes = await resp.json();
        if (imagenes.length === 0) {
            contenedor.innerHTML = '<p>No hay imágenes en la carpeta. Sube una nueva arriba.</p>';
            return;
        }
        let html = '';
        imagenes.forEach(img => {
            html += `
                <div class="imagen-galeria-item" style="text-align:center; cursor:pointer; padding: 8px; border-radius: 8px; transition: background 0.2s;" 
                     onclick="seleccionarImagen('${escapeHtml(img.nombre)}')"
                     onmouseover="this.style.backgroundColor='#f0f0f0'" 
                     onmouseout="this.style.backgroundColor='transparent'">
                    <img src="${escapeHtml(img.ruta)}" style="width:100px; height:100px; object-fit:cover; border-radius: 8px; border: 1px solid #ddd;">
                    <div style="font-size: 11px; margin-top: 5px; word-break: break-all;">${escapeHtml(img.nombre)}</div>
                </div>
            `;
        });
        contenedor.innerHTML = html;
    } catch (error) {
        contenedor.innerHTML = '<p style="color:red;">Error al cargar imágenes</p>';
    }
}

function seleccionarImagen(nombreArchivo) {
    if (campoImagenDestino) {
        const input = document.getElementById(campoImagenDestino);
        if (input) {
            input.value = nombreArchivo;
            input.dispatchEvent(new Event('input'));
        }
        cerrarModalImagenes();
    }
}

// Subir imagen desde el modal
document.getElementById('formSubirImagen')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('ajax_action', 'subir_imagen');
    const mensajeDiv = document.getElementById('mensajeSubida');
    mensajeDiv.innerHTML = '<span style="color:blue;">Subiendo...</span>';
    try {
        const resp = await fetch('platillos.php', { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            mensajeDiv.innerHTML = '<span style="color:green;">✓ Imagen subida correctamente</span>';
            e.target.reset();
            cargarGaleria();
            setTimeout(() => mensajeDiv.innerHTML = '', 3000);
        } else {
            mensajeDiv.innerHTML = `<span style="color:red;">✗ Error: ${result.message}</span>`;
        }
    } catch (error) {
        mensajeDiv.innerHTML = '<span style="color:red;">✗ Error de conexión</span>';
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

refreshData();
</script>
</body>
</html>