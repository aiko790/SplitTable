<?php
require_once __DIR__ . "/../../config/mesero_session.php"; // carga config.php y sesión

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit;
}

$id_mesa = isset($_POST['id_mesa']) ? (int)$_POST['id_mesa'] : 0;
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

if (!$id_mesa || $nombre === '') {
    header("Location: ../dashboard.php?id_mesa=" . $id_mesa);
    exit;
}

$stmt = $conexion->prepare("INSERT INTO clientes_mesa (id_mesa, nombre) VALUES (?, ?)");
$stmt->bind_param("is", $id_mesa, $nombre);
$stmt->execute();
$stmt->close();

header("Location: ../dashboard.php?id_mesa=" . $id_mesa);
exit;