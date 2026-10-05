<?php
// Archivo: tablaFacturasUsuario.php (REPARADO)
// Mantiene tu lógica, pero corrige inconsistencia de nombre/ruta del Excel y evita respuestas rotas en AJAX.

require_once 'utilidades.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer para que notices/warnings no contaminen JSON
if (!ob_get_level()) {
    ob_start();
}

// JSON helper (sin redeclare)
if (!function_exists('responder_json')) {
    function responder_json(array $payload, int $code = 200): void
    {
        if (ob_get_length()) { @ob_end_clean(); }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

function es_post(): bool {
    return (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
}

// Autoloader
require_once 'vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Conectar BD
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) { @$conexion->set_charset('utf8mb4'); }

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    if (es_post()) {
        responder_json(['status' => 'error', 'message' => 'Sesión expirada. Inicia sesión nuevamente.'], 401);
    }
    header("Location: index.php");
    exit();
}

$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? '';
$correo_usuario = $_SESSION['correo'] ?? '';

// Monto caja (si no existe, queda 0)
$montoCaja = 0;
$stmtCaja = $conexion->prepare("SELECT montoCaja FROM usuarios WHERE nombre = ?");
if ($stmtCaja) {
    $stmtCaja->bind_param("s", $nombre_usuario);
    $stmtCaja->execute();
    $stmtCaja->bind_result($montoCaja);
    $stmtCaja->fetch();
    $stmtCaja->close();
}

// Timezone
date_default_timezone_set('America/Guatemala');

// Obtener lote más reciente
$lote = '';
$stmtLote = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC LIMIT 1");
if ($stmtLote) {
    $stmtLote->bind_param("s", $nombre_usuario);
    $stmtLote->execute();
    $resLote = $stmtLote->get_result();
    $stmtLote->close();
    if ($resLote && $resLote->num_rows > 0) {
        $lote = (string)$resLote->fetch_assoc()['nombre_lote'];
    }
}

// Traer facturas del lote actual (para la vista GET)
$sql = "SELECT * FROM facturas WHERE usuario = ? AND lote = ? ORDER BY fecha_documento DESC";
$stmt = $conexion->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ss", $nombre_usuario, $lote);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

// ---------- Helpers ----------
function nombre_usuario_formateado(string $u): string {
    return preg_replace('/[\/\\\\:*?"<>| ]/', '_', $u);
}
function lote_formateado(string $l): string {
    return preg_replace('/[\/\\\\:*?"<>| ]/', '_', $l);
}
function ruta_pdf_unificado(string $usuario, string $lote): string {
    $u = nombre_usuario_formateado($usuario);
    $l = lote_formateado($lote);
    return __DIR__ . "/unificadosPDF/{$u}/facturasadjuntas_{$u}_{$l}.pdf";
}
function ruta_excel_lote(string $usuario, string $lote): string {
    $u = nombre_usuario_formateado($usuario);
    $l = lote_formateado($lote);
    return __DIR__ . "/facturasExcel/{$u}/CajaChica_{$u}_{$l}.xlsx";
}

// Función para generar una fila HTML
function generarFilaFactura($row)
{
    $nombreUsuarioFormateado = nombre_usuario_formateado((string)$row['usuario']);
    $loteFormateado = lote_formateado((string)$row['lote']);

    $rutaArchivoPDF   = __DIR__ . "/unificadosPDF/{$nombreUsuarioFormateado}/facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivoExcel = __DIR__ . "/facturasExcel/{$nombreUsuarioFormateado}/CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $existePDF   = file_exists($rutaArchivoPDF);
    $existeExcel = file_exists($rutaArchivoExcel);

    $html = "<tr id='fila-" . (int)$row["id"] . "'>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["usuario"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["descripcion"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["cuenta_mayor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["nombre_cuenta_mayor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["sucursal"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["nombre_cc"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["indicador_impuestos"]) . "</td>";
    $html .= "<td class='border p-1 text-sm valor-factura'>" . htmlspecialchars((string)$row["valor_factura"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["sujeto_retencion"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["tipo_compraventa"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["nit_proveedor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["nombre_proveedor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["serie_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["numero_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["fecha_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["tipo_combustible"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["galones"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars((string)$row["total_factura_combustible"]) . "</td>";

    if (!empty($row['ruta_archivo_factura'])) {
        $archivoUrl = htmlspecialchars((string)$row['ruta_archivo_factura']);
        $html .= "<td class='border px-4 py-2'>
            <div class='flex flex-col space-y-2'>
                <button class='bg-blue-500 text-white px-2 py-0.5 text-sm rounded modal-btn' data-url='$archivoUrl'>
                    Previsualizar archivo
                </button>";

        if (!$existePDF || !$existeExcel) {
            $html .= "<button class='bg-red-500 text-white px-2 py-0.5 text-sm rounded eliminar-archivo' data-id='" . (int)$row['id'] . "'>
                        Eliminar
                    </button>";
        }

        $html .= "</div></td>";
    } else {
        $html .= "<td class='border px-4 py-2'>
            <button class='bg-green-500 text-white px-2 py-0.5 rounded modal-subir' data-id='" . (int)$row['id'] . "'>
                Subir archivo
            </button>
          </td>";
    }

    if (!$existePDF || !$existeExcel) {
        $html .= "<td class='border px-4 py-2'>
            <button class='bg-red-500 text-white px-1 py-0.5 text-sm rounded eliminar-factura' data-id='" . (int)$row['id'] . "'>
                Eliminar factura
            </button>
          </td>";
    } else {
        $html .= "<td class='border px-4 py-2 text-gray-400'>Acciones bloqueadas</td>";
    }

    $html .= "</tr>";
    return $html;
}

// ===========================
// POST: subir archivo factura
// ===========================
if (es_post() && isset($_FILES['archivo']) && isset($_POST['factura_id'])) {
    $archivo    = $_FILES['archivo'];
    $factura_id = (int)$_POST['factura_id'];

    $nombreUsuario = $_SESSION['usuario'] ?? 'usuario';
    $nombreLoteFormateado = lote_formateado($lote);
    $fechaActual = date('Y-m-d_g-i-sA');

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nuevoNombreArchivo = $nombreUsuario . '_' . $fechaActual . '.' . $extension;

    $carpetaFacturas = __DIR__ . '/facturas/' . $nombreUsuario . '/' . $nombreLoteFormateado;
    if (!file_exists($carpetaFacturas)) {
        if (!mkdir($carpetaFacturas, 0777, true)) {
            responder_json(['status' => 'error', 'message' => 'No se pudo crear la carpeta para el lote actual.'], 500);
        }
    }

    $rutaDestino = $carpetaFacturas . '/' . $nuevoNombreArchivo;

    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'pdf'];

    $tipoArchivo = @mime_content_type($archivo['tmp_name']);
    if (!in_array($tipoArchivo, $tiposPermitidos) || !in_array($extension, $extensionesPermitidas)) {
        responder_json(['status' => 'error', 'message' => 'Formato de archivo no permitido.'], 400);
    }

    if ($extension === 'pdf') {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($archivo['tmp_name']);

            $cartaAncho = 215.9;
            $cartaAlto  = 279.4;

            for ($i = 1; $i <= $pageCount; $i++) {
                $pdf->AddPage('P', [$cartaAncho, $cartaAlto]);
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplIdx);

                $scale = min($cartaAncho / $size['width'], $cartaAlto / $size['height']);
                $scaledWidth = $size['width'] * $scale;
                $scaledHeight = $size['height'] * $scale;

                $x = ($cartaAncho - $scaledWidth) / 2;
                $y = ($cartaAlto - $scaledHeight) / 2;

                $pdf->useTemplate($tplIdx, $x, $y, $scaledWidth, $scaledHeight);
            }

            $pdf->Output($rutaDestino, 'F');
        } catch (Exception $e) {
            responder_json([
                'status' => 'error',
                'message' => 'Error al procesar el PDF. Si da incompatibilidad, adjunta la factura como imagen.'
            ], 500);
        }
    } else {
        $compresionExitosa = false;
        $imagen = null;

        if ($extension === 'jpg' || $extension === 'jpeg') {
            $imagen = @imagecreatefromjpeg($archivo['tmp_name']);
            if ($imagen) {
                $imagenRedimensionada = imagescale($imagen, imagesx($imagen) * 0.5, imagesy($imagen) * 0.5);
                $compresionExitosa = imagejpeg($imagenRedimensionada, $rutaDestino, 70);
            }
        } elseif ($extension === 'png') {
            $imagen = @imagecreatefrompng($archivo['tmp_name']);
            if ($imagen) {
                $imagenRedimensionada = imagescale($imagen, imagesx($imagen) * 0.5, imagesy($imagen) * 0.5);
                $compresionExitosa = imagepng($imagenRedimensionada, $rutaDestino, 7);
            }
        }

        if ($compresionExitosa) {
            if ($imagen) imagedestroy($imagen);
            if (isset($imagenRedimensionada)) imagedestroy($imagenRedimensionada);
        } else {
            responder_json(['status' => 'error', 'message' => 'Error al comprimir la imagen.'], 500);
        }
    }

    $rutaArchivoBD = 'facturas/' . $nombreUsuario . '/' . $nombreLoteFormateado . '/' . $nuevoNombreArchivo;
    $stmtUp = $conexion->prepare("UPDATE facturas SET ruta_archivo_factura = ? WHERE id = ?");
    $stmtUp->bind_param("si", $rutaArchivoBD, $factura_id);
    $stmtUp->execute();
    $stmtUp->close();

    $stmtSel = $conexion->prepare("SELECT * FROM facturas WHERE id = ?");
    $stmtSel->bind_param("i", $factura_id);
    $stmtSel->execute();
    $resSel = $stmtSel->get_result();
    $stmtSel->close();

    if ($resSel && $resSel->num_rows > 0) {
        $row = $resSel->fetch_assoc();
        responder_json(['status' => 'success', 'message' => 'Archivo subido exitosamente.', 'html' => generarFilaFactura($row)]);
    }

    responder_json(['status' => 'error', 'message' => 'No se pudo refrescar la factura.'], 500);
}

// ================================
// POST: eliminar factura / archivo
// ================================
if (es_post() && isset($_POST['eliminar_factura']) && isset($_POST['factura_id'])) {
    $factura_id = (int)$_POST['factura_id'];

    $stmt = $conexion->prepare("SELECT ruta_archivo_factura FROM facturas WHERE id = ?");
    $stmt->bind_param("i", $factura_id);
    $stmt->execute();
    $stmt->bind_result($rutaArchivo);
    $stmt->fetch();
    $stmt->close();

    if ($rutaArchivo) {
        $rutaAbs = __DIR__ . '/' . ltrim($rutaArchivo, '/');
        if (file_exists($rutaAbs)) { @unlink($rutaAbs); }
    }

    $stmt = $conexion->prepare("DELETE FROM facturas WHERE id = ?");
    $stmt->bind_param("i", $factura_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        responder_json(['status' => 'success', 'message' => 'Factura y archivo eliminados exitosamente.']);
    }

    $stmt->close();
    responder_json(['status' => 'error', 'message' => 'Error al eliminar la factura.'], 500);
}

if (es_post() && isset($_POST['eliminar_archivo']) && isset($_POST['factura_id'])) {
    $factura_id = (int)$_POST['factura_id'];

    $stmt = $conexion->prepare("SELECT ruta_archivo_factura FROM facturas WHERE id = ?");
    $stmt->bind_param("i", $factura_id);
    $stmt->execute();
    $stmt->bind_result($rutaArchivo);
    $stmt->fetch();
    $stmt->close();

    if ($rutaArchivo) {
        $rutaAbs = __DIR__ . '/' . ltrim($rutaArchivo, '/');
        if (file_exists($rutaAbs)) { @unlink($rutaAbs); }
    }

    $stmt = $conexion->prepare("UPDATE facturas SET ruta_archivo_factura = NULL WHERE id = ?");
    $stmt->bind_param("i", $factura_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexion->prepare("SELECT * FROM facturas WHERE id = ?");
    $stmt->bind_param("i", $factura_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        responder_json(['status' => 'success', 'message' => 'Archivo eliminado.', 'html' => generarFilaFactura($row)]);
    }

    responder_json(['status' => 'error', 'message' => 'No se pudo refrescar la factura.'], 500);
}

// =====================================
// POST: filtrar por lote
// =====================================
if (es_post() && isset($_POST['lote']) && !isset($_POST['combinar_archivos'])) {
    $loteSeleccionado = (string)$_POST['lote'];

    $sqlF = "SELECT * FROM facturas WHERE usuario = ? AND lote = ? ORDER BY fecha_documento DESC";
    $stmtF = $conexion->prepare($sqlF);
    $stmtF->bind_param("ss", $nombre_usuario, $loteSeleccionado);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    $stmtF->close();

    $htmlRows = '';
    if ($resF) {
        while ($row = $resF->fetch_assoc()) {
            $htmlRows .= generarFilaFactura($row);
        }
    }

    responder_json(['status' => 'success', 'html' => $htmlRows]);
}

// ================================
// POST: guardar excel en servidor
// ✅ FIX: guardar EXACTAMENTE con el nombre/ruta que luego verificas
// ================================
if (es_post() && isset($_POST['guardar_excel']) && isset($_FILES['archivo'])) {

    if (trim($lote) === '') {
        responder_json(['status' => 'error', 'message' => 'No hay lote activo para guardar el Excel.'], 400);
    }

    $nombreUsuarioFormateado = nombre_usuario_formateado($nombre_usuario);
    $loteFormateado = lote_formateado($lote);

    $carpetaFacturasExcel = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    if (!file_exists($carpetaFacturasExcel)) {
        if (!mkdir($carpetaFacturasExcel, 0777, true)) {
            responder_json(['status' => 'error', 'message' => 'No se pudo crear la carpeta para el usuario.'], 500);
        }
    }

    // Nombre estándar esperado por verificar_existencia_archivos
    $nombreFinal = "CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $rutaDestino = $carpetaFacturasExcel . '/' . $nombreFinal;

    $tmp = $_FILES['archivo']['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        responder_json(['status' => 'error', 'message' => 'Archivo Excel inválido o no subido correctamente.'], 400);
    }

    if (move_uploaded_file($tmp, $rutaDestino)) {
        responder_json(['status' => 'success', 'message' => 'Excel guardado exitosamente en el servidor.', 'archivo' => $nombreFinal]);
    } else {
        responder_json(['status' => 'error', 'message' => 'Error al guardar el Excel.'], 500);
    }
}

if (es_post() && isset($_POST['verificar_existencia_excel']) && isset($_POST['fileName'])) {
    $nombreUsuarioFormateado = nombre_usuario_formateado($nombre_usuario);
    $directory = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    $filePath = $directory . '/' . (string)$_POST['fileName'];
    responder_json(['existe' => file_exists($filePath)]);
}

// =============================
// Combinar archivos PDF/Imagen
// =============================
function combinarArchivosPDF($nombreUsuario, $lote, $archivos): array
{
    $rutaArchivo = ruta_pdf_unificado($nombreUsuario, $lote);
    $carpetaPDF = dirname($rutaArchivo);

    if (!file_exists($carpetaPDF)) {
        mkdir($carpetaPDF, 0777, true);
    }

    if (file_exists($rutaArchivo)) { @unlink($rutaArchivo); }

    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);
    $cartaAncho = 215.9;
    $cartaAlto = 279.4;

    foreach ($archivos as $archivo) {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $pageCount = $pdf->setSourceFile($archivo);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplIdx);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pageWidth  = ($orientation === 'P') ? $cartaAncho : $cartaAlto;
                $pageHeight = ($orientation === 'P') ? $cartaAlto  : $cartaAncho;

                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $scale = min($pageWidth / $size['width'], $pageHeight / $size['height'], 1);
                $scaledWidth = $size['width'] * $scale;
                $scaledHeight = $size['height'] * $scale;

                $x = ($pageWidth - $scaledWidth) / 2;
                $y = ($pageHeight - $scaledHeight) / 2;

                $pdf->useTemplate($tplIdx, $x, $y, $scaledWidth, $scaledHeight);
            }
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $info = @getimagesize($archivo);
            if (!$info) { continue; }
            [$width, $height] = $info;

            $dpi = 96;
            $width_mm = ($width / $dpi) * 25.4;
            $height_mm = ($height / $dpi) * 25.4;
            $orientation = ($width_mm > $height_mm) ? 'L' : 'P';

            $pageWidth  = ($orientation === 'P') ? $cartaAncho : $cartaAlto;
            $pageHeight = ($orientation === 'P') ? $cartaAlto  : $cartaAncho;

            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            $scale = min($pageWidth / $width_mm, $pageHeight / $height_mm, 1);
            $scaledWidth = $width_mm * $scale;
            $scaledHeight = $height_mm * $scale;

            $x = ($pageWidth - $scaledWidth) / 2;
            $y = ($pageHeight - $scaledHeight) / 2;

            $pdf->Image($archivo, $x, $y, $scaledWidth, $scaledHeight);
        }
    }

    $pdf->Output($rutaArchivo, 'F');

    return [
        'status' => 'success',
        'message' => 'Archivo combinado guardado exitosamente.',
        'ruta' => $rutaArchivo
    ];
}

if (es_post() && isset($_POST['combinar_archivos'])) {

    if (trim($lote) === '') {
        responder_json(['status' => 'error', 'message' => 'No hay lote activo para generar el PDF.'], 400);
    }

    $archivos = [];
    $facturasSinArchivo = [];

    $query = "SELECT id, ruta_archivo_factura FROM facturas WHERE usuario = ? AND lote = ? ORDER BY id DESC";
    $stmtC = $conexion->prepare($query);
    $stmtC->bind_param("ss", $nombre_usuario, $lote);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    $stmtC->close();

    while ($row = $resC->fetch_assoc()) {
        if (!empty($row['ruta_archivo_factura'])) {
            $archivos[] = __DIR__ . '/' . ltrim($row['ruta_archivo_factura'], '/');
        } else {
            $facturasSinArchivo[] = (int)$row['id'];
        }
    }

    if (!empty($facturasSinArchivo)) {
        responder_json([
            'status' => 'error',
            'message' => 'Debes adjuntar todos los archivos (imagen/pdf) a las facturas antes de continuar.',
            'facturasSinArchivo' => $facturasSinArchivo
        ], 400);
    }

    if (empty($archivos)) {
        responder_json(['status' => 'error', 'message' => 'No hay archivos para combinar.'], 400);
    }

    $resp = combinarArchivosPDF($nombre_usuario, $lote, $archivos);
    responder_json($resp);
}

if (es_post() && isset($_POST['verificar_existencia_pdf'])) {
    $rutaArchivo = ruta_pdf_unificado($nombre_usuario, $lote);
    responder_json([
        'existe' => file_exists($rutaArchivo),
        'ruta' => file_exists($rutaArchivo) ? $rutaArchivo : null
    ]);
}

if (es_post() && isset($_POST['verificar_existencia_archivos'])) {
    $rutaPDF   = ruta_pdf_unificado($nombre_usuario, $lote);
    $rutaExcel = ruta_excel_lote($nombre_usuario, $lote);

    responder_json([
        'existen'  => (file_exists($rutaPDF) && file_exists($rutaExcel)),
        'rutaPDF'  => file_exists($rutaPDF) ? $rutaPDF : null,
        'rutaExcel'=> file_exists($rutaExcel) ? $rutaExcel : null
    ]);
}

// POST no reconocido
if (es_post()) {
    responder_json(['status' => 'error', 'message' => 'Acción inválida.'], 400);
}

// GET: render
include 'tablaFacturasUsuariohtml.php';
