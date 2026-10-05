<?php
// Archivo: formularioFactura.php (CORREGIDO)
// Objetivo: JSON limpio en acciones AJAX y evitar redeclare de responder_json().

require_once 'utilidades.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer para evitar que notices/warnings rompan JSON
if (!ob_get_level()) {
    ob_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Conexión a la base de datos
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Datos del usuario actual (evitar undefined)
$usuarioActual = [
    'nombre'       => $_SESSION['usuario'],
    'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'empleado',
    'correo'       => $_SESSION['correo'] ?? ''
];

// Verificar inactividad (15 minutos)
function verificarInactividad(): void
{
    $inactividadPermitida = 900; // 15 minutos
    $ahora = time();

    if (isset($_SESSION['ultimo_acceso'])) {
        $inactividad = $ahora - (int)$_SESSION['ultimo_acceso'];
        if ($inactividad > $inactividadPermitida) {
            session_unset();
            session_destroy();
            header("Location: index.php");
            exit();
        }
    }

    $_SESSION['ultimo_acceso'] = $ahora;
}
verificarInactividad();

// Obtener la sucursal del usuario (para todos excepto admin)
$sucursalEmpleado = '';
if (($usuarioActual['tipo_usuario'] ?? '') !== 'admin') {
    $sql = "SELECT sucursal FROM usuarios WHERE nombre = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $usuarioActual['nombre']);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($resultado && $resultado->num_rows > 0) {
            $fila = $resultado->fetch_assoc();
            $sucursalEmpleado = $fila['sucursal'] ?? '';
        }
        $stmt->close();
    }
}

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

// =========================
// ACCIONES AJAX (POST)
// =========================

// Crear un nuevo lote
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['accion'] ?? '') === 'crear_lote') {
    $nombreLote = $usuarioActual['nombre'] . "_" . date('Y/m/d h:i:s A');
    $fechaCreacion = date('Y-m-d H:i:s');

    $sql = "INSERT INTO lotes (nombre_lote, fecha_creacion, usuario) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        responder_json(['status' => 'error', 'mensaje' => "Error en la preparación: " . $conexion->error], 500);
    }

    $stmt->bind_param("sss", $nombreLote, $fechaCreacion, $usuarioActual['nombre']);

    if ($stmt->execute()) {
        $stmt->close();
        responder_json(['status' => 'success', 'mensaje' => "Lote creado con éxito", 'lote' => $nombreLote]);
    } else {
        $err = $stmt->error;
        $stmt->close();
        responder_json(['status' => 'error', 'mensaje' => "Error al crear el lote: " . $err], 500);
    }
}

// Obtener el lote actual del usuario
function obtenerLoteActual(mysqli $conexion, string $usuario): ?string
{
    $sql = "SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error en la preparación: " . $conexion->error);
    }

    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado ? $resultado->fetch_assoc() : null;
    $stmt->close();

    return $fila['nombre_lote'] ?? null;
}

// Obtener lote actual
try {
    $loteActual = obtenerLoteActual($conexion, $usuarioActual['nombre']);
} catch (Throwable $e) {
    $loteActual = null;
}
$loteMensaje = $loteActual ? $loteActual : "No hay lotes, debe crear uno.";

// Grabar factura
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['accion'] ?? '') === 'grabar_factura') {

    if (!$loteActual) {
        responder_json(['status' => 'error', 'message' => 'No existe un lote actual. Cree un lote antes de añadir una factura.'], 400);
    }

    // Verificar si lote está “cerrado” (ya tiene PDF y Excel)
    $nombreUsuarioFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $usuarioActual['nombre']);
    $loteFormateado = preg_replace('/[\/\\:*?"<>| ]/', '_', $loteActual);

    $carpetaPDF = __DIR__ . '/unificadosPDF/' . $nombreUsuarioFormateado;
    $nombreArchivoPDF = "facturasadjuntas_{$nombreUsuarioFormateado}_{$loteFormateado}.pdf";
    $rutaArchivoPDF = $carpetaPDF . '/' . $nombreArchivoPDF;

    $carpetaExcel = __DIR__ . '/facturasExcel/' . $nombreUsuarioFormateado;
    $nombreArchivoExcel = "CajaChica_{$nombreUsuarioFormateado}_{$loteFormateado}.xlsx";
    $rutaArchivoExcel = $carpetaExcel . '/' . $nombreArchivoExcel;

    if (file_exists($rutaArchivoPDF) && file_exists($rutaArchivoExcel)) {
        responder_json(['status' => 'error', 'message' => 'Error: Lote cerrado. Debe crear uno nuevo antes de grabar una nueva factura.'], 400);
    }

    // Fecha documento
    $fechaDocumento = sanitizar($_POST['fecha_documento'] ?? '');
    if ($fechaDocumento !== '' && $fechaDocumento > date('Y-m-d')) {
        responder_json(['status' => 'error', 'message' => 'La fecha del documento no puede ser mayor a la fecha actual.'], 400);
    }

    // Datos
    $nombreCC        = sanitizar($_POST['nombre_cc'] ?? '');
    $tipoCompraventa = sanitizar($_POST['tipo_compraventa'] ?? '');
    $descripcion     = sanitizar($_POST['descripcion'] ?? '');
    $cuentaMayor     = sanitizar($_POST['cuenta_mayor'] ?? '');
    $nombreCuenta    = sanitizar($_POST['nombre_cuenta'] ?? '');
    $nitProveedor    = sanitizar($_POST['nit_proveedor'] ?? '');
    $nombreProveedor = sanitizar($_POST['nombre_proveedor'] ?? '');
    $valorFactura    = (float)($_POST['valor_factura'] ?? 0);
    $serieDocumento  = sanitizar($_POST['serie_documento'] ?? '');
    $numeroDocumento = sanitizar($_POST['numero_documento'] ?? '');

    $fechaDocumentoCompleta = ($fechaDocumento !== '' ? $fechaDocumento : date('Y-m-d')) . ' ' . date('H:i:s');
    $indicadorImpuestos = ($tipoCompraventa === 'pequeño contribuyente' || $tipoCompraventa === 'No aplica') ? 'EXE' : 'IVA';

    $sucursal = sanitizar($_POST['sucursal'] ?? $sucursalEmpleado);

    $esCombustible = ($tipoCompraventa === 'Combustible');
    $tipoCombustible = $esCombustible ? sanitizar($_POST['tipo_combustible'] ?? '') : null;
    $galones = $esCombustible ? (float)($_POST['galones'] ?? 0) : null;
    $totalFacturaCombustible = $esCombustible ? (float)($_POST['total_factura_combustible'] ?? 0) : null;

    // Duplicados serie/numero
    $sqlVerificar = "SELECT COUNT(*) AS total FROM facturas WHERE serie_documento = ? OR numero_documento = ?";
    $stmtVerificar = $conexion->prepare($sqlVerificar);
    if (!$stmtVerificar) {
        responder_json(['status' => 'error', 'message' => 'Error en la preparación: ' . $conexion->error], 500);
    }
    $stmtVerificar->bind_param("ss", $serieDocumento, $numeroDocumento);
    $stmtVerificar->execute();
    $resultadoVerificar = $stmtVerificar->get_result();
    $totalDup = 0;
    if ($resultadoVerificar) {
        $rowDup = $resultadoVerificar->fetch_assoc();
        $totalDup = (int)($rowDup['total'] ?? 0);
    }

    if ($totalDup > 0 && !isset($_POST['confirmacion'])) {
        $sqlTipoUsuario = "SELECT tipo_usuario FROM usuarios WHERE nombre = ? LIMIT 1";
        $stmtTipoUsuario = $conexion->prepare($sqlTipoUsuario);
        $tipoUsuario = $usuarioActual['tipo_usuario'] ?? 'empleado';

        if ($stmtTipoUsuario) {
            $stmtTipoUsuario->bind_param("s", $usuarioActual['nombre']);
            $stmtTipoUsuario->execute();
            $resultadoTipoUsuario = $stmtTipoUsuario->get_result();
            if ($resultadoTipoUsuario && $resultadoTipoUsuario->num_rows > 0) {
                $tipoUsuario = $resultadoTipoUsuario->fetch_assoc()['tipo_usuario'] ?? $tipoUsuario;
            }
            $stmtTipoUsuario->close();
        }

        $stmtVerificar->close();

        if ($tipoUsuario === 'empleado') {
            responder_json(['status' => 'error', 'message' => 'La serie o número del documento/factura ya existe y no se puede agregar de nuevo para este tipo de usuario.'], 400);
        } else {
            responder_json(['status' => 'warning', 'message' => 'El número o serie del documento/factura ya existe. ¿Desea agregarlo de nuevo?']);
        }
    }
    $stmtVerificar->close();

    // Insert
    if ($esCombustible) {
        $sqlInsert = "INSERT INTO facturas (lote, descripcion, cuenta_mayor, nombre_cuenta_mayor, nombre_cc, indicador_impuestos, tipo_compraventa, valor_factura, nit_proveedor, nombre_proveedor, serie_documento, numero_documento, fecha_documento, sucursal, usuario, tipo_combustible, galones, total_factura_combustible)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtInsert = $conexion->prepare($sqlInsert);
        if (!$stmtInsert) {
            responder_json(['status' => 'error', 'message' => 'Error en la preparación: ' . $conexion->error], 500);
        }

        $stmtInsert->bind_param(
            "sssssssdssssssssdd",
            $loteActual,
            $descripcion,
            $cuentaMayor,
            $nombreCuenta,
            $nombreCC,
            $indicadorImpuestos,
            $tipoCompraventa,
            $valorFactura,
            $nitProveedor,
            $nombreProveedor,
            $serieDocumento,
            $numeroDocumento,
            $fechaDocumentoCompleta,
            $sucursal,
            $usuarioActual['nombre'],
            $tipoCombustible,
            $galones,
            $totalFacturaCombustible
        );
    } else {
        $sqlInsert = "INSERT INTO facturas (lote, descripcion, cuenta_mayor, nombre_cuenta_mayor, nombre_cc, indicador_impuestos, tipo_compraventa, valor_factura, nit_proveedor, nombre_proveedor, serie_documento, numero_documento, fecha_documento, sucursal, usuario, tipo_combustible, galones, total_factura_combustible)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL)";
        $stmtInsert = $conexion->prepare($sqlInsert);
        if (!$stmtInsert) {
            responder_json(['status' => 'error', 'message' => 'Error en la preparación: ' . $conexion->error], 500);
        }

        $stmtInsert->bind_param(
            "sssssssdsssssss",
            $loteActual,
            $descripcion,
            $cuentaMayor,
            $nombreCuenta,
            $nombreCC,
            $indicadorImpuestos,
            $tipoCompraventa,
            $valorFactura,
            $nitProveedor,
            $nombreProveedor,
            $serieDocumento,
            $numeroDocumento,
            $fechaDocumentoCompleta,
            $sucursal,
            $usuarioActual['nombre']
        );
    }

    if ($stmtInsert->execute()) {
        $stmtInsert->close();
        responder_json(['status' => 'success', 'message' => 'Factura guardada exitosamente.']);
    } else {
        $err = $stmtInsert->error;
        $stmtInsert->close();
        responder_json(['status' => 'error', 'message' => 'Error al guardar la factura: ' . $err], 500);
    }
}

// Buscar NITs
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['accion'] ?? '') === 'buscar_nit') {
    $filtro = sanitizar($_POST['filtro'] ?? '');
    $sqlNit = "SELECT nit, nombre FROM datosnit WHERE nit LIKE CONCAT('%', ?, '%') OR nombre LIKE CONCAT('%', ?, '%')";
    $stmtNit = $conexion->prepare($sqlNit);
    if (!$stmtNit) responder_json(['status' => 'error', 'message' => 'Error en consulta NIT.'], 500);

    $stmtNit->bind_param("ss", $filtro, $filtro);
    $stmtNit->execute();
    $resultadoNit = $stmtNit->get_result();

    $nits = [];
    if ($resultadoNit) {
        while ($fila = $resultadoNit->fetch_assoc()) $nits[] = $fila;
    }
    $stmtNit->close();
    responder_json(['nits' => $nits]);
}

// Buscar cuentas
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['accion'] ?? '') === 'buscar_cuentas') {
    $filtro = sanitizar($_POST['filtro'] ?? '');
    $sqlCuentas = "SELECT codigo, nombre FROM datosCuentas WHERE codigo LIKE CONCAT('%', ?, '%') OR nombre LIKE CONCAT('%', ?, '%')";
    $stmtCuentas = $conexion->prepare($sqlCuentas);
    if (!$stmtCuentas) responder_json(['status' => 'error', 'message' => 'Error en consulta cuentas.'], 500);

    $stmtCuentas->bind_param("ss", $filtro, $filtro);
    $stmtCuentas->execute();
    $resultadoCuentas = $stmtCuentas->get_result();

    $cuentas = [];
    if ($resultadoCuentas) {
        while ($fila = $resultadoCuentas->fetch_assoc()) $cuentas[] = $fila;
    }
    $stmtCuentas->close();
    responder_json(['cuentas' => $cuentas]);
}

// Buscar usuarios
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['accion'] ?? '') === 'buscar_usuarios') {
    $filtro = sanitizar($_POST['filtro'] ?? '');

    if (($usuarioActual['tipo_usuario'] ?? '') === 'admin') {
        $sqlUsuarios = "SELECT sucursal, nombre, tipo_usuario FROM usuarios WHERE sucursal LIKE CONCAT('%', ?, '%') OR nombre LIKE CONCAT('%', ?, '%')";
    } else {
        $sqlUsuarios = "SELECT sucursal, nombre, tipo_usuario FROM usuarios WHERE (sucursal LIKE CONCAT('%', ?, '%') OR nombre LIKE CONCAT('%', ?, '%')) AND tipo_usuario != 'admin'";
    }

    $stmtUsuarios = $conexion->prepare($sqlUsuarios);
    if (!$stmtUsuarios) responder_json(['status' => 'error', 'message' => 'Error en consulta usuarios.'], 500);

    $stmtUsuarios->bind_param("ss", $filtro, $filtro);
    $stmtUsuarios->execute();
    $resultadoUsuarios = $stmtUsuarios->get_result();

    $usuarios = [];
    if ($resultadoUsuarios) {
        while ($fila = $resultadoUsuarios->fetch_assoc()) $usuarios[] = $fila;
    }

    $stmtUsuarios->close();
    responder_json(['usuarios' => $usuarios]);
}

// Agregar NIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar_nit') {
    $nit = sanitizar($_POST['nit'] ?? '');
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $nombre = strtoupper(trim($nombre));

    if ($nit === '' || $nombre === '') {
        responder_json(['status' => 'error', 'message' => 'Todos los campos son obligatorios.'], 400);
    }

    $sqlVerificar = "SELECT COUNT(*) AS total FROM datosnit WHERE nit = ?";
    $stmtVerificar = $conexion->prepare($sqlVerificar);
    if (!$stmtVerificar) responder_json(['status' => 'error', 'message' => 'Error al verificar NIT.'], 500);

    $stmtVerificar->bind_param("s", $nit);
    $stmtVerificar->execute();
    $resultadoVerificar = $stmtVerificar->get_result();
    $total = 0;
    if ($resultadoVerificar) {
        $row = $resultadoVerificar->fetch_assoc();
        $total = (int)($row['total'] ?? 0);
    }

    if ($total > 0) {
        $stmtVerificar->close();
        responder_json(['status' => 'error', 'message' => 'El NIT ya existe en la base de datos.'], 409);
    }
    $stmtVerificar->close();

    $nit = preg_replace("/[^0-9kK]/", "", $nit);
    $nit = strtoupper($nit);

    $sqlInsertar = "INSERT INTO datosnit (nit, nombre) VALUES (?, ?)";
    $stmtInsertar = $conexion->prepare($sqlInsertar);
    if (!$stmtInsertar) responder_json(['status' => 'error', 'message' => 'Error al preparar inserción NIT.'], 500);

    $stmtInsertar->bind_param("ss", $nit, $nombre);

    if ($stmtInsertar->execute()) {
        $stmtInsertar->close();
        responder_json(['status' => 'success', 'message' => 'NIT agregado exitosamente.']);
    } else {
        $err = $stmtInsertar->error;
        $stmtInsertar->close();
        responder_json(['status' => 'error', 'message' => 'Error al agregar el NIT: ' . $err], 500);
    }
}

// ✅ Si fue POST pero con acción no reconocida: responder JSON (evita “respuesta inválida”)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    responder_json(['status' => 'error', 'message' => 'Acción inválida.'], 400);
}

// Render normal (GET)
cerrarConexion($conexion);
include 'formularioFacturahtml.php';
