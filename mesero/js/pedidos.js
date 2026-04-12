// ==================== GESTIÓN DE PEDIDOS ====================
function cambiarTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-text').forEach(t => {
        if (t.getAttribute('data-tab') === tab) t.classList.add('active');
        else t.classList.remove('active');
    });
    document.getElementById('tabCapturado').classList.toggle('hidden', tab !== 'capturado');
    document.getElementById('tabListo').classList.toggle('hidden', tab !== 'listo');
    document.getElementById('tabEntregado').classList.toggle('hidden', tab !== 'entregado');
    
    const btnAccion = document.getElementById('btnAccionPedido');
    if (tab === 'capturado') {
        btnAccion.textContent = 'Mandar a cocina';
        btnAccion.style.display = 'block';
    } else if (tab === 'listo') {
        btnAccion.textContent = 'Entregar pedido';
        btnAccion.style.display = 'block';
    } else {
        btnAccion.style.display = 'none';
    }
    renderPedidos();
    actualizarTotales();
    actualizarBotonAccion();
}

function cargarPedidos(idMesa) {
    fetch('api/pedidos.php?id_mesa=' + idMesa)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentPedidos.capturado = data.pedidos.filter(p => p.estado === 'capturado');
                currentPedidos.listo = data.pedidos.filter(p => p.estado === 'listo');
                currentPedidos.entregado = data.pedidos.filter(p => p.estado === 'entregado');
                renderPedidos();
                actualizarTotales();
                actualizarBotonAccion();
                actualizarTotalesClientes();
                actualizarResumenClienteEnModal();
                // 👇 NUEVO: Actualiza el botón de cerrar cuenta
                if (typeof verificarYBotonCerrar === 'function') {
                    verificarYBotonCerrar(idMesa);
                }
            } else {
                mostrarModalInformativo('Error', data.msg || 'Error al cargar pedidos', false);
                actualizarBotonAccion();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('No se pudieron cargar los pedidos.');
            actualizarBotonAccion();
        });
}

function renderPedidos() {
    let pedidos = [];
    if (currentTab === 'capturado') pedidos = currentPedidos.capturado;
    else if (currentTab === 'listo') pedidos = currentPedidos.listo;
    else pedidos = currentPedidos.entregado;
    
    const container = document.getElementById('tab' + (currentTab === 'capturado' ? 'Capturado' : (currentTab === 'listo' ? 'Listo' : 'Entregado')));
    if (!pedidos.length) {
        container.innerHTML = '<div class="loading">No hay pedidos</div>';
        actualizarBotonAccion();
        return;
    }
    const clientesMap = new Map();
    pedidos.forEach(pedido => {
        pedido.ordenes.forEach(orden => {
            const clienteId = orden.id_cliente;
            if (!clientesMap.has(clienteId)) {
                clientesMap.set(clienteId, { id_cliente: clienteId, nombre: orden.cliente_nombre, items: [] });
            }
            clientesMap.get(clienteId).items.push({
                id_orden: orden.id_orden,
                id_pedido: pedido.id_pedido,
                nombre: orden.platillo_nombre,
                cantidad: orden.cantidad,
                precio_unitario: parseFloat(orden.precio_unitario),
                subtotal: parseFloat(orden.subtotal),
                comentario: orden.comentario || ''
            });
        });
    });
    let html = '';
    const esCapturado = currentTab === 'capturado';
    for (const cliente of clientesMap.values()) {
        html += '<div class="cliente-group" data-cliente-id="' + cliente.id_cliente + '">';
        html += '<div class="cliente-header">' + escapeHtml(cliente.nombre) + '</div>';
        cliente.items.forEach(item => {
            html += `
                <div class="pedido-item" data-id-orden="${item.id_orden}" data-id-pedido="${item.id_pedido}">
                    <div class="pedido-item-info">
                        <span class="pedido-item-cantidad">${item.cantidad}x</span>
                        <span class="pedido-item-nombre">${escapeHtml(item.nombre)}</span>
                        <span class="pedido-item-precio">$${item.subtotal.toFixed(2)}</span>
                    </div>
                    ${esCapturado ? `<div class="pedido-item-acciones"><button class="btn-dots" data-id-orden="${item.id_orden}" data-id-pedido="${item.id_pedido}" data-nombre="${escapeHtml(item.nombre)}" data-cantidad="${item.cantidad}" data-comentario="${escapeHtml(item.comentario)}" data-precio="${item.precio_unitario}" title="Editar / Eliminar">...</button></div>` : ''}
                </div>
            `;
            if (item.comentario) html += `<div class="pedido-item-comentario">${escapeHtml(item.comentario)}</div>`;
        });
        html += '</div>';
    }
    container.innerHTML = html;
    if (esCapturado) {
        document.querySelectorAll('.btn-dots').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const idOrden = btn.getAttribute('data-id-orden');
                const idPedido = btn.getAttribute('data-id-pedido');
                const nombre = btn.getAttribute('data-nombre');
                const cantidad = parseInt(btn.getAttribute('data-cantidad'));
                const comentario = btn.getAttribute('data-comentario') || '';
                const precio = parseFloat(btn.getAttribute('data-precio'));
                abrirModalEdicion(idOrden, idPedido, nombre, cantidad, comentario, precio);
            });
        });
    }
    actualizarBotonAccion();
}

