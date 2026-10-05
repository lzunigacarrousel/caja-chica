<?php
// nombre del archivo: tablaFacturasUsuariohtml.php (REPARADO)
// llamado en tablaFacturasUsuario.php
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Facturas</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <!-- Importar Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="estilos/tablaFacturas.css">
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
    <h2 class="text-center text-2xl font-bold mb-4">Tabla Listado de facturas</h2>
    <h2 class="text-center text-lg font-bold mb-2">Lote: <?php echo htmlspecialchars($lote); ?></h2>
    <hr>
    <br>

    <!-- Contenedor principal de botones y buscador centrado -->
    <div class="flex justify-center items-center">
        <div class="min-w-[95%] bg-white">
            <!-- Buscador -->
            <div class="flex flex-col md:flex-row items-center justify-center md:space-x-2 w-full">
                <input type="text" id="buscador" placeholder="Buscar contenido en la tabla..."
                       class="border px-4 py-1 rounded-md w-full"/>
                <button id="limpiarBusqueda"
                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md shadow-md text-sm transition duration-300 ease-in-out mt-2 md:mt-0">
                    Limpiar
                </button>
            </div>

            <!-- Botones -->
            <div class="flex justify-center space-x-4 mt-4">
                <button id="descargarExcel"
                        class="bg-green-500 hover:bg-green-600 text-white font-semibold py-1.5 px-3 rounded-md shadow-md hover:shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                    Descargar Excel
                </button>

                <button id="guardarPDFYExcel"
                        class="bg-blue-700 hover:bg-blue-800 text-white font-semibold py-1.5 px-3 rounded-md shadow-md hover:shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                    Guardar Excel y PDF
                </button>

                <button id="enviarCorreo"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1.5 px-3 rounded-md shadow-md hover:shadow-lg transition duration-300 ease-in-out transform hover:scale-105">
                    Enviar por Correo
                </button>
            </div>

            <!-- Modal correo -->
            <div id="modalCorreo" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
                <div class="bg-white p-6 rounded-lg max-w-md">
                    <button id="modalCloseCorreo"
                            class="text-white bg-red-500 hover:bg-red-600 font-bold text-2xl p-2 rounded-full w-10 h-10 flex items-center justify-center">X</button>
                    <br>
                    <label for="correoDestino">Correo destino</label>

                    <?php
                    $area_usuario = $_SESSION['area'] ?? '';
                    if ($tipo_usuario === 'empleado') {
                        if ($area_usuario === 'central') {
                            echo '<input type="email" name="correoDestino" id="correoDestino" value="elisa@carrousel.com.gt" class="border p-2 mb-4 w-full" readonly required>';
                        } elseif ($area_usuario === 'suroccidente') {
                            echo '<input type="email" name="correoDestino" id="correoDestino" value="fabiola@carrousel.com.gt" class="border p-2 mb-4 w-full" readonly required>';
                        } elseif ($area_usuario === 'nororiente') {
                            echo '<input type="email" name="correoDestino" id="correoDestino" value="ceidy@carrousel.com.gt" class="border p-2 mb-4 w-full" readonly required>';
                        } else {
                            // fallback por si el área no viene
                            echo '<input type="email" name="correoDestino" id="correoDestino" class="border p-2 mb-4 w-full" required>';
                        }
                    } else {
                        echo '<input type="email" name="correoDestino" id="correoDestino" placeholder="Correo destino" class="border p-2 mb-4 w-full" required>';
                    }
                    ?>

                    <label for="correoCC">Correo copia (correo de su parque)</label>
                    <input type="email" name="correoCC" id="correoCC"
                           placeholder="Correo copia (correo de su parque)" class="border p-2 mb-4 w-full">

                    <!-- Campos ocultos -->
                    <input type="hidden" name="ruta_excel" id="rutaExcel">
                    <input type="hidden" name="ruta_pdf" id="rutaPDF">
                    <input type="hidden" id="loteActual" value="<?php echo htmlspecialchars($lote); ?>">

                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded"
                            id="enviarFormularioCorreo">Enviar</button>
                </div>
            </div>

            <!-- Modal enviando -->
            <div id="modalEnviando" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
                <div class="bg-white p-6 rounded-lg max-w-md">
                    <h2 class="text-xl font-bold">Enviando...</h2>
                    <p class="text-center">Por favor espere.</p>
                </div>
            </div>
        </div>
    </div>

    <br><hr><br>

    <!-- Monto de caja -->
    <div class="grid grid-cols-3 gap-4 text-center items-center justify-center">
        <div class="flex flex-col items-center">
            <h3 class="text-lg font-bold">Monto de caja:</h3>
            <span id="montoCaja" class="text-green-600 font-bold">Q<?php echo number_format($montoCaja, 2); ?></span>
        </div>

        <div class="flex flex-col items-center">
            <h3 class="text-lg font-bold">Total consumido:</h3>
            <span id="totalConsumido" class="text-red-600 font-bold">Q0.00</span>
        </div>

        <div class="flex flex-col items-center">
            <h3 class="text-lg font-bold">Monto restante:</h3>
            <span id="montoRestante" class="text-black font-bold">Q0.00</span>
        </div>
    </div>

    <!-- Tabla de facturas -->
    <div class="overflow-x-auto mt-4" style="max-height: 400px; width: 100%; white-space: wrap;">
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
                <th class="px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody id="facturasBody">
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo generarFilaFactura($row);
                }
            } else {
                echo "<tr><td colspan='21' class='border px-4 py-2 text-center'>No se encontraron facturas para este lote.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<br><br>

<!-- Modal para subir archivos -->
<div id="modalSubir" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg max-w-md">
        <button id="modalCloseSubir"
                class="text-white bg-red-500 hover:bg-red-600 font-bold text-2xl p-2 rounded-full w-10 h-10 flex items-center justify-center">X</button>
        <br>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="archivo" class="border p-2 mb-4 w-full">
            <input type="hidden" name="factura_id" id="facturaId">
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Subir</button>
        </form>
    </div>
</div>

<!-- Modal para previsualizar archivos -->
<div id="modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg max-w-xl max-h-screen overflow-y-auto">
        <button id="modalClose"
                class="text-white bg-red-500 hover:bg-red-600 font-bold text-2xl p-2 rounded-full w-10 h-10 flex items-center justify-center">X</button>
        <div class="flex justify-between mt-4">
            <a id="descargarArchivo" class="bg-blue-500 text-white px-4 py-2 rounded" download>Descargar</a>
        </div>
        <br>
        <div id="modalContent"></div>
    </div>
</div>

<footer class="bg-gray-800 p-4 text-center text-white">
    <p>Corporación Carrousel S.A.</p>
</footer>

<script>
    // =========
    // Helpers
    // =========
    function parseJsonSafe(resp) {
        // Si jQuery ya devolvió objeto, no intentes parsear
        if (typeof resp === 'object') return resp;
        try { return JSON.parse(resp); } catch(e) { return null; }
    }

    // Función para actualizar el color del monto restante
    function actualizarMontos(montoRestante) {
        const montoRestanteElement = document.getElementById('montoRestante');
        if (!montoRestanteElement) return;
        if (montoRestante > 0) {
            montoRestanteElement.classList.remove('text-red-600');
            montoRestanteElement.classList.add('text-green-600');
        } else {
            montoRestanteElement.classList.remove('text-green-600');
            montoRestanteElement.classList.add('text-red-600');
        }
    }

    // Función para calcular los totales y actualizar el monto restante
    function calcularTotales() {
        var totalConsumido = 0;
        $('.valor-factura').each(function () {
            var valorFactura = $(this).text().replace(/[^0-9.-]+/g, "");
            totalConsumido += parseFloat(valorFactura) || 0;
        });

        var montoCaja = parseFloat($('#montoCaja').text().replace(/[^0-9.-]+/g, "")) || 0;
        var montoRestante = montoCaja - totalConsumido;

        $('#totalConsumido').text('Q' + totalConsumido.toFixed(2));
        $('#montoRestante').text('Q' + montoRestante.toFixed(2));

        actualizarMontos(montoRestante);
    }

    // =========
    // Ready
    // =========
    $(document).ready(function () {
        calcularTotales();

        // Scroll restore
        var scrollPos = sessionStorage.getItem('scrollPos');
        if (scrollPos) {
            window.scrollTo(0, scrollPos);
            sessionStorage.removeItem('scrollPos');
        }

        // Buscar
        $('#buscador').on('input', function () {
            var query = $(this).val().toLowerCase();
            $('#facturasBody tr').each(function () {
                var fila = $(this);
                var textoFila = fila.text().toLowerCase();
                if (textoFila.includes(query)) fila.show(); else fila.hide();
            });
        });

        $('#limpiarBusqueda').on('click', function () {
            $('#buscador').val('');
            $('#facturasBody tr').show();
        });

        // Restaurar scroll tras reload por guardar archivos
        var scrollPosition = localStorage.getItem('scrollPosition');
        if (scrollPosition) {
            window.scrollTo(0, scrollPosition);
            localStorage.removeItem('scrollPosition');
        }
    });

    // =========
    // Upload
    // =========
    $('#uploadForm').on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: 'tablaFacturasUsuario.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (jsonResponse) {
                Swal.fire({
                    title: jsonResponse.status === 'success' ? '¡Éxito!' : 'Error',
                    text: jsonResponse.message,
                    icon: jsonResponse.status === 'success' ? 'success' : 'error',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    if (jsonResponse.status === 'success') {
                        $('#fila-' + $('#facturaId').val()).replaceWith(jsonResponse.html);
                        inicializarEventos();
                        $('#modalSubir').addClass('hidden');
                        calcularTotales();
                    }
                });
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Error al subir el archivo.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    });

    // Eliminar archivo
    $(document).on('click', '.eliminar-archivo', function () {
        var facturaId = $(this).data('id');
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡No podrás revertir esto!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'tablaFacturasUsuario.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        eliminar_archivo: true,
                        factura_id: facturaId
                    },
                    success: function (jsonResponse) {
                        Swal.fire({
                            title: jsonResponse.status === 'success' ? '¡Éxito!' : 'Error',
                            text: jsonResponse.message,
                            icon: jsonResponse.status === 'success' ? 'success' : 'error',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            if (jsonResponse.status === 'success') {
                                $('#fila-' + facturaId).replaceWith(jsonResponse.html);
                                inicializarEventos();
                            }
                        });
                    },
                    error: function () {
                        Swal.fire({
                            title: 'Error',
                            text: 'Error al eliminar el archivo.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            }
        });
    });

    // Eliminar factura
    $(document).on('click', '.eliminar-factura', function () {
        var facturaId = $(this).data('id');
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡No podrás revertir esto!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'tablaFacturasUsuario.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        eliminar_factura: true,
                        factura_id: facturaId
                    },
                    success: function (jsonResponse) {
                        Swal.fire({
                            title: jsonResponse.status === 'success' ? '¡Éxito!' : 'Error',
                            text: jsonResponse.message,
                            icon: jsonResponse.status === 'success' ? 'success' : 'error',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            if (jsonResponse.status === 'success') {
                                $('#fila-' + facturaId).remove();
                                calcularTotales();
                            }
                        });
                    },
                    error: function () {
                        Swal.fire({
                            title: 'Error',
                            text: 'Error al eliminar la factura.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            }
        });
    });

    // =========
    // Modales
    // =========
    function inicializarEventos() {
        $('.modal-subir').off('click').on('click', function () {
            var facturaId = $(this).data('id');
            $('#facturaId').val(facturaId);
            $('#modalSubir').removeClass('hidden');
        });

        $('#modalCloseSubir').off('click').on('click', function () {
            $('#modalSubir').addClass('hidden');
        });

        $(document).off('click', '.modal-btn').on('click', '.modal-btn', function () {
            var archivoUrl = $(this).data('url');
            var modalContent = '';
            if ((archivoUrl || '').toString().toLowerCase().endsWith('.pdf')) {
                modalContent = `<embed src="${archivoUrl}" type="application/pdf" width="600" height="400">`;
            } else {
                modalContent = `<img src="${archivoUrl}" alt="Previsualización" class="max-w-full h-auto">`;
            }
            $('#modalContent').html(modalContent);
            $('#descargarArchivo').attr('href', archivoUrl);
            $('#modal').removeClass('hidden');
        });

        $('#modalClose').off('click').on('click', function () {
            $('#modal').addClass('hidden');
        });
    }
    inicializarEventos();

    // =========
    // Excel local
    // =========
    $('#descargarExcel').on('click', function () {
        var table = document.querySelector('table');
        var nombreUsuario = "<?php echo htmlspecialchars($nombre_usuario); ?>";
        var montoCaja = "<?php echo number_format($montoCaja, 2); ?>";
        var totalConsumido = document.getElementById('totalConsumido').innerText;
        var montoRestante = document.getElementById('montoRestante').innerText;

        var wb = XLSX.utils.book_new();

        var data = [
            ["Monto de caja:", "Total consumido:", "Monto restante:"],
            [montoCaja, totalConsumido, montoRestante],
            []
        ];

        var ws = XLSX.utils.aoa_to_sheet(data);
        var tableData = XLSX.utils.table_to_sheet(table);

        XLSX.utils.sheet_add_aoa(ws, XLSX.utils.sheet_to_json(tableData, {header: 1}), {origin: -1});
        XLSX.utils.book_append_sheet(wb, ws, "Facturas");

        var lote = "<?php echo $lote; ?>";
        var fileName = `CajaChica_${nombreUsuario}_${lote}.xlsx`;
        XLSX.writeFile(wb, fileName);
    });

    // =========
    // Guardar Excel+PDF server
    // =========
    $('#guardarPDFYExcel').on('click', function () {
        Swal.fire({
            title: 'Advertencia',
            text: "Una vez guardado el archivo ya no podrás editar ni agregar más facturas a tu lote actual. ¿Estás seguro?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'No, cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            var table = document.querySelector('table');
            var nombreUsuario = "<?php echo htmlspecialchars($nombre_usuario); ?>";
            var lote = "<?php echo $lote; ?>";
            var loteFormateado = lote.replace(/[\/:]/g, "_").replace(/\s/g, "_");

            localStorage.setItem('scrollPosition', window.scrollY);

            var wb = XLSX.utils.book_new();

            var montoCaja = "<?php echo number_format($montoCaja, 2); ?>";
            var totalConsumido = document.getElementById('totalConsumido').innerText;
            var montoRestante = document.getElementById('montoRestante').innerText;

            var data = [
                ["Monto de caja:", "Total consumido:", "Monto restante:"],
                [montoCaja, totalConsumido, montoRestante],
                []
            ];

            const columnasTexto = ['Serie del Documento', 'Numero de Documento', 'Descripción', 'Nombre de la cuenta de mayor'];

            var headers = Array.from(table.querySelectorAll('th')).map(header => header.innerText);
            var rows = Array.from(table.querySelectorAll('tr')).map(row => {
                var cells = Array.from(row.cells);
                var rowData = [];
                cells.forEach((cell, index) => {
                    var cellValue = cell.innerText.trim();
                    var header = headers[index] || '';

                    if (columnasTexto.includes(header)) {
                        rowData.push({t: 's', v: cellValue});
                    } else if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(cellValue)) {
                        var dateParts = cellValue.split(' ')[0].split('-');
                        var timeParts = cellValue.split(' ')[1].split(':');
                        rowData.push(new Date(dateParts[0], dateParts[1] - 1, dateParts[2], timeParts[0], timeParts[1], timeParts[2]));
                    } else if (/^\d{2}\/\d{2}\/\d{4}$/.test(cellValue) || /^\d{4}-\d{2}-\d{2}$/.test(cellValue)) {
                        var dp = cellValue.includes('-') ? cellValue.split('-') : cellValue.split('/');
                        rowData.push(cellValue.includes('-') ? new Date(dp[0], dp[1] - 1, dp[2]) : new Date(dp[2], dp[1] - 1, dp[0]));
                    } else {
                        var numericValue = parseFloat(cellValue.replace(',', ''));
                        rowData.push(isNaN(numericValue) ? cellValue : numericValue);
                    }
                });
                return rowData;
            });

            data = data.concat(rows);
            var ws = XLSX.utils.aoa_to_sheet(data);

            headers.forEach((header, index) => {
                if ((header || '').toLowerCase().includes("fecha")) {
                    for (let i = 1; i < rows.length; i++) {
                        let cellAddress = XLSX.utils.encode_cell({r: i, c: index});
                        if (ws[cellAddress] && ws[cellAddress].v instanceof Date) {
                            ws[cellAddress].z = 'dd/mm/yyyy hh:mm:ss';
                        }
                    }
                }
            });

            XLSX.utils.book_append_sheet(wb, ws, "Facturas");

            var fileName = `CajaChica_${nombreUsuario}_${loteFormateado}.xlsx`;
            var wbout = XLSX.write(wb, {bookType: 'xlsx', type: 'binary'});

            function s2ab(s) {
                var buf = new ArrayBuffer(s.length);
                var view = new Uint8Array(buf);
                for (var i = 0; i !== s.length; ++i) view[i] = s.charCodeAt(i) & 0xFF;
                return buf;
            }

            var blob = new Blob([s2ab(wbout)], {type: "application/octet-stream"});
            var formData = new FormData();
            formData.append('guardar_excel', true);
            formData.append('archivo', blob, fileName);

            $.ajax({
                url: 'tablaFacturasUsuario.php',
                type: 'POST',
                dataType: 'json',
                data: {verificar_existencia_pdf: true},
                success: function (jsonResponse) {
                    if (jsonResponse && jsonResponse.existe) {
                        Swal.fire({
                            title: 'Archivo PDF existente',
                            text: "El PDF combinado ya existe, ¿quieres sobrescribirlo?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, sobrescribir',
                            cancelButtonText: 'No, cancelar'
                        }).then((r) => {
                            if (r.isConfirmed) {
                                combinarArchivosPDF(function () {
                                    verificarExistenciaExcel(formData, nombreUsuario, loteFormateado);
                                });
                            }
                        });
                    } else {
                        combinarArchivosPDF(function () {
                            verificarExistenciaExcel(formData, nombreUsuario, loteFormateado);
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'Error al verificar la existencia del archivo PDF.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
        });
    });

    function verificarExistenciaExcel(formData, nombreUsuario, loteFormateado) {
        var fileName = `CajaChica_${nombreUsuario}_${loteFormateado}.xlsx`;
        $.ajax({
            url: 'tablaFacturasUsuario.php',
            type: 'POST',
            dataType: 'json',
            data: {verificar_existencia_excel: true, fileName: fileName},
            success: function (jsonResponse) {
                if (jsonResponse && jsonResponse.existe) {
                    Swal.fire({
                        title: 'Archivo existente',
                        text: "El archivo Excel ya existe, ¿deseas sobrescribirlo?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, sobrescribir',
                        cancelButtonText: 'No, cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) subirArchivo(formData);
                    });
                } else {
                    subirArchivo(formData);
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Error al verificar la existencia del archivo Excel.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    }

    function subirArchivo(formData) {
        $.ajax({
            url: 'tablaFacturasUsuario.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            // no pedimos dataType porque aquí tu PHP puede responder vacío o JSON; no lo usamos
            success: function () {
                Swal.fire({
                    title: 'Éxito',
                    text: 'Archivos guardados exitosamente en el servidor.',
                    icon: 'success',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    location.reload();
                });
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Error al guardar los archivos en el servidor.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    }

    function combinarArchivosPDF(callback) {
        $.ajax({
            url: 'tablaFacturasUsuario.php',
            type: 'POST',
            dataType: 'json',
            data: {combinar_archivos: true},
            success: function (jsonResponse) {
                if (!jsonResponse) {
                    Swal.fire({title: 'Error', text: 'Respuesta inválida del servidor.', icon: 'error', confirmButtonText: 'Aceptar'});
                    return;
                }
                if (jsonResponse.status === 'error') {
                    Swal.fire({title: 'Error', text: jsonResponse.message, icon: 'error', confirmButtonText: 'Aceptar'});
                } else if (jsonResponse.status === 'success') {
                    Swal.fire({title: 'Éxito', text: jsonResponse.message, icon: 'success', confirmButtonText: 'Aceptar'})
                        .then(() => { if (callback) callback(); });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Error al combinar los archivos en PDF.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    }

    // =========
    // Enviar correo
    // =========
    $('#enviarCorreo').on('click', function () {
        $.ajax({
            url: 'tablaFacturasUsuario.php',
            type: 'POST',
            dataType: 'json',
            data: {verificar_existencia_archivos: true},
            success: function (jsonResponse) {
                if (jsonResponse && jsonResponse.existen) {
                    $('#modalCorreo').removeClass('hidden');
                    $('#rutaExcel').val(jsonResponse.rutaExcel);
                    $('#rutaPDF').val(jsonResponse.rutaPDF);
                } else {
                    Swal.fire({
                        title: 'Archivos faltantes',
                        text: 'Debes guardar los archivos antes de enviar el correo.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                }
            },
            error: function () {
                Swal.fire({
                    title: 'Error',
                    text: 'Hubo un problema al verificar los archivos.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    });

    $('#modalCloseCorreo').on('click', function () {
        $('#modalCorreo').addClass('hidden');
    });

    $('#enviarFormularioCorreo').on('click', function (e) {
    e.preventDefault();

    var correoDestino = $('#correoDestino').val();
    var correoCC = $('#correoCC').val();
    var rutaExcel = $('#rutaExcel').val();
    var rutaPDF = $('#rutaPDF').val();
    var lote = $('#loteActual').val();

    if (correoDestino && rutaExcel && rutaPDF) {
        $('#modalEnviando').removeClass('hidden');

        $.ajax({
            url: 'enviarCorreo.php',
            type: 'POST',
            dataType: 'json',
            data: {
                correo_destino: correoDestino,
                correo_cc: correoCC,
                ruta_excel: rutaExcel,
                ruta_pdf: rutaPDF,
                lote: lote
            },
            success: function (jsonResponse) {
                Swal.fire({
                    title: jsonResponse.status === 'success' ? '¡Éxito!' : 'Error',
                    text: jsonResponse.message,
                    icon: jsonResponse.status === 'success' ? 'success' : 'error',
                    confirmButtonText: 'Aceptar'
                }).then(function () {
                    $('#modalEnviando').addClass('hidden');
                    $('#modalCorreo').addClass('hidden');
                });
            },
            error: function (xhr, status, error) {
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('Respuesta del servidor:', xhr.responseText);

                Swal.fire({
                    title: 'Error',
                    text: 'Error al enviar el correo. Revisa la consola y la respuesta del servidor.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                }).then(function () {
                    $('#modalEnviando').addClass('hidden');
                });
            }
        });
    } else {
        Swal.fire({
            title: 'Error',
            text: 'Por favor, asegúrate de que todos los campos están completos y los archivos están adjuntados.',
            icon: 'error',
            confirmButtonText: 'Aceptar'
        });
    }
});
</script>

</body>
</html>
