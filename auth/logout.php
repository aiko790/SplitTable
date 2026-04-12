<?php
session_start();

// Destruir la sesión por completo
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirigir al index con el parámetro logout
header("Location: ../index.html?logout=1");
exit;