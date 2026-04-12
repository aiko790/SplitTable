<?php
require_once __DIR__ . "/../config/cocina_session.php";

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'cocinero') {
    header("Location: ../index.html");
    exit();
}

$nombre_cocinero = $_SESSION['nombre_cocinero'] ?? 'Cocinero';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina | Panel de pedidos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #e9ecef);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: #2d3436;
            border-bottom: 1px solid #3d4446;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .header-subtitle {
            font-size: 0.875rem;
            color: #9aa8b0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #3d4446;
            color: #e9ecef;
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s ease;
            border: 1px solid #4a5356;
        }

        .btn-back:hover {
            background: #4a5356;
            color: #ffffff;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
        }

        /* Grid de pedidos */
        .orders-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Card de pedido */
        .order-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f4;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .order-card:hover {
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            padding: 20px 28px;
            border-bottom: 1px solid #eef2f4;
            background: #fafcfd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #2d3436;
            margin: 0;
        }

        .card-header p {
            font-size: 0.75rem;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .badge {
            background: #f0f2f4;
            color: #2d3436;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Tabla de platillos */
        .table-wrapper {
            overflow-x: auto;
            padding: 0 28px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .data-table th {
            text-align: left;
            padding: 14px 8px;
            background: #ffffff;
            font-weight: 600;
            color: #5d6f7f;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #eef2f4;
        }

        .data-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #f0f4f7;
            vertical-align: top;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .comentario {
            color: #7f8c8d;
            font-style: italic;
            font-size: 0.75rem;
            max-width: 200px;
        }

        /* Acciones del pedido */
        .card-actions {
            padding: 16px 28px 24px;
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid #eef2f4;
            background: #fafcfd;
        }

        .btn-listo {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 12px;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-listo:hover {
            background: #2ecc71;
        }

        /* Estado vacío */
        .empty-state {
            background: #ffffff;
            border-radius: 20px;
            padding: 48px 32px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border: 1px solid #eef2f4;
        }

        .empty-state p {
            color: #7f8c8d;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .empty-state small {
            color: #bdc3c7;
            font-size: 0.75rem;
        }

        /* Actualización automática */
        .auto-refresh {
            text-align: right;
            font-size: 0.6875rem;
            color: #95a5a6;
            margin-top: 16px;
            padding: 8px 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .table-wrapper {
                padding: 0 16px;
            }
            .data-table th, .data-table td {
                padding: 10px 6px;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-content">
        <div>
            <div class="header-title">🍳 Panel de Cocina</div>
            <div class="header-subtitle">Pedidos en preparación</div>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <span style="color: #e9ecef; font-size: 0.875rem;">👨‍🍳 <?= htmlspecialchars($nombre_cocinero) ?></span>
            <a href="../auth/logout.php" class="btn-back">Salir</a>
        </div>
    </div>
</header>

<main class="container">
    <div id="orders-container" class="orders-grid">
        <!-- Aquí se cargarán los pedidos dinámicamente -->
        <div class="empty-state">
            <p>Cargando pedidos...</p>
        </div>
    </div>
    <div class="auto-refresh">
        Actualización automática cada 5 segundos
    </div>
</main>

<script>
    // Función para formatear fecha
    function formatearFecha(fecha) {
        if (!fecha) return '-';
        const date = new Date(fecha);
        return date.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    // Cargar pedidos desde el servidor
    async function cargarPedidos() {
        try {
            const response = await fetch('api/pedidos_cocina.php');
            const data = await response.json();
            renderPedidos(data);
        } catch (error) {
            console.error('Error cargando pedidos:', error);
            document.getElementById('orders-container').innerHTML = `
                <div class="empty-state">
                    <p>Error al cargar los pedidos</p>
                    <small>Intente recargar la página</small>
                </div>
            `;
        }
    }

    // Renderizar lista de pedidos
    function renderPedidos(pedidos) {
        const container = document.getElementById('orders-container');
        
        if (!pedidos.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>🍽️ No hay pedidos en preparación</p>
                    <small>Los pedidos aparecerán aquí cuando los meseros los envíen a cocina</small>
                </div>
            `;
            return;
        }

        let html = '';
        for (const pedido of pedidos) {
            html += `
                <div class="order-card" data-id-pedido="${pedido.id_pedido}">
                    <div class="card-header">
                        <div>
                            <h3>Mesa: ${escapeHtml(pedido.nombre_mesa)}</h3>
                            <p>Pedido #${pedido.id_pedido} · ${formatearFecha(pedido.fecha_creacion)}</p>
                        </div>
                        <span class="badge">En preparación</span>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Platillo</th>
                                    <th>Categoría</th>
                                    <th>Cant.</th>
                                    <th>Comentario</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            for (const item of pedido.items) {
                html += `
                    <tr>
                        <td>${escapeHtml(item.cliente)}</td>
                        <td>${escapeHtml(item.platillo)}</td>
                        <td>${escapeHtml(item.categoria)}</td>
                        <td>${item.cantidad}</td>
                        <td class="comentario">${item.comentario ? escapeHtml(item.comentario) : '—'}</td>
                    </tr>
                `;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    <div class="card-actions">
                        <button class="btn-listo" onclick="marcarListo(${pedido.id_pedido})">✓ Marcar como listo</button>
                    </div>
                </div>
            `;
        }
        container.innerHTML = html;
    }

    // Marcar pedido como listo (envío POST)
    async function marcarListo(idPedido) {
        try {
            const formData = new FormData();
            formData.append('id_pedido', idPedido);
            
            const response = await fetch('pedido_listo.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                // Recargar la lista después de marcar
                cargarPedidos();
            } else {
                alert('Error al marcar el pedido como listo');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error de conexión');
        }
    }

    // Escapar HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Cargar al inicio y cada 5 segundos
    cargarPedidos();
    setInterval(cargarPedidos, 5000);
</script>

</body>
</html>