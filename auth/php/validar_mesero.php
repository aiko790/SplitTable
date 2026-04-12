<?php
session_start();
require_once __DIR__ . "/../../config/config.php";

header('Content-Type: application/json');

$correo = $_POST['correo'] ?? '';
$password = $_POST['password'] ?? '';

if (!$correo || !$password) {
    echo json_encode([
        'success' => false,
        'message' => 'Todos los campos son obligatorios'
    ]);
    exit();
}

$sql = "SELECT * FROM meseros WHERE correo = ? AND estado = 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $mesero = $resultado->fetch_assoc();
    
    if (password_verify($password, $mesero['password'])) {
        $_SESSION['id_mesero'] = $mesero['id_mesero'];
        $_SESSION['nombre_mesero'] = $mesero['nombre'];
        $_SESSION['rol'] = "mesero";
        
        echo json_encode([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'redirect' => '../mesero/dashboard.php'
        ]);
        exit();
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Correo o contraseña incorrectos'
]);
exit();
?>