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
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Respuesta no válida del servidor:', text);
            throw new Error('El servidor devolvió una respuesta inválida.');
        }
    })
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
        console.error('Error en cierre de mesa:', err);
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