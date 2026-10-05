<?php

// tablaFacturasUsuario.php

require_once 'utilidades.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Conectar a la base de datos
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) { @$conexion->set_charset('utf8mb4'); }

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Asignar variables de sesión para el usuario, tipo de usuario y correo
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario = $_SESSION['tipo_usuario'] ?? '';
$correo_usuario = $_SESSION['correo'] ?? '';

// Obtener el monto de caja del usuario
$montoCaja = 0;
$stmtCaja = $conexion->prepare("SELECT montoCaja FROM usuarios WHERE nombre = ?");
$stmtCaja->bind_param("s", $nombre_usuario);
$stmtCaja->execute();
$stmtCaja->bind_result($montoCaja);
$stmtCaja->fetch();
$stmtCaja->close();

// Establecer la zona horaria
date_default_timezone_set('America/Guatemala');

// Inicializar variables
$lote = '';
$result = false;

// Obtener el lote más reciente del usuario actual
$lotesStmt = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC LIMIT 1");
$lotesStmt->bind_param("s", $nombre_usuario);
$lotesStmt->execute();
$lotesResult = $lotesStmt->get_result();
$lotesStmt->close();
if ($lotesResult->num_rows > 0) {
    $lote = $lotesResult->fetch_assoc()['nombre_lote'];
}

// Obtener todas las facturas del usuario actual y del lote más reciente, ordenadas por fecha de más reciente a más antigua
$sql = "SELECT * FROM facturas WHERE usuario = ? AND lote = ? ORDER BY fecha_documento DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $nombre_usuario, $lote);
$stmt->execute();
$result = $stmt->get_result();



