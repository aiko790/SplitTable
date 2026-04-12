// ==================== MODAL DE RESUMEN (enviar a cocina / entregar) ====================
function inicializarModalResumen() {
    modalResumenPedido = document.getElementById('modalResumenPedido');
    if (!modalResumenPedido) return;
    const btnCancelar = document.getElementById('btnCancelarResumen');
    const btnConfirmar = document.getElementById('btnConfirmarResumen');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            modalResumenPedido.classList.add('hidden');
            pendingResumenAction = null;
        });
    }
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', () => {
            modalResumenPedido.classList.add('hidden');
            if (pendingResumenAction) {
                pendingResumenAction();
                pendingResumenAction = null;
            }
        });
    }
}

function mostrarResumenEnvioCocina() {
    if (!currentSelectedMesa) return;
    const pedidosCapturados = currentPedidos.capturado;
    if (pedidosCapturados.length === 0) {
        mostrarModalInformativo('Aviso', 'No hay pedidos capturados para enviar a cocina', false);
        return;
    }
    let resumenHtml = '';
    let totalGeneral = 0;
    const clientesMap = new Map();
    pedidosCapturados.forEach(pedido => {
        pedido.ordenes.forEach(orden => {
            const clienteId = orden.id_cliente;
            if (!clientesMap.has(clienteId)) {
                clientesMap.set(clienteId, { nombre: orden.cliente_nombre, items: [] });
            }
            clientesMap.get(clienteId).items.push({
                nombre: orden.platillo_nombre,
                cantidad: orden.cantidad,
                subtotal: parseFloat(orden.subtotal)
            });
            totalGeneral += parseFloat(orden.subtotal);
        });
    });
    for (const [clienteId, cliente] of clientesMap) {
        resumenHtml += `<div class="cliente-group" style="margin-bottom:12px;">`;
        resumenHtml += `<div class="cliente-header">${escapeHtml(cliente.nombre)}</div>`;
        cliente.items.forEach(item => {
            resumenHtml += `
                <div class="pedido-item">
                    <div class="pedido-item-info">
                        <span class="pedido-item-cantidad">${item.cantidad}x</span>
                        <span class="pedido-item-nombre">${escapeHtml(item.nombre)}</span>
                        <span class="pedido-item-precio">$${item.subtotal.toFixed(2)}</span>
                    </div>
                </div>
            `;
        });
        resumenHtml += `</div>`;
    }
    document.getElementById('resumenPedidoTitulo').innerText = 'Resumen del pedido a enviar a cocina';
    document.getElementById('resumenPedidoContent').innerHTML = resumenHtml || '<div class="hint">No hay platillos</div>';
    document.getElementById('resumenPedidoTotal').innerText = '$' + totalGeneral.toFixed(2);
    modalResumenPedido.classList.remove('hidden');
    pendingResumenAction = () => mandarACocina();
}

function mostrarResumenEntregarPedido() {
    const pedidosListos = currentPedidos.listo;
    if (pedidosListos.length === 0) {
        mostrarModalInformativo('Aviso', 'No hay pedidos listos para entregar', false);
        return;
    }
    let resumenHtml = '';
    let totalGeneral = 0;
    const clientesMap = new Map();
    pedidosListos.forEach(pedido => {
        pedido.ordenes.forEach(orden => {
            const clienteId = orden.id_cliente;
            if (!clientesMap.has(clienteId)) {
                clientesMap.set(clienteId, { nombre: orden.cliente_nombre, items: [] });
            }
            clientesMap.get(clienteId).items.push({
                nombre: orden.platillo_nombre,
                cantidad: orden.cantidad,
                subtotal: parseFloat(orden.subtotal)
            });
            totalGeneral += parseFloat(orden.subtotal);
        });
    });
    for (const [clienteId, cliente] of clientesMap) {
        resumenHtml += `<div class="cliente-group" style="margin-bottom:12px;">`;
        resumenHtml += `<div class="cliente-header">${escapeHtml(cliente.nombre)}</div>`;
        cliente.items.forEach(item => {
            resumenHtml += `
                <div class="pedido-item">
                    <div class="pedido-item-info">
                        <span class="pedido-item-cantidad">${item.cantidad}x</span>
                        <span class="pedido-item-nombre">${escapeHtml(item.nombre)}</span>
                        <span class="pedido-item-precio">$${item.subtotal.toFixed(2)}</span>
                    </div>
                </div>
            `;
        });
        resumenHtml += `</div>`;
    }
    document.getElementById('resumenPedidoTitulo').innerText = 'Resumen del pedido a entregar';
    document.getElementById('resumenPedidoContent').innerHTML = resumenHtml || '<div class="hint">No hay platillos</div>';
    document.getElementById('resumenPedidoTotal').innerText = '$' + totalGeneral.toFixed(2);
    modalResumenPedido.classList.remove('hidden');
    pendingResumenAction = () => entregarPedido();
}

function mandarACocina() {
    if (!currentSelectedMesa) return;
    fetch('api/enviar_cocina.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_mesa: currentSelectedMesa })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            mostrarModalInformativo('Éxito', 'Pedido enviado a cocina', true);
            cargarPedidos(currentSelectedMesa);
            // CAMBIO: forzar actualización del botón después de recargar
            setTimeout(() => actualizarBotonAccion(), 100);
        } else {
            mostrarModalInformativo('Error', data.msg || 'No se pudo enviar a cocina', false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarModalErrorConexion('Error al enviar a cocina. Verifica tu conexión.');
    });
}

function entregarPedido() {
    const pedidos = currentPedidos.listo;
    if (pedidos.length === 0) return;
    const idPedido = pedidos[0].id_pedido;
    const formData = new FormData();
    formData.append('id_pedido', idPedido);
    formData.append('id_mesa', currentSelectedMesa);
    fetch('acciones/entregar_pedido.php', { method: 'POST', body: formData })
        .then(() => {
            mostrarModalInformativo('Éxito', 'Pedido entregado', true);
            cargarPedidos(currentSelectedMesa);
            // CAMBIO: forzar actualización del botón después de recargar
            setTimeout(() => actualizarBotonAccion(), 100);
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('Error al entregar pedido.');
        });
}