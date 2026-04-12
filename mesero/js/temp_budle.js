// ==================== FUNCIÓN DE SEGURIDAD HTML ====================
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ==================== VARIABLES GLOBALES ====================
let refreshInterval = null;
let currentSelectedMesa = null;
let currentTab = 'capturado';
let currentPedidos = { capturado: [], listo: [], entregado: [] };
let pendingMesaToTake = null;
let pendingConfirmAction = null;

let currentCategorias = [];
let currentPlatillos = [];
let currentPlatilloSeleccionado = null;
let currentClienteSeleccionado = null;
let isAgregando = false;

// Variables para el modal rápido
let modalMenuRapido = null;
let currentModalClienteId = null;
let currentModalClienteNombre = null;
let currentModalCategoriaId = null;

let modalPlatillo = null;
let btnGuardarPlatillo = null;
let btnCancelarModal = null;
let minusBtn = null;
let plusBtn = null;
let cantidadActual = 1;

// Variables para modales
let modalConfirmacion = null;
let modalInformativo = null;
let modalErrorConexion = null;
let modalClientOrders = null;
let modalResumenPedido = null;
let pendingResumenAction = null;
let resumenData = null;

// Edición
let currentEditIdOrden = null, currentEditIdPedido = null, currentEditPrecio = 0;

// ==================== MODALES BASE ====================
function inicializarModales() {
    modalConfirmacion = document.getElementById('modalConfirmacion');
    modalInformativo = document.getElementById('modalInformativo');
    modalErrorConexion = document.getElementById('modalErrorConexion');

    const btnCancelarConf = document.getElementById('btnConfirmacionCancelar');
    const btnAceptarConf = document.getElementById('btnConfirmacionAceptar');
    if (btnCancelarConf) {
        btnCancelarConf.addEventListener('click', () => {
            modalConfirmacion.classList.add('hidden');
            pendingConfirmAction = null;
        });
    }
    if (btnAceptarConf) {
        btnAceptarConf.addEventListener('click', () => {
            modalConfirmacion.classList.add('hidden');
            if (pendingConfirmAction) {
                pendingConfirmAction();
                pendingConfirmAction = null;
            }
        });
    }

    const btnInfoCerrar = document.getElementById('btnInformativoCerrar');
    if (btnInfoCerrar) {
        btnInfoCerrar.addEventListener('click', () => {
            modalInformativo.classList.add('hidden');
        });
    }

    const btnErrorCerrar = document.getElementById('btnErrorConexionCerrar');
    if (btnErrorCerrar) {
        btnErrorCerrar.addEventListener('click', () => {
            modalErrorConexion.classList.add('hidden');
        });
    }
}

function mostrarModalConfirmacion(titulo, mensaje, accionSiAcepta) {
    if (!modalConfirmacion) return;
    document.getElementById('confirmacionTitulo').innerText = titulo;
    document.getElementById('confirmacionMensaje').innerText = mensaje;
    pendingConfirmAction = accionSiAcepta;
    modalConfirmacion.classList.remove('hidden');
}

function mostrarModalInformativo(titulo, mensaje, autoCerrar = true) {
    if (!modalInformativo) return;
    document.getElementById('informativoTitulo').innerText = titulo;
    document.getElementById('informativoMensaje').innerText = mensaje;
    modalInformativo.classList.remove('hidden');
    if (autoCerrar) {
        setTimeout(() => {
            if (modalInformativo && !modalInformativo.classList.contains('hidden')) {
                modalInformativo.classList.add('hidden');
            }
        }, 2000);
    }
}

function mostrarModalErrorConexion(mensaje) {
    if (!modalErrorConexion) return;
    document.getElementById('errorConexionMensaje').innerText = mensaje;
    modalErrorConexion.classList.remove('hidden');
}

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

// ==================== MODAL MENÚ RÁPIDO ====================
function inicializarModalMenuRapido() {
    modalMenuRapido = document.getElementById('modalMenuRapido');
    if (!modalMenuRapido) return;

    const btnCerrarFooter = document.getElementById('btnCerrarModalMenuFooter');
    if (btnCerrarFooter) btnCerrarFooter.addEventListener('click', () => cerrarModalMenu());

    const searchInput = document.getElementById('modalSearchPlatillo');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filtrarPlatillosEnModal(e.target.value);
        });
    }
}

function cerrarModalMenu() {
    if (modalMenuRapido) modalMenuRapido.classList.add('hidden');
    currentModalClienteId = null;
    currentModalClienteNombre = null;
    currentModalCategoriaId = null;
}

function abrirModalMenu(clienteId, clienteNombre, categoriaId = null) {
    if (!currentSelectedMesa) {
        mostrarModalInformativo('Atención', 'Selecciona una mesa primero', false);
        return;
    }
    currentModalClienteId = clienteId;
    currentModalClienteNombre = clienteNombre;
    document.querySelector('#modalMenuClienteNombre span').innerText = clienteNombre;
    document.querySelector('#modalResumenClienteNombre').innerText = clienteNombre;
    cargarCategoriasEnModal(categoriaId);
    cargarResumenClienteEnModal(clienteId);
    modalMenuRapido.classList.remove('hidden');
}

