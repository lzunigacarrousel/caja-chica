<?php
// archivo: baseDatosCajaChica.php

require_once 'utilidades.php';
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// ✅ Evitar "Undefined index" en sesión
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? '';
$correo_usuario = $_SESSION['correo'] ?? '';

$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Auditoría de actividad
function registrarActividad($accion, $detalle, $usuario) {
    global $conexion;
    $stmt = $conexion->prepare("INSERT INTO auditoria (accion, detalle, usuario, fecha_hora) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("sss", $accion, $detalle, $usuario);
        $stmt->execute();
        $stmt->close();
    }
}

// Manejar la acción de obtener los lotes de un usuario mediante AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion'])) {
    $accion = $_GET['accion'];

    if ($accion === 'obtener_lotes') {
        // ✅ Evitar undefined / null
        $usuario_seleccionado = isset($_GET['usuario']) ? (string)$_GET['usuario'] : '';

        header('Content-Type: application/json; charset=utf-8');

        if ($usuario_seleccionado === '') {
            echo json_encode([]);
            exit;
        }

        $stmt = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC");
        if (!$stmt) {
            echo json_encode([]);
            exit;
        }

        $stmt->bind_param("s", $usuario_seleccionado);
        $stmt->execute();
        $result = $stmt->get_result();

        $lotes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $lotes[] = $row['nombre_lote'];
            }
        }

        echo json_encode($lotes, JSON_UNESCAPED_UNICODE);
        $stmt->close();
        exit;
    }
}

// Obtener lista de usuarios, excluyendo al usuario "administrador"
$usuariosResult = $conexion->query("SELECT DISTINCT usuario FROM facturas WHERE usuario != 'administrador' ORDER BY usuario ASC");

// Inicializar variables para los resultados de facturas
$result = false;
$lote = '';
$usuario_seleccionado = isset($_POST['usuario']) ? (string)$_POST['usuario'] : '';

