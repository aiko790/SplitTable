// ==================== VARIABLES GLOBALES ====================
let chartInstance = null;
let periodoActual = '7d';
let fechaInicio = '';
let fechaFin = '';

// Variables para el modal de historial completo
let paginaActualCuentas = 1;
let cargandoCuentas = false;
let hayMasCuentas = true;
let todasLasCuentas = [];
let modalHistorialAbierto = false;

// ==================== INICIALIZACIÓN ====================
document.addEventListener('DOMContentLoaded', () => {
    cargarDashboard();
    document.getElementById('mostrarModalDescarga').addEventListener('click', mostrarModalDescarga);
    document.getElementById('confirmarDescarga').addEventListener('click', descargarPDF);
    document.getElementById('periodoDescarga').addEventListener('change', togglePersonalizadoDescarga);
    document.getElementById('topPlatillosCard').addEventListener('click', () => abrirModalPlatillos());
    document.getElementById('topMeserosCard').addEventListener('click', () => abrirModalMeseros());

    // Hacer que la tarjeta de últimas cuentas abra el modal de historial completo
    const cardUltimasCuentas = document.querySelector('#ultimasCuentas').closest('.card');
    if (cardUltimasCuentas) {
        cardUltimasCuentas.style.cursor = 'pointer';
        cardUltimasCuentas.addEventListener('click', (e) => {
            if (e.target.classList.contains('ver-detalle')) return;
            abrirModalHistorialCompleto();
        });
    }

    // Hacer clicable la tarjeta de ingresos
    const cardIngresos = document.getElementById('cardIngresos');
    if (cardIngresos) {
        cardIngresos.style.cursor = 'pointer';
        cardIngresos.addEventListener('click', abrirModalIngresosDetalle);
    }
});

// ==================== MODAL DE DESCARGA PDF ====================
function togglePersonalizadoDescarga() {
    const tipo = document.getElementById('periodoDescarga').value;
    document.getElementById('personalizadoDescarga').style.display = tipo === 'personalizado' ? 'block' : 'none';
}

function mostrarModalDescarga() {
    document.getElementById('modalDescargaPDF').style.display = 'flex';
}

function closeModalDescarga() {
    document.getElementById('modalDescargaPDF').style.display = 'none';
}

function descargarPDF() {
    const tipo = document.getElementById('periodoDescarga').value;
    let inicio, fin;
    const hoy = new Date();

    switch (tipo) {
        case 'hoy': inicio = fin = new Date().toISOString().split('T')[0]; break;
        case '7d': fin = new Date().toISOString().split('T')[0]; inicio = new Date(hoy.setDate(hoy.getDate() - 7)).toISOString().split('T')[0]; break;
        case 'semana': inicio = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() + 1)).toISOString().split('T')[0]; fin = new Date(new Date(inicio).setDate(new Date(inicio).getDate() + 6)).toISOString().split('T')[0]; break;
        case 'mes': inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0]; fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().split('T')[0]; break;
        case 'personalizado': inicio = document.getElementById('fechaInicioDescarga').value; fin = document.getElementById('fechaFinDescarga').value; break;
    }

    window.open(`api/historial_pdf.php?tipo=resumen&inicio=${inicio}&fin=${fin}`, '_blank');
    closeModalDescarga();
}

// ==================== CARGA DEL DASHBOARD ====================
async function cargarDashboard() {
    try {
        const res = await fetch(`api/historial_kpis.php?action=dashboard&periodo=7d`);
        const data = await res.json();
        if (!data.ok) throw new Error('Error en API');

        actualizarKPIs(data.kpis, data.mejor_mesero);
        actualizarGrafico(data.grafico);
        actualizarTopPlatillos(data.top_platillos);
        actualizarTopMeseros(data.top_meseros);
        actualizarUltimasCuentas(data.ultimas_cuentas);

        window.periodoPDF = data.periodo;
    } catch (e) {
        console.error('Error cargando dashboard:', e);
    }
}

