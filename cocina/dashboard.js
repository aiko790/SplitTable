// ==================== PANEL DE COCINA ====================

let ultimoIdPedido = 0;
let pedidoPendienteMarcar = null;
let pedidosVistos = new Set();

// Elementos del modal
const modal = document.getElementById('modalConfirmacion');
const modalTitulo = document.getElementById('modalTitulo');
const modalMensaje = document.getElementById('modalMensaje');
const btnCancelarModal = document.getElementById('btnCancelarModal');
const btnConfirmarModal = document.getElementById('btnConfirmarModal');

// Configurar modal
btnCancelarModal.addEventListener('click', () => {
    modal.classList.add('hidden');
    pedidoPendienteMarcar = null;
});

btnConfirmarModal.addEventListener('click', () => {
    modal.classList.add('hidden');
    if (pedidoPendienteMarcar !== null) {
        marcarListoConfirmado(pedidoPendienteMarcar);
        pedidoPendienteMarcar = null;
    }
});

// Mostrar modal de confirmación
function mostrarConfirmacion(titulo, mensaje, idPedido) {
    modalTitulo.textContent = titulo;
    modalMensaje.textContent = mensaje;
    pedidoPendienteMarcar = idPedido;
    modal.classList.remove('hidden');
}

// Formatear fecha completa
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const date = new Date(fecha);
    return date.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Calcular tiempo transcurrido
function tiempoTranscurrido(fecha) {
    const ahora = new Date();
    const entonces = new Date(fecha);
    const diffMin = Math.floor((ahora - entonces) / 60000);
    
    if (diffMin < 1) return 'Ahora mismo';
    if (diffMin < 60) return `Hace ${diffMin} min`;
    const horas = Math.floor(diffMin / 60);
    const mins = diffMin % 60;
    return `Hace ${horas}h ${mins}m`;
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
        ultimoIdPedido = 0;
        pedidosVistos.clear();
        container.innerHTML = `
            <div class="empty-state">
                <p>🍽️ No hay pedidos en preparación</p>
                <small>Los pedidos aparecerán aquí cuando los meseros los envíen a cocina</small>
            </div>
        `;
        return;
    }

    const idsActuales = new Set(pedidos.map(p => p.id_pedido));
    const nuevosIds = [...idsActuales].filter(id => !pedidosVistos.has(id));
    
    nuevosIds.forEach(id => pedidosVistos.add(id));
    for (let id of pedidosVistos) {
        if (!idsActuales.has(id)) {
            pedidosVistos.delete(id);
        }
    }

    let html = '';
    for (const pedido of pedidos) {
        const esNuevo = nuevosIds.includes(pedido.id_pedido);
        
        html += `
            <div class="order-card" data-id-pedido="${pedido.id_pedido}">
                ${esNuevo ? '<span class="badge-new">🆕 Nuevo</span>' : ''}
                <div class="card-header">
                    <div>
                        <h3>Mesa: ${escapeHtml(pedido.nombre_mesa)}</h3>
                        <p>Pedido #${pedido.id_pedido} · ${tiempoTranscurrido(pedido.fecha_creacion)}</p>
                    </div>
                    <span class="badge-status">En preparación</span>
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
                    <button class="btn-listo" onclick="solicitarMarcarListo(${pedido.id_pedido}, '${escapeHtml(pedido.nombre_mesa)}')">✓ Marcar como listo</button>
                </div>
            </div>
        `;
    }
    container.innerHTML = html;
}

// Solicitar confirmación antes de marcar
function solicitarMarcarListo(idPedido, nombreMesa) {
    mostrarConfirmacion(
        'Marcar pedido como listo',
        `¿Confirmas que el pedido de la ${nombreMesa} está listo para entregar?`,
        idPedido
    );
}

async function marcarListoConfirmado(idPedido) {
    try {
        const formData = new FormData();
        formData.append('id_pedido', idPedido);
        
        const response = await fetch('api/pedido_listo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.ok) {
            pedidosVistos.delete(idPedido);
            cargarPedidos();
        } else {
            alert(data.message || 'Error al marcar el pedido como listo');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
}

// ==================== INICIAR CARGA ====================
cargarPedidos();
setInterval(cargarPedidos, 5000);