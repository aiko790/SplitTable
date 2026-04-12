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