function actualizarKPIs(kpis, mejorMesero) {
    document.getElementById('kpiCuentas').textContent = kpis.total_cuentas;
    document.getElementById('kpiIngresos').textContent = '$' + kpis.ingresos_totales.toLocaleString('es-MX', { minimumFractionDigits: 2 });
    document.getElementById('kpiPromedio').textContent = '$' + kpis.ticket_promedio.toLocaleString('es-MX', { minimumFractionDigits: 2 });

    if (mejorMesero) {
        document.getElementById('kpiMejorMeseroNombre').textContent = mejorMesero.nombre_mesero;
        document.getElementById('kpiMejorMeseroMonto').textContent = '$' + parseFloat(mejorMesero.total).toLocaleString('es-MX', { minimumFractionDigits: 2 });
    } else {
        document.getElementById('kpiMejorMeseroNombre').textContent = '--';
        document.getElementById('kpiMejorMeseroMonto').textContent = '';
    }
}

function actualizarGrafico(grafico) {
    const ctx = document.getElementById('ventasChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: grafico.labels,
            datasets: [{
                label: 'Ingresos ($)',
                data: grafico.valores,
                backgroundColor: '#8b0000',
                borderRadius: 6
            }]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } } }
    });
}

function actualizarTopPlatillos(platillos) {
    const max = platillos.length ? platillos[0].total_vendido : 1;
    const html = platillos.map(p => `
        <div class="top-item">
            <span class="top-nombre">${escapeHtml(p.nombre_platillo)}</span>
            <span class="top-valor">${p.total_vendido}</span>
            <div class="top-barra"><div class="top-barra-fill" style="width:${(p.total_vendido / max) * 100}%"></div></div>
        </div>
    `).join('');
    document.getElementById('topPlatillos').innerHTML = html;
}

function actualizarTopMeseros(meseros) {
    const max = meseros.length ? meseros[0].total : 1;
    const html = meseros.map(m => `
        <div class="top-item">
            <span class="top-nombre">${escapeHtml(m.nombre_mesero)}</span>
            <span class="top-valor">$${parseFloat(m.total).toLocaleString()}</span>
            <div class="top-barra"><div class="top-barra-fill" style="width:${(m.total / max) * 100}%"></div></div>
        </div>
    `).join('');
    document.getElementById('topMeseros').innerHTML = html;
}

function actualizarUltimasCuentas(cuentas) {
    const html = `
        <table class="ultimas-table">
            <thead><tr><th>Mesa</th><th>Mesero</th><th>Total</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
                ${cuentas.map(c => `
                    <tr>
                        <td>${escapeHtml(c.nombre_mesa)}</td>
                        <td>${escapeHtml(c.nombre_mesero)}</td>
                        <td>$${parseFloat(c.total_general).toLocaleString()}</td>
                        <td>${new Date(c.fecha_cierre).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })}</td>
                        <td><span class="ver-detalle" data-id="${c.id_historial}">Ver</span></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('ultimasCuentas').innerHTML = html;
    document.querySelectorAll('.ver-detalle').forEach(el => {
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            mostrarDetalleCuenta(el.dataset.id);
        });
    });
}

async function abrirModalHistorialCompleto() {
    const modal = document.getElementById('detalleModal');
    const body = document.getElementById('detalleModalBody');

    body.innerHTML = `
        <h2>Historial completo de cuentas</h2>
        <div style="margin-bottom:20px;">
            <label>Buscar por fecha:</label>
            <input type="date" id="buscadorFechaHistorial" style="padding:8px; border-radius:8px; border:1px solid #e2e8f0;">
            <button class="btn-submit" id="irAFechaHistorial" style="margin-left:10px;">Ir</button>
        </div>
        <div id="historialCuentasContainer" class="historial-cuentas-container"></div>
        <div id="cargandoCuentas" class="loading-indicator" style="display:none;">Cargando más cuentas...</div>
    `;
    modal.style.display = 'flex';
    modalHistorialAbierto = true;

    paginaActualCuentas = 1;
    todasLasCuentas = [];
    hayMasCuentas = true;

    await cargarMultiplesPaginas(3);
    configurarScrollInfinitoEnModal();

    document.getElementById('irAFechaHistorial').addEventListener('click', () => {
        const fecha = document.getElementById('buscadorFechaHistorial').value;
        if (fecha) hacerScrollAFecha(fecha);
    });
}

