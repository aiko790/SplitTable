<?php
session_start();
require_once __DIR__ . "/../../config/config.php"; // Ajusta la ruta si es necesario

header('Content-Type: application/json');

// Recibir datos del formulario (correo y contraseña)
$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

// Validar que no estén vacíos
if (empty($correo) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Todos los campos son obligatorios'
    ]);
    exit();
}

// Consultar en la tabla cocineros por correo y estado activo (estado = 1)
$sql = "SELECT * FROM cocineros WHERE correo = ? AND estado = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $cocinero = $resultado->fetch_assoc();
    
    // Verificar la contraseña (debe estar hasheada en la BD)
    if (password_verify($password, $cocinero['password'])) {
        // Guardar datos en sesión
        $_SESSION['id_cocinero'] = $cocinero['id_cocinero'];
        $_SESSION['nombre_cocinero'] = $cocinero['nombre'];
        $_SESSION['rol'] = 'cocinero';
        
        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'redirect' => '../cocina/dashboard.php' // Cambia a la ruta real de tu dashboard de cocinero
        ]);
        exit();
    }
}

// Si no se encontró o la contraseña es incorrecta
echo json_encode([
    'success' => false,
    'message' => 'Correo o contraseña incorrectos, o cuenta desactivada'
]);
exit();
?>