function cargarCategoriasEnModal(categoriaSeleccionadaId = null) {
    const container = document.getElementById('modalCategoriasList');
    if (!container) return;
    fetch('api/menu.php')
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentCategorias = data.categorias;
                currentPlatillos = data.platillos;
                if (currentCategorias.length === 0) {
                    container.innerHTML = '<div class="hint">No hay categorías</div>';
                    return;
                }
                let html = '';
                currentCategorias.forEach(cat => {
                    const activeClass = (categoriaSeleccionadaId && cat.id_categoria == categoriaSeleccionadaId) ? 'active' : '';
                    html += `<button class="modal-cat-item ${activeClass}" data-id-categoria="${cat.id_categoria}">${escapeHtml(cat.nombre)}</button>`;
                });
                container.innerHTML = html;
                
                if (categoriaSeleccionadaId) {
                    currentModalCategoriaId = categoriaSeleccionadaId;
                    cargarPlatillosEnModal(categoriaSeleccionadaId);
                } else if (currentCategorias.length > 0) {
                    const firstCat = document.querySelector('.modal-cat-item');
                    if (firstCat) {
                        firstCat.classList.add('active');
                        const idCat = firstCat.getAttribute('data-id-categoria');
                        currentModalCategoriaId = idCat;
                        cargarPlatillosEnModal(idCat);
                    }
                }
                
                document.querySelectorAll('.modal-cat-item').forEach(btn => {
                    btn.addEventListener('click', () => {
                        document.querySelectorAll('.modal-cat-item').forEach(c => c.classList.remove('active'));
                        btn.classList.add('active');
                        const idCat = btn.getAttribute('data-id-categoria');
                        currentModalCategoriaId = idCat;
                        cargarPlatillosEnModal(idCat);
                        const searchInput = document.getElementById('modalSearchPlatillo');
                        if (searchInput) searchInput.value = '';
                    });
                });
            } else {
                container.innerHTML = '<div class="hint">Error cargando categorías</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="hint">Error de conexión</div>';
        });
}

function cargarPlatillosEnModal(categoriaId) {
    const container = document.getElementById('modalPlatillosGrid');
    if (!container) return;
    const platillosFiltrados = currentPlatillos.filter(p => p.id_categoria == categoriaId);
    if (platillosFiltrados.length === 0) {
        container.innerHTML = '<div class="hint">No hay platillos en esta categoría</div>';
        return;
    }
    let html = '';
    platillosFiltrados.forEach(p => {
        // Usar imagen por defecto si no hay
        const imagenSrc = (p.imagen && p.imagen !== '') ? p.imagen : 'iconos/vacio.png';
        const imagenHtml = `<img src="${imagenSrc}" alt="${escapeHtml(p.nombre)}">`;
        
        html += `
            <div class="modal-dish-card" data-id-platillo="${p.id_platillo}" data-nombre="${escapeHtml(p.nombre)}" data-precio="${p.precio}" data-descripcion="${escapeHtml(p.descripcion || '')}">
                <div class="dish-image">
                    ${imagenHtml}
                </div>
                <div class="dish-info">
                    <div class="modal-dish-title">${escapeHtml(p.nombre)}</div>
                    <div class="modal-dish-desc">${escapeHtml(p.descripcion ? p.descripcion.substring(0, 60) : 'Sin descripción')}</div>
                    <div class="modal-dish-bottom">
                        <span class="modal-dish-price">$${parseFloat(p.precio).toFixed(2)}</span>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    
    // Hacer que toda la tarjeta sea clickeable
    document.querySelectorAll('.modal-dish-card').forEach(card => {
        card.addEventListener('click', (e) => {
            e.stopPropagation();
            const idPlatillo = card.getAttribute('data-id-platillo');
            const nombre = card.getAttribute('data-nombre');
            const precio = parseFloat(card.getAttribute('data-precio'));
            abrirModalAgregarPlatilloDesdeModal(idPlatillo, nombre, precio);
        });
    });
}

function filtrarPlatillosEnModal(termino) {
    if (!termino.trim()) {
        if (currentModalCategoriaId) cargarPlatillosEnModal(currentModalCategoriaId);
        return;
    }
    const terminoLower = termino.toLowerCase();
    const platillosFiltrados = currentPlatillos.filter(p => p.nombre.toLowerCase().includes(terminoLower));
    const container = document.getElementById('modalPlatillosGrid');
    if (!container) return;
    if (platillosFiltrados.length === 0) {
        container.innerHTML = '<div class="hint">No se encontraron platillos</div>';
        return;
    }
    let html = '';
    platillosFiltrados.forEach(p => {
        // Usar imagen por defecto si no hay
        const imagenSrc = (p.imagen && p.imagen !== '') ? p.imagen : 'iconos/vacio.png';
        const imagenHtml = `<img src="${imagenSrc}" alt="${escapeHtml(p.nombre)}">`;
        
        html += `
            <div class="modal-dish-card" data-id-platillo="${p.id_platillo}" data-nombre="${escapeHtml(p.nombre)}" data-precio="${p.precio}" data-descripcion="${escapeHtml(p.descripcion || '')}">
                <div class="dish-image">
                    ${imagenHtml}
                </div>
                <div class="dish-info">
                    <div class="modal-dish-title">${escapeHtml(p.nombre)}</div>
                    <div class="modal-dish-desc">${escapeHtml(p.descripcion ? p.descripcion.substring(0, 60) : 'Sin descripción')}</div>
                    <div class="modal-dish-bottom">
                        <span class="modal-dish-price">$${parseFloat(p.precio).toFixed(2)}</span>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    
    document.querySelectorAll('.modal-dish-card').forEach(card => {
        card.addEventListener('click', (e) => {
            e.stopPropagation();
            const idPlatillo = card.getAttribute('data-id-platillo');
            const nombre = card.getAttribute('data-nombre');
            const precio = parseFloat(card.getAttribute('data-precio'));
            abrirModalAgregarPlatilloDesdeModal(idPlatillo, nombre, precio);
        });
    });
}

function abrirModalAgregarPlatilloDesdeModal(idPlatillo, nombre, precio) {
    if (!currentModalClienteId) return;
    currentPlatilloSeleccionado = { id: idPlatillo, nombre, precio };
    cantidadActual = 1;
    document.getElementById('modalCantidad').innerText = '1';
    document.getElementById('modalClienteName').innerText = currentModalClienteNombre;
    document.getElementById('modalPlatilloNombre').innerText = nombre;
    document.getElementById('modalPrecio').innerText = '$' + precio.toFixed(2);
    document.getElementById('modalComentario').value = '';
    const eliminarBtn = document.getElementById('btnEliminarPlatillo');
    if (eliminarBtn) eliminarBtn.classList.add('hidden');
    modalPlatillo.classList.remove('hidden');
}

function cargarResumenClienteEnModal(clienteId) {
    if (!currentSelectedMesa) return;
    fetch(`api/pedidos.php?id_mesa=${currentSelectedMesa}`)
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                const pedidosCapturados = data.pedidos.filter(p => p.estado === 'capturado');
                let items = [];
                pedidosCapturados.forEach(pedido => {
                    pedido.ordenes.forEach(orden => {
                        if (orden.id_cliente == clienteId) {
                            items.push({
                                id_orden: orden.id_orden,
                                id_pedido: pedido.id_pedido,
                                nombre: orden.platillo_nombre,
                                cantidad: orden.cantidad,
                                precio_unitario: parseFloat(orden.precio_unitario),
                                subtotal: parseFloat(orden.subtotal),
                                comentario: orden.comentario || ''
                            });
                        }
                    });
                });
                renderResumenClienteEnModal(items);
            }
        })
        .catch(error => console.error('Error:', error));
}