async function cargarMultiplesPaginas(cantidad) {
    for (let i = 0; i < cantidad; i++) {
        if (!hayMasCuentas) break;
        await cargarMasCuentas();
    }
}

async function cargarMasCuentas() {
    if (cargandoCuentas || !hayMasCuentas) return;

    cargandoCuentas = true;
    document.getElementById('cargandoCuentas').style.display = 'block';

    try {
        const res = await fetch(`api/historial_kpis.php?action=cuentas_paginadas&pagina=${paginaActualCuentas}`);
        const data = await res.json();
        if (!data.ok) throw new Error('Error en API');

        if (paginaActualCuentas === 1) {
            todasLasCuentas = data.cuentas;
        } else {
            todasLasCuentas = [...todasLasCuentas, ...data.cuentas];
        }

        hayMasCuentas = data.hay_mas;
        paginaActualCuentas++;

        renderizarHistorialCuentasEnModal();
    } catch (e) {
        console.error('Error cargando cuentas:', e);
    } finally {
        cargandoCuentas = false;
        document.getElementById('cargandoCuentas').style.display = 'none';
    }
}

function renderizarHistorialCuentasEnModal() {
    const container = document.getElementById('historialCuentasContainer');

    if (!todasLasCuentas.length) {
        container.innerHTML = '<p class="hint">No hay cuentas cerradas aún.</p>';
        return;
    }

    const grupos = {};
    todasLasCuentas.forEach(c => {
        const fecha = c.fecha_cierre.split(' ')[0];
        if (!grupos[fecha]) grupos[fecha] = [];
        grupos[fecha].push(c);
    });

    const fechasOrdenadas = Object.keys(grupos).sort().reverse();

    let html = '';
    fechasOrdenadas.forEach(fecha => {
        const cuentasDia = grupos[fecha];
        const fechaObj = new Date(fecha + 'T12:00:00');
        const fechaFormateada = fechaObj.toLocaleDateString('es-MX', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        html += `
            <div class="dia-grupo" data-fecha="${fecha}">
                <div class="dia-header">${fechaFormateada}</div>
                <div class="cuentas-grid">
        `;

        cuentasDia.forEach(c => {
            const hora = c.fecha_cierre.split(' ')[1].substring(0, 5);
            html += `
                <div class="cuenta-card" data-id="${c.id_historial}">
                    <div class="cuenta-card-header">
                        <span class="cuenta-mesa">${escapeHtml(c.nombre_mesa)}</span>
                        <span class="cuenta-hora">${hora}</span>
                    </div>
                    <div class="cuenta-card-body">
                        <div class="cuenta-mesero">👨‍🍳 ${escapeHtml(c.nombre_mesero)}</div>
                        <div class="cuenta-info">
                            <span>👥 ${c.cantidad_personas}</span>
                            <span>🍽️ ${c.total_productos}</span>
                        </div>
                        <div class="cuenta-total">$${parseFloat(c.total_general).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>
                    </div>
                </div>
            `;
        });

        html += `</div></div>`;
    });

    container.innerHTML = html;

    document.querySelectorAll('#historialCuentasContainer .cuenta-card').forEach(card => {
        card.addEventListener('click', () => mostrarDetalleCuenta(card.dataset.id));
    });
}

function hacerScrollAFecha(fecha) {
    const grupo = document.querySelector(`.dia-grupo[data-fecha="${fecha}"]`);
    if (grupo) {
        grupo.scrollIntoView({ behavior: 'smooth', block: 'start' });
        grupo.style.transition = 'background 0.3s';
        grupo.style.background = '#fff3e0';
        setTimeout(() => grupo.style.background = '', 1500);
    } else {
        alert('No hay cuentas en esa fecha.');
    }
}

function configurarScrollInfinitoEnModal() {
    const loadingEl = document.getElementById('cargandoCuentas');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !cargandoCuentas && hayMasCuentas && modalHistorialAbierto) {
                cargarMasCuentas();
            }
        });
    }, { rootMargin: '100px' });

    if (loadingEl) observer.observe(loadingEl);
}

