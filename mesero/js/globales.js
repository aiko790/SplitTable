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