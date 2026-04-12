<?php
/**
 * Cerrar mesa, guardar historial, generar ticket PDF (con FPDF) y enviar por correo
 */

require_once __DIR__ . "/../../config/mesero_session.php"; // Define $conexion y sesión
require_once __DIR__ . "/../../vendor/autoload.php";        // PHPMailer y FPDF
require_once __DIR__ . "/../../config/mail_config.php";      // Credenciales SMTP
require_once __DIR__ . "/ticket_diseño.php";                // Plantilla del ticket

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);

// ========== LIMPIEZA TOTAL DEL BUFFER Y CONFIGURACIÓN DE ERRORES ==========
while (ob_get_level()) {
    ob_end_clean();
}
ini_set('display_errors', 0);    // No mostrar errores en pantalla (evita contaminar JSON)
ini_set('log_errors', 1);        // Pero guardarlos en el log para depuración

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión a BD
if (!isset($conexion)) {
    echo json_encode(['ok' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

// Leer JSON enviado desde el frontend
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'message' => 'No se recibieron datos']);
    exit;
}

$id_mesa = (int)($input['id_mesa'] ?? 0);
$tipo_pago = $input['tipo_pago'] ?? 'conjunta';
$pagos = $input['pagos'] ?? [];
$correos = $input['correos'] ?? [];

if (!$id_mesa) {
    echo json_encode(['ok' => false, 'message' => 'ID de mesa no válido']);
    exit;
}

$conexion->begin_transaction();