// ==================== DETALLE DE CUENTA ====================
async function mostrarDetalleCuenta(id) {
    const modal = document.getElementById('detalleModal');
    const body = document.getElementById('detalleModalBody');
    body.innerHTML = 'Cargando...';
    modal.style.display = 'flex';
    try {
        const res = await fetch(`api/historial_detalle.php?tipo=cuenta&id=${id}`);
        const data = await res.json();
        if (data.ok) {
            body.innerHTML = renderDetalleCuenta(data);
        } else {
            body.innerHTML = '<p>Error al cargar el detalle</p>';
        }
    } catch {
        body.innerHTML = '<p>Error de conexión</p>';
    }
}

function renderDetalleCuenta(data) {
    const v = data.venta;
    const pago = data.pago;

    let pagoHtml = '';
    if (v.tipo_pago === 'conjunta') {
        const metodo = pago?.pagos?.[0]?.metodo || 'efectivo';
        pagoHtml = `<strong>Pago:</strong> Cuenta conjunta (${metodo})`;
    } else {
        const pagosStr = pago?.pagos?.map(p => `${p.nombre}: ${p.metodo} - $${parseFloat(p.subtotal).toFixed(2)}`).join('; ') || '';
        pagoHtml = `<strong>Pago:</strong> Cuentas separadas (${pagosStr})`;
    }

    const correosStr = (pago?.correos?.length) ? pago.correos.join(', ') : 'No se enviaron correos';

    const ticketHtml = data.ticket_existe
        ? `<a href="${data.ticket_url}" target="_blank" class="btn-submit" style="display:inline-block; margin-top:15px;">📄 Ver Ticket PDF</a>`
        : '<p class="hint" style="margin-top:15px;">Ticket no disponible</p>';

    return `
        <h3>Cuenta #${v.id_historial} - ${v.nombre_mesa}</h3>
        <p><strong>Mesero:</strong> ${v.nombre_mesero} | <strong>Personas:</strong> ${v.cantidad_personas}</p>
        <table class="data-table">
            <thead><tr><th>Cliente</th><th>Platillo</th><th>Cant.</th><th>P.Unit</th><th>Subtotal</th></tr></thead>
            <tbody>
                ${data.detalles.map(d => `
                    <tr><td>${escapeHtml(d.nombre_cliente)}</td><td>${escapeHtml(d.nombre_platillo)}</td><td>${d.cantidad}</td>
                    <td>$${parseFloat(d.precio_unitario).toFixed(2)}</td><td>$${parseFloat(d.subtotal).toFixed(2)}</td></tr>
                `).join('')}
            </tbody>
            <tfoot><tr><td colspan="4">Total</td><td>$${parseFloat(v.total_general).toFixed(2)}</td></tr></tfoot>
        </table>
        <p>${pagoHtml}</p>
        <p><strong>Correos:</strong> ${correosStr}</p>
        ${ticketHtml}
    `;
}

async function abrirModalPlatillos() {
    const modal = document.getElementById('detalleModal');
    const body = document.getElementById('detalleModalBody');
    body.innerHTML = `
        <h2>📊 Análisis de platillos y categorías</h2>
        <div class="modal-periodo-selector">
            <select id="modalPeriodoPlatillos">
                <option value="7d">Últimos 7 días</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Mes actual</option>
                <option value="personalizado">Personalizado</option>
            </select>
            <div id="personalizadoPlatillos" style="display:none; gap:10px;">
                <input type="date" id="platilloInicio" value="${fechaInicio || ''}">
                <input type="date" id="platilloFin" value="${fechaFin || ''}">
            </div>
            <button class="btn-submit" id="aplicarPeriodoPlatillos">Aplicar</button>
        </div>
        
        <div class="modal-dos-columnas">
            <!-- Columna Platillos -->
            <div class="modal-col">
                <div class="col-header">
                    <h3>🍽️ Platillos más vendidos</h3>
                    <div id="resumenPlatillos" class="resumen-mini"></div>
                </div>
                <div class="canvas-wrapper">
                    <canvas id="donutPlatillos"></canvas>
                </div>
                <div class="tabla-scroll">
                    <table class="tabla-mini" id="tablaPlatillos">
                        <thead><tr><th>Platillo</th><th>Cant.</th><th>%</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            
            <!-- Columna Categorías -->
            <div class="modal-col">
                <div class="col-header">
                    <h3>📂 Categorías</h3>
                </div>
                <div class="canvas-wrapper">
                    <canvas id="donutCategorias"></canvas>
                </div>
                <div class="tabla-scroll">
                    <table class="tabla-mini" id="tablaCategorias">
                        <thead><tr><th>Categoría</th><th>Cant.</th><th>%</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="evolucionContainer" style="margin-top:25px;"></div>
    `;
    modal.style.display = 'flex';
    
    document.getElementById('modalPeriodoPlatillos').addEventListener('change', togglePersonalizadoPlatillos);
    document.getElementById('aplicarPeriodoPlatillos').addEventListener('click', () => cargarDatosModalPlatillos());
    cargarDatosModalPlatillos();
}

