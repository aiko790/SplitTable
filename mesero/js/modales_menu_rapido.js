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