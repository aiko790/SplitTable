<?php
require_once "../../config/config.php";

$usuario  = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if($usuario && $password){

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO administradores (usuario, password) VALUES (?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $usuario, $hash);

    if($stmt->execute()){
        echo "Administrador registrado correctamente.<br>";
        echo '<a href="../login_admin.html">Iniciar sesión</a>';
    }else{
        echo "Error al registrar administrador.";
    }

}else{
    echo "Datos incompletos.";
}