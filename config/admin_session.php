<?php
require_once __DIR__ . "/config.php";

/* verificar que sea admin */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.html");
    exit();
}