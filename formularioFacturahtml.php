<?php
// Archivo: formulariofacturahtml.php (REPARADO)
// Nota: este archivo contiene HTML + bloques PHP. Puede ser incluido desde formularioFactura.php.
// Correcciones: evitar variables indefinidas (usuarioActual/loteMensaje/sucursalEmpleado), asegurar sesión y charset.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('conectarBD')) {
    require_once 'utilidades.php';
}

// Si se accede directo y no hay sesión, redirigir
if (!isset($_SESSION['usuario'])) {
    // Si este archivo se incluye dentro de otro flujo, es posible que no quieras redirigir;
    // pero la condición solo aplica cuando se accede directo sin sesión.
    header("Location: index.php");
    exit();
}

// Evitar warnings: si el controlador ya definió estas variables, no las tocamos.
if (!isset($usuarioActual) || !is_array($usuarioActual)) {
    // Intentar obtener datos del usuario desde BD (si existe tabla usuarios)
    $usuarioActual = [
        'nombre' => $_SESSION['usuario'],
        'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'empleado',
    ];

    // Si hay conexión disponible, intentamos completar sucursal/tipo
    try {
        $cx = conectarBD();
        if ($cx && method_exists($cx, 'set_charset')) { @$cx->set_charset('utf8mb4'); }

        $stmt = $cx->prepare("SELECT nombre, tipo_usuario, sucursal, correo, area, montoCaja FROM usuarios WHERE nombre = ? LIMIT 1");
        if ($stmt) {
            $u = $_SESSION['usuario'];
            $stmt->bind_param("s", $u);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $usuarioActual = $res->fetch_assoc();
            }
            $stmt->close();
        }
        if ($cx) { $cx->close(); }
    } catch (Throwable $e) {
        // Silencioso para no romper la vista
    }
}

if (!isset($loteMensaje)) {
    $loteMensaje = '';
}
if (!isset($sucursalEmpleado)) {
    // Si el usuarioActual trae sucursal, úsala
    $sucursalEmpleado = $usuarioActual['sucursal'] ?? '';
}
?>

<!--formulariofacturahtml.pho-->

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario ingreso facturas</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">


    <!-- Importación de Tailwind CSS para los estilos del formulario -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Importación de jQuery para manejar solicitudes AJAX y eventos dinámicos -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Importación de SweetAlert para las alertas interactivas -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Hoja de estilos personalizada para el formulario -->
    <link rel="stylesheet" href="estilos/formularioFactura.css">
</head>

