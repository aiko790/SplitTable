<?php

/* Iniciar sesión */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Evitar cache */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* Datos de conexión */
$host = "localhost";
$usuario = "root";
$password = "";
$base_datos = "menu_meseros";

/* Crear conexión */
$conexion = new mysqli($host, $usuario, $password, $base_datos);

/* Verificar conexión */
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");