function renderResumenClienteEnModal(items) {
    const container = document.getElementById('modalResumenList');
    let total = 0;
    if (items.length === 0) {
        container.innerHTML = '<div class="hint">No hay platillos agregados</div>';
        document.getElementById('modalResumenTotal').innerText = '$0.00';
        return;
    }
    let html = '';
    items.forEach(item => {
        total += item.subtotal;
        html += `
            <div class="modal-resumen-item" data-id-orden="${item.id_orden}" data-id-pedido="${item.id_pedido}">
                <div class="modal-resumen-item-info">
                    <span class="modal-resumen-item-cantidad">${item.cantidad}x</span>
                    <span class="modal-resumen-item-nombre">${escapeHtml(item.nombre)}</span>
                    <span class="modal-resumen-item-precio">$${item.subtotal.toFixed(2)}</span>
                </div>
                <div class="modal-resumen-item-acciones">
                    <button class="btn-dots" data-id-orden="${item.id_orden}" data-id-pedido="${item.id_pedido}" data-nombre="${escapeHtml(item.nombre)}" data-cantidad="${item.cantidad}" data-comentario="${escapeHtml(item.comentario)}" data-precio="${item.precio_unitario}" title="Editar / Eliminar">...</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    document.getElementById('modalResumenTotal').innerText = '$' + total.toFixed(2);
    document.querySelectorAll('#modalResumenList .btn-dots').forEach(btn => {
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

function actualizarResumenClienteEnModal() {
    if (currentModalClienteId && modalMenuRapido && !modalMenuRapido.classList.contains('hidden')) {
        cargarResumenClienteEnModal(currentModalClienteId);
    }
}

// ==================== MODAL CIERRE DE CUENTA ====================
let modalCerrar = null;
let clientesCerrar = [];
let totalGeneralCerrar = 0;
let tipoCuentaActual = 'conjunta';

function inicializarModalCerrar() {
    modalCerrar = document.getElementById('modalCerrarCuenta');
    if (!modalCerrar) return;

    // Botones del modal
    const btnCancelar = document.getElementById('btnCancelarCerrar');
    const btnConfirmar = document.getElementById('btnConfirmarCerrar');
    const btnAddEmail = document.getElementById('btnAddEmail');
    const radiosTipo = document.querySelectorAll('#modalCerrarCuenta .btn-tipo');

    if (btnCancelar) btnCancelar.addEventListener('click', cerrarModalCerrar);
    if (btnConfirmar) btnConfirmar.addEventListener('click', enviarCierre);
    if (btnAddEmail) btnAddEmail.addEventListener('click', agregarCampoCorreo);

    radiosTipo.forEach(radio => {
        radio.addEventListener('click', (e) => {
            const valor = radio.getAttribute('data-value');
            tipoCuentaActual = valor === 'separada' ? 'separada' : 'conjunta';
            actualizarVistaPagos();
        });
    });

    const emailsList = document.getElementById('emailsList');
    if (emailsList && emailsList.children.length === 0) {
        agregarCampoCorreo();
    }
}

function verificarYBotonCerrar(mesaId) {
    if (!mesaId) return;
    fetch(`api/verificar_pedidos_entregados.php?id_mesa=${mesaId}`)
        .then(res => res.json())
        .then(data => {
            console.log('Verificación:', data);
            const btnCerrar = document.getElementById('btnCerrarMesa');
            if (btnCerrar) {
                btnCerrar.disabled = !(data.ok && data.todos_entregados);
            }
        })
        .catch(err => console.error('Error verificando pedidos:', err));
}

function abrirModalCerrarMesa(mesaId) {
    if (!mesaId) return;

    // Mostrar loading
    const contenedorPagos = document.getElementById('contenedorPagos');
    if (contenedorPagos) contenedorPagos.innerHTML = '<div class="loading">Cargando...</div>';

    fetch(`api/verificar_pedidos_entregados.php?id_mesa=${mesaId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.ok || !data.todos_entregados) {
                mostrarModalInformativo('Atención', 'No se puede cerrar la cuenta porque hay pedidos pendientes de entregar.', false);
                return;
            }
            return fetch(`api/obtener_clientes_con_total.php?id_mesa=${mesaId}`);
        })
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                mostrarModalInformativo('Error', data.msg || 'Error al cargar los clientes', false);
                return;
            }
            clientesCerrar = data.clientes;
            totalGeneralCerrar = data.total_general;
            tipoCuentaActual = 'conjunta';

            document.querySelectorAll('#modalCerrarCuenta .btn-tipo').forEach(btn => {
                if (btn.getAttribute('data-value') === 'conjunta') btn.classList.add('active');
                else btn.classList.remove('active');
            });

            const emailsList = document.getElementById('emailsList');
            if (emailsList) emailsList.innerHTML = '';
            agregarCampoCorreo();

            actualizarVistaPagos();

            const inputIdMesa = document.getElementById('cerrar_id_mesa');
            if (inputIdMesa) inputIdMesa.value = mesaId;

            // Mostrar modal con validación de seguridad
            if (!modalCerrar) {
                modalCerrar = document.getElementById('modalCerrarCuenta');
                if (!modalCerrar) {
                    mostrarModalInformativo('Error', 'Error técnico: no se encontró el modal de cierre.', false);
                    return;
                }
            }
            modalCerrar.classList.remove('hidden');
        })
        .catch(err => {
            console.error(err);
            mostrarModalInformativo('Error', 'No se pudieron cargar los datos de la mesa.', false);
        });
}