// Función para generar el HTML de una fila específica
function generarFilaFactura($row)
{
    // Formatear los nombres de usuario y lote para que sean válidos en el sistema de archivos
    $nombreUsuarioFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $row['usuario']);
    $loteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $row['lote']);

    // Verificación del archivo PDF
    $carpetaPDF = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    $nombreArchivoPDF = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivoPDF = $carpetaPDF . '/' . $nombreArchivoPDF;
    $existePDF = file_exists($rutaArchivoPDF);

    // Verificación del archivo Excel
    $carpetaExcel = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    $nombreArchivoExcel = "CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $rutaArchivoExcel = $carpetaExcel . '/' . $nombreArchivoExcel;
    $existeExcel = file_exists($rutaArchivoExcel);

    // Generar el HTML de la fila
    $html = "<tr id='fila-" . $row["id"] . "'>";  // Añadir ID único para la fila
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["usuario"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["descripcion"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["cuenta_mayor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["nombre_cuenta_mayor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["sucursal"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["nombre_cc"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["indicador_impuestos"]) . "</td>";
    $html .= "<td class='border p-1 text-sm valor-factura'>" . htmlspecialchars($row["valor_factura"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["sujeto_retencion"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["tipo_compraventa"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["nit_proveedor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["nombre_proveedor"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["serie_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["numero_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["fecha_documento"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["tipo_combustible"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["galones"]) . "</td>";
    $html .= "<td class='border p-1 text-sm'>" . htmlspecialchars($row["total_factura_combustible"]) . "</td>";

    // Verificar si hay archivo adjunto
    if (!empty($row['ruta_archivo_factura'])) {
        $archivoUrl = htmlspecialchars($row['ruta_archivo_factura']);
        $html .= "<td class='border px-4 py-2'>
            <div class='flex flex-col space-y-2'>
                <button class='bg-blue-500 text-white px-2 py-0.5 text-sm rounded modal-btn' data-url='$archivoUrl'>
                    Previsualizar archivo
                </button>";

        // Si alguno de los archivos no existe, permitir eliminar el archivo
        if (!$existePDF || !$existeExcel) {
            $html .= "<button class='bg-red-500 text-white px-2 py-0.5 text-sm rounded eliminar-archivo' data-id='{$row['id']}'>
                        Eliminar
                    </button>";
        }

        $html .= "</div></td>";
    } else {
        // Si no hay archivo, mostrar opción para subir archivo
        $html .= "<td class='border px-4 py-2'>
            <button class='bg-green-500 text-white px-2 py-0.5 rounded modal-subir' data-id='{$row['id']}'>
                Subir archivo
            </button>
          </td>";
    }

    // Si ambos archivos no existen, permitir eliminar la factura
    if (!$existePDF || !$existeExcel) {
        $html .= "<td class='border px-4 py-2'>
            <button class='bg-red-500 text-white px-1 py-0.5 text-sm rounded eliminar-factura' data-id='{$row['id']}'>
                Eliminar factura
            </button>
          </td>";
    } else {
        // Si ambos archivos existen, bloquear las acciones
        $html .= "<td class='border px-4 py-2 text-gray-400'>Acciones bloqueadas</td>";
    }

    $html .= "</tr>";

    return $html;
}






// Manejo de subida de archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && isset($_POST['factura_id'])) {
    $archivo = $_FILES['archivo'];
    $factura_id = $_POST['factura_id'];

    // Obtener el nombre del usuario
    $nombreUsuario = $_SESSION['usuario'] ?? 'usuario';

    // Formatear el nombre del lote para que sea válido en un nombre de carpeta
    $nombreLoteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $lote);

    // Obtener la fecha actual en el formato (12 horas)
    $fechaActual = date('Y-m-d_g-i-sA'); // Formato: Año-Mes-Día_Hora-Minuto-SegundoAM/PM (12 horas)

    // Obtener la extensión original del archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    // Reemplazar espacios en el nombre original del archivo por guiones bajos
    $nombreOriginal = str_replace(' ', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));

    // Renombrar el archivo
    $nuevoNombreArchivo = $nombreUsuario . '_' . $fechaActual . '.' . $extension;

    // Crear la carpeta "facturas", luego una carpeta con el nombre del usuario y dentro otra carpeta con el nombre del lote actual
    $carpetaFacturas = __DIR__ . '/facturas/' . $nombreUsuario . '/' . $nombreLoteFormateado;
    if (!file_exists($carpetaFacturas)) {
        if (!mkdir($carpetaFacturas, 0777, true)) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta para el lote actual.']);
            exit();
        }
    }

    // Ruta donde se almacenará el archivo
    $rutaDestino = $carpetaFacturas . '/' . $nuevoNombreArchivo;

    // Validar que el archivo sea imagen o PDF
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/pdf'];
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    // Validación por MIME y extensión
    $tipoArchivo = mime_content_type($archivo['tmp_name']);
    if (in_array($tipoArchivo, $tiposPermitidos) && in_array($extension, $extensionesPermitidas)) {

        // Comprimir la imagen si es un archivo de imagen
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $compresionExitosa = false;

            if ($extension === 'jpg' || $extension === 'jpeg') {
                $imagen = imagecreatefromjpeg($archivo['tmp_name']);
                $compresionExitosa = imagejpeg($imagen, $rutaDestino, 75); // Calidad de 75 para JPEG
            } elseif ($extension === 'png') {
                $imagen = imagecreatefrompng($archivo['tmp_name']);
                $compresionExitosa = imagepng($imagen, $rutaDestino, 6); // Nivel de compresión para PNG (0-9)
            }

            if ($compresionExitosa) {
                imagedestroy($imagen); // Liberar memoria
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al comprimir la imagen.']);
                exit();
            }

        } else {
            // Mover el archivo directamente si no es una imagen
            if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
                echo json_encode(['status' => 'error', 'message' => 'Error al subir el archivo.']);
                exit();
            }
        }

        // Almacenar la ruta del archivo en la base de datos
        $rutaArchivoBD = 'facturas/' . $nombreUsuario . '/' . $nombreLoteFormateado . '/' . $nuevoNombreArchivo;
        $stmt = $conexion->prepare("UPDATE facturas SET ruta_archivo_factura = ? WHERE id = ?");
        $stmt->bind_param("si", $rutaArchivoBD, $factura_id);
        $stmt->execute();
        $stmt->close();

        // Obtener la fila actualizada y devolverla como HTML
        $stmt = $conexion->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $filaHtml = generarFilaFactura($row);
            echo json_encode(['status' => 'success', 'message' => "Archivo subido exitosamente.", 'html' => $filaHtml]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Formato de archivo no permitido.']);
    }
    exit();
}














// Manejo de eliminación de facturas y archivos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['eliminar_factura']) && isset($_POST['factura_id'])) {
        $factura_id = $_POST['factura_id'];

        // Obtener la ruta del archivo asociado a la factura
        $stmt = $conexion->prepare("SELECT ruta_archivo_factura FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();
        $stmt->bind_result($rutaArchivo);
        $stmt->fetch();
        $stmt->close();

        // Si existe un archivo asociado, eliminarlo del sistema de archivos
        if ($rutaArchivo) {
            $rutaAbs = __DIR__ . '/' . ltrim($rutaArchivo, '/');
            if (file_exists($rutaAbs)) {
                unlink($rutaAbs);
            }
        }

        // Eliminar la factura de la base de datos
        $stmt = $conexion->prepare("DELETE FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();

        // Respuesta exitosa o fallida
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Factura y archivo eliminados exitosamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al eliminar la factura.']);
        }
        exit();
    }

    if (isset($_POST['eliminar_archivo']) && isset($_POST['factura_id'])) {
        $factura_id = $_POST['factura_id'];

        // Obtener la ruta del archivo
        $stmt = $conexion->prepare("SELECT ruta_archivo_factura FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();
        $stmt->bind_result($rutaArchivo);
        $stmt->fetch();
        $stmt->close();

        // Eliminar el archivo del sistema de archivos
        if ($rutaArchivo) {
            $rutaAbs = __DIR__ . '/' . ltrim($rutaArchivo, '/');
            if (file_exists($rutaAbs)) {
                unlink($rutaAbs);
            }
        }

        // Eliminar la ruta del archivo en la base de datos
        $stmt = $conexion->prepare("UPDATE facturas SET ruta_archivo_factura = NULL WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();
        $stmt->close();

        // Obtener la fila actualizada y devolverla como HTML
        $stmt = $conexion->prepare("SELECT * FROM facturas WHERE id = ?");
        $stmt->bind_param("i", $factura_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $filaHtml = generarFilaFactura($row);
            echo json_encode(['status' => 'success', 'message' => 'Archivo eliminado.', 'html' => $filaHtml]);
        }
        exit();
    }
}









// Verificar si el lote fue enviado a través de POST (para la solicitud AJAX de filtrado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lote'])) {
    $loteSeleccionado = $_POST['lote'];

    // Modificar la consulta SQL para filtrar las facturas por el lote seleccionado, ordenadas por fecha de más reciente a más antigua
    $sql = "SELECT * FROM facturas WHERE usuario = ? AND lote = ? ORDER BY fecha_documento DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $nombre_usuario, $loteSeleccionado);
    $stmt->execute();
    $result = $stmt->get_result();
    // Aquí deberías devolver el resultado como HTML o JSON según lo que necesites en tu frontend.
    exit();
}

// Guardar archivo Excel en el servidor
if (isset($_POST['guardar_excel']) && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    $nombreArchivo = $_FILES['archivo']['name'];

    // Obtener el nombre del usuario
    $nombreUsuario = $_SESSION['usuario'] ?? 'usuario';

    // Crear la carpeta "facturasExcel" y una subcarpeta con el nombre del usuario
    $carpetaFacturasExcel = __DIR__ . '/facturasExcel/' . $nombreUsuario;
    if (!file_exists($carpetaFacturasExcel)) {
        if (!mkdir($carpetaFacturasExcel, 0777, true)) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la carpeta para el usuario.']);
            exit();
        }
    }

    // Ruta donde se almacenará el archivo
    $rutaDestino = $carpetaFacturasExcel . '/' . $nombreArchivo;

    if (move_uploaded_file($archivo, $rutaDestino)) {
        echo json_encode(['message' => 'Archivo guardado exitosamente en el servidor.']);
    } else {
        echo json_encode(['message' => 'Error al guardar el archivo.']);
    }
    exit();
}

// Verificar si el archivo excel existe
if (isset($_POST['verificar_existencia_excel']) && isset($_POST['fileName'])) {
    $nombreUsuario = $_SESSION['usuario'] ?? 'usuario';

    $directory = __DIR__ . '/facturasExcel/' . $nombreUsuario;
    $filePath = $directory . '/' . $_POST['fileName'];

    if (file_exists($filePath)) {
        echo json_encode(['existe' => true]);
    } else {
        echo json_encode(['existe' => false]);
    }
    exit();
}








require_once 'vendor/autoload.php';
use setasign\Fpdi\Fpdi;

// Función para convertir cada archivo PDF a un formato compatible
function estandarizarPDF($archivoOriginal, $archivoTemporal)
{
    $pdf = new Fpdi();
    try {
        $pageCount = $pdf->setSourceFile($archivoOriginal);
    } catch (Exception $e) {
        return false; // Indicar que el archivo es incompatible
    }

    for ($i = 1; $i <= $pageCount; $i++) {
        $tplIdx = $pdf->importPage($i);
        $pdf->AddPage();
        $pdf->useTemplate($tplIdx, 0, 0, 210, 297);  // Tamaño A4
    }

    $pdf->Output($archivoTemporal, 'F');  // Guarda el archivo temporal estandarizado
    return true; // Indicar que el archivo es compatible
}

// Función para combinar archivos en un solo PDF
function combinarArchivosPDF($nombreUsuario, $lote, $archivos, $archivosIncompatibles)
{
    $nombreUsuarioFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $nombreUsuario);
    $loteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $lote);

    $carpetaPDF = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    if (!file_exists($carpetaPDF)) {
        mkdir($carpetaPDF, 0777, true);
    }

    $nombreArchivo = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivo = $carpetaPDF . '/' . $nombreArchivo;

    if (file_exists($rutaArchivo)) {
        unlink($rutaArchivo); // Eliminar el archivo existente
    }

    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);
    $cartaAncho = 215.9;
    $cartaAlto = 279.4;

    foreach ($archivos as $archivo) {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            // Crear un archivo temporal para estandarizar el PDF
            $archivoTemporal = tempnam(sys_get_temp_dir(), 'pdf');
            if (!estandarizarPDF($archivo, $archivoTemporal)) {
                $archivosIncompatibles[] = basename($archivo);
                continue; // Saltar archivos incompatibles
            }

            // Procesar el archivo estandarizado
            $pageCount = $pdf->setSourceFile($archivoTemporal);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplIdx);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pageWidth = ($orientation == 'P') ? $cartaAncho : $cartaAlto;
                $pageHeight = ($orientation == 'P') ? $cartaAlto : $cartaAncho;

                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $scale = min($pageWidth / $size['width'], $pageHeight / $size['height'], 1);
                $scaledWidth = $size['width'] * $scale;
                $scaledHeight = $size['height'] * $scale;

                $x = ($pageWidth - $scaledWidth) / 2;
                $y = ($pageHeight - $scaledHeight) / 2;

                $pdf->useTemplate($tplIdx, $x, $y, $scaledWidth, $scaledHeight);
            }

            unlink($archivoTemporal); // Eliminar el archivo temporal después de usarlo
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            list($width, $height) = getimagesize($archivo);
            $dpi = 96;
            $width_mm = ($width / $dpi) * 25.4;
            $height_mm = ($height / $dpi) * 25.4;
            $orientation = ($width_mm > $height_mm) ? 'L' : 'P';

            $pageWidth = ($orientation == 'P') ? $cartaAncho : $cartaAlto;
            $pageHeight = ($orientation == 'P') ? $cartaAlto : $cartaAncho;

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
        'ruta' => $rutaArchivo,
        'incompatibles' => $archivosIncompatibles
    ];
}