try {
    // 1. Validar que todos los pedidos estén entregados
    $stmt = $conexion->prepare("SELECT COUNT(*) as pendientes FROM pedidos WHERE id_mesa = ? AND estado != 'entregado'");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $pendientes = $res->fetch_assoc()['pendientes'];
    $stmt->close();
    if ($pendientes > 0) {
        throw new Exception("Hay $pendientes pedido(s) no entregados.");
    }

    // 2. Obtener resumen de consumos entregados
    $stmt = $conexion->prepare("
        SELECT 
            IFNULL(SUM(o.subtotal), 0) AS total_general,
            IFNULL(SUM(o.cantidad), 0) AS total_productos,
            MIN(p.fecha_creacion) AS fecha_apertura
        FROM ordenes o
        JOIN pedidos p ON o.id_pedido = p.id_pedido
        WHERE p.id_mesa = ? AND p.estado = 'entregado'
    ");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $summary = $res->fetch_assoc();
    $stmt->close();

    $total_general = round((float)$summary['total_general'], 2);
    $total_productos = (int)$summary['total_productos'];
    $fecha_apertura = $summary['fecha_apertura'] ?? date('Y-m-d H:i:s');

    // Cantidad de personas
    $stmt = $conexion->prepare("SELECT COUNT(*) AS cp FROM clientes_mesa WHERE id_mesa = ?");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $cp = $res->fetch_assoc();
    $cantidad_personas = (int)$cp['cp'];
    $stmt->close();

    // Nombre mesa y mesero
    $stmt = $conexion->prepare("
        SELECT m.nombre_mesa, me.nombre AS nombre_mesero
        FROM mesas m
        LEFT JOIN meseros me ON m.id_mesero_actual = me.id_mesero
        WHERE m.id_mesa = ?
    ");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $nombre_mesa = $row['nombre_mesa'] ?? ("Mesa " . $id_mesa);
    $nombre_mesero = $row['nombre_mesero'] ?? ($_SESSION['nombre_mesero'] ?? 'Mesero');
    $stmt->close();

    // 3. Insertar en historial_ventas
    $fecha_cierre = date('Y-m-d H:i:s');
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    $detalle_pago = json_encode([
        'tipo' => $tipo_pago,
        'pagos' => $pagos,
        'correos' => $correos
    ], JSON_UNESCAPED_UNICODE);

    $stmt = $conexion->prepare("
        INSERT INTO historial_ventas 
        (nombre_mesa, nombre_mesero, cantidad_personas, total_productos, tipo_pago, total_general, fecha_apertura, fecha_cierre, fecha, hora, detalle_pago)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssiisdsssss", $nombre_mesa, $nombre_mesero, $cantidad_personas, $total_productos, $tipo_pago, $total_general, $fecha_apertura, $fecha_cierre, $fecha, $hora, $detalle_pago);
    $stmt->execute();
    $id_historial = $stmt->insert_id;
    $stmt->close();

    // 4. Insertar detalle histórico (con categoría NOMBRE)
    $stmt = $conexion->prepare("
        SELECT 
            cm.nombre AS nombre_cliente,
            p.nombre AS nombre_platillo,
            cat.nombre AS categoria,
            o.cantidad,
            o.precio_unitario,
            o.subtotal
        FROM ordenes o
        JOIN pedidos ped ON o.id_pedido = ped.id_pedido
        JOIN clientes_mesa cm ON o.id_cliente = cm.id_cliente
        JOIN platillos p ON o.id_platillo = p.id_platillo
        JOIN categorias_platillo cat ON p.id_categoria = cat.id_categoria
        WHERE ped.id_mesa = ? AND ped.estado = 'entregado'
    ");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $res = $stmt->get_result();
    $ins_stmt = $conexion->prepare("
        INSERT INTO historial_detalle 
        (id_historial, nombre_cliente, nombre_platillo, categoria, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $detalles_ticket = [];
    while ($row = $res->fetch_assoc()) {
        $ins_stmt->bind_param("isssidd", $id_historial, $row['nombre_cliente'], $row['nombre_platillo'], $row['categoria'], $row['cantidad'], $row['precio_unitario'], $row['subtotal']);
        $ins_stmt->execute();
        $detalles_ticket[] = $row;
    }
    $ins_stmt->close();
    $stmt->close();

    // 5. Limpiar la mesa (órdenes, pedidos, clientes, liberar mesa)
    $stmt = $conexion->prepare("DELETE o FROM ordenes o JOIN pedidos p ON o.id_pedido = p.id_pedido WHERE p.id_mesa = ?");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare("DELETE FROM pedidos WHERE id_mesa = ?");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare("DELETE FROM clientes_mesa WHERE id_mesa = ?");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare("UPDATE mesas SET estado = 0, id_mesero_actual = NULL WHERE id_mesa = ?");
    $stmt->bind_param("i", $id_mesa);
    $stmt->execute();
    $stmt->close();

    // Confirmar transacción
    $conexion->commit();

    // ========== GENERAR PDF CON FPDF Y ENVIAR CORREOS ==========
    $pdf_path = null;
    $email_errors = [];

    try {
        // Crear carpeta tickets/ si no existe
        $ticket_dir = __DIR__ . "/../../tickets/";
        if (!is_dir($ticket_dir)) {
            mkdir($ticket_dir, 0755, true);
        }

        // Generar ticket PDF usando la plantilla externa
        $pdf_path = generarTicketPDF(
            $nombre_mesa,
            $nombre_mesero,
            $fecha_cierre,
            $total_general,
            $detalles_ticket,
            $pagos,
            $tipo_pago,
            $id_historial,
            $ticket_dir
        );

        // Enviar correos si hay destinatarios y credenciales definidas
        if (!empty($correos) && defined('SMTP_USER')) {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->isHTML(true);
            
            // Asunto del correo
            $mail->Subject = "Ticket de consumo - " . limpiarTexto($nombre_mesa);

            // Cuerpo HTML con diseño mejorado
            $mail->Body = '
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Ticket de consumo</title>
            </head>
            <body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f4; padding:20px 0;">
                    <tr>
                        <td align="center">
                            <table width="550" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); overflow:hidden;">
                                <!-- Encabezado -->
                                <tr>
                                    <td style="background-color:#8B0000; padding:25px 20px; text-align:center;">
                                        <h1 style="color:#ffffff; margin:0; font-size:28px; font-weight:bold; letter-spacing:1px;">🍽️ EL BUEN SAZÓN</h1>
                                        <p style="color:#f0e6d2; margin:5px 0 0; font-size:16px;">Calle Ficticia 123, Ciudad</p>
                                    </td>
                                </tr>
                                <!-- Cuerpo -->
                                <tr>
                                    <td style="padding:30px 25px;">
                                        <h2 style="color:#8B0000; margin-top:0; margin-bottom:15px; font-size:22px;">¡Gracias por su visita!</h2>
                                        <p style="color:#333333; font-size:15px; line-height:1.5; margin-bottom:20px;">
                                            Apreciamos su preferencia. Adjunto encontrará el ticket correspondiente a su consumo en <strong>' . limpiarTexto($nombre_mesa) . '</strong>.
                                        </p>
                                        
                                        <!-- Información de la mesa -->
                                        <table width="100%" cellpadding="8" cellspacing="0" style="background-color:#fafafa; border-radius:8px; margin-bottom:20px;">
                                            <tr>
                                                <td style="border-bottom:1px solid #eeeeee;"><strong style="color:#8B0000;">Mesa:</strong></td>
                                                <td style="border-bottom:1px solid #eeeeee;">' . limpiarTexto($nombre_mesa) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="border-bottom:1px solid #eeeeee;"><strong style="color:#8B0000;">Mesero:</strong></td>
                                                <td style="border-bottom:1px solid #eeeeee;">' . limpiarTexto($nombre_mesero) . '</td>
                                            </tr>
                                            <tr>
                                                <td><strong style="color:#8B0000;">Fecha y hora:</strong></td>
                                                <td>' . date('d/m/Y H:i', strtotime($fecha_cierre)) . '</td>
                                            </tr>
                                        </table>
                                        
                                        <p style="color:#555555; font-size:14px; line-height:1.5; margin-bottom:15px;">
                                            Si tiene alguna duda sobre su ticket, no dude en contactarnos respondiendo a este correo o llamando al <strong>(555) 123-4567</strong>.
                                        </p>
                                        
                                        <p style="color:#555555; font-size:14px; line-height:1.5; margin-bottom:25px;">
                                            <em>Nota: La propina sugerida (15%) está indicada en el ticket adjunto.</em>
                                        </p>
                                        
                                        <p style="color:#777777; font-size:13px; margin-top:20px; border-top:1px solid #eeeeee; padding-top:20px;">
                                            Este correo fue generado automáticamente. Por favor no responda a esta dirección a menos que tenga una consulta específica.
                                        </p>
                                    </td>
                                </tr>
                                <!-- Pie -->
                                <tr>
                                    <td style="background-color:#2d2d2d; padding:15px 20px; text-align:center;">
                                        <p style="color:#bbbbbb; font-size:12px; margin:0;">
                                            &copy; ' . date('Y') . ' Restaurante El Buen Sazón. Todos los derechos reservados.<br>
                                            <a href="#" style="color:#dddddd; text-decoration:none;">www.elbuensazon.com</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ';
            $mail->AltBody = "Gracias por su visita. Adjuntamos el ticket de su consumo en " . $nombre_mesa . ". Fecha: " . date('d/m/Y H:i', strtotime($fecha_cierre));

            // Adjuntar PDF
            $nombre_archivo = "ticket_mesa_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_mesa) . "_{$id_historial}.pdf";
            if (file_exists($pdf_path) && is_readable($pdf_path)) {
                $mail->addAttachment($pdf_path, $nombre_archivo);
            }

            foreach ($correos as $email) {
                $mail->addAddress(trim($email));
            }
            $mail->send();
        }
    } catch (Exception $e) {
        $email_errors[] = $e->getMessage();
        error_log("Error en PDF/Correo: " . $e->getMessage());
    }

    // Respuesta exitosa
    echo json_encode([
        'ok' => true,
        'message' => 'Mesa cerrada correctamente.',
        'pdf_generado' => $pdf_path ? basename($pdf_path) : null,
        'correos_enviados' => count($correos),
        'errores_correo' => $email_errors
    ]);

} catch (Exception $e) {
    $conexion->rollback();
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>