function togglePersonalizadoPlatillos() {
    const tipo = document.getElementById('modalPeriodoPlatillos').value;
    document.getElementById('personalizadoPlatillos').style.display = tipo === 'personalizado' ? 'flex' : 'none';
}

async function cargarDatosModalPlatillos() {
    const periodo = document.getElementById('modalPeriodoPlatillos').value;
    let inicio, fin;
    const hoy = new Date();
    
    if (periodo === '7d') {
        fin = new Date().toISOString().split('T')[0];
        inicio = new Date(hoy.setDate(hoy.getDate() - 7)).toISOString().split('T')[0];
    } else if (periodo === 'semana') {
        inicio = new Date(hoy.setDate(hoy.getDate() - hoy.getDay() + 1)).toISOString().split('T')[0];
        fin = new Date(new Date(inicio).setDate(new Date(inicio).getDate() + 6)).toISOString().split('T')[0];
    } else if (periodo === 'mes') {
        inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
        fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().split('T')[0];
    } else if (periodo === 'personalizado') {
        inicio = document.getElementById('platilloInicio').value;
        fin = document.getElementById('platilloFin').value;
        if (!inicio || !fin) { alert('Selecciona ambas fechas'); return; }
    }
    
    const res = await fetch(`api/historial_kpis.php?action=donut&inicio=${inicio}&fin=${fin}`);
    const data = await res.json();
    
    // Corregir categoría "0"
    data.categorias = data.categorias.map(c => {
        if (c.categoria === '0') c.categoria = 'Sin categoría';
        return c;
    });
    
    const totalPlatillos = data.platillos.reduce((sum, p) => sum + parseInt(p.total), 0);
    const totalCategorias = data.categorias.reduce((sum, c) => sum + parseInt(c.total), 0);
    
    // Colores
    const coloresPlatillos = ['#8b0000','#a52a2a','#b22222','#dc143c','#cd5c5c','#e67e22','#f39c12','#16a085','#27ae60','#2980b9'];
    const coloresCategorias = ['#8b0000','#a52a2a','#cd5c5c','#e67e22','#f39c12','#16a085','#27ae60','#2980b9','#8e44ad','#d35400'];
    
    // --- DONA PLATILLOS (Top 8 + Otros) ---
    const TOP_N = 8;
    let platillosTop = data.platillos.slice(0, TOP_N);
    const sumaTop = platillosTop.reduce((sum, p) => sum + parseInt(p.total), 0);
    const hayOtros = data.platillos.length > TOP_N;
    if (hayOtros) {
        platillosTop.push({ nombre_platillo: 'Otros', total: totalPlatillos - sumaTop });
    }
    
    const ctxP = document.getElementById('donutPlatillos').getContext('2d');
    if (window.donutPlatillosChart) window.donutPlatillosChart.destroy();
    window.donutPlatillosChart = new Chart(ctxP, {
        type: 'doughnut',
        data: {
            labels: platillosTop.map(p => p.nombre_platillo),
            datasets: [{
                data: platillosTop.map(p => p.total),
                backgroundColor: platillosTop.map((p,i) => p.nombre_platillo==='Otros'?'#bdc3c7':coloresPlatillos[i%coloresPlatillos.length]),
                borderWidth: 0
            }]
        },
        options: {
            plugins: {
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} (${((ctx.raw/totalPlatillos)*100).toFixed(1)}%)` } },
                legend: { display: false }
            },
            onClick: (e, item) => {
                if (item.length) {
                    const nombre = platillosTop[item[0].index].nombre_platillo;
                    if (nombre !== 'Otros') mostrarEvolucionPlatillo(nombre, inicio, fin);
                }
            }
        }
    });
    
    // --- DONA CATEGORÍAS ---
    const ctxC = document.getElementById('donutCategorias').getContext('2d');
    if (window.donutCategoriasChart) window.donutCategoriasChart.destroy();
    window.donutCategoriasChart = new Chart(ctxC, {
        type: 'doughnut',
        data: {
            labels: data.categorias.map(c => c.categoria),
            datasets: [{
                data: data.categorias.map(c => c.total),
                backgroundColor: data.categorias.map((_,i) => coloresCategorias[i%coloresCategorias.length]),
                borderWidth: 0
            }]
        },
        options: {
            plugins: {
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} (${((ctx.raw/totalCategorias)*100).toFixed(1)}%)` } },
                legend: { display: false }
            }
        }
    });
    
    // --- RESUMEN Y TABLA PLATILLOS ---
    document.getElementById('resumenPlatillos').innerHTML = `
        <span class="resumen-cifra">${totalPlatillos}</span> unidades vendidas
    `;
    
    const tbodyP = document.querySelector('#tablaPlatillos tbody');
    tbodyP.innerHTML = '';
    data.platillos.slice(0, 10).forEach(p => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.onclick = () => mostrarEvolucionPlatillo(p.nombre_platillo, inicio, fin);
        tr.innerHTML = `
            <td>${escapeHtml(p.nombre_platillo)}</td>
            <td>${p.total}</td>
            <td>${((p.total/totalPlatillos)*100).toFixed(1)}%</td>
        `;
        tbodyP.appendChild(tr);
    });
    
    // --- TABLA CATEGORÍAS ---
    const tbodyC = document.querySelector('#tablaCategorias tbody');
    tbodyC.innerHTML = '';
    data.categorias.forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(c.categoria)}</td>
            <td>${c.total}</td>
            <td>${((c.total/totalCategorias)*100).toFixed(1)}%</td>
        `;
        tbodyC.appendChild(tr);
    });
}

async function mostrarEvolucionPlatillo(nombre, inicio, fin) {
    const res = await fetch(`api/historial_kpis.php?action=evolucion_platillo&nombre=${encodeURIComponent(nombre)}&inicio=${inicio}&fin=${fin}`);
    const data = await res.json();
    const container = document.getElementById('evolucionContainer');
    container.innerHTML = `<h4>Evolución de "${nombre}"</h4><canvas id="evolucionChart"></canvas>`;
    const ctx = document.getElementById('evolucionChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets: [{ label: 'Cantidad vendida', data: data.valores, borderColor: '#8b0000', tension: 0.1 }] }
    });
}

// ==================== MODAL DE MESEROS (NUEVA VERSIÓN CON EVOLUCIÓN COMPARATIVA) ====================
async function abrirModalMeseros() {
    const modal = document.getElementById('detalleModal');
    const body = document.getElementById('detalleModalBody');
    
    body.innerHTML = `
        <h2>📊 Evolución de ventas por mesero</h2>
        <div class="modal-periodo-selector">
            <select id="modalPeriodoMeserosEvo">
                <option value="7d">Últimos 7 días</option>
                <option value="semana" selected>Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="personalizado">Personalizado</option>
            </select>
            <div id="personalizadoMeseros" style="display:none; gap:10px;">
                <input type="date" id="meseroInicio" value="${fechaInicio || ''}">
                <input type="date" id="meseroFin" value="${fechaFin || ''}">
            </div>
            <button class="btn-submit" id="aplicarPeriodoMeserosEvo">Aplicar</button>
        </div>
        
        <div id="leyendaMeserosCheck" style="margin:15px 0; display:flex; flex-wrap:wrap; gap:12px;"></div>
        <canvas id="evolucionTodosMeserosChart" style="max-height:350px;"></canvas>
    `;
    
    modal.style.display = 'flex';
    
    document.getElementById('modalPeriodoMeserosEvo').addEventListener('change', togglePersonalizadoMeseros);
    document.getElementById('aplicarPeriodoMeserosEvo').addEventListener('click', () => cargarEvolucionMeseros());
    
    await cargarEvolucionMeseros();
}

function togglePersonalizadoMeseros() {
    const tipo = document.getElementById('modalPeriodoMeserosEvo').value;
    document.getElementById('personalizadoMeseros').style.display = tipo === 'personalizado' ? 'flex' : 'none';
}

let evolucionMeserosChart = null;

async function cargarEvolucionMeseros() {
    const periodo = document.getElementById('modalPeriodoMeserosEvo').value;
    let params = `action=evolucion_meseros_todos&periodo=${periodo}`;
    
    if (periodo === 'personalizado') {
        const inicio = document.getElementById('meseroInicio').value;
        const fin = document.getElementById('meseroFin').value;
        if (!inicio || !fin) {
            alert('Selecciona ambas fechas');
            return;
        }
        params += `&inicio=${inicio}&fin=${fin}`;
    }
    
    try {
        const res = await fetch(`api/historial_kpis.php?${params}`);
        const data = await res.json();
        if (!data.ok) throw new Error('Error en API');
        
        const leyendaDiv = document.getElementById('leyendaMeserosCheck');
        leyendaDiv.innerHTML = data.datasets.map((ds, index) => `
            <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
                <input type="checkbox" class="toggle-mesero" data-index="${index}" checked style="accent-color:${ds.borderColor};">
                <span style="color:${ds.borderColor}; font-weight:600;">${ds.label}</span>
            </label>
        `).join('');
        
        const ctx = document.getElementById('evolucionTodosMeserosChart').getContext('2d');
        if (evolucionMeserosChart) evolucionMeserosChart.destroy();
        
        evolucionMeserosChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: data.datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: $${ctx.raw.toLocaleString()}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                }
            }
        });
        
        document.querySelectorAll('.toggle-mesero').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const index = e.target.dataset.index;
                const meta = evolucionMeserosChart.getDatasetMeta(index);
                meta.hidden = !e.target.checked;
                evolucionMeserosChart.update();
            });
        });
        
    } catch (e) {
        console.error('Error cargando evolución de meseros:', e);
    }
}

// ==================== NUEVO MODAL DE INGRESOS DETALLADO (COMPARATIVA) ====================
async function abrirModalIngresosDetalle() {
    const modal = document.getElementById('detalleModal');
    const body = document.getElementById('detalleModalBody');
    
    body.innerHTML = `
        <h2>📈 Análisis de ingresos</h2>
        <div class="modal-periodo-selector">
            <select id="modalPeriodoIngresos">
                <option value="7d">Últimos 7 días</option>
                <option value="semana" selected>Esta semana vs semana pasada</option>
                <option value="mes">Este mes vs mes pasado</option>
                <option value="personalizado">Personalizado</option>
            </select>
            <div id="personalizadoIngresos" style="display:none; gap:10px;">
                <input type="date" id="ingresoInicio" value="${fechaInicio || ''}">
                <input type="date" id="ingresoFin" value="${fechaFin || ''}">
            </div>
            <button class="btn-submit" id="aplicarPeriodoIngresos">Aplicar</button>
        </div>
        
        <div style="display:flex; gap:20px; margin:20px 0;">
            <div class="metrica-card">
                <span>Período actual</span>
                <strong id="ingresoActualTotal">--</strong>
            </div>
            <div class="metrica-card">
                <span>Período anterior</span>
                <strong id="ingresoAnteriorTotal">--</strong>
            </div>
            <div class="metrica-card">
                <span>Variación</span>
                <strong id="ingresoVariacion">--</strong>
            </div>
        </div>
        
        <canvas id="comparativaIngresosChart" style="max-height:300px;"></canvas>
        <p id="comentarioIngresos" style="margin-top:20px; padding:12px; background:#f8f9fa; border-radius:12px;"></p>
    `;
    
    modal.style.display = 'flex';
    
    document.getElementById('modalPeriodoIngresos').addEventListener('change', togglePersonalizadoIngresos);
    document.getElementById('aplicarPeriodoIngresos').addEventListener('click', () => cargarComparativaIngresos());
    
    await cargarComparativaIngresos();
}

function togglePersonalizadoIngresos() {
    const tipo = document.getElementById('modalPeriodoIngresos').value;
    document.getElementById('personalizadoIngresos').style.display = tipo === 'personalizado' ? 'flex' : 'none';
}

async function cargarComparativaIngresos() {
    const periodo = document.getElementById('modalPeriodoIngresos').value;
    let params = `action=comparativa_ingresos&periodo=${periodo}`;
    
    if (periodo === 'personalizado') {
        const inicio = document.getElementById('ingresoInicio').value;
        const fin = document.getElementById('ingresoFin').value;
        if (!inicio || !fin) {
            alert('Selecciona ambas fechas');
            return;
        }
        params += `&inicio=${inicio}&fin=${fin}`;
    }
    
    try {
        const res = await fetch(`api/historial_kpis.php?${params}`);
        const data = await res.json();
        if (!data.ok) throw new Error('Error en API');
        
        document.getElementById('ingresoActualTotal').textContent = '$' + data.total_actual.toLocaleString('es-MX', { minimumFractionDigits: 2 });
        document.getElementById('ingresoAnteriorTotal').textContent = '$' + data.total_anterior.toLocaleString('es-MX', { minimumFractionDigits: 2 });
        
        const variacion = data.variacion;
        const variacionEl = document.getElementById('ingresoVariacion');
        variacionEl.textContent = (variacion > 0 ? '+' : '') + variacion.toFixed(1) + '%';
        variacionEl.style.color = variacion >= 0 ? '#16a085' : '#c0392b';
        
        const comentario = generarComentarioIngresos(data);
        document.getElementById('comentarioIngresos').innerHTML = comentario;
        
        renderComparativaChart(data);
        
    } catch (e) {
        console.error('Error cargando comparativa:', e);
    }
}

function generarComentarioIngresos(data) {
    const actual = data.total_actual;
    const anterior = data.total_anterior;
    const variacion = data.variacion;
    const periodoNombre = {
        '7d': 'últimos 7 días',
        'semana': 'esta semana',
        'mes': 'este mes',
        'personalizado': 'período seleccionado'
    }[document.getElementById('modalPeriodoIngresos').value] || 'período';
    
    let signo = variacion >= 0 ? '📈' : '📉';
    let comparativo = variacion >= 0 ? 'superior' : 'inferior';
    let diferencia = Math.abs(actual - anterior).toLocaleString('es-MX', { minimumFractionDigits: 2 });
    
    if (anterior === 0) {
        return `No hay datos del período anterior para comparar.`;
    }
    
    return `${signo} En ${periodoNombre} se facturó <strong>$${actual.toLocaleString()}</strong>, un <strong>${variacion.toFixed(1)}%</strong> ${comparativo} que el período anterior ($${anterior.toLocaleString()}). La diferencia es de $${diferencia}.`;
}

let comparativaChartInstance = null;
function renderComparativaChart(data) {
    const ctx = document.getElementById('comparativaIngresosChart').getContext('2d');
    if (comparativaChartInstance) comparativaChartInstance.destroy();
    
    comparativaChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Período actual',
                    data: data.valores_actual,
                    borderColor: '#8b0000',
                    backgroundColor: 'rgba(139, 0, 0, 0.05)',
                    borderWidth: 2,
                    tension: 0.2,
                    fill: true
                },
                {
                    label: 'Período anterior',
                    data: data.valores_anterior,
                    borderColor: '#7f8c8d',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 3],
                    tension: 0.2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: $${ctx.raw.toLocaleString()}`
                    }
                }
            }
        }
    });
}

// ==================== UTILIDADES ====================
function closeDetalleModal() {
    document.getElementById('detalleModal').style.display = 'none';
    modalHistorialAbierto = false;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[m]));
}

window.onclick = (e) => {
    if (e.target === document.getElementById('detalleModal')) closeDetalleModal();
    if (e.target === document.getElementById('modalDescargaPDF')) closeModalDescarga();
};