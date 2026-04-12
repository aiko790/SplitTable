// ==================== GESTIÓN DE CLIENTES ====================
function cargarClientes(idMesa) {
    fetch('api/cliente_mesa.php?id_mesa=' + idMesa)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                const clientesList = document.getElementById('clients-list');
                if (!clientesList) return;
                if (data.clientes.length === 0) {
                    clientesList.innerHTML = '<li class="hint">Agrega un cliente para comenzar</li>';
                } else {
                    let html = '';
                    data.clientes.forEach(c => {
                        html += `
                            <li class="cliente-item" data-client="${c.id_cliente}">
                                <div class="cliente-left">
                                    <span class="cliente-nombre">${escapeHtml(c.nombre)}</span>
                                </div>
                                <div class="cliente-right">
                                    <span class="cliente-total" data-total="0">$0.00</span>
                                    <button type="button" class="btn small btn-ver-pedidos" data-client-id="${c.id_cliente}" data-client-name="${escapeHtml(c.nombre)}">Pedidos</button>
                                </div>
                            </li>
                        `;
                    });
                    clientesList.innerHTML = html;
                }
                initSeleccionCliente();
                actualizarTotalesClientes();
            } else {
                mostrarModalInformativo('Error', data.msg || 'Error al cargar clientes', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('No se pudieron cargar los clientes.');
        });
}

function actualizarTotalesClientes() {
    if (!currentSelectedMesa) return;
    fetch(`api/pedidos.php?id_mesa=${currentSelectedMesa}`)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                const totalesPorCliente = {};
                data.pedidos.forEach(pedido => {
                    pedido.ordenes.forEach(orden => {
                        const clienteId = orden.id_cliente;
                        if (!totalesPorCliente[clienteId]) totalesPorCliente[clienteId] = 0;
                        totalesPorCliente[clienteId] += parseFloat(orden.subtotal);
                    });
                });
                document.querySelectorAll('.cliente-item').forEach(item => {
                    const clienteId = item.getAttribute('data-client');
                    const totalSpan = item.querySelector('.cliente-total');
                    if (totalSpan && totalesPorCliente[clienteId]) {
                        totalSpan.innerText = '$' + totalesPorCliente[clienteId].toFixed(2);
                    } else if (totalSpan) {
                        totalSpan.innerText = '$0.00';
                    }
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

function initSeleccionCliente() {
    const clientesList = document.getElementById('clients-list');
    if (!clientesList) return;
    
    clientesList.addEventListener('click', (e) => {
        const btnPedidos = e.target.closest('.btn-ver-pedidos');
        if (btnPedidos) {
            const clienteId = btnPedidos.getAttribute('data-client-id');
            const clienteNombre = btnPedidos.getAttribute('data-client-name');
            if (clienteId) {
                mostrarPedidosCliente(clienteId, clienteNombre);
            }
            return;
        }
        
        const item = e.target.closest('.cliente-item');
        if (!item) return;
        const clienteId = item.getAttribute('data-client');
        if (clienteId) {
            document.querySelectorAll('.cliente-item').forEach(c => c.classList.remove('cliente-activo'));
            item.classList.add('cliente-activo');
            currentClienteSeleccionado = clienteId;
        }
    });
}

function obtenerNombreCliente(idCliente) {
    const item = document.querySelector(`.cliente-item[data-client="${idCliente}"] .cliente-nombre`);
    return item ? item.innerText : 'Cliente';
}