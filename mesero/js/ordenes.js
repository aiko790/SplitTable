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
    console.log('🗑️ eliminarOrden llamada con idOrden:', idOrden);
    fetch('api/actualizar_orden.php?id_orden=' + idOrden, { method: 'DELETE' })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                mostrarModalInformativo('Eliminado', 'Platillo eliminado del pedido', true);
                cargarPedidos(currentSelectedMesa);
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
    console.log('🏗️ crearModalEdicion ejecutándose');
    if (document.getElementById('modalEditarOrden')) {
        console.log('⚠️ El modal ya existe, no se crea de nuevo');
        return;
    }
    
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
    
    // Listeners para botones de cantidad
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
    
    // Listener para Cancelar
    document.getElementById('btnCancelarEditar').addEventListener('click', () => {
        const modal = document.getElementById('modalEditarOrden');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    });
    
    // Listener para Guardar
    document.getElementById('btnGuardarEditar').addEventListener('click', () => {
        guardarEdicionOrden();
    });
    
    // ⚡ DELEGACIÓN DE EVENTOS PARA EL BOTÓN ELIMINAR (más seguro)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#btnEliminarPlatillo');
        if (!btn) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        console.log('🔴 Botón Eliminar clickeado (delegación). currentEditIdOrden:', currentEditIdOrden);
        
        mostrarModalConfirmacion(
            'Eliminar platillo',
            '¿Estás seguro de eliminar este platillo del pedido? Esta acción no se puede deshacer.',
            () => {
                console.log('✅ Callback de confirmación ejecutado. Eliminando orden:', currentEditIdOrden);
                eliminarOrden(currentEditIdOrden, currentEditIdPedido);
                const modal = document.getElementById('modalEditarOrden');
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
            }
        );
    });
}

function abrirModalEdicion(idOrden, idPedido, nombre, cantidad, comentario, precio) {
    console.log('🟢 abrirModalEdicion recibió:', { idOrden, idPedido, nombre, cantidad, comentario, precio });
    currentEditIdOrden = idOrden;
    currentEditIdPedido = idPedido;
    currentEditPrecio = precio;
    document.getElementById('editPlatilloNombre').textContent = nombre;
    document.getElementById('editPrecioUnitario').textContent = precio.toFixed(2);
    document.getElementById('editCantidad').value = cantidad;
    document.getElementById('editComentario').value = comentario;
    
    const modal = document.getElementById('modalEditarOrden');
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
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
            const modal = document.getElementById('modalEditarOrden');
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            cargarPedidos(currentSelectedMesa);
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