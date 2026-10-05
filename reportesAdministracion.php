<?php
// archivo: reportesAdministracion.php (REPARADO)
// - No cambia la lógica de negocio; solo corrige respuestas AJAX/JSON y robustez del JS.

require_once 'utilidades.php';
session_start();

require_once 'vendor/autoload.php';
use setasign\Fpdi\Fpdi;

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? '';
$correo_usuario = $_SESSION['correo'] ?? '';

$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Helper JSON consistente para AJAX (y evita basura antes del JSON)
function responder_json($payload, int $httpCode = 200): void {
    if (ob_get_length()) { @ob_clean(); } // evita que se mezcle HTML/notices con JSON

    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Auditoría de actividad
function registrarActividad($accion, $detalle, $usuario)
{
    global $conexion;
    $stmt = $conexion->prepare("INSERT INTO auditoria (accion, detalle, usuario, fecha_hora) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("sss", $accion, $detalle, $usuario);
        $stmt->execute();
        $stmt->close();
    }
}

// ==============================
// AJAX GET: obtener lotes
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'obtener_lotes') {
    $usuario_seleccionado = $_GET['usuario'] ?? '';

    $stmt = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC");
    if (!$stmt) {
        responder_json([]);
    }

    $stmt->bind_param("s", $usuario_seleccionado);
    $stmt->execute();
    $resultAjax = $stmt->get_result();

    $lotes = [];
    while ($row = $resultAjax->fetch_assoc()) {
        $lotes[] = $row['nombre_lote'];
    }
    $stmt->close();

    responder_json($lotes);
}

// ==============================
// AJAX POST: verificar archivos
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_existencia_archivos'])) {
    $usuarioFiltrado = isset($_POST['usuario_filtrado']) ? (string)$_POST['usuario_filtrado'] : (string)$nombre_usuario;
    $loteFiltrado    = isset($_POST['lote_filtrado']) ? (string)$_POST['lote_filtrado'] : '';

    $nombreUsuarioFormateado = preg_replace('/[\/\\\\:*?"<>| ]/', '_', $usuarioFiltrado);
    $loteFormateado          = preg_replace('/[\/\\\\:*?"<>| ]/', '_', $loteFiltrado);

    $carpetaPDF       = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    $nombreArchivoPDF = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivoPDF   = $carpetaPDF . '/' . $nombreArchivoPDF;
    $existePDF        = file_exists($rutaArchivoPDF);

    $carpetaExcel       = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    $nombreArchivoExcel = "CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $rutaArchivoExcel   = $carpetaExcel . '/' . $nombreArchivoExcel;
    $existeExcel        = file_exists($rutaArchivoExcel);

    $respuesta = [
        'existePDF'   => $existePDF,
        'existeExcel' => $existeExcel,
        'rutaPDF'     => $existePDF ? 'unificadosPDF/' . $nombreUsuarioFormateado . '/' . $nombreArchivoPDF : '',
        'rutaExcel'   => $existeExcel ? 'facturasExcel/' . $nombreUsuarioFormateado . '/' . $nombreArchivoExcel : '',
    ];

    responder_json($respuesta);
}

// ==============================
// Render (HTML)
// ==============================

$usuariosResult = $conexion->query("SELECT DISTINCT usuario FROM facturas WHERE usuario != 'administrador' ORDER BY usuario ASC");

$result = false;
$lote = '';
$usuario_seleccionado = isset($_GET['usuario']) ? (string)$_GET['usuario'] : '';