if ($usuario_seleccionado) {
    // Obtener los lotes según el usuario seleccionado, ordenados por fecha de creación
    $lotesStmt = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC");
    if ($lotesStmt) {
        $lotesStmt->bind_param("s", $usuario_seleccionado);
        $lotesStmt->execute();
        $lotesResult = $lotesStmt->get_result();
        $lotesStmt->close();
    } else {
        $lotesResult = false;
    }

    // Comprobar si se ha seleccionado un lote
    $lote = isset($_POST['lote']) ? (string)$_POST['lote'] : '';
    $sql = "SELECT * FROM facturas WHERE usuario = ? ";

    // Si se selecciona un lote, filtrarlo también
    if (!empty($lote)) {
        $sql .= "AND lote = ? ORDER BY id DESC";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $usuario_seleccionado, $lote);
        }
    } else {
        $sql .= "ORDER BY id DESC";
        $stmt = $conexion->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $usuario_seleccionado);
        }
    }

    if (isset($stmt) && $stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = false;
    }
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
    <link rel="stylesheet" href="estilos/baseDatosCajaChica.css">
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
            <!-- Formulario de filtros para seleccionar usuario y lote -->
            <form id="filterForm" method="post"
                class="flex flex-wrap md:flex-nowrap items-center justify-center space-y-4 md:space-y-0 md:space-x-4">
                <div
                    class="w-full md:w-auto flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-2">
                    <label for="usuario" class="block text-gray-700">Filtrar por Usuario:</label>
                    <select id="usuario" name="usuario"
                        class="form-select block w-full mt-1 md:mt-0 rounded-md border-gray-300 shadow-sm">
                        <option value="">Seleccione un usuario</option>
                        <?php
                        if ($usuariosResult && $usuariosResult->num_rows > 0) {
                            while ($usuarioRow = $usuariosResult->fetch_assoc()) {
                                $selected = ($usuario_seleccionado == $usuarioRow['usuario']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($usuarioRow['usuario']) . "' $selected>" . htmlspecialchars($usuarioRow['usuario']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div
                    class="w-full md:w-auto flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-2">
                    <label for="lote" class="block text-gray-700">Filtrar por Lote:</label>
                    <select id="lote" name="lote"
                        class="form-select block w-full mt-1 md:mt-0 rounded-md border-gray-300 shadow-sm"
                        <?php echo empty($usuario_seleccionado) ? 'disabled' : ''; ?>>
                        <?php
                        if ($lotesResult && $lotesResult->num_rows > 0) {
                            while ($loteRow = $lotesResult->fetch_assoc()) {
                                $selected = ($lote == $loteRow['nombre_lote']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($loteRow['nombre_lote']) . "' $selected>" . htmlspecialchars($loteRow['nombre_lote']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="w-full md:w-auto flex space-y-2 md:space-y-0 md:space-x-2 justify-center">
                    <button type="submit" id="filtrarBtn" class="btn btn-primary" aria-label="Filtrar">Filtrar</button>
                    <button type="button" id="limpiarFiltro"
                        class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition duration-300 ease-in-out">Limpiar
                        filtro</button>
                </div>
            </form>
        </div>

        <!-- Botón para descargar las facturas en formato Excel -->
        <button id="descargarExcel"
            class="bg-green-500 hover:bg-green-600 text-white font-semibold py-1.5 px-3 rounded-md shadow-md hover:shadow-lg transition duration-300 ease-in-out transform hover:scale-105">Descargar
            Excel</button>

        <!-- Buscador dinámico en tiempo real -->
        <div class="flex justify-between mt-4 mb-4">
            <input type="text" id="buscador" placeholder="Buscar..." class="border px-4 py-2 w-full">
            <button id="limpiarBusqueda"
                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md ml-4">Limpiar búsqueda
            </button>
        </div>

        <div class="overflow-x-auto overflow-y-auto" style="max-height: 400px;">
            <!-- Tabla de facturas con scroll vertical habilitado -->
            <table class="table-auto w-full">
                <thead>
                    <tr class="bg-black-200">
                        <th class="px-4 py-2">Usuario</th>
                        <th class="px-4 py-2 col-large ">Descripción</th>
                        <th class="px-4 py-2">Cuenta Mayor</th>
                        <th class="px-4 py-2 col-medium ">Nombre Cuenta Mayor</th>
                        <th class="px-4 py-2">Sucursal</th>
                        <th class="px-4 py-2">Nombre CC</th>
                        <th class="px-4 py-2">Indicador Impuestos</th>
                        <th class="px-4 py-2">Tipo Compra/Venta</th>
                        <th class="px-4 py-2">Valor Factura</th>
                        <th class="px-4 py-2">Sujeto Retención</th>
                        <th class="px-4 py-2">NIT Proveedor</th>
                        <th class="px-4 py-2 col-medium">Nombre Proveedor</th>
                        <th class="px-4 py-2">Serie Documento</th>
                        <th class="px-4 py-2">Número Documento</th>
                        <th class="px-4 py-2 col-medium">Fecha Documento</th>
                        <th class="px-4 py-2">Tipo Combustible</th>
                        <th class="px-4 py-2">Galones</th>
                        <th class="px-4 py-2">Total Factura Combustible</th>
                        <th class="px-4 py-2 col-small">Archivo Factura</th>
                    </tr>
                </thead>
                <tbody id="facturasBody">
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr id='factura-{$row['id']}'>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["usuario"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["descripcion"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["cuenta_mayor"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_cuenta_mayor"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["sucursal"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_cc"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["indicador_impuestos"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["tipo_compraventa"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["valor_factura"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["sujeto_retencion"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nit_proveedor"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["nombre_proveedor"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["serie_documento"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["numero_documento"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["fecha_documento"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["tipo_combustible"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["galones"]) . "</td>";
                            echo "<td class='border px-4 py-2'>" . htmlspecialchars($row["total_factura_combustible"]) . "</td>";

                            echo "<td class='border px-4 py-2'>";
                            if (!empty($row['ruta_archivo_factura'])) {
                                echo "<button class='bg-blue-500 text-white px-2 py-1 rounded' onclick=\"mostrarModalArchivo('" . htmlspecialchars($row['ruta_archivo_factura']) . "')\">Ver Archivo</button>";
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

    <!-- Modal para mostrar el archivo -->
    <div id="modalArchivo" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog"
        aria-modal="true">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all max-w-2xl w-full">
                <div class="bg-gray-50 px-4 py-3 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Archivo de Factura</h3>
                    <button onclick="cerrarModal()"
                        class="text-white bg-red-600 hover:bg-red-700 font-bold py-2 px-4 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:scale-105">Cerrar</button>
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
    $(document).ready(function() {
        // Filtrar por usuario y obtener los lotes de ese usuario mediante AJAX
        $('#usuario').on('change', function(e) {
            e.preventDefault();
            var usuarioSeleccionado = $(this).val();
            if (usuarioSeleccionado !== '') {
                $('#filtrarBtn').prop('disabled', false);

                $.ajax({
                    url: 'baseDatosCajaChica.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_lotes',
                        usuario: usuarioSeleccionado
                    },
                    success: function(response) {
                        // ✅ Si ya viene JSON por header, jQuery a veces lo interpreta; por eso hacemos try/catch
                        var lotes = [];
                        try {
                            lotes = (typeof response === 'string') ? JSON.parse(response) : response;
                        } catch (e) {
                            lotes = [];
                        }

                        $('#lote').empty();
                        lotes.forEach(function(lote) {
                            $('#lote').append(new Option(lote, lote));
                        });
                        $('#lote').prop('disabled', false);

                        if (lotes.length > 0) {
                            $('#lote').val(lotes[0]);
                        }
                    }
                });
            } else {
                $('#filtrarBtn').prop('disabled', true);
                $('#lote').prop('disabled', true).empty();
            }
        });

        // Limpiar filtro y mostrar una tabla vacía
        $('#limpiarFiltro').on('click', function(e) {
            e.preventDefault();
            $('#usuario').val('');
            $('#lote').val('');
            $('#facturasBody').html(
                '<tr><td colspan="19" class="border px-4 py-2 text-center">No se encontraron resultados</td></tr>'
            );
        });

        // Descargar tabla como archivo Excel
        $('#descargarExcel').on('click', function(e) {
            e.preventDefault();
            var table = document.querySelector('table');
            var nombreUsuario = "<?php echo htmlspecialchars($nombre_usuario); ?>";
            var fecha = new Date().toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }).replace(/\//g, '-');

            var wb = XLSX.utils.table_to_book(table, { sheet: "Facturas" });
            var fileName = `CajaChica_${nombreUsuario}_${fecha}.xlsx`;
            XLSX.writeFile(wb, fileName);
        });

        // Buscador en tiempo real
        $('#buscador').on('input', function() {
            var query = $(this).val().toLowerCase();
            $('#facturasBody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(query) > -1);
            });
        });

        // Limpiar búsqueda
        $('#limpiarBusqueda').on('click', function() {
            $('#buscador').val('');
            $('#facturasBody tr').show();
        });
    });

    function mostrarModalArchivo(rutaArchivo) {
        $('#iframeArchivo').attr('src', rutaArchivo);
        $('#descargarArchivo').attr('href', rutaArchivo);
        $('#modalArchivo').removeClass('hidden');
    }

    function cerrarModal() {
        $('#modalArchivo').addClass('hidden');
        $('#iframeArchivo').attr('src', '');
    }
    </script>

</body>
</html>