// Manejo de combinación de archivos PDF e imágenes en un solo PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['combinar_archivos'])) {
    $archivos = [];
    $facturasSinArchivo = [];
    $archivosIncompatibles = [];

    $query = "SELECT id, ruta_archivo_factura FROM facturas WHERE usuario = ? AND lote = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ss", $nombre_usuario, $lote);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['ruta_archivo_factura'])) {
            $archivos[] = __DIR__ . '/' . $row['ruta_archivo_factura'];
        } else {
            $facturasSinArchivo[] = $row['id'];
        }
    }

    if (!empty($facturasSinArchivo)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Debes adjuntar todos los archivos (imagen/pdf) a las facturas antes de continuar. Algunas facturas no tienen archivos adjuntos.',
            'facturasSinArchivo' => $facturasSinArchivo
        ]);
        exit();
    }

    // Procesar archivos y responder UNA sola vez
    header('Content-Type: application/json; charset=utf-8');

    if (!empty($archivos)) {
        $resp = combinarArchivosPDF($nombre_usuario, $lote, $archivos, $archivosIncompatibles);

        // Si hay incompatibles, mandamos WARNING (JSON válido) e incluimos ruta si se creó
        if (!empty($archivosIncompatibles)) {
            echo json_encode([
                'status' => 'warning',
                'message' => 'Algunos archivos PDF tienen un formato incompatible y no se pueden combinar:',
                'archivosIncompatibles' => $archivosIncompatibles,
                'ruta' => $resp['ruta'] ?? null,
                'proceed' => '¿Desea continuar con los archivos compatibles?'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No hay archivos para combinar.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
// Verificar la existencia de un PDF ya combinado para el lote actual
if (isset($_POST['verificar_existencia_pdf'])) {
    $nombreUsuarioFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $nombre_usuario);
    $loteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $lote);

    $carpetaPDF = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    $nombreArchivo = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivo = $carpetaPDF . '/' . $nombreArchivo;

    if (file_exists($rutaArchivo)) {
        echo json_encode(['existe' => true, 'ruta' => $rutaArchivo]);
    } else {
        echo json_encode(['existe' => false]);
    }
    exit();
}




// Verificar la existencia de ambos archivos (PDF y Excel) para el envio de correo con ambos adjuntos
if (isset($_POST['verificar_existencia_archivos'])) {
    $nombreUsuarioFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $nombre_usuario);
    $loteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $lote);

    // Verificación del archivo PDF
    $carpetaPDF = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    $nombreArchivoPDF = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivoPDF = $carpetaPDF . '/' . $nombreArchivoPDF;
    $existePDF = file_exists($rutaArchivoPDF);

    // Verificación del archivo Excel
    $carpetaExcel = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    $nombreArchivoExcel = "CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $rutaArchivoExcel = $carpetaExcel . '/' . $nombreArchivoExcel;
    $existeExcel = file_exists($rutaArchivoExcel);

    // Responder si ambos existen
    if ($existePDF && $existeExcel) {
        echo json_encode(['existen' => true, 'rutaPDF' => $rutaArchivoPDF, 'rutaExcel' => $rutaArchivoExcel]);
    } else {
        echo json_encode(['existen' => false]);
    }
    exit();
}


?>

<?php
// aca se incluye todo el frontend html y JavaScript
include 'tablaFacturasUsuariohtmlform.php';
?>