if ($usuario_seleccionado) {
    $lotesStmt = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC");
    if ($lotesStmt) {
        $lotesStmt->bind_param("s", $usuario_seleccionado);
        $lotesStmt->execute();
        $lotesResult = $lotesStmt->get_result();
    } else {
        $lotesResult = false;
    }

    $lote = isset($_GET['lote']) ? (string)$_GET['lote'] : '';
    $sql = "SELECT * FROM facturas WHERE usuario = ? ";

    if (!empty($lote)) {
        $sql .= "AND lote = ? ORDER BY id DESC";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $usuario_seleccionado, $lote);
        } else {
            $result = false;
        }
    } else {
        $sql .= "ORDER BY id DESC";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $usuario_seleccionado);
        } else {
            $result = false;
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if (isset($lotesStmt) && $lotesStmt) { $lotesStmt->close(); }
} else {
    $result = false;
    $lotesResult = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Facturas</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/reportesAdministracion.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 font-sans flex flex-col min-h-screen">
<header class="bg-gray-800 p-4 flex justify-between items-center text-white">
    <div class="flex items-center">
        <a href="logout.php" class="text-white mr-4">Cerrar sesión</a>
    </div>
    <div class="text-center">
        <img src="imagenes/logo.png" alt="logo" class="h-12 mx-auto">
    </div>
    <div class="flex items-center">
        <a href="menu.php" class="text-white ml-4">Volver al Menú</a>
    </div>
</header>

<div class="container mx-auto mt-8 p-8 bg-white rounded-lg shadow-md flex-grow">
    <h2 class="text-center text-2xl font-bold mb-4">Lista de Facturas</h2>

    <div class="mb-4">
        <form id="filterForm" method="get"
              class="flex flex-wrap md:flex-nowrap items-center justify-center space-y-4 md:space-y-0 md:space-x-4">
            <div class="w-full md:w-auto flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-2">
                <label for="usuario" class="block text-gray-700">Filtrar por Usuario:</label>
                <select id="usuario" name="usuario" class="form-select block w-full mt-1 md:mt-0 rounded-md border-gray-300 shadow-sm">
                    <option value="">Seleccione un usuario</option>
                    <?php
                    if ($usuariosResult && $usuariosResult->num_rows > 0) {
                        while ($usuarioRow = $usuariosResult->fetch_assoc()) {
                            $selected = ($usuario_seleccionado === $usuarioRow['usuario']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($usuarioRow['usuario']) . "' $selected>" . htmlspecialchars($usuarioRow['usuario']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="w-full md:w-auto flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-2">
                <label for="lote" class="block text-gray-700">Filtrar por Lote:</label>
                <select id="lote" name="lote" class="form-select block w-full mt-1 md:mt-0 rounded-md border-gray-300 shadow-sm"
                        <?php echo empty($usuario_seleccionado) ? 'disabled' : ''; ?>>
                    <option value="">Seleccione un lote</option>
                    <?php
                    if ($lotesResult && $lotesResult->num_rows > 0) {
                        while ($loteRow = $lotesResult->fetch_assoc()) {
                            $selected = ($lote === $loteRow['nombre_lote']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($loteRow['nombre_lote']) . "' $selected>" . htmlspecialchars($loteRow['nombre_lote']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="w-full md:w-auto flex space-y-2 md:space-y-0 md:space-x-2 justify-center">
                <button type="submit" id="filtrarBtn" class="btn btn-primary" aria-label="Filtrar">Filtrar</button>
                <button type="button" id="limpiarFiltro"
                        class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition duration-300 ease-in-out">
                    Limpiar filtro
                </button>
            </div>
        </form>
    </div>

    <div class="flex space-x-4 mb-4">
        <button id="descargarExcelTabla"
                class="bg-green-500 hover:bg-green-600 text-white font-semibold py-1.5 px-3 rounded-md shadow-md hover:shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
            Descargar tabla Filtrada
        </button>

        <a id="descargarPDF"
           class="bg-gray-400 text-white py-1.5 px-3 rounded-md shadow-md font-semibold cursor-not-allowed"
           title="Lote no cerrado" href="">
            Descargar PDF
        </a>

        <a id="descargarExcel"
           class="bg-gray-400 text-white py-1.5 px-3 rounded-md shadow-md font-semibold cursor-not-allowed"
           title="Lote no cerrado" href="">
            Descargar Excel
        </a>
    </div>

    <div class="flex justify-between mt-4 mb-4">
        <input type="text" id="buscador" placeholder="Buscar..." class="border px-4 py-1 w-full">
        <button id="limpiarBusqueda" class="bg-red-500 hover:bg-red-600 text-white px-4 py-1 rounded-md ml-4">
            Limpiar búsqueda
        </button>
    </div>

    <div class="flex justify-start mt-6 p-2">
        <span class="font-semibold text-gray-600">Total consumido:</span>
        <span id="totalValorFactura" class="ml-2 text-lg font-semibold text-gray-800">0.00</span>
    </div>

    <div class="overflow-x-auto overflow-y-auto" style="max-height: 400px;">
        <table class="table-auto w-full">
            <thead>
            <tr class="bg-black-200">
                <th class="px-4 py-2">Usuario</th>
                <th class="px-4 py-2 col-large">Descripción</th>
                <th class="px-4 py-2">Cuenta de mayor</th>
                <th class="px-4 py-2 col-medium">Nombre de la cuenta de mayor</th>
                <th class="px-4 py-2">Sucursal</th>
                <th class="px-4 py-2">Nombre CC</th>
                <th class="px-4 py-2">Indicador de impuestos</th>
                <th class="px-4 py-2">Valor Factura</th>
                <th class="px-4 py-2">Sujeto a retención de impuesto</th>
                <th class="px-4 py-2">Tipo de Compra\Venta</th>
                <th class="px-4 py-2">NIT del Socio de Negocios</th>
                <th class="px-4 py-2 col-medium">Nombre</th>
                <th class="px-4 py-2">Serie del Documento</th>
                <th class="px-4 py-2">Numero de Documento</th>
                <th class="px-4 py-2 col-medium">Fecha del Documento</th>
                <th class="px-4 py-2">Tipo de Combustible</th>
                <th class="px-4 py-2">Galones</th>
                <th class="px-4 py-2">Total Factura de Combustible</th>
                <th class="px-4 py-2 col-small">Archivo Factura</th>
            </tr>
            </thead>

            <tbody id="facturasBody">
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr id='factura-" . (int)$row['id'] . "'>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["usuario"]) . "</td>";
                    echo "<td class='border p-1 text-sm'>" . htmlspecialchars(strtoupper($row["descripcion"])) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["cuenta_mayor"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_cuenta_mayor"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["sucursal"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_cc"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["indicador_impuestos"]) . "</td>";
                    echo "<td class='border px-4 py-2 valor-factura'>" . htmlspecialchars($row["valor_factura"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["sujeto_retencion"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["tipo_compraventa"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nit_proveedor"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_proveedor"]) . "</td>";
                    echo "<td class='border p-1 text-sm'>" . htmlspecialchars(strtoupper($row["serie_documento"])) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["numero_documento"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["fecha_documento"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["tipo_combustible"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["galones"]) . "</td>";
                    echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["total_factura_combustible"]) . "</td>";

                    echo "<td class='border px-4 py-2'>";
                    if (!empty($row['ruta_archivo_factura'])) {
                        echo "<button class='bg-blue-500 text-white px-2 py-1 rounded ver-archivo-btn' data-ruta='" . htmlspecialchars($row['ruta_archivo_factura']) . "'>Ver Archivo</button>";
                    } else {
                        echo "<button class='bg-gray-400 text-white px-2 py-1 rounded cursor-not-allowed' disabled>Sin archivo</button>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='19' class='border px-4 py-2 text-center'>No se encontraron resultados</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalArchivo" class="fixed z-10 inset-0 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all max-w-2xl w-full">
            <div class="bg-gray-50 px-4 py-3 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Archivo de Factura</h3>
                <button type="button" id="cerrarModalBtn"
                        class="text-white bg-red-600 hover:bg-red-700 font-bold py-2 px-4 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                    Cerrar
                </button>
            </div>
            <div class="bg-white px-4 py-5 sm:p-6">
                <iframe id="iframeArchivo" class="w-full h-96" src="" frameborder="0"></iframe>
                <div class="flex justify-between mt-4">
                    <a id="descargarArchivo" class="bg-blue-500 text-white px-4 py-2 rounded" download>Descargar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-gray-800 p-4 text-center text-white">
    <p>Corporación Carrousel S.A.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
(function () {
    "use strict";

    function deshabilitarBoton($el, texto) {
        $el.attr('href','')
           .removeClass('bg-green-500 hover:bg-green-600 bg-purple-500 hover:bg-purple-600')
           .addClass('bg-gray-400 cursor-not-allowed')
           .prop('disabled', true)
           .text(texto);
        $el.removeAttr('download');
    }

    function habilitarBoton($el, href, clases, texto, downloadAttr) {
        $el.attr('href', href)
           .removeClass('bg-gray-400 cursor-not-allowed')
           .addClass(clases)
           .prop('disabled', false)
           .text(texto);
        if (downloadAttr) $el.attr('download', downloadAttr);
    }

    function verificarArchivos(usuario, lote, cb) {
        $.ajax({
            url: 'reportesAdministracion.php',
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: { verificar_existencia_archivos: true, usuario_filtrado: usuario, lote_filtrado: lote },
            success: function (res) {
                if (!res || typeof res !== 'object') {
                    Swal.fire('Error', 'Respuesta inválida del servidor al verificar archivos.', 'error');
                    return;
                }

                if (res.existeExcel) {
                    habilitarBoton($('#descargarExcel'), res.rutaExcel, 'bg-green-500 hover:bg-green-600', 'Descargar Excel');
                } else {
                    deshabilitarBoton($('#descargarExcel'), 'Excel No Disponible, lote no cerrado.');
                }

                if (res.existePDF) {
                    habilitarBoton($('#descargarPDF'), res.rutaPDF, 'bg-purple-500 hover:bg-purple-600', 'Descargar PDF', res.rutaPDF);
                } else {
                    deshabilitarBoton($('#descargarPDF'), 'PDF No Disponible, lote no cerrado.');
                }

                if (cb) cb();
            },
            error: function () {
                Swal.fire('Error', 'Hubo un problema al verificar los archivos.', 'error');
            }
        });
    }

    function calcularTotalValorFactura() {
        let total = 0;
        $('#facturasBody tr:visible').each(function() {
            const valor = $(this).find('td.valor-factura').text().trim();
            const n = parseFloat(valor.replace(/,/g, '')) || 0;
            total += n;
        });
        $('#totalValorFactura').text(total.toFixed(2));
    }

    $(function () {

        deshabilitarBoton($('#descargarExcel'), 'Descargar Excel');
        deshabilitarBoton($('#descargarPDF'), 'Descargar PDF');

        $('#buscador').on('keyup', function() {
            const value = ($(this).val() || '').toLowerCase();
            $('#facturasBody tr').each(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
            calcularTotalValorFactura();
        });

        $('#limpiarBusqueda').on('click', function() {
            $('#buscador').val('');
            $('#facturasBody tr').show();
            calcularTotalValorFactura();
        });

        $('#descargarExcelTabla').on('click', function(e) {
            e.preventDefault();

            const usuarioSeleccionado = $('#usuario').val();
            const loteSeleccionado = $('#lote').val();

            if (!usuarioSeleccionado || !loteSeleccionado) {
                Swal.fire('Por favor, seleccione un usuario y un lote para continuar.');
                return;
            }

            const headers = [];
            $('#facturasBody').closest('table').find('thead th').each(function() {
                headers.push($(this).text().trim());
            });

            const columnasTexto = [
                'Serie del Documento',
                'Numero de Documento',
                'Descripción',
                'Nombre de la cuenta de mayor',
                'NIT del Socio de Negocios'
            ];

            const rows = [];
            $('#facturasBody tr:visible').each(function() {
                const row = [];
                $(this).find('td').each(function(index) {
                    const cellValue = $(this).text().trim();
                    const header = headers[index];

                    if (columnasTexto.includes(header)) {
                        row.push({ t: 's', v: cellValue });
                    } else if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(cellValue)) {
                        const dp = cellValue.split(' ')[0].split('-');
                        const tp = cellValue.split(' ')[1].split(':');
                        row.push(new Date(dp[0], dp[1]-1, dp[2], tp[0], tp[1], tp[2]));
                    } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(cellValue) || /^\d{4}-\d{2}-\d{2}$/.test(cellValue)) {
                        const dp = cellValue.includes('-') ? cellValue.split('-') : cellValue.split('/');
                        row.push(cellValue.includes('-')
                            ? new Date(dp[0], dp[1]-1, dp[2])
                            : new Date(dp[2], dp[1]-1, dp[0]));
                    } else {
                        const numericValue = parseFloat(cellValue.replace(/,/g, ''));
                        row.push(isNaN(numericValue) ? cellValue : numericValue);
                    }
                });
                rows.push(row);
            });

            rows.unshift(headers);

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(rows);

            headers.forEach((header, index) => {
                if (header.toLowerCase().includes("fecha")) {
                    for (let i = 1; i < rows.length; i++) {
                        const addr = XLSX.utils.encode_cell({ r: i, c: index });
                        if (ws[addr] && ws[addr].v instanceof Date) ws[addr].z = 'dd/mm/yyyy hh:mm:ss';
                    }
                }
            });

            XLSX.utils.book_append_sheet(wb, ws, 'Facturas Filtradas');
            const fileName = `${usuarioSeleccionado}_${loteSeleccionado}_Admon.xlsx`;
            XLSX.writeFile(wb, fileName);
        });

        // Cargar lotes al cambiar usuario (FORZAMOS JSON)
        $('#usuario').on('change', function() {
            const usuario = $(this).val();

            if (!usuario) {
                $('#lote').prop('disabled', true).empty().append(new Option('Seleccione un lote', ''));
                deshabilitarBoton($('#descargarExcel'), 'Descargar Excel');
                deshabilitarBoton($('#descargarPDF'), 'Descargar PDF');
                return;
            }

            $.ajax({
                url: 'reportesAdministracion.php',
                type: 'GET',
                dataType: 'json',
                cache: false,
                data: { accion: 'obtener_lotes', usuario: usuario },
                success: function(lotes) {
                    if (!Array.isArray(lotes)) {
                        Swal.fire('Error', 'Respuesta inválida del servidor al obtener lotes.', 'error');
                        return;
                    }

                    $('#lote').empty().append(new Option('Seleccione un lote', ''));
                    lotes.forEach(function(l) { $('#lote').append(new Option(l, l)); });
                    $('#lote').prop('disabled', false);

                    if (lotes.length > 0) $('#lote').val(lotes[0]);

                    deshabilitarBoton($('#descargarExcel'), 'Descargar Excel');
                    deshabilitarBoton($('#descargarPDF'), 'Descargar PDF');
                },
                error: function() {
                    Swal.fire('Error', 'No se pudieron cargar los lotes.', 'error');
                }
            });
        });

        $('#limpiarFiltro').on('click', function(e) {
            e.preventDefault();
            $('#usuario').val('');
            $('#lote').val('').prop('disabled', true);
            $('#facturasBody').html('<tr><td colspan="19" class="border px-4 py-2 text-center">No se encontraron resultados</td></tr>');
            $('#totalValorFactura').text('0.00');
            deshabilitarBoton($('#descargarExcel'), 'Excel No Disponible');
            deshabilitarBoton($('#descargarPDF'), 'PDF No Disponible');
        });

        $('#filterForm').on('submit', function(e) {
            e.preventDefault();

            const usuario = $('#usuario').val();
            const lote = $('#lote').val();

            if (!usuario) { Swal.fire('Por favor, seleccione un usuario.'); return; }
            if (!lote) { Swal.fire('Por favor, seleccione un lote.'); return; }

            verificarArchivos(usuario, lote, () => {
                this.submit();
            });
        });

        $(document).on('click', '.ver-archivo-btn', function() {
            const ruta = $(this).data('ruta');
            if (!ruta) { Swal.fire('No se encontró el archivo.'); return; }
            $('#iframeArchivo').attr('src', ruta);
            $('#descargarArchivo').attr('href', ruta);
            $('#modalArchivo').removeClass('hidden');
        });

        $('#cerrarModalBtn').on('click', function() {
            $('#modalArchivo').addClass('hidden');
            $('#iframeArchivo').attr('src', '');
        });

        calcularTotalValorFactura();

        <?php if (!empty($usuario_seleccionado) && !empty($lote)): ?>
        verificarArchivos('<?php echo addslashes($usuario_seleccionado); ?>', '<?php echo addslashes($lote); ?>');
        <?php endif; ?>
    });
})();
</script>

</body>
</html>
