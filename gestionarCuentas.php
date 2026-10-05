<?php
// Archivo: gestionarCuentas.php (REPARADO)
// Gestionar Cuentas (añadir, editar, eliminar) - mismas funciones, solo correcciones.

session_start();
require_once 'utilidades.php';

date_default_timezone_set('America/Guatemala');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Obtener detalles del usuario desde la sesión (evitar undefined)
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? '';
$correo_usuario = $_SESSION['correo'] ?? '';

// Función para manejar el tiempo de inactividad
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

    // ✅ siempre actualizar (incluye primera carga)
    $_SESSION['ultimo_acceso'] = $ahora;
}
verificarInactividad();

// Verificar si el usuario es admin o administracion
if ($tipo_usuario !== 'admin' && $tipo_usuario !== 'administracion') {
    header("Location: index.php");
    exit();
}

$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Helper JSON (evita que warnings rompan fetch().json())
function responder_json(array $payload, int $httpCode = 200): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

// Manejar las acciones del formulario (AJAX)
if (isset($_POST['accion'])) {
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'agregar') {
        $codigo = function_exists('sanitizar') ? sanitizar($_POST['codigo'] ?? '') : trim((string)($_POST['codigo'] ?? ''));
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            responder_json(['success' => false, 'message' => 'Código y Nombre son obligatorios.'], 400);
        }

        // Verificar si el código ya existe
        $sql_check = "SELECT id FROM datosCuentas WHERE codigo = ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) responder_json(['success' => false, 'message' => 'Error al preparar verificación.'], 500);

        $stmt_check->bind_param("s", $codigo);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();

        if ($resultado_check && $resultado_check->num_rows > 0) {
            $stmt_check->close();
            responder_json(['success' => false, 'message' => "El código ya existe."], 409);
        }
        $stmt_check->close();

        $sql = "INSERT INTO datosCuentas (codigo, nombre) VALUES (?, ?)";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar inserción.'], 500);

        $stmt->bind_param("ss", $codigo, $nombre);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => "Cuenta agregada con éxito."]);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => "Error al agregar cuenta."], 500);
    }

    if ($accion === 'editar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $codigo = function_exists('sanitizar') ? sanitizar($_POST['codigo'] ?? '') : trim((string)($_POST['codigo'] ?? ''));
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));

        if ($id <= 0 || $codigo === '' || $nombre === '') {
            responder_json(['success' => false, 'message' => 'Datos inválidos para editar.'], 400);
        }

        // Verificar si el código ya existe para otro registro
        $sql_check = "SELECT id FROM datosCuentas WHERE codigo = ? AND id != ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) responder_json(['success' => false, 'message' => 'Error al preparar verificación.'], 500);

        $stmt_check->bind_param("si", $codigo, $id);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();

        if ($resultado_check && $resultado_check->num_rows > 0) {
            $stmt_check->close();
            responder_json(['success' => false, 'message' => "El código ya existe."], 409);
        }
        $stmt_check->close();

        $sql = "UPDATE datosCuentas SET codigo = ?, nombre = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar actualización.'], 500);

        $stmt->bind_param("ssi", $codigo, $nombre, $id);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => "Cuenta actualizada con éxito."]);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => "Error al actualizar cuenta."], 500);
    }

    if ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) responder_json(['success' => false, 'message' => 'ID inválido.'], 400);

        $sql = "DELETE FROM datosCuentas WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar eliminación.'], 500);

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => "Cuenta eliminada con éxito."]);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => "Error al eliminar cuenta."], 500);
    }

    responder_json(['success' => false, 'message' => 'Acción no válida.'], 400);
}

// Filtro de búsqueda
$filtro = '';
if (isset($_GET['filtro'])) {
    $filtro = function_exists('sanitizar') ? sanitizar($_GET['filtro']) : trim((string)$_GET['filtro']);
    $sql = "SELECT * FROM datosCuentas WHERE codigo LIKE ? OR nombre LIKE ? ORDER BY id DESC";
    $stmt = $conexion->prepare($sql);
    $filtro_param = "%" . $filtro . "%";
    $stmt->bind_param("ss", $filtro_param, $filtro_param);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $sql = "SELECT * FROM datosCuentas ORDER BY id DESC";
    $resultado = $conexion->query($sql);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $cuentas = [];
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $cuentas[] = $fila;
        }
    }
    responder_json(['success' => true, 'cuentas' => $cuentas]);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Cuentas</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <!-- ✅ antes apuntaba a gestionarNit.css -->
    <link rel="stylesheet" href="estilos/gestionarCuentas.css">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
</head>

<body class="bg-gray-100">
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

<br><br><br>

