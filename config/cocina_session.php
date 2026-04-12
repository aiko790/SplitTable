<?php
require_once __DIR__ . "/config.php";

/* verificar que sea cocinero */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'cocinero') {
    header("Location: ../index.html");
    exit();
}