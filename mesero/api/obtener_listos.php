<?php
session_start();
require_once __DIR__ . "/../../config/mesero_session.php"; // carga config.php y sesión
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['id_mesero'])) { echo json_encode(['count'=>0]); exit(); }
$id_mesero = (int)$_SESSION['id_mesero'];
$stmt = $conexion->prepare("SELECT COUNT(*) AS cnt FROM pedidos p JOIN mesas m ON p.id_mesa = m.id_mesa WHERE p.estado = 'listo' AND m.id_mesero_actual = ?");
$stmt->bind_param("i",$id_mesero); $stmt->execute(); $c = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0; $stmt->close();
echo json_encode(['count'=>(int)$c]);