<?php
/**
 * Plantilla de ticket PDF con FPDF
 * Soporta cuenta conjunta y cuentas separadas
 */

function generarTicketPDF($nombre_mesa, $nombre_mesero, $fecha_cierre, $total_general, $detalles, $pagos, $tipo_pago, $id_historial, $ticket_dir) {
    // Crear PDF angosto 80mm
    $pdf = new FPDF('P', 'mm', array(80, 297));
    $pdf->AddPage();
    $pdf->SetMargins(4, 4, 4);
    $pdf->SetAutoPageBreak(true, 10);

    // Fuente monoespaciada (efecto ticket)
    $pdf->SetFont('Courier', '', 10);
    $pdf->Cell(0, 5, 'RESTAURANTE EL BUEN SAZON', 0, 1, 'C');
    $pdf->SetFont('Courier', '', 8);
    $pdf->Cell(0, 4, 'Calle Ficticia 123, Ciudad', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Tel: (555) 123-4567', 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->Cell(0, 0, '', 'T');
    $pdf->Ln(3);

    // Datos de la mesa
    $pdf->SetFont('Courier', '', 8);
    $pdf->Cell(25, 4, 'Mesa:', 0, 0);
    $pdf->Cell(0, 4, limpiarTexto($nombre_mesa), 0, 1);
    $pdf->Cell(25, 4, 'Mesero:', 0, 0);
    $pdf->Cell(0, 4, limpiarTexto($nombre_mesero), 0, 1);
    $pdf->Cell(25, 4, 'Fecha:', 0, 0);
    $fecha_cierre_obj = new DateTime($fecha_cierre);
    $fecha_cierre_obj->setTimezone(new DateTimeZone('America/Mexico_City')); // Ajusta zona horaria
    $pdf->Cell(0, 4, $fecha_cierre_obj->format('d/m/Y H:i'), 0, 1);
    $pdf->Ln(2);

    if ($tipo_pago == 'conjunta') {
        // ========== CUENTA CONJUNTA ==========
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Cell(10, 5, 'CANT', 0, 0, 'L');
        $pdf->Cell(42, 5, 'DESCRIPCION', 0, 0, 'L');
        $pdf->Cell(20, 5, 'IMPORTE', 0, 1, 'R');
        $pdf->Cell(0, 0, '', 'T');
        $pdf->Ln(2);

        $pdf->SetFont('Courier', '', 8);
        foreach ($detalles as $item) {
            $nombre = limpiarTexto($item['nombre_platillo']);
            $cantidad = $item['cantidad'];
            $subtotal = number_format($item['subtotal'], 2);
            
            if (strlen($nombre) > 22) {
                $nombre = substr($nombre, 0, 19) . '...';
            }
            
            $pdf->Cell(10, 4, $cantidad, 0, 0, 'L');
            $pdf->Cell(42, 4, $nombre, 0, 0, 'L');
            $pdf->Cell(20, 4, '$' . $subtotal, 0, 1, 'R');
        }
        
        // --- Desglose por cliente (NUEVO) ---
        // Agrupar items por cliente
        $clientesItems = [];
        foreach ($detalles as $item) {
            $cliente = $item['nombre_cliente'];
            if (!isset($clientesItems[$cliente])) {
                $clientesItems[$cliente] = [];
            }
            $clientesItems[$cliente][] = $item;
        }
        
        $pdf->Ln(2);
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Cell(0, 5, 'CONSUMO POR CLIENTE', 0, 1, 'C');
        $pdf->Ln(1);
        
        foreach ($clientesItems as $nombreCliente => $items) {
            $subtotalCliente = 0;
            foreach ($items as $it) {
                $subtotalCliente += $it['subtotal'];
            }
            $pdf->SetFont('Courier', '', 8);
            $pdf->Cell(52, 4, limpiarTexto($nombreCliente), 0, 0, 'L');
            $pdf->Cell(20, 4, '$' . number_format($subtotalCliente, 2), 0, 1, 'R');
        }
        
        $pdf->Ln(2);
        $pdf->Cell(0, 0, '', 'T');
        $pdf->Ln(3);
        
        // Total
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->Cell(52, 6, 'TOTAL:', 0, 0, 'R');
        $pdf->Cell(20, 6, '$' . number_format($total_general, 2), 0, 1, 'R');
        $pdf->Ln(1);
        
        // Método de pago
        $pdf->SetFont('Courier', '', 8);
        $metodo = $pagos[0]['metodo'] ?? 'efectivo';
        $pdf->Cell(0, 4, 'Forma de pago: ' . ucfirst($metodo), 0, 1);
        
        // Propina sugerida con estilo mejorado
        $propina = $total_general * 0.15;
        $pdf->Ln(2);
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Cell(0, 5, '+---------------------------+', 0, 1, 'C');
        $pdf->Cell(0, 5, '|  Propina sugerida 15%: $' . str_pad(number_format($propina, 2), 7, ' ', STR_PAD_LEFT) . '  |', 0, 1, 'C');
        $pdf->Cell(0, 5, '+---------------------------+', 0, 1, 'C');
        
    } else {
        // ========== CUENTAS SEPARADAS ==========
        $clientesItems = [];
        foreach ($detalles as $item) {
            $cliente = $item['nombre_cliente'];
            if (!isset($clientesItems[$cliente])) {
                $clientesItems[$cliente] = [];
            }
            $clientesItems[$cliente][] = $item;
        }
        
        $pdf->SetFont('Courier', 'B', 9);
        $pdf->Cell(0, 5, 'CUENTAS SEPARADAS', 0, 1, 'C');
        $pdf->Ln(2);
        
        foreach ($clientesItems as $nombreCliente => $items) {
            $subtotalCliente = 0;
            foreach ($items as $it) {
                $subtotalCliente += $it['subtotal'];
            }
            
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->Cell(0, 5, limpiarTexto($nombreCliente), 0, 1, 'L');
            $pdf->SetFont('Courier', '', 8);
            
            $pdf->Cell(10, 4, 'CANT', 0, 0, 'L');
            $pdf->Cell(42, 4, 'DESCRIPCION', 0, 0, 'L');
            $pdf->Cell(20, 4, 'IMPORTE', 0, 1, 'R');
            
            foreach ($items as $item) {
                $nombre = limpiarTexto($item['nombre_platillo']);
                $cantidad = $item['cantidad'];
                $subtotal = number_format($item['subtotal'], 2);
                
                if (strlen($nombre) > 22) {
                    $nombre = substr($nombre, 0, 19) . '...';
                }
                
                $pdf->Cell(10, 4, $cantidad, 0, 0, 'L');
                $pdf->Cell(42, 4, $nombre, 0, 0, 'L');
                $pdf->Cell(20, 4, '$' . $subtotal, 0, 1, 'R');
            }
            
            $pdf->SetFont('Courier', 'B', 8);
            $pdf->Cell(52, 4, 'Subtotal:', 0, 0, 'R');
            $pdf->Cell(20, 4, '$' . number_format($subtotalCliente, 2), 0, 1, 'R');
            
            $metodoCliente = '';
            foreach ($pagos as $p) {
                if (isset($p['nombre']) && $p['nombre'] == $nombreCliente) {
                    $metodoCliente = $p['metodo'];
                    break;
                }
            }
            $pdf->SetFont('Courier', '', 8);
            $pdf->Cell(0, 4, 'Pago: ' . ucfirst($metodoCliente ?: 'efectivo'), 0, 1, 'L');
            $pdf->Ln(3);
        }
        
        $pdf->Cell(0, 0, '', 'T');
        $pdf->Ln(3);
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->Cell(52, 6, 'TOTAL GENERAL:', 0, 0, 'R');
        $pdf->Cell(20, 6, '$' . number_format($total_general, 2), 0, 1, 'R');
        
        // Propina sugerida con estilo mejorado
        $propina = $total_general * 0.15;
        $pdf->Ln(2);
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Cell(0, 5, '+---------------------------+', 0, 1, 'C');
        $pdf->Cell(0, 5, '|  Propina sugerida 15%: $' . str_pad(number_format($propina, 2), 7, ' ', STR_PAD_LEFT) . '  |', 0, 1, 'C');
        $pdf->Cell(0, 5, '+---------------------------+', 0, 1, 'C');
    }

    // Pie de ticket
    $pdf->Ln(4);
    $pdf->SetFont('Courier', 'I', 8);
    $pdf->Cell(0, 4, '!Gracias por su visita!', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Este ticket es su comprobante', 0, 0, 'C');

    // Guardar archivo
    $pdf_filename = "ticket_mesa_{$nombre_mesa}_{$id_historial}.pdf";
    $pdf_path = $ticket_dir . $pdf_filename;
    $pdf->Output('F', $pdf_path);
    
    return $pdf_path;
}

/**
 * Limpia texto para evitar caracteres extraños en FPDF
 */
function limpiarTexto($texto) {
    // Convertir a ISO-8859-1//TRANSLIT para eliminar caracteres no soportados
    $texto = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    // Si falla, intentar con windows-1252
    if ($texto === false) {
        $texto = iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    }
    return $texto ?: '';
}
?>