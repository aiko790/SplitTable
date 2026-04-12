<?php
require_once __DIR__ . "/config.php";

/* verificar que sea mesero */
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'mesero') {
    header("Location: ../index.html");
    exit();
}