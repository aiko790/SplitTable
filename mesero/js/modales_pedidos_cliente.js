// ==================== MODAL PEDIDOS DEL CLIENTE ====================
function inicializarModalClientOrders() {
    modalClientOrders = document.getElementById('modalClientOrders');
    if (!modalClientOrders) return;
    const btnClose = document.getElementById('btnCloseClientOrders');
    if (btnClose) {
        btnClose.addEventListener('click', () => {
            modalClientOrders.classList.add('hidden');
        });
    }
}

function mostrarPedidosCliente(clienteId, clienteNombre) {
    if (!currentSelectedMesa) {
        mostrarModalInformativo('Atención', 'No hay una mesa seleccionada', false);
        return;
    }
    document.getElementById('clientOrdersName').innerText = clienteNombre;
    const mesaNombre = document.querySelector('.mesa-card.mesa-selected .mesa-nombre')?.innerText || currentSelectedMesa;
    document.getElementById('clientOrdersMesa').innerText = mesaNombre;
    const content = document.getElementById('clientOrdersContent');
    content.innerHTML = '<div class="loading">Cargando pedidos...</div>';
    modalClientOrders.classList.remove('hidden');
    
    fetch(`api/pedidos.php?id_mesa=${currentSelectedMesa}`)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                let ordenes = [];
                data.pedidos.forEach(pedido => {
                    pedido.ordenes.forEach(orden => {
                        if (orden.id_cliente == clienteId) {
                            ordenes.push({
                                id_orden: orden.id_orden,
                                id_pedido: pedido.id_pedido,
                                nombre: orden.platillo_nombre,
                                cantidad: orden.cantidad,
                                precio_unitario: parseFloat(orden.precio_unitario),
                                subtotal: parseFloat(orden.subtotal),
                                comentario: orden.comentario || '',
                                estado: pedido.estado
                            });
                        }
                    });
                });
                renderPedidosClientePorEstado(ordenes);
            } else {
                content.innerHTML = '<div class="hint">Error al cargar los pedidos</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="hint">Error de conexión</div>';
        });
}

function renderPedidosClientePorEstado(ordenes) {
    const container = document.getElementById('clientOrdersContent');
    if (ordenes.length === 0) {
        container.innerHTML = '<div class="hint">Este cliente no tiene pedidos en esta mesa</div>';
        document.getElementById('clientOrdersTotal').innerText = '$0.00';
        return;
    }

    // Agrupar por estado
    const ordenesPorEstado = {
        capturado: [],
        en_preparacion: [],
        listo: [],
        entregado: []
    };

    ordenes.forEach(orden => {
        if (ordenesPorEstado[orden.estado]) {
            ordenesPorEstado[orden.estado].push(orden);
        }
    });

    // Definir colores y textos para cada estado
    const estadoInfo = {
        capturado: { texto: '📝 Capturado', clase: 'estado-capturado', icono: '📝' },
        en_preparacion: { texto: '🔪 En preparación', clase: 'estado-preparacion', icono: '🔪' },
        listo: { texto: '✅ Listo', clase: 'estado-listo', icono: '✅' },
        entregado: { texto: '🍽️ Entregado', clase: 'estado-entregado', icono: '🍽️' }
    };

    let html = '';
    let totalGeneral = 0;

    // Generar secciones solo para estados que tienen platillos
    for (const [estadoKey, estadoOrdenes] of Object.entries(ordenesPorEstado)) {
        if (estadoOrdenes.length === 0) continue;

        const info = estadoInfo[estadoKey];
        let subtotalEstado = 0;

        html += `
            <div class="estado-seccion">
                <div class="estado-header ${info.clase}">
                    <span>${info.icono} ${info.texto}</span>
                    <span class="estado-subtotal" data-estado-subtotal>Subtotal: $0.00</span>
                </div>
                <div class="estado-ordenes">
        `;

        estadoOrdenes.forEach(orden => {
            subtotalEstado += orden.subtotal;
            totalGeneral += orden.subtotal;
            html += `
                <div class="orden-cliente-item">
                    <div class="orden-cliente-header">
                        <span class="orden-platillo-nombre">${escapeHtml(orden.nombre)}</span>
                        <span class="orden-platillo-precio">$${orden.precio_unitario.toFixed(2)} c/u</span>
                    </div>
                    <div class="orden-cliente-detalle">
                        <div class="orden-detalle-cantidad"><strong>Cantidad:</strong> ${orden.cantidad}</div>
                        <div class="orden-detalle-subtotal"><strong>Subtotal:</strong> $${orden.subtotal.toFixed(2)}</div>
                        ${orden.comentario ? `<div class="orden-detalle-comentario"><strong>Comentario:</strong> ${escapeHtml(orden.comentario)}</div>` : ''}
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
    document.getElementById('clientOrdersTotal').innerText = '$' + totalGeneral.toFixed(2);

    // Actualizar los subtotales por estado después de inyectar el HTML
    let currentEstadoIndex = 0;
    for (const [estadoKey, estadoOrdenes] of Object.entries(ordenesPorEstado)) {
        if (estadoOrdenes.length === 0) continue;
        const subtotalEstado = estadoOrdenes.reduce((sum, orden) => sum + orden.subtotal, 0);
        const estadoHeaders = document.querySelectorAll('.estado-header');
        if (estadoHeaders[currentEstadoIndex]) {
            const subtotalSpan = estadoHeaders[currentEstadoIndex].querySelector('.estado-subtotal');
            if (subtotalSpan) subtotalSpan.innerText = `Subtotal: $${subtotalEstado.toFixed(2)}`;
        }
        currentEstadoIndex++;
    }
}