<?php
require_once __DIR__ . "/../../config/mesero_session.php";
header('Content-Type: application/json; charset=utf-8');

// Ruta base para imágenes (desde la carpeta mesero, subimos un nivel y luego a img_platillos)
define('RUTA_IMG', '../img_platillos/');

try {
    // Categorías
    $cats = [];
    $res = $conexion->query("SELECT id_categoria, nombre FROM categorias_platillo ORDER BY nombre");
    while ($r = $res->fetch_assoc()) $cats[] = $r;

    // Platillos activos y disponibles (incluye imagen)
    $platillos = [];
    $stmt = $conexion->prepare("SELECT id_platillo, nombre, descripcion, id_categoria, precio, imagen FROM platillos WHERE activo = 1 AND disponible = 1 ORDER BY nombre");
    $stmt->execute();
    $res = $stmt->get_result();
    while ($p = $res->fetch_assoc()) {
        // Si tiene imagen, construir la ruta completa
        if (!empty($p['imagen'])) {
            $p['imagen'] = RUTA_IMG . $p['imagen'];
        }
        $platillos[] = $p;
    }
    $stmt->close();

    echo json_encode(['ok' => true, 'categorias' => $cats, 'platillos' => $platillos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>