<body class="bg-gray-100">
    <!-- Encabezado del sitio -->
    <header class="bg-gray-800 p-4 flex justify-between items-center text-white">
        <!-- Enlace para cerrar sesión -->
        <div class="flex items-center">
            <a href="logout.php" class="text-white mr-4">Cerrar sesión</a>
        </div>
        <!-- Logotipo del sitio -->
        <div class="text-center">
            <img src="imagenes/logo.png" alt="logo" class="h-12 mx-auto">
        </div>
        <!-- Enlace para volver al menú principal -->
        <div class="flex items-center">
            <a href="menu.php" class="text-white ml-4">Volver al Menú</a>
        </div>
    </header>
    <br><br>

    <!-- Contenedor principal que contiene el formulario -->
    <div class="container mx-auto mt-16 p-8 bg-white rounded-lg shadow-lg">



        <!-- Contenedor para alinear ambos botones en una fila -->
        <div class="flex justify-between mb-4 button-container">





            <!-- Botón para agregar Nuevo NIT a la base de datos a la izquierda -->
            <?php if ($usuarioActual['tipo_usuario'] !== 'empleado'): ?>
            <div>
                <button type="button" class="px-4 py-2 bg-purple-900 text-white rounded" onclick="toggleAgregarNit()">
                    Agregar Nuevo NIT a la base de datos
                </button>
            </div>
            <?php endif; ?>


            <!-- Botón para crear un nuevo lote de facturas a la derecha -->
            <div class="flex justify-end">
                <button id="crearLoteBtn" class="py-2 bg-indigo-700 text-white px-4 rounded">
                    Crear Nuevo Lote de facturas
                </button>
            </div>



        </div>


        <!--   Formulario expandible para agregar un nuevo NIT -->
        <div id="formAgregarNit" style="display: none;" class="mt-4">
            <div class="mb-4">
                <label class="block text-gray-700" for="nuevo-nit">NIT:</label>
                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="text" id="nuevo-nit"
                    name="nuevo-nit">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700" for="nuevo-nombre">Nombre:</label>
                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="text" id="nuevo-nombre"
                    name="nuevo-nombre">
            </div>
            <!--Botón para enviar el nuevo NIT -->
            <button type="button" class="px-4 py-2 bg-indigo-700 text-white rounded" onclick="agregarNit()">
                Agregar NIT
            </button>
        </div>



        <!-- Titulo del formulario-->
        <h2 class="text-center text-3xl font-extrabold mb-6 text-primary ">
            Formulario de Ingreso de Facturas
        </h2>


        <!-- Inicio del formulario de Compra/Venta -->
        <form id="compraVentaForm" method="post" class="space-y-4">
            <!-- Campo para mostrar el lote actual de las facturas -->
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2 text-lg text-center" for="lote_actual">Lote de
                    facturas Actual:</label>
                <input
                    class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg bg-gray-100 text-blue-700 font-bold text-center"
                    type="text" id="lote_actual" name="lote_actual" value="<?php echo $loteMensaje; ?>" readonly>
            </div>









            <div class="container-elementos">
                <!-- Columna única -->
                <div>
                    <div class="mb-4">

                        <!-- dos columnas-->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Campo para seleccionar un Usuario -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="nombre_cc">Parque:</label>
                                <div class="flex">
                                    <?php if ($usuarioActual['tipo_usuario'] == 'empleado'): ?>
                                    <input
                                        class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                        type="text" id="nombre_cc" name="nombre_cc"
                                        value="<?php echo $usuarioActual['nombre']; ?>" readonly>
                                    <?php else: ?>
                                    <input class="w-full px-2 py-1 border border-gray-300 rounded" type="text"
                                        id="nombre_cc" name="nombre_cc" required readonly>
                                    <button id="botonSeleccionarUsuario" type="button"
                                        class="ml-2 px-3 py-1 bg-blue-600 text-white rounded"
                                        onclick="mostrarVentanaUsuario()">Seleccionar</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Campo para mostrar la Sucursal seleccionada -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="sucursal">Sucursal:</label>
                                <div class="flex">
                                    <?php if ($usuarioActual['tipo_usuario'] !== 'admin'): ?>
                                    <input
                                        class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                        type="text" id="sucursal" name="sucursal"
                                        value="<?php echo $sucursalEmpleado; ?>" readonly required>
                                    <?php else: ?>
                                    <input
                                        class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                        type="text" id="sucursal" name="sucursal" readonly required>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Campo para seleccionar la Cuenta mediante un modal -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="nombre_cuenta">Nombre de la cuenta:</label>
                                <div class="flex">
                                    <input class="w-full px-2 py-1 border border-gray-300 rounded" type="text"
                                        id="nombre_cuenta" name="nombre_cuenta" required readonly>
                                    <button type="button" class="ml-2 px-3 py-1 bg-blue-600 text-white rounded"
                                        onclick="mostrarVentanaCuentas()">Seleccionar</button>
                                </div>
                            </div>

                            <!-- Campo para mostrar la cuenta de mayor seleccionada -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="cuenta_mayor">Cuenta de Mayor:</label>
                                <input class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                    type="text" id="cuenta_mayor" name="cuenta_mayor" required readonly>
                            </div>

                            <!-- Campo para mostrar el NIT del proveedor seleccionado -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="nit_proveedor">NIT Proveedor:</label>
                                <div class="flex">
                                    <input class="w-full px-3 py-2 border border-gray-300 rounded" type="text"
                                        id="nit_proveedor" name="nit_proveedor" required readonly>
                                    <button type="button" class="ml-2 px-4 py-2 bg-blue-600 text-white rounded"
                                        onclick="mostrarVentanaNit()">Seleccionar</button>
                                </div>
                            </div>

                            <!-- Campo para mostrar el nombre del proveedor -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="nombre_proveedor">Nombre Proveedor:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                    type="text" id="nombre_proveedor" name="nombre_proveedor" required readonly>
                            </div>


                            <!-- Selección del tipo de Compra/Venta -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="tipo_compraventa">Tipo de Compra/Venta:</label>
                                <select id="tipo_compraventa" name="tipo_compraventa"
                                    class="w-full px-3 py-2 border border-gray-300 rounded" required
                                    onchange="setIndicadorImpuestos(); toggleCombustibleFields()">
                                    <option value="Seleccionar">Seleccionar</option>
                                    <option value="pequeño contribuyente">Pequeño Contribuyente</option>
                                    <option value="Bien">Bien</option>
                                    <option value="Servicio">Servicio</option>
                                    <option value="Combustible">Combustible</option>


                                </select>
                            </div>

                            <!-- Campo para mostrar el Indicador de Impuestos -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="indicador_impuestos">Indicador de
                                    Impuestos:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                    type="text" id="indicador_impuestos" name="indicador_impuestos" readonly required>
                            </div>
                        </div>
                        <!-- fin dos columnas-->




                        <!-- una columna -->

                        <!-- Campo para la descripción de la compra o venta -->
                        <div class="mb-4">
                            <label class="block text-gray-700" for="descripcion">Descripción:</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="descripcion"
                                name="descripcion" rows="3" required></textarea>
                        </div>




                        <!-- dos columnas-->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <!-- Campo para ingresar la serie del documento -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="serie_documento">Serie del documento:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="text"
                                    id="serie_documento" name="serie_documento" required>
                            </div>

                            <!-- Campo para ingresar el número del documento -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="numero_documento">Número del documento:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="text"
                                    id="numero_documento" name="numero_documento" required>
                            </div>

                            <!-- Campo para seleccionar la fecha del documento -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="fecha_documento">Fecha del documento:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="date"
                                    id="fecha_documento" name="fecha_documento" required
                                    max="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <!-- Campo para ingresar el valor de la factura -->
                            <div class="mb-4">
                                <label class="block text-gray-700" for="valor_factura">Valor de la factura:</label>
                                <input class="w-full px-3 py-2 border border-gray-300 rounded" type="number" step="0.01"
                                    id="valor_factura" name="valor_factura" required>
                            </div>

                        </div>
                        <!-- fin dos columnas-->

                        <!-- Campos para la selección de combustible, ocultos por defecto -->
                        <div id="combustibleFields" class="hidden mt-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Columna 1: Selección del tipo de combustible -->
                                <div class="mb-4">
                                    <label class="block text-gray-700" for="tipo_combustible">Tipo de
                                        Combustible:</label>
                                    <select id="tipo_combustible" name="tipo_combustible"
                                        class="w-full px-3 py-2 border border-gray-300 rounded">
                                        <option value="">Seleccionar</option>
                                        <option value="Diesel">Diesel</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Super">Super (premium)</option>
                                    </select>
                                </div>

                                <!-- Columna 2: Ingreso de galones -->
                                <div class="mb-4">
                                    <label class="block text-gray-700" for="galones">Galones:</label>
                                    <input class="w-full px-3 py-2 border border-gray-300 rounded" type="number"
                                        step="0.0001" id="galones" name="galones">
                                </div>

                                <!-- Columna 3: Total de la factura de combustible -->
                                <div class="mb-4">
                                    <label class="block text-gray-700" for="total_factura_combustible">Total Factura
                                        Combustible:</label>
                                    <input
                                        class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600"
                                        type="number" step="0.01" id="total_factura_combustible"
                                        name="total_factura_combustible" readonly>
                                </div>
                            </div>
                        </div>


                        <!-- Botón para grabar los datos -->
                        <div class="text-center">
                            <input id="submitBtn" class="px-4 py-2 py-2 bg-indigo-700 text-white rounded cursor-pointer"
                                type="submit" value="Grabar registro" <?php if ($loteMensaje == "No hay lotes, debe crear uno.")
                                    echo 'disabled'; ?>>
                        </div>




                    </div>
                </div>
            </div>











        </form>



    </div>

    <!-- Ventanas modales para la selección de NIT, Cuentas y Usuarios -->
    <div id="ventana_fondo_nit" class="modal-overlay" onclick="cerrarVentanaNit()">
        <div id="ventana_nit" class="modal-content" onclick="event.stopPropagation()">
            <h3 class="text-center text-xl font-bold">Seleccionar NIT</h3>
            <!-- Búsqueda por NIT -->
            <input type="text" id="filtro_nit" onkeyup="buscarNit()" placeholder="Buscar NIT"
                class="w-full px-3 py-2 border border-gray-300 rounded mb-4">
            <div id="lista_nit" class="modal-list"></div>
            <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded mt-4 mx-auto block"
                onclick="cerrarVentanaNit()">Cerrar</button>
        </div>
    </div>

    <div id="ventana_fondo_cuentas" class="modal-overlay" onclick="cerrarVentanaCuentas()">
        <div id="ventana_cuentas" class="modal-content" onclick="event.stopPropagation()">
            <h3 class="text-center text-xl font-bold mb-4">Seleccionar Cuenta</h3>
            <!-- Búsqueda por Cuenta -->
            <input type="text" id="filtro_cuenta" onkeyup="buscarCuentas()" placeholder="Buscar Cuenta"
                class="w-full px-3 py-2 border border-gray-300 rounded mb-4">
            <div id="lista_cuentas" class="modal-list"></div>
            <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded mt-4 mx-auto block"
                onclick="cerrarVentanaCuentas()">Cerrar</button>
        </div>
    </div>

    <!-- Modal para selección de Usuarios -->
    <div id="ventana_fondo_usuario" class="modal-overlay" onclick="cerrarVentanaUsuario()">
        <div id="ventana_usuario" class="modal-content" onclick="event.stopPropagation()">
            <h3 class="text-center text-xl font-bold mb-4">Seleccionar Usuario</h3>
            <!-- Búsqueda por Usuario -->
            <input type="text" id="filtro_usuario" onkeyup="buscarUsuarios()" placeholder="Buscar Usuario"
                class="w-full px-3 py-2 border border-gray-300 rounded mb-4">
            <div id="lista_usuarios" class="modal-list"></div>
            <button type="button" class="px-4 py-2 bg-blue-500 text-white rounded mt-4 mx-auto block"
                onclick="cerrarVentanaUsuario()">Cerrar</button>
        </div>
    </div>



    <!-- Tabla de facturas registradas  Importación-->
    <?php include 'tablaFacturasUsuarioform.php'; ?>
    <!-- Tabla de facturas registradas  Importación-->



    <!-- Pie de página -->
    <footer class="bg-gray-800 p-4 text-center text-white">
        <p>Corporación Carrousel S.A.</p>
    </footer>




    <!-- Script que contiene todas las funciones JavaScript -->
    <script>
    // Funciones para mostrar y cerrar los modales

    function mostrarVentanaNit() {
        document.getElementById("ventana_fondo_nit").style.display = "flex"; // Muestra el modal de NIT
        document.getElementById("ventana_nit").style.display = "block"; // Asegura que el modal esté visible
        buscarNit(); // Llama a la función para realizar la búsqueda de NIT
    }

    function cerrarVentanaNit() {
        document.getElementById("ventana_fondo_nit").style.display = "none"; // Oculta el modal de NIT
        document.getElementById("ventana_nit").style.display = "none"; // Oculta el contenido del modal
    }

    function mostrarVentanaCuentas() {
        document.getElementById("ventana_fondo_cuentas").style.display = "flex"; // Muestra el modal de Cuentas
        document.getElementById("ventana_cuentas").style.display = "block"; // Asegura que el modal esté visible
        buscarCuentas(); // Llama a la función para realizar la búsqueda de Cuentas
    }

    function cerrarVentanaCuentas() {
        document.getElementById("ventana_fondo_cuentas").style.display = "none"; // Oculta el modal de Cuentas
        document.getElementById("ventana_cuentas").style.display = "none"; // Oculta el contenido del modal
    }

    function mostrarVentanaUsuario() {
        document.getElementById("ventana_fondo_usuario").style.display = "flex"; // Muestra el modal de Usuarios
        document.getElementById("ventana_usuario").style.display = "block"; // Asegura que el modal esté visible
        buscarUsuarios(); // Llama a la función para realizar la búsqueda de Usuarios
    }

    function cerrarVentanaUsuario() {
        document.getElementById("ventana_fondo_usuario").style.display = "none"; // Oculta el modal de Usuarios
        document.getElementById("ventana_usuario").style.display = "none"; // Oculta el contenido del modal
    }

    // Funciones para seleccionar valores desde los modales y llenar los campos del formulario
    function seleccionarNit(nit, nombre) {
        document.getElementById("nit_proveedor").value = nit; // Asigna el NIT seleccionado al campo NIT del formulario
        document.getElementById("nombre_proveedor").value = nombre; // Asigna el nombre del proveedor seleccionado

        // Comprobar si el NIT o nombre es "CORPORACIÓN CARROUSEL, S.A." o NIT es 26255790
        if (nit === '26255790' || nombre === 'CORPORACIÓN CARROUSEL, S.A.') {
            document.getElementById("tipo_compraventa").innerHTML =
                '<option value="No aplica" selected>No aplica</option>';
            document.getElementById("indicador_impuestos").value = "EXE"; // Cambia el indicador de impuestos a EXE
        } else {
            // Si no se cumple la condición, vuelve a mostrar las opciones originales
            document.getElementById("tipo_compraventa").innerHTML = `
            <option value="Seleccionar">Seleccionar</option>
            <option value="pequeño contribuyente">Pequeño Contribuyente</option>
            <option value="Bien">Bien</option>
            <option value="Servicio">Servicio</option>
            <option value="Combustible">Combustible</option>
        `;
            document.getElementById("indicador_impuestos").value = ""; // Resetea el indicador de impuestos
        }

        cerrarVentanaNit(); // Cierra el modal de selección de NIT
    }


    function seleccionarCuenta(codigo, nombre) {
        document.getElementById('cuenta_mayor').value =
            codigo; // Asigna el código de la cuenta al campo de cuenta mayor
        document.getElementById('nombre_cuenta').value = nombre; // Asigna el nombre de la cuenta al formulario
        cerrarVentanaCuentas(); // Cierra el modal de selección de cuenta
    }

    function seleccionarUsuario(sucursal, nombre) {
        document.getElementById('sucursal').value =
            sucursal; // Asigna la sucursal seleccionada al campo del formulario
        document.getElementById('nombre_cc').value = nombre; // Asigna el nombre del usuario seleccionado
        cerrarVentanaUsuario(); // Cierra el modal de selección de usuarios
    }

    // Funciones para realizar las búsquedas en los modales
    function buscarNit() {
        var filtro = $('#filtro_nit').val(); // Obtiene el valor del campo de filtro de NIT
        $.post('formularioFactura.php', {
            accion: 'buscar_nit', // Envia la acción 'buscar_nit' al servidor
            filtro: filtro // Envia el filtro introducido por el usuario
        }, function(response) {
            var lista = $('#lista_nit'); // Referencia al contenedor donde se mostrarán los resultados
            lista.empty(); // Vacía la lista antes de agregar los resultados

            if (response.nits.length > 0) {
                // Si se encuentran resultados, se muestran en la lista
                response.nits.forEach(function(nit) {
                    lista.append('<div class="nit-item" onclick="seleccionarNit(\'' + nit.nit +
                        '\', \'' + nit.nombre + '\')">' + nit.nit + ' - ' + nit.nombre +
                        '</div>');
                });
            } else {
                // Si no se encuentran resultados, se muestra un mensaje
                lista.append(
                    '<div class="nit-item text-red-500">No se encontró NIT, comuníquese con su supervisor o el departamento de Sistemas.</div>'
                );
            }
        }, 'json');
    }

    function buscarCuentas() {
        var filtro = $('#filtro_cuenta').val(); // Obtiene el valor del campo de filtro de cuentas
        $.post('formularioFactura.php', {
            accion: 'buscar_cuentas', // Envia la acción 'buscar_cuentas' al servidor
            filtro: filtro // Envia el filtro introducido por el usuario
        }, function(response) {
            var lista = $('#lista_cuentas'); // Referencia al contenedor donde se mostrarán los resultados
            lista.empty(); // Vacía la lista antes de agregar los resultados

            if (response.cuentas.length > 0) {
                // Si se encuentran resultados, se muestran en la lista
                response.cuentas.forEach(function(cuenta) {
                    lista.append('<div class="cuenta-item" onclick="seleccionarCuenta(\'' + cuenta
                        .codigo + '\', \'' + cuenta.nombre + '\')">' + cuenta.codigo + ' - ' +
                        cuenta.nombre + '</div>');
                });
            } else {
                // Si no se encuentran resultados, se muestra un mensaje
                lista.append(
                    '<div class="cuenta-item text-red-500">No se encontró ningún valor, por favor comuníquese con su supervisor o con el departamento de sistemas.</div>'
                );
            }
        }, 'json');
    }

    function buscarUsuarios() {
        var filtro = $('#filtro_usuario').val(); // Obtiene el valor del campo de filtro de usuarios
        $.post('formularioFactura.php', {
            accion: 'buscar_usuarios', // Envia la acción 'buscar_usuarios' al servidor
            filtro: filtro // Envia el filtro introducido por el usuario
        }, function(response) {
            var lista = $('#lista_usuarios'); // Referencia al contenedor donde se mostrarán los resultados
            lista.empty(); // Vacía la lista antes de agregar los resultados

            if (response.usuarios.length > 0) {
                // Si se encuentran resultados, filtra los usuarios llamados "administrador" y "diego"
                response.usuarios.forEach(function(usuario) {
                    if (usuario.nombre !== 'administrador' && usuario.nombre !== 'diego')
                    // Si se encuentran resultados, filtra los usuarios llamados "administrador" y "diego"
                    {
                        lista.append('<div class="usuario-item" onclick="seleccionarUsuario(\'' +
                            usuario.sucursal + '\', \'' + usuario.nombre + '\')">' + usuario
                            .sucursal +
                            ' - ' + usuario.nombre + '</div>');
                    }
                });
            } else {
                // Si no se encuentran resultados, se muestra un mensaje
                lista.append('<div class="usuario-item text-red-500">No se encontró ningún usuario</div>');
            }
        }, 'json');
    }




    // Confirmación para crear un nuevo lote de facturas
    document.getElementById('crearLoteBtn').addEventListener('click', function() {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas crear un nuevo lote?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Si el usuario confirma, se envía la solicitud para crear el lote
                $.post('formularioFactura.php', {
                    accion: 'crear_lote'
                }, function(response) {
                    if (response.status === 'success') {
                        // Si se crea correctamente, muestra un mensaje de éxito
                        Swal.fire('Lote Creado', response.mensaje, 'success').then(() => {
                            window.location
                                .reload(); // Recarga toda la página después de guardar el lote
                        });
                    } else {
                        // Si hay un error, se muestra un mensaje de error
                        Swal.fire('Error', response.mensaje, 'error');
                    }
                }, 'json');
            }
        });
    });




    // Función para establecer el indicador de impuestos según el tipo de compra/venta seleccionado
    function setIndicadorImpuestos() {
        const tipoCompraventa = document.getElementById("tipo_compraventa").value.trim();
        const indicadorImpuestos = document.getElementById("indicador_impuestos");

        if (tipoCompraventa === "Seleccionar") {
            indicadorImpuestos.value = "";
        } else if (tipoCompraventa === "pequeño contribuyente" || tipoCompraventa === "No aplica") {
            indicadorImpuestos.value = "EXE"; // Exento
        } else {
            indicadorImpuestos.value = "IVA";
        }
    }




    // Manejo del envío del formulario con validaciones y AJAX
    document.getElementById('compraVentaForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Evita que el formulario se envíe automáticamente

        // Variables que almacenan los valores de los campos del formulario
        const loteActual = document.getElementById('lote_actual').value;
        const nombreCC = document.getElementById('nombre_cc').value;
        const sucursal = document.getElementById('sucursal').value;
        const nombreCuenta = document.getElementById('nombre_cuenta').value;
        const cuentaMayor = document.getElementById('cuenta_mayor').value;
        const tipoCompraventa = document.getElementById('tipo_compraventa').value;
        const indicadorImpuestos = document.getElementById('indicador_impuestos').value;
        const descripcion = document.getElementById('descripcion').value;
        const nitProveedor = document.getElementById('nit_proveedor').value;
        const nombreProveedor = document.getElementById('nombre_proveedor').value;
        const valorFactura = document.getElementById('valor_factura').value;
        const serieDocumento = document.getElementById('serie_documento').value;
        const numeroDocumento = document.getElementById('numero_documento').value;
        const fechaDocumento = document.getElementById('fecha_documento').value;

        // Campos adicionales para combustible
        const tipoCombustible = document.getElementById('tipo_combustible').value;
        const galones = document.getElementById('galones').value;
        const totalFacturaCombustible = document.getElementById('total_factura_combustible').value;

        // Verificar si los campos de combustible están visibles
        const combustibleFields = document.getElementById('combustibleFields');
        let errorMessage = '';

        // Verificar los campos obligatorios generales
        if (!loteActual) {
            errorMessage = 'El campo "Lote Actual" es obligatorio.';
        } else if (!nombreCC) {
            errorMessage = 'El campo "Usuario" es obligatorio.';
        } else if (!sucursal) {
            errorMessage = 'El campo "Sucursal" es obligatorio.';
        } else if (!nombreCuenta) {
            errorMessage = 'El campo "Nombre de la cuenta" es obligatorio.';
        } else if (!cuentaMayor) {
            errorMessage = 'El campo "Cuenta de Mayor" es obligatorio.';
        } else if (!tipoCompraventa || tipoCompraventa === 'Seleccionar') {
            errorMessage = 'El campo "Tipo de Compra/Venta" es obligatorio.';
        } else if (!indicadorImpuestos) {
            errorMessage = 'El campo "Indicador de Impuestos" es obligatorio.';
        } else if (!descripcion) {
            errorMessage = 'El campo "Descripción" es obligatorio.';
        } else if (!nitProveedor) {
            errorMessage = 'El campo "NIT Proveedor" es obligatorio.';
        } else if (!nombreProveedor) {
            errorMessage = 'El campo "Nombre Proveedor" es obligatorio.';
        } else if (!valorFactura) {
            errorMessage = 'El campo "Valor de la factura" es obligatorio.';
        } else if (!serieDocumento) {
            errorMessage = 'El campo "Serie del documento" es obligatorio.';
        } else if (!numeroDocumento) {
            errorMessage = 'El campo "Número del documento" es obligatorio.';
        } else if (!fechaDocumento) {
            errorMessage = 'El campo "Fecha del documento" es obligatorio.';
        }

        // Verificar los campos de combustible solo si están visibles
        if (!errorMessage && !combustibleFields.classList.contains('hidden')) {
            if (!tipoCombustible) {
                errorMessage = 'El campo "Tipo de Combustible" es obligatorio.';
            } else if (!galones) {
                errorMessage = 'El campo "Galones" es obligatorio.';
            } else if (!totalFacturaCombustible) {
                errorMessage = 'El campo "Total Factura Combustible" es obligatorio.';
            }
        }

        // Mostrar el mensaje de error si se encuentra algún campo vacío
        if (errorMessage) {
            Swal.fire({
                title: 'Error',
                text: errorMessage,
                icon: 'error'
            });
            return; // Salir de la función para evitar el envío del formulario
        }

        // Si no hay errores, el formulario se puede enviar mediante AJAX
        $.ajax({
            type: 'POST',
            url: 'formularioFactura.php', // URL a la que se enviará el formulario
            data: $('#compraVentaForm').serialize() +
                '&accion=grabar_factura', // Serializa el formulario y añade la acción
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'La factura ha sido guardada correctamente.',
                        icon: 'success'
                    }).then(() => {
                        window.location.reload(); // Recarga la página después de guardar
                    });
                } else if (response.status === 'warning') {
                    // Si el documento ya existe y el usuario puede agregarlo de nuevo
                    Swal.fire({
                        title: 'Documento Duplicado',
                        text: response.message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, agregar de nuevo',
                        cancelButtonText: 'No, cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Si el usuario confirma, enviar otra solicitud para agregar el documento
                            $.ajax({
                                type: 'POST',
                                url: 'formularioFactura.php',
                                data: $('#compraVentaForm').serialize() +
                                    '&accion=grabar_factura&confirmacion=si',
                                dataType: 'json',
                                success: function(response) {
                                    if (response.status === 'success') {
                                        Swal.fire({
                                            title: '¡Éxito!',
                                            text: 'El documento ha sido guardado correctamente.',
                                            icon: 'success'
                                        }).then(() => {
                                            window.location
                                                .reload(); // Recargar la página después de guardar
                                        });
                                    } else {
                                        Swal.fire({
                                            title: 'Error',
                                            text: response.message ||
                                                'Ocurrió un error al guardar el documento.',
                                            icon: 'error'
                                        });
                                    }
                                }
                            });
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message || 'Ocurrió un error al guardar la factura.',
                        icon: 'error'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error',
                    text: 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.',
                    icon: 'error'
                });
            }
        });
    });





    // Mostrar u ocultar los campos de combustible según el tipo de compra/venta seleccionado
    function toggleCombustibleFields() {
        const tipoCompraventa = document.getElementById("tipo_compraventa").value;
        const combustibleFields = document.getElementById("combustibleFields");

        if (tipoCompraventa === "Combustible") {
            combustibleFields.classList.remove("hidden"); // Muestra los campos de combustible
            document.getElementById('total_factura_combustible').value = document.getElementById('valor_factura')
                .value;
        } else {
            // Oculta los campos de combustible y los resetea si no es "Combustible"
            combustibleFields.classList.add("hidden");
            document.getElementById('tipo_combustible').value = '';
            document.getElementById('galones').value = '';
            document.getElementById('total_factura_combustible').value = '';
        }
    }

    // Actualizar el total_factura_combustible automáticamente cuando cambia el valor de la factura
    document.getElementById('valor_factura').addEventListener('input', function() {
        const tipoCompraventa = document.getElementById("tipo_compraventa").value;
        if (tipoCompraventa === "Combustible") {
            document.getElementById('total_factura_combustible').value = this
                .value; // Actualiza el total de combustible
        }
    });

    // Función para expandir o colapsar el formulario de agregar NIT
    function toggleAgregarNit() {
        var formAgregarNit = document.getElementById("formAgregarNit");
        if (formAgregarNit.style.display === "none" || formAgregarNit.style.display === "") {
            formAgregarNit.style.display = "block"; // Muestra el formulario de agregar NIT
        } else {
            formAgregarNit.style.display = "none"; // Oculta el formulario de agregar NIT
        }
    }


    // Función para agregar un nuevo NIT a la base de datos
    function agregarNit() {
        var nit = document.getElementById("nuevo-nit").value.trim(); // Obtener el NIT ingresado
        var nombre = document.getElementById("nuevo-nombre").value.trim(); // Obtener el nombre ingresado

        // Verificar si los campos están vacíos
        if (nit === '' || nombre === '') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Todos los campos son obligatorios.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // Datos a enviar al servidor
        var formData = {
            nit: nit,
            nombre: nombre,
            accion: 'agregar_nit' // Acción para agregar NIT
        };

        // Enviar los datos mediante AJAX al archivo PHP
        $.ajax({
            url: 'formularioFactura.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                response = JSON.parse(response); // Parsear la respuesta JSON
                if (response.status === 'error' && response.message.includes('NIT ya existe')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'El NIT ya se encuentra en la base de datos.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'NIT agregado con éxito',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    toggleAgregarNit(); // Ocultar el formulario de agregar NIT después de guardarlo
                    document.getElementById("nuevo-nit").value = ''; // Limpiar el campo de NIT
                    document.getElementById("nuevo-nombre").value = ''; // Limpiar el campo de nombre
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al agregar el NIT. Por favor, inténtelo de nuevo.',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }
    </script>
</body>

</html>