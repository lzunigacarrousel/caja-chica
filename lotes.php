<?php
// archivo: lotes.php
require_once 'utilidades.php';

// ✅ Evita notice si la sesión ya está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

// ✅ Helper simple para responder JSON limpio y salir
function responder_json(array $payload, int $httpCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    // ✅ Si es AJAX (POST), no redirigir (eso rompe el JSON)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        responder_json(['status' => 'error', 'message' => 'Sesión expirada. Inicia sesión nuevamente.'], 401);
    }
    header("Location: index.php");
    exit();
}

// Conectar a la base de datos
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Obtener todos los usuarios para el filtro (pantalla HTML)
$usuarios = [];
$sqlUsuarios = "SELECT nombre FROM usuarios";
$resultadoUsuarios = $conexion->query($sqlUsuarios);
if ($resultadoUsuarios && $resultadoUsuarios->num_rows > 0) {
    while ($fila = $resultadoUsuarios->fetch_assoc()) {
        $usuarios[] = $fila['nombre'];
    }
}

// =============================
// Manejo de solicitudes AJAX
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $usuarioSeleccionado = $_POST['usuario_seleccionado'] ?? null;

    if ($accion === 'obtener_lotes' && $usuarioSeleccionado) {
        // Obtener el lote actual del usuario seleccionado
        $sql = "SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC LIMIT 1";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'message' => 'Error al preparar consulta de lote actual.'], 500);
        }

        $stmt->bind_param("s", $usuarioSeleccionado);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $loteActual = $resultado ? ($resultado->fetch_assoc()['nombre_lote'] ?? null) : null;
        $stmt->close();

        // Obtener los lotes creados por el usuario seleccionado
        $sqlLotes = "SELECT id, nombre_lote, DATE_FORMAT(fecha_creacion, '%Y/%m/%d-%h:%i:%s%p') as fecha_creacion
                     FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC";
        $stmtLotes = $conexion->prepare($sqlLotes);
        if (!$stmtLotes) {
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'message' => 'Error al preparar consulta de lotes.'], 500);
        }

        $stmtLotes->bind_param("s", $usuarioSeleccionado);
        $stmtLotes->execute();
        $resultadoLotes = $stmtLotes->get_result();

        $lotes = [];
        if ($resultadoLotes) {
            while ($fila = $resultadoLotes->fetch_assoc()) {
                $lotes[] = $fila;
            }
        }
        $stmtLotes->close();

        cerrarConexion($conexion);
        responder_json(['status' => 'success', 'loteActual' => $loteActual, 'lotes' => $lotes]);
    }

    if ($accion === 'contar_facturas') {
        $idLote = $_POST['id_lote'] ?? null;
        $usuario = $_POST['usuario'] ?? null;

        if (!$idLote || !$usuario) {
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'message' => 'Datos incompletos para contar facturas.'], 400);
        }

        // Contar facturas asociadas al lote
        $sqlContarFacturas = "SELECT COUNT(*) AS facturasCount
                              FROM facturas
                              WHERE lote = (SELECT nombre_lote FROM lotes WHERE id = ? AND usuario = ?)";
        $stmtContarFacturas = $conexion->prepare($sqlContarFacturas);
        if (!$stmtContarFacturas) {
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'message' => 'Error al preparar conteo de facturas.'], 500);
        }

        $stmtContarFacturas->bind_param("is", $idLote, $usuario);
        $stmtContarFacturas->execute();
        $resultadoContarFacturas = $stmtContarFacturas->get_result();
        $facturasCount = $resultadoContarFacturas ? ($resultadoContarFacturas->fetch_assoc()['facturasCount'] ?? 0) : 0;
        $stmtContarFacturas->close();

        cerrarConexion($conexion);
        responder_json(['status' => 'success', 'facturasCount' => (int)$facturasCount]);
    }

    if ($accion === 'eliminar_lote') {
        $idLote = $_POST['id_lote'] ?? null;
        $usuario = $_POST['usuario'] ?? null;

        if (!$idLote || !$usuario) {
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'message' => 'Datos incompletos para eliminar lote.'], 400);
        }

        // Iniciar transacción
        $conexion->begin_transaction();

        try {
            // Eliminar facturas asociadas al lote y contar cuántas fueron eliminadas
            $sqlEliminarFacturas = "DELETE FROM facturas
                                    WHERE lote = (SELECT nombre_lote FROM lotes WHERE id = ? AND usuario = ?)";
            $stmtFacturas = $conexion->prepare($sqlEliminarFacturas);
            if (!$stmtFacturas) {
                throw new Exception("Error al preparar eliminación de facturas.");
            }

            $stmtFacturas->bind_param("is", $idLote, $usuario);
            $stmtFacturas->execute();
            $facturasEliminadas = $stmtFacturas->affected_rows;
            $stmtFacturas->close();

            // Eliminar el lote
            $sqlEliminarLote = "DELETE FROM lotes WHERE id = ? AND usuario = ?";
            $stmtLote = $conexion->prepare($sqlEliminarLote);
            if (!$stmtLote) {
                throw new Exception("Error al preparar eliminación del lote.");
            }

            $stmtLote->bind_param("is", $idLote, $usuario);
            $stmtLote->execute();
            $stmtLote->close();

            // Confirmar transacción
            $conexion->commit();

            $mensaje = "Lote y $facturasEliminadas facturas relacionadas eliminados con éxito.";
            cerrarConexion($conexion);
            responder_json(['status' => 'success', 'mensaje' => $mensaje, 'facturasEliminadas' => $facturasEliminadas]);
        } catch (Exception $e) {
            $conexion->rollback();
            cerrarConexion($conexion);
            responder_json(['status' => 'error', 'mensaje' => 'Error al eliminar el lote y las facturas relacionadas.', 'facturasEliminadas' => 0], 500);
        }
    }

    // Acción no reconocida
    cerrarConexion($conexion);
    responder_json(['status' => 'error', 'message' => 'Acción inválida.'], 400);
}

// Si no es POST, sigue cargando la pantalla HTML normalmente (como tu backup)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Lotes</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/lotes.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
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

<div class="container mt-8 flex-grow">
    <h2 class="text-center text-2xl font-bold mb-6">Gestionar Lotes</h2>

    <div class="text-center mb-4">
        <label for="usuario_seleccionado" class="mr-2">Seleccionar Usuario:</label>
        <select id="usuario_seleccionado" class="border border-gray-300 rounded">
            <option value="">-- Seleccione un usuario --</option>
            <?php foreach ($usuarios as $usuario): ?>
                <option value="<?php echo htmlspecialchars($usuario); ?>">
                    <?php echo htmlspecialchars($usuario); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <h4 class="text-center text-xl mt-4">Lote Actual de <span id="loteActualUsuario"></span>:
        <span id="loteActual"></span>
    </h4>

    <div class="overflow-x-auto max-h-96 mt-4">
        <h4 class="text-center text-xl mb-4">Lotes Creados</h4>
        <table class="table-auto w-full">
            <thead>
            <tr class="bg-black-200">
                <th class="px-4 py-2">Nombre del Lote</th>
                <th class="px-4 py-2">Fecha de Creación</th>
                <th class="px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody id="lotesTableBody">
            <tr>
                <td colspan="3" class="border px-4 py-2 text-center">No hay lotes creados.</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
<br>

<footer class="bg-gray-800 p-4 text-center text-white">
    <p>Corporación Carrousel S.A.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
$(document).ready(function () {
    $('#usuario_seleccionado').on('change', function () {
        const usuario = $(this).val();
        if (usuario) {
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    accion: 'obtener_lotes',
                    usuario_seleccionado: usuario
                },
                dataType: 'json',
                success: function (response) {
                    $('#loteActualUsuario').text(usuario);
                    $('#loteActual').text(response.loteActual);

                    const lotes = response.lotes || [];
                    const tbody = $('#lotesTableBody');
                    tbody.empty();

                    if (lotes.length > 0) {
                        lotes.forEach(lote => {
                            tbody.append(`
                            <tr data-id="${lote.id}" data-usuario="${usuario}">
                                <td class="border px-4 py-2">${lote.nombre_lote}</td>
                                <td class="border px-4 py-2">${lote.fecha_creacion}</td>
                                <td class="border px-4 py-2 text-center">
                                    <button class="bg-red-500 text-white px-2 py-1 rounded eliminarLoteBtn">Eliminar</button>
                                </td>
                            </tr>
                        `);
                        });
                    } else {
                        tbody.append('<tr><td colspan="3" class="border px-4 py-2 text-center">No hay lotes creados.</td></tr>');
                    }
                }
            });
        } else {
            $('#loteActualUsuario').text('');
            $('#loteActual').text('');
            $('#lotesTableBody').html('<tr><td colspan="3" class="border px-4 py-2 text-center">No hay lotes creados.</td></tr>');
        }
    });

    $(document).on('click', '.eliminarLoteBtn', function () {
        const row = $(this).closest('tr');
        const idLote = row.data('id');
        const usuario = row.data('usuario');

        $.post('', {
            accion: 'contar_facturas',
            id_lote: idLote,
            usuario: usuario
        }, function (response) {
            const facturasCount = response.facturasCount || 0;

            Swal.fire({
                title: '¿Estás seguro?',
                text: `Esto eliminará el lote seleccionado y ${facturasCount} facturas asociadas. ¿Deseas continuar?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        accion: 'eliminar_lote',
                        id_lote: idLote,
                        usuario: usuario
                    }, function (response) {
                        const facturasEliminadas = response.facturasEliminadas || 0;
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminación exitosa',
                            text: `El lote y ${facturasEliminadas} facturas asociadas han sido eliminados.`
                        }).then(() => {
                            row.remove();
                        });
                    }, 'json');
                }
            });
        }, 'json');
    });
});
</script>
</body>
</html>
