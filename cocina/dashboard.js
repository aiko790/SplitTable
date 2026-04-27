// ==================== PANEL DE COCINA (MEJORADO) ====================

let pedidoPendienteMarcar = null;
let mesaPendienteMarcar = null;
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
    mesaPendienteMarcar = null;
});

btnConfirmarModal.addEventListener('click', () => {
    modal.classList.add('hidden');
    if (pedidoPendienteMarcar !== null) {
        marcarListoConfirmado(pedidoPendienteMarcar);
        pedidoPendienteMarcar = null;
    }
    if (mesaPendienteMarcar !== null) {
        marcarMesaListoConfirmado(mesaPendienteMarcar);
        mesaPendienteMarcar = null;
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

// Calcular tiempo transcurrido (devuelve texto y clase de color)
function tiempoTranscurrido(fecha) {
    const ahora = new Date();
    const entonces = new Date(fecha);
    const diffMin = Math.floor((ahora - entonces) / 60000);
    
    if (diffMin < 1) return { texto: 'Ahora', clase: 'verde' };
    if (diffMin < 5) return { texto: `${diffMin} min`, clase: 'verde' };
    if (diffMin < 10) return { texto: `${diffMin} min`, clase: 'naranja' };
    return { texto: `${diffMin} min`, clase: 'rojo' };
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
        
        if (!data.ok) {
            document.getElementById('pedidosArea').innerHTML = `
                <div class="empty-state">
                    <p>Error al cargar los pedidos</p>
                    <small>Intente recargar la página</small>
                </div>`;
            return;
        }
        
        renderPedidos(data.mesas);
        actualizarSidebar(data.mesas);
    } catch (error) {
        console.error('Error cargando pedidos:', error);
        document.getElementById('pedidosArea').innerHTML = `
            <div class="empty-state">
                <p>Error al cargar los pedidos</p>
                <small>Intente recargar la página</small>
            </div>`;
    }
}

// Renderizar lista de pedidos agrupados por mesa
function renderPedidos(mesas) {
    const container = document.getElementById('pedidosArea');
    
    if (!mesas || mesas.length === 0) {
        pedidosVistos.clear();
        container.innerHTML = `
            <div class="empty-state">
                <p>🍽️ No hay pedidos en preparación</p>
                <small>Los pedidos aparecerán aquí cuando los meseros los envíen a cocina</small>
            </div>`;
        return;
    }

    // Detectar nuevos pedidos
    const idsActuales = new Set();
    mesas.forEach(m => m.pedidos.forEach(p => idsActuales.add(p.id_pedido)));
    const nuevosIds = [...idsActuales].filter(id => !pedidosVistos.has(id));
    nuevosIds.forEach(id => pedidosVistos.add(id));
    for (let id of pedidosVistos) {
        if (!idsActuales.has(id)) pedidosVistos.delete(id);
    }

    let html = '';
    mesas.forEach(mesa => {
        // Determinar el color más urgente entre todos los pedidos de la mesa
        const clasesPedidos = mesa.pedidos.map(p => tiempoTranscurrido(p.fecha_creacion).clase);
        const claseUrgente = clasesPedidos.includes('rojo') ? 'rojo' :
                             clasesPedidos.includes('naranja') ? 'naranja' : 'verde';

        html += `
            <div class="mesa-card borde-izq-${claseUrgente}" id="mesa-${mesa.id_mesa}">
                ${nuevosIds.some(id => mesa.pedidos.some(p => p.id_pedido === id)) ? '<span class="badge-new">🆕 Nuevo</span>' : ''}
                <div class="mesa-card-header">
                    <div>
                        <h3>${escapeHtml(mesa.nombre_mesa)}</h3>
                        <p>${mesa.pedidos.length} pedido(s) · ${tiempoTranscurrido(new Date(Math.min(...mesa.pedidos.map(p => new Date(p.fecha_creacion).getTime())))).texto}</p>
                    </div>
                    <span class="badge-status">En preparación</span>
                </div>
        `;

        // Subtarjetas por pedido
        mesa.pedidos.forEach(pedido => {
            const { texto, clase } = tiempoTranscurrido(pedido.fecha_creacion);
            html += `
                <div class="pedido-subcard borde-${clase}">
                    <div class="pedido-subcard-header">
                        <span>Pedido #${pedido.id_pedido}</span>
                        <span>${formatearFecha(pedido.fecha_creacion)}</span>
                    </div>
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
            pedido.items.forEach(item => {
                html += `
                    <tr>
                        <td>${escapeHtml(item.cliente)}</td>
                        <td>${escapeHtml(item.platillo)}</td>
                        <td>${escapeHtml(item.categoria)}</td>
                        <td><span class="cant-badge">${item.cantidad}</span></td>
                        <td class="comentario">${item.comentario ? escapeHtml(item.comentario) : '—'}</td>
                    </tr>`;
            });
            html += `</tbody></table>
                    <div class="pedido-acciones">
                        <button class="btn-listo-individual" onclick="solicitarMarcarListo(${pedido.id_pedido}, '${escapeHtml(mesa.nombre_mesa)}')">✓ Listo</button>
                    </div>
                </div>`;
        });

        // Botón marcar todo listo
        html += `
                <div class="card-actions">
                    <button class="btn-listo-mesa" onclick="solicitarMarcarMesaListo(${mesa.id_mesa}, '${escapeHtml(mesa.nombre_mesa)}')">✓ Marcar todo listo</button>
                </div>
            </div>`;
    });

    container.innerHTML = html;
}

// Sidebar
function actualizarSidebar(mesas) {
    const items = document.querySelectorAll('.item-mesa');
    const idsConPedidos = new Set(mesas.map(m => m.id_mesa));

    items.forEach(item => {
        const idMesa = parseInt(item.dataset.mesa);
        if (idsConPedidos.has(idMesa)) {
            item.classList.add('con-pedidos');
            item.classList.remove('sin-pedidos');
            item.onclick = () => {
                const target = document.getElementById(`mesa-${idMesa}`);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };
        } else {
            item.classList.remove('con-pedidos');
            item.classList.add('sin-pedidos');
            item.onclick = null;
        }
    });
}

// Marcar mesa completa
function solicitarMarcarMesaListo(idMesa, nombreMesa) {
    mesaPendienteMarcar = idMesa;
    modalTitulo.textContent = 'Marcar todo listo';
    modalMensaje.textContent = `¿Marcar todos los pedidos de ${nombreMesa} como listos?`;
    modal.classList.remove('hidden');
}

async function marcarMesaListoConfirmado(idMesa) {
    try {
        const response = await fetch('api/pedidos_listo_mesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_mesa: idMesa })
        });
        const data = await response.json();
        if (data.ok) {
            cargarPedidos();
        } else {
            alert(data.message || 'Error');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
}

// Marcar pedido individual
function solicitarMarcarListo(idPedido, nombreMesa) {
    mostrarConfirmacion(
        'Marcar pedido como listo',
        `¿Confirmas que el pedido de ${nombreMesa} está listo para entregar?`,
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