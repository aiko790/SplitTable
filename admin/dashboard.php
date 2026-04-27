<?php
require_once "../config/admin_session.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

    <h1>Admin Console</h1>

    <div class="container">
        <div class="section-title">Gestión de Infraestructura</div>

        <div class="grid">
            <a href="meseros.php" class="card">
                <i class="fas fa-users"></i>
                <span class="label">Meseros</span>
            </a>

            <a href="cocineros.php" class="card">
                <span class="emoji">👨‍🍳</span>
                <span class="label">Cocineros</span>
            </a>

            <a href="mesas.php" class="card">
                <i class="fas fa-chair"></i>
                <span class="label">Mesas</span>
            </a>

            <a href="platillos.php" class="card">
                <i class="fas fa-utensils"></i>
                <span class="label">Platillos</span>
            </a>            

            <a href="historial.php" class="card">
                <i class="fas fa-chart-line"></i>
                <span class="label">Historial</span>
            </a>
        </div>

        <div class="footer">
            <a href="../auth/logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

</body>
</html>