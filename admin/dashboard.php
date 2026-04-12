<?php
require_once "../config/admin_session.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Administrador</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>

<body>

    <div class="header">
        <h2>Panel del Administrador</h2>
        <a href="../auth/logout.php" class="logout">Cerrar sesión</a>
    </div>

    <div class="container">
        <div class="card">
            <h3>Gestión del sistema</h3>

            <a href="meseros.php">Meseros</a>
            <a href="mesas.php">Mesas</a>
            <a href="platillos.php">Platillos</a>
            <a href="cocineros.php">Cocineros</a>
        </div>
    </div>

</body>
</html>