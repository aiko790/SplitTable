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
});