function actualizarTotales() {
    let pedidosActual = [];
    if (currentTab === 'capturado') pedidosActual = currentPedidos.capturado;
    else if (currentTab === 'listo') pedidosActual = currentPedidos.listo;
    else pedidosActual = currentPedidos.entregado;
    
    let subtotal = 0;
    pedidosActual.forEach(pedido => {
        pedido.ordenes.forEach(orden => { subtotal += parseFloat(orden.subtotal); });
    });
    document.getElementById('subtotalGeneral').textContent = '$' + subtotal.toFixed(2);
    
    let totalGeneral = 0;
    [...currentPedidos.capturado, ...currentPedidos.listo, ...currentPedidos.entregado].forEach(pedido => {
        pedido.ordenes.forEach(orden => { totalGeneral += parseFloat(orden.subtotal); });
    });
    document.getElementById('totalGeneral').textContent = '$' + totalGeneral.toFixed(2);
}

function actualizarBotonAccion() {
    const btn = document.getElementById('btnAccionPedido');
    if (!btn) return;
    
    if (currentTab === 'entregado') {
        btn.style.display = 'none';
        return;
    }
    
    btn.style.display = 'block';
    const pedidos = currentTab === 'capturado' ? currentPedidos.capturado : currentPedidos.listo;
    btn.disabled = pedidos.length === 0;
}

// Nueva función para procesar la acción del botón (Mandar a cocina / Entregar pedido)
function procesarAccionPedido() {
        console.trace('❌ procesarAccionPedido ejecutada desde:');

    const btn = document.getElementById('btnAccionPedido');
    if (!btn || btn.disabled) return;
    
    if (currentTab === 'capturado') {
        // Enviar todos los pedidos capturados de la mesa a cocina
        if (currentPedidos.capturado.length === 0) return;
        
        fetch('api/enviar_cocina.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_mesa: currentSelectedMesa })
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                cargarPedidos(currentSelectedMesa);
                // No es necesario verificar el botón de cerrar aquí (no cambia estado)
            } else {
                mostrarModalInformativo('Error', data.msg || 'Error al enviar a cocina', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('No se pudo enviar a cocina.');
        });
    } 
    else if (currentTab === 'listo') {
        if (currentPedidos.listo.length === 0) return;
        
        fetch('acciones/entregar_pedido.php', {   // Ruta corregida
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_mesa: currentSelectedMesa })
        })
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                cargarPedidos(currentSelectedMesa);
                if (typeof verificarYBotonCerrar === 'function') {
                    verificarYBotonCerrar(currentSelectedMesa);
                }
            } else {
                mostrarModalInformativo('Error', data.msg || 'Error al entregar pedido', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('No se pudo entregar el pedido.');
        });
    }
}