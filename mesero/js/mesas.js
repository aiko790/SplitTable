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