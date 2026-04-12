<?php
require_once __DIR__ . "/../config/cocina_session.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'cocinero') {
    header("Location: ../index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$id_pedido = isset($_POST['id_pedido']) ? (int)$_POST['id_pedido'] : 0;

if ($id_pedido <= 0) {
    header("Location: dashboard.php");
    exit();
}

$sql = "
    UPDATE pedidos
    SET estado = 'listo',
        fecha_listo = NOW()
    WHERE id_pedido = ?
      AND estado = 'en_preparacion'
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$stmt->close();

header("Location: dashboard.php");
exit();