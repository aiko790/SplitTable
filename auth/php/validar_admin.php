<?php
require_once __DIR__ . "/../../config/config.php";

$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

// Consulta SIN la columna estado
$sql = "SELECT id_admin, password FROM administradores WHERE usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

$success = false;
$message = '';
$redirect = '';

if ($resultado->num_rows === 1) {
    $admin = $resultado->fetch_assoc();
    
    // Verificar contraseña
    if (password_verify($password, $admin['password'])) {
        $_SESSION['rol'] = 'admin';
        $_SESSION['id_admin'] = $admin['id_admin'];
        $_SESSION['usuario'] = $usuario;
        
        $success = true;
        $message = 'Inicio de sesión exitoso';
        $redirect = '../admin/dashboard.php';
    } else {
        $message = 'Credenciales incorrectas';
    }
} else {
    $message = 'Credenciales incorrectas';
}

// Si es AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
    exit;
}

// Fallback tradicional
if ($success) {
    header("Location: $redirect");
} else {
    header("Location: ../login_admin.html?error=1");
}
exit;