function cerrarModalCerrar() {
    if (modalCerrar) modalCerrar.classList.add('hidden');
}

function actualizarVistaPagos() {
    const contenedor = document.getElementById('contenedorPagos');
    if (!contenedor) return;

    if (tipoCuentaActual === 'conjunta') {
        contenedor.innerHTML = `
            <div class="pago-conjunto">
                <label>Método de pago:</label>
                <select id="metodoPagoConjunto" class="select-metodo-pago">
                    <option value="efectivo">Efectivo</option>
                    <option value="tarjeta">Tarjeta</option>
                </select>
                <div class="total-conjunto">Total a pagar: <strong>$${totalGeneralCerrar.toFixed(2)}</strong></div>
            </div>
        `;
    } else {
        if (clientesCerrar.length === 0) {
            contenedor.innerHTML = '<div class="hint">No hay clientes con consumos entregados.</div>';
            return;
        }
        let html = '<div class="pagos-separados">';
        clientesCerrar.forEach(cliente => {
            html += `
                <div class="cliente-pago-item" data-id="${cliente.id_cliente}">
                    <div>
                        <span class="cliente-nombre-pago">${escapeHtml(cliente.nombre)}</span>
                        <span class="cliente-total-pago">$${cliente.total.toFixed(2)}</span>
                    </div>
                    <div>
                        <select class="select-metodo-pago" data-id="${cliente.id_cliente}">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        contenedor.innerHTML = html;
    }
}

function agregarCampoCorreo() {
    const emailsList = document.getElementById('emailsList');
    if (!emailsList) return;

    const emailItem = document.createElement('div');
    emailItem.className = 'email-item';
    emailItem.innerHTML = `
        <input type="email" name="emails[]" placeholder="correo@cliente.com" class="input-email" />
        <button type="button" class="btn-remove-email" title="Remover">✕</button>
    `;
    const removeBtn = emailItem.querySelector('.btn-remove-email');
    removeBtn.addEventListener('click', () => emailItem.remove());
    emailsList.appendChild(emailItem);
}

function enviarCierre() {
    const btnConfirmar = document.getElementById('btnConfirmarCerrar');
    if (btnConfirmar) btnConfirmar.disabled = true;

    const idMesa = document.getElementById('cerrar_id_mesa')?.value;
    if (!idMesa) {
        mostrarModalInformativo('Error', 'No se identificó la mesa.', false);
        if (btnConfirmar) btnConfirmar.disabled = false;
        return;
    }

    const emails = [];
    document.querySelectorAll('#emailsList .email-item input[type="email"]').forEach(input => {
        const email = input.value.trim();
        if (email) emails.push(email);
    });

    let pagos = [];
    if (tipoCuentaActual === 'conjunta') {
        const metodo = document.getElementById('metodoPagoConjunto')?.value || 'efectivo';
        pagos.push({
            cliente_id: null,
            nombre: 'Mesa',
            metodo: metodo,
            subtotal: totalGeneralCerrar
        });
    } else {
        const selects = document.querySelectorAll('#contenedorPagos .select-metodo-pago');
        selects.forEach(select => {
            const clienteId = parseInt(select.getAttribute('data-id'));
            const cliente = clientesCerrar.find(c => c.id_cliente === clienteId);
            if (cliente) {
                pagos.push({
                    cliente_id: cliente.id_cliente,
                    nombre: cliente.nombre,
                    metodo: select.value,
                    subtotal: cliente.total
                });
            }
        });
    }

    const payload = {
        id_mesa: parseInt(idMesa),
        tipo_pago: tipoCuentaActual,
        pagos: pagos,
        correos: emails
    };

    if (btnConfirmar) btnConfirmar.textContent = 'Procesando...';

    fetch('acciones/cerrar_mesa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            mostrarModalInformativo('Éxito', data.message || 'Mesa cerrada correctamente.', true);
            cerrarModalCerrar();
            if (typeof refreshMesas === 'function') refreshMesas();
            currentSelectedMesa = null;
            mostrarContenidoGestion(false);
            mostrarContenidoDetalle(false);
            const mesaNumero = document.getElementById('mesaNumero');
            if (mesaNumero) mesaNumero.innerText = '—';
        } else {
            mostrarModalInformativo('Error', data.message || 'Ocurrió un error al cerrar la mesa.', false);
        }
    })
    .catch(err => {
        console.error(err);
        mostrarModalInformativo('Error', 'Error de conexión con el servidor.', false);
    })
    .finally(() => {
        if (btnConfirmar) {
            btnConfirmar.disabled = false;
            btnConfirmar.textContent = 'Cerrar mesa';
        }
    });
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    inicializarModalCerrar();
});

// ==================== GESTIÓN DE MESAS ====================
function mostrarContenidoGestion(mostrar) {
    const contenidoNormal = document.getElementById('contenidoNormalGestion');
    const mensajeSeleccion = document.getElementById('mensajeSeleccionMesa');
    if (contenidoNormal) contenidoNormal.style.display = mostrar ? '' : 'none';
    if (mensajeSeleccion) mensajeSeleccion.style.display = mostrar ? 'none' : 'flex';
}

function mostrarContenidoDetalle(mostrar) {
    const contenidoNormal = document.getElementById('contenidoNormalDetalle');
    const mensajeSeleccion = document.getElementById('mensajeSeleccionDetalle');
    if (contenidoNormal) contenidoNormal.style.display = mostrar ? 'flex' : 'none';
    if (mensajeSeleccion) mensajeSeleccion.style.display = mostrar ? 'none' : 'flex';
}

function refreshMesas() {
    fetch('api/obtener_mesas.php')
        .then(response => response.json())
        .then(mesas => {
            const grid = document.getElementById('mesasGrid');
            if (!grid) return;
            const currentCards = Array.from(grid.querySelectorAll('.mesa-card'));
            const currentIds = currentCards.map(card => card.getAttribute('data-mesa'));
            const nuevasMesasMap = new Map();
            mesas.forEach(m => nuevasMesasMap.set(String(m.id_mesa), m));
            currentCards.forEach(card => {
                const id = card.getAttribute('data-mesa');
                if (!nuevasMesasMap.has(id)) card.remove();
            });
            mesas.forEach(m => {
                const id = String(m.id_mesa);
                let card = grid.querySelector(`.mesa-card[data-mesa="${id}"]`);
                if (!card) {
                    card = document.createElement('button');
                    card.className = 'mesa-card';
                    card.setAttribute('data-mesa', id);
                    card.innerHTML = `<div class="barra"></div><div class="cuerpo"><div class="info"><div class="mesa-nombre"></div><div class="estado"></div></div><div class="accion"></div></div>`;
                    grid.appendChild(card);
                }
                const isSelected = (currentSelectedMesa && currentSelectedMesa == m.id_mesa);
                const selectedClass = isSelected ? 'mesa-selected' : '';
                let estadoClase = '', textoBarra = '', textoEstado = '', textoAccion = '';
                let iconoBarra = '';
                
                switch (m.visualState) {
                    case 'libre':
                        estadoClase = 'libre';
                        textoBarra = 'Libre';
                        textoEstado = 'Disponible';
                        textoAccion = 'Tomar mesa';
                        iconoBarra = 'mesa_libre.png';
                        break;
                    case 'lista':
                        estadoClase = 'listo';
                        textoBarra = 'Listo';
                        textoEstado = 'Pedido listo';
                        textoAccion = 'Abrir mesa';
                        iconoBarra = 'listo.png';
                        break;
                    case 'cocina':
                        estadoClase = 'cocina';
                        textoBarra = 'En cocina';
                        textoEstado = 'Pedido en cocina';
                        textoAccion = 'Abrir mesa';
                        iconoBarra = 'en_cocina.png';
                        break;
                    case 'propia':
                        estadoClase = 'tu';
                        textoBarra = 'Ocupada';
                        textoEstado = 'Ocupada';
                        textoAccion = 'Abrir mesa';
                        iconoBarra = 'listo.png';
                        break;
                    case 'otra':
                        estadoClase = 'otro';
                        textoBarra = 'Ocupada';
                        textoEstado = 'Ocupada';
                        textoAccion = m.nombre_mesero ? 'Mesero: ' + escapeHtml(m.nombre_mesero) : 'Ver mesa';
                        iconoBarra = 'ocupado.png';
                        break;
                    default:
                        return;
                }
                
                card.className = `mesa-card ${estadoClase} ${selectedClass}`;
                card.setAttribute('data-estado', m.visualState);
                card.setAttribute('data-ismine', m.isMine ? '1' : '0');
                card.setAttribute('data-nombre-mesa', m.nombre_mesa);
                card.setAttribute('data-accion', textoAccion);
                
                // Barra con icono
                const barraDiv = card.querySelector('.barra');
                if (barraDiv) {
                    barraDiv.innerHTML = `${iconoBarra ? `<img src="iconos/${iconoBarra}" class="icono-barra" alt="">` : ''}<span>${textoBarra}</span>`;
                }
                
                const nombreDiv = card.querySelector('.mesa-nombre');
                if (nombreDiv) nombreDiv.textContent = m.nombre_mesa;
                const estadoDiv = card.querySelector('.estado');
                if (estadoDiv) estadoDiv.textContent = textoEstado;
                const accionDiv = card.querySelector('.accion');
                if (accionDiv) accionDiv.textContent = textoAccion;
            });
            asignarEventosMesas();
        })
        .catch(error => {
            console.error('Error al actualizar mesas:', error);
            mostrarModalErrorConexion('No se pudo actualizar el estado de las mesas. Verifica tu conexión.');
        });
}

function asignarEventosMesas() {
    document.querySelectorAll('.mesa-card').forEach(card => {
        card.removeEventListener('click', handleMesaClick);
        card.addEventListener('click', handleMesaClick);
    });
}

function handleMesaClick(e) {
    if (e.target.closest('.btn') || e.target.closest('a')) return;
    const card = e.currentTarget;
    const mesaId = card.getAttribute('data-mesa');
    const estado = card.getAttribute('data-estado');
    const nombreMesa = card.getAttribute('data-nombre-mesa');
    const isMine = card.getAttribute('data-ismine') === '1';
    if (!isMine && estado !== 'libre') {
        if (window.mostrarModalMesaOcupada) window.mostrarModalMesaOcupada(nombreMesa);
        return;
    }
    if (estado === 'libre') {
        if (window.mostrarModalTomarMesa) window.mostrarModalTomarMesa(mesaId, nombreMesa);
        return;
    }
    document.querySelectorAll('.mesa-card').forEach(m => m.classList.remove('mesa-selected'));
    card.classList.add('mesa-selected');
    currentSelectedMesa = mesaId;
    mostrarContenidoGestion(true);
    mostrarContenidoDetalle(true);
    const mesaNumeroSpan = document.getElementById('mesaNumero');
    if (mesaNumeroSpan) mesaNumeroSpan.innerText = nombreMesa;
    const formIdMesa = document.getElementById('form_id_mesa');
    if (formIdMesa) formIdMesa.value = mesaId;
    const cerrarIdMesa = document.getElementById('cerrar_id_mesa');
    if (cerrarIdMesa) cerrarIdMesa.value = mesaId;
    cargarClientes(mesaId);
    cargarPedidos(mesaId);
    cargarCategorias();

    if (typeof verificarYBotonCerrar === 'function') {
        verificarYBotonCerrar(mesaId);
    }
}

function tomarMesa(idMesa) {
    const formData = new FormData();
    formData.append('id_mesa', idMesa);
    return fetch('acciones/tomar_mesa.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                mostrarModalInformativo('Error', data.message || 'No se pudo tomar la mesa', false);
                throw new Error(data.message);
            }
        });
}

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
    
    // Remover event listener anterior para evitar duplicados
    btn.removeEventListener('click', procesarAccionPedido);
    if (pedidos.length > 0) {
        btn.addEventListener('click', procesarAccionPedido);
    }
}

// Nueva función para procesar la acción del botón (Mandar a cocina / Entregar pedido)
function procesarAccionPedido() {
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

// ==================== GESTIÓN DE ÓRDENES ====================
function agregarOrden(idMesa, idCliente, idPlatillo, cantidad, comentario) {
    if (isAgregando) return;
    isAgregando = true;
    if (btnGuardarPlatillo) btnGuardarPlatillo.disabled = true;
    fetch('api/agregar_orden.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_mesa: idMesa, id_cliente: idCliente, id_platillo: idPlatillo, cantidad: cantidad, comentario: comentario })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            if (currentSelectedMesa) cargarPedidos(currentSelectedMesa);
            actualizarTotales();
            actualizarBotonAccion();
            // CAMBIO: forzar actualización adicional después de un breve retraso
            setTimeout(() => actualizarBotonAccion(), 50);
        } else {
            mostrarModalInformativo('Error', data.msg || 'No se pudo agregar el platillo', false);
        }
    })
    .catch(error => {
        console.error('Error al agregar orden:', error);
        mostrarModalErrorConexion('Error al agregar el platillo.');
    })
    .finally(() => {
        isAgregando = false;
        if (btnGuardarPlatillo) btnGuardarPlatillo.disabled = false;
    });
}

function eliminarOrden(idOrden, idPedido) {
    fetch('api/actualizar_orden.php?id_orden=' + idOrden, { method: 'DELETE' })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                mostrarModalInformativo('Eliminado', 'Platillo eliminado del pedido', true);
                cargarPedidos(currentSelectedMesa);
                // CAMBIO: forzar actualización del botón después de eliminar
                setTimeout(() => actualizarBotonAccion(), 50);
            } else {
                mostrarModalInformativo('Error', data.msg || 'No se pudo eliminar', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarModalErrorConexion('Error al eliminar el platillo.');
        });
}

function crearModalEdicion() {
    if (document.getElementById('modalEditarOrden')) return;
    const modalHtml = `
        <div id="modalEditarOrden" class="modal hidden" aria-hidden="true">
            <div class="modal-box modal-editar" role="dialog">
                <h3>Editar platillo</h3>
                <div class="modal-info"><div><strong id="editPlatilloNombre"></strong></div><div>Precio unitario: $<span id="editPrecioUnitario"></span></div></div>
                <div class="campo"><label>Cantidad</label><div style="display:flex;align-items:center;gap:10px;"><button id="editQtyMinus" class="btn small" type="button">-</button><input type="number" id="editCantidad" value="1" min="1" style="width:80px;text-align:center;"><button id="editQtyPlus" class="btn small" type="button">+</button></div></div>
                <div class="campo"><label>Comentario para cocina</label><textarea id="editComentario" rows="3" placeholder="Ej: sin cebolla, bien cocido..."></textarea></div>
                <div class="modal-actions"><button id="btnEliminarPlatillo" class="btn danger">Eliminar</button><button id="btnCancelarEditar" class="btn ghost">Cancelar</button><button id="btnGuardarEditar" class="btn success">Guardar cambios</button></div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('editQtyMinus').addEventListener('click', () => {
        const input = document.getElementById('editCantidad');
        let val = parseInt(input.value);
        if (val > 1) input.value = val - 1;
    });
    document.getElementById('editQtyPlus').addEventListener('click', () => {
        const input = document.getElementById('editCantidad');
        let val = parseInt(input.value);
        input.value = val + 1;
    });
    document.getElementById('btnCancelarEditar').addEventListener('click', () => {
        document.getElementById('modalEditarOrden').classList.add('hidden');
    });
    document.getElementById('btnGuardarEditar').addEventListener('click', () => {
        guardarEdicionOrden();
    });
    document.getElementById('btnEliminarPlatillo').addEventListener('click', () => {
        mostrarModalConfirmacion('Eliminar platillo', '¿Estás seguro de eliminar este platillo del pedido? Esta acción no se puede deshacer.', () => {
            eliminarOrden(currentEditIdOrden, currentEditIdPedido);
            document.getElementById('modalEditarOrden').classList.add('hidden');
        });
    });
}

function abrirModalEdicion(idOrden, idPedido, nombre, cantidad, comentario, precio) {
    currentEditIdOrden = idOrden;
    currentEditIdPedido = idPedido;
    currentEditPrecio = precio;
    document.getElementById('editPlatilloNombre').textContent = nombre;
    document.getElementById('editPrecioUnitario').textContent = precio.toFixed(2);
    document.getElementById('editCantidad').value = cantidad;
    document.getElementById('editComentario').value = comentario;
    document.getElementById('modalEditarOrden').classList.remove('hidden');
}

function guardarEdicionOrden() {
    const nuevaCantidad = parseInt(document.getElementById('editCantidad').value);
    const nuevoComentario = document.getElementById('editComentario').value;
    if (nuevaCantidad <= 0) {
        mostrarModalInformativo('Error', 'La cantidad debe ser mayor a 0', false);
        return;
    }
    fetch('api/actualizar_orden.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_orden: currentEditIdOrden, cantidad: nuevaCantidad, comentario: nuevoComentario })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            document.getElementById('modalEditarOrden').classList.add('hidden');
            cargarPedidos(currentSelectedMesa);
            // CAMBIO: forzar actualización después de editar
            setTimeout(() => actualizarBotonAccion(), 50);
        } else {
            mostrarModalInformativo('Error', data.msg || 'No se pudo actualizar', false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarModalErrorConexion('Error al guardar cambios.');
    });
}

function inicializarModalPlatillo() {
    modalPlatillo = document.getElementById('modalPlatillo');
    if (!modalPlatillo) return;
    minusBtn = document.getElementById('qtyMinus');
    plusBtn = document.getElementById('qtyPlus');
    btnGuardarPlatillo = document.getElementById('btnGuardarPlatillo');
    btnCancelarModal = document.getElementById('btnCancelarModal');
    const qtySpan = document.getElementById('modalCantidad');
    const updateQty = (newQty) => {
        if (newQty < 1) newQty = 1;
        cantidadActual = newQty;
        qtySpan.innerText = cantidadActual;
    };
    minusBtn.addEventListener('click', () => updateQty(cantidadActual - 1));
    plusBtn.addEventListener('click', () => updateQty(cantidadActual + 1));
    btnGuardarPlatillo.addEventListener('click', () => {
        if (isAgregando) return;
        if (!currentPlatilloSeleccionado) return;
        const comentario = document.getElementById('modalComentario').value;
        const clienteId = currentModalClienteId || currentClienteSeleccionado;
        if (!clienteId) {
            mostrarModalInformativo('Atención', 'Selecciona un cliente primero', false);
            return;
        }
        agregarOrden(currentSelectedMesa, clienteId, currentPlatilloSeleccionado.id, cantidadActual, comentario);
        cerrarModalPlatillo();
    });
    btnCancelarModal.addEventListener('click', () => { cerrarModalPlatillo(); });
}

function cerrarModalPlatillo() {
    if (modalPlatillo) modalPlatillo.classList.add('hidden');
    currentPlatilloSeleccionado = null;
}

// ==================== CATEGORÍAS Y MENÚ ====================
function cargarCategorias() {
    fetch('api/menu.php')
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                currentCategorias = data.categorias;
                currentPlatillos = data.platillos;
                renderBotonesCategorias();
            } else {
                document.getElementById('categorias-botones').innerHTML = '<div class="hint">Error cargando categorías</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('categorias-botones').innerHTML = '<div class="hint">Error de conexión</div>';
        });
}

function renderBotonesCategorias() {
    const container = document.getElementById('categorias-botones');
    if (!container) return;
    if (!currentCategorias.length) {
        container.innerHTML = '<div class="hint">No hay categorías disponibles</div>';
        return;
    }
    let html = '';
    currentCategorias.forEach(cat => {
        html += `<button class="btn-categoria" data-id-categoria="${cat.id_categoria}" data-nombre-categoria="${escapeHtml(cat.nombre)}">${escapeHtml(cat.nombre)}</button>`;
    });
    container.innerHTML = html;
    document.querySelectorAll('.btn-categoria').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!currentClienteSeleccionado) {
                mostrarModalInformativo('Atención', 'Primero selecciona un cliente', false);
                return;
            }
            const clienteNombre = obtenerNombreCliente(currentClienteSeleccionado);
            const categoriaId = btn.getAttribute('data-id-categoria');
            abrirModalMenu(currentClienteSeleccionado, clienteNombre, categoriaId);
        });
    });
}

// ==================== INICIALIZACIÓN PRINCIPAL ====================
document.addEventListener('DOMContentLoaded', () => {
    const selectedCard = document.querySelector('.mesa-card.mesa-selected');
    if (selectedCard) {
        currentSelectedMesa = selectedCard.getAttribute('data-mesa');
    }

    asignarEventosMesas();

    refreshInterval = setInterval(() => {
        refreshMesas();
        if (currentSelectedMesa) {
            cargarPedidos(currentSelectedMesa);
        }
    }, 10000);

    document.querySelectorAll('.tab-text').forEach(tab => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-tab');
            cambiarTab(tabId);
        });
    });

    document.getElementById('btnAccionPedido').addEventListener('click', () => {
        if (currentTab === 'capturado') {
            mostrarResumenEnvioCocina();
        } else {
            mostrarResumenEntregarPedido();
        }
    });

    inicializarModales();
    inicializarModalPlatillo();
    inicializarModalMenuRapido();
    inicializarModalClientOrders();
    inicializarModalResumen();
    crearModalEdicion();

    // ==================== NUEVO: INICIALIZAR BOTÓN CERRAR CUENTA ====================
    const btnCerrarMesa = document.getElementById('btnCerrarMesa');
    if (btnCerrarMesa) {
        btnCerrarMesa.addEventListener('click', function() {
            if (!currentSelectedMesa) {
                mostrarModalInformativo('Atención', 'Selecciona una mesa primero', false);
                return;
            }
            
            const mesaCard = document.querySelector(`.mesa-card[data-mesa="${currentSelectedMesa}"]`);
            const isMine = mesaCard?.getAttribute('data-ismine') === '1';
            const estado = mesaCard?.getAttribute('data-estado');
            
            if (!isMine && estado !== 'libre') {
                mostrarModalInformativo('Atención', 'Esta mesa no está asignada a ti', false);
                return;
            }
            
            if (typeof abrirModalCerrarMesa === 'function') {
                abrirModalCerrarMesa(currentSelectedMesa);
            } else {
                console.error('La función abrirModalCerrarMesa no está definida');
                mostrarModalInformativo('Error', 'Error al abrir el modal de cierre. Verifica que modales_cerrar.js esté cargado.', false);
            }
        });
    }

    // Modal para tomar mesa
    const modalTake = document.getElementById('modalConfirmTake');
    const confirmTakeBtn = document.getElementById('btnConfirmarTake');
    const cancelTakeBtn = document.getElementById('btnCancelarTake');
    if (modalTake && confirmTakeBtn && cancelTakeBtn) {
        window.mostrarModalTomarMesa = (mesaId, mesaNombre) => {
            pendingMesaToTake = mesaId;
            const msg = document.getElementById('confirmTakeText');
            if (msg) msg.innerText = '¿Seguro que quieres tomar la ' + mesaNombre + '?';
            modalTake.classList.remove('hidden');
        };
        confirmTakeBtn.onclick = () => {
            if (pendingMesaToTake) {
                tomarMesa(pendingMesaToTake).then(() => {
                    modalTake.classList.add('hidden');
                    window.location.href = 'dashboard.php?id_mesa=' + pendingMesaToTake;
                }).catch(err => console.error(err));
                pendingMesaToTake = null;
            } else {
                modalTake.classList.add('hidden');
            }
        };
        cancelTakeBtn.onclick = () => {
            modalTake.classList.add('hidden');
            pendingMesaToTake = null;
        };
    }

    const modalOcupada = document.getElementById('modalMesaOcupada');
    const btnCerrarOcupada = document.getElementById('btnCerrarModalOcupada');
    if (modalOcupada && btnCerrarOcupada) {
        window.mostrarModalMesaOcupada = (nombreMesa) => {
            const texto = document.getElementById('modalMesaOcupadaText');
            if (texto) texto.innerText = `La mesa ${nombreMesa} está siendo atendida por otro mesero`;
            modalOcupada.classList.remove('hidden');
        };
        btnCerrarOcupada.onclick = () => {
            modalOcupada.classList.add('hidden');
        };
    }

    if (currentSelectedMesa) {
        cargarPedidos(currentSelectedMesa);
        cargarCategorias();
        mostrarContenidoGestion(true);
        mostrarContenidoDetalle(true);
    } else {
        mostrarContenidoGestion(false);
        mostrarContenidoDetalle(false);
    }

    initSeleccionCliente();

    const style = document.createElement('style');
    style.textContent = `
        .cliente-item.cliente-activo {
            background: #e0f0ff;
            border-left: 3px solid #2b90ff;
        }
    `;
    document.head.appendChild(style);
    console.log('✅ main.js se ejecutó completamente');
});

console.log('✅ main.js se ejecutó completamente');