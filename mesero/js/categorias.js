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