<div class="container mx-auto mt-8">
    <h1 class="text-center text-2xl font-bold">Gestionar Cuentas</h1>

    <div class="text-center mt-4">
        <button id="btnAgregarCuenta" class="bg-blue-500 text-white px-4 py-2 rounded">Agregar Nueva Cuenta</button>
    </div>

    <form id="buscarForm" class="mt-3 flex justify-center">
        <input type="text" id="filtro" name="filtro" placeholder="Buscar por Código o Nombre"
               value="<?php echo htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8'); ?>"
               class="form-input w-full md:w-2/3 lg:w-1/2 rounded-l px-4 py-2 border-t mr-0 border-b border-l text-gray-800 border-gray-200 bg-white">
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r">Buscar</button>
    </form>

    <h2 class="text-center text-xl mt-4">Cuentas Existentes</h2>
    <div class="table-responsive">
        <table class="table-auto w-full mt-4">
            <thead>
            <tr class="bg-gray-200">
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">Código</th>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody id="cuentas">
            <?php if ($resultado): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr id="cuenta-<?php echo (int)$fila['id']; ?>">
                        <td class="border px-4 py-2"><?php echo (int)$fila['id']; ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2">
                            <button class="bg-yellow-500 text-white px-2 py-1 rounded edit-btn"
                                    data-id="<?php echo (int)$fila['id']; ?>"
                                    data-codigo="<?php echo htmlspecialchars($fila['codigo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-nombre="<?php echo htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                Editar
                            </button>
                            <button class="bg-red-500 text-white px-2 py-1 rounded delete-btn"
                                    data-id="<?php echo (int)$fila['id']; ?>">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });

function escapeHtml(str){
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}
function escapeAttr(str){ return escapeHtml(str); }

// Agregar
document.getElementById('btnAgregarCuenta').addEventListener('click', function () {
    Swal.fire({
        title: 'Agregar Nueva Cuenta',
        html: `
        <form id="formAgregar">
            <input type="hidden" name="accion" value="agregar">
            <div class="form-group">
                <label class="form-label" for="agregar-codigo">Código:</label>
                <input class="swal2-input" type="text" id="agregar-codigo" name="codigo" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="agregar-nombre">Nombre:</label>
                <input class="swal2-input" type="text" id="agregar-nombre" name="nombre" required>
            </div>
        </form>`,
        showCancelButton: true,
        confirmButtonText: 'Agregar Cuenta',
        preConfirm: () => {
            const form = document.getElementById('formAgregar');
            const formData = new FormData(form);
            return fetch('gestionarCuentas.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => Swal.showValidationMessage(`Error: ${error}`));
        }
    }).then((result) => {
        if (result.isConfirmed) {
            notyf.success('Cuenta agregada con éxito');
            actualizarTablaCuentas(document.getElementById('filtro').value);
        }
    });
});

// Editar
document.addEventListener('click', function (event) {
    if (!event.target.matches('.edit-btn')) return;

    const id = event.target.dataset.id;
    const codigo = event.target.dataset.codigo || '';
    const nombre = event.target.dataset.nombre || '';

    Swal.fire({
        title: 'Editar Cuenta',
        html: `
        <form id="formEditar">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" value="${escapeAttr(id)}">
            <div class="form-group">
                <label class="form-label" for="edit-codigo">Código:</label>
                <input class="swal2-input" type="text" id="edit-codigo" name="codigo" value="${escapeAttr(codigo)}" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-nombre">Nombre:</label>
                <input class="swal2-input" type="text" id="edit-nombre" name="nombre" value="${escapeAttr(nombre)}" required>
            </div>
        </form>`,
        showCancelButton: true,
        confirmButtonText: 'Guardar cambios',
        preConfirm: () => {
            const form = document.getElementById('formEditar');
            const formData = new FormData(form);
            return fetch('gestionarCuentas.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => Swal.showValidationMessage(`Error: ${error}`));
        }
    }).then((result) => {
        if (result.isConfirmed) {
            notyf.success('Cuenta editada con éxito');
            actualizarTablaCuentas(document.getElementById('filtro').value);
        }
    });
});

// Eliminar
document.addEventListener('click', function (event) {
    if (!event.target.matches('.delete-btn')) return;

    const id = event.target.dataset.id;

    Swal.fire({
        title: '¿Estás seguro de eliminar esta cuenta?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('accion', 'eliminar');
        formData.append('id', id);

        fetch('gestionarCuentas.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success('Cuenta eliminada con éxito');
                    const row = document.getElementById('cuenta-' + id);
                    if (row) row.remove();
                } else {
                    notyf.error(data.message || 'Error al eliminar cuenta');
                }
            })
            .catch(() => notyf.error('Error al eliminar cuenta'));
    });
});

// Actualizar tabla
function actualizarTablaCuentas(filtro = '') {
    fetch(`gestionarCuentas.php?ajax=1&filtro=${encodeURIComponent(filtro)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const cuentas = data.cuentas || [];
            const tbody = document.getElementById('cuentas');
            tbody.innerHTML = '';

            cuentas.forEach(c => {
                tbody.innerHTML += `
                <tr id="cuenta-${escapeAttr(c.id)}">
                    <td class="border px-4 py-2">${escapeHtml(c.id)}</td>
                    <td class="border px-4 py-2">${escapeHtml(c.codigo)}</td>
                    <td class="border px-4 py-2">${escapeHtml(c.nombre)}</td>
                    <td class="border px-4 py-2">
                        <button class="bg-yellow-500 text-white px-2 py-1 rounded edit-btn"
                                data-id="${escapeAttr(c.id)}"
                                data-codigo="${escapeAttr(c.codigo)}"
                                data-nombre="${escapeAttr(c.nombre)}">Editar</button>
                        <button class="bg-red-500 text-white px-2 py-1 rounded delete-btn"
                                data-id="${escapeAttr(c.id)}">Eliminar</button>
                    </td>
                </tr>`;
            });
        })
        .catch(() => {});
}

// Buscar
document.getElementById('buscarForm').addEventListener('submit', function (event) {
    event.preventDefault();
    actualizarTablaCuentas(document.getElementById('filtro').value);
});

// Evitar reenvío
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

// Cargar inicial
actualizarTablaCuentas();
</script>
</body>
</html>
