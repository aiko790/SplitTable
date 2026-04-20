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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap');

        :root {
            --bg-color: #000000;
            --container-bg: #0c0c0c;
            --card-inactive: #d1d1d1;
            --card-active: #ffffff;
            --text-main: #ffffff;
            --text-dim: #666666;
            --glow-color: rgba(255, 255, 255, 0.4);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 3rem;
            letter-spacing: -2px;
        }

        .container {
            background-color: var(--container-bg);
            border-radius: 15px;
            padding: 50px;
            width: 85%;
            max-width: 1000px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .section-title {
            color: var(--text-dim);
            font-size: 1.8rem;
            font-weight: 400;
            margin-bottom: 25px;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .card {
            background-color: var(--card-inactive);
            border-radius: 12px;
            aspect-ratio: 1 / 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .card i, .card span.emoji {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }

        .card span.label {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tarjeta Activa (Cocineros) con el efecto Glow de la imagen */
        .card.active {
            background-color: var(--card-active);
            box-shadow: 0 0 35px var(--glow-color);
            transform: scale(1.02);
            color: #000;
        }

        .card:hover:not(.active) {
            background-color: #eee;
            transform: translateY(-5px);
        }

        .footer {
            display: flex;
            justify-content: flex-end;
        }

        .logout-btn {
            background-color: #111;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.85rem;
            padding: 10px 25px;
            border-radius: 8px;
            border: 1px solid #222;
            transition: 0.3s;
        }

        .logout-btn:hover {
            color: #fff;
            background-color: #1a1a1a;
        }

        @media (max-width: 800px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
            h1 { font-size: 2.2rem; }
        }
    </style>
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

            <a href="mesas.php" class="card">
                <i class="fas fa-chair"></i>
                <span class="label">Mesas</span>
            </a>

            <a href="platillos.php" class="card">
                <i class="fas fa-utensils"></i>
                <span class="label">Platillos</span>
            </a>

            <a href="cocineros.php" class="card active">
                <span class="emoji">🧑‍🍳</span>
                <span class="label">Cocineros</span>
            </a>

            <a href="reportes.php" class="card">
                <i class="fas fa-chart-line"></i>
                <span class="label">Reportes</span>
            </a>
        </div>

        <div class="footer">
            <a href="../auth/logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

</body>
</html>