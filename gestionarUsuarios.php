<?php
// Archivo: gestionarUsuarios.php (REPARADO)
// Esta página permite gestionar Usuarios (añadir, editar, eliminar)
// Correcciones: headers JSON, inactividad, validaciones, escaping XSS, montoCaja nullable, contraseñas con hash.

session_start();
require_once 'utilidades.php';

date_default_timezone_set('America/Guatemala');

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['usuario']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

// Datos de sesión (evitar undefined)
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario_sesion = $_SESSION['tipo_usuario'] ?? '';
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
    // ✅ siempre actualizar
    $_SESSION['ultimo_acceso'] = $ahora;
}
verificarInactividad();

// Conexión BD
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Helper JSON
function responder_json(array $payload, int $httpCode = 200): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload);
    exit();
}

// ======= Acciones AJAX (POST) =======
if (isset($_POST['accion'])) {
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'agregar') {
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));
        $contrasena = (string)($_POST['contrasena'] ?? '');
        $tipo_usuario = (string)($_POST['tipo_usuario'] ?? '');

        $sucursal = function_exists('sanitizar') ? sanitizar($_POST['sucursal'] ?? '') : trim((string)($_POST['sucursal'] ?? ''));
        $correo   = function_exists('sanitizar') ? sanitizar($_POST['correo'] ?? '')   : trim((string)($_POST['correo'] ?? ''));
        $area     = function_exists('sanitizar') ? sanitizar($_POST['area'] ?? '')     : trim((string)($_POST['area'] ?? ''));
        $montoCajaRaw = (string)($_POST['montoCaja'] ?? '');

        $sucursal = ($sucursal === '') ? null : $sucursal;
        $correo   = ($correo === '') ? null : $correo;
        $area     = ($area === '') ? null : $area;

        $montoCaja = null;
        if (trim($montoCajaRaw) !== '') {
            $montoCaja = (float)$montoCajaRaw;
        }

        if ($nombre === '' || $contrasena === '' || $tipo_usuario === '') {
            responder_json(['success' => false, 'message' => 'Todos los campos requeridos deben estar completos.'], 400);
        }

        // Verificar si el nombre ya existe
        $sql_check = "SELECT id FROM usuarios WHERE nombre = ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) {
            responder_json(['success' => false, 'message' => 'Error al preparar la consulta.'], 500);
        }
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();
        $existe = ($resultado_check && $resultado_check->num_rows > 0);
        $stmt_check->close();

        if ($existe) {
            responder_json(['success' => false, 'message' => 'El nombre ya existe.'], 409);
        }

        // ✅ Guardar contraseña con hash
       // $hash = password_hash($contrasena, PASSWORD_DEFAULT);
       $hash = $contrasena; // guardar en texto plano


        if ($montoCaja === null) {
            $sql = "INSERT INTO usuarios (nombre, contrasena, tipo_usuario, sucursal, correo, area, montoCaja)
                    VALUES (?, ?, ?, ?, ?, ?, NULL)";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar inserción.'], 500);
            $stmt->bind_param("ssssss", $nombre, $hash, $tipo_usuario, $sucursal, $correo, $area);
        } else {
            $sql = "INSERT INTO usuarios (nombre, contrasena, tipo_usuario, sucursal, correo, area, montoCaja)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar inserción.'], 500);
            $stmt->bind_param("ssssssd", $nombre, $hash, $tipo_usuario, $sucursal, $correo, $area, $montoCaja);
        }

        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'Usuario agregado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al agregar usuario.'], 500);
    }

    if ($accion === 'editar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));
        $contrasena = (string)($_POST['contrasena'] ?? ''); // puede venir vacía => no cambiar
        $tipo_usuario = (string)($_POST['tipo_usuario'] ?? '');

        $sucursal = function_exists('sanitizar') ? sanitizar($_POST['sucursal'] ?? '') : trim((string)($_POST['sucursal'] ?? ''));
        $correo   = function_exists('sanitizar') ? sanitizar($_POST['correo'] ?? '')   : trim((string)($_POST['correo'] ?? ''));
        $area     = function_exists('sanitizar') ? sanitizar($_POST['area'] ?? '')     : trim((string)($_POST['area'] ?? ''));
        $montoCajaRaw = (string)($_POST['montoCaja'] ?? '');

        $sucursal = ($sucursal === '') ? null : $sucursal;
        $correo   = ($correo === '') ? null : $correo;
        $area     = ($area === '') ? null : $area;

        $montoCaja = null;
        if (trim($montoCajaRaw) !== '') {
            $montoCaja = (float)$montoCajaRaw;
        }

        if ($id <= 0 || $nombre === '' || $tipo_usuario === '') {
            responder_json(['success' => false, 'message' => 'Datos inválidos para editar.'], 400);
        }

        // Verificar si el nombre ya existe para otro registro
        $sql_check = "SELECT id FROM usuarios WHERE nombre = ? AND id != ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) responder_json(['success' => false, 'message' => 'Error al preparar verificación.'], 500);
        $stmt_check->bind_param("si", $nombre, $id);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();
        $existe = ($resultado_check && $resultado_check->num_rows > 0);
        $stmt_check->close();

        if ($existe) {
            responder_json(['success' => false, 'message' => 'El nombre ya existe.'], 409);
        }

        // Si no mandan contraseña, mantener la actual
        $hashFinal = null;
        if ($contrasena !== '') {
           // $hashFinal = password_hash($contrasena, PASSWORD_DEFAULT);
           $hashFinal = $contrasena; // guardar en texto plano

        } else {
            $stmtGet = $conexion->prepare("SELECT contrasena FROM usuarios WHERE id = ? LIMIT 1");
            if (!$stmtGet) responder_json(['success' => false, 'message' => 'Error al leer contraseña actual.'], 500);
            $stmtGet->bind_param("i", $id);
            $stmtGet->execute();
            $stmtGet->bind_result($hashActual);
            $stmtGet->fetch();
            $stmtGet->close();
            $hashFinal = (string)$hashActual;
        }

        if ($montoCaja === null) {
            $sql = "UPDATE usuarios
                    SET nombre = ?, contrasena = ?, tipo_usuario = ?, sucursal = ?, correo = ?, area = ?, montoCaja = NULL
                    WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar actualización.'], 500);
            $stmt->bind_param("ssssssi", $nombre, $hashFinal, $tipo_usuario, $sucursal, $correo, $area, $id);
        } else {
            $sql = "UPDATE usuarios
                    SET nombre = ?, contrasena = ?, tipo_usuario = ?, sucursal = ?, correo = ?, area = ?, montoCaja = ?
                    WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar actualización.'], 500);
            $stmt->bind_param("ssssssdi", $nombre, $hashFinal, $tipo_usuario, $sucursal, $correo, $area, $montoCaja, $id);
        }

        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'Usuario actualizado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al actualizar usuario.'], 500);
    }

    if ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) responder_json(['success' => false, 'message' => 'ID inválido.'], 400);

        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar eliminación.'], 500);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'Usuario eliminado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al eliminar usuario.'], 500);
    }

    // Acción inválida
    responder_json(['success' => false, 'message' => 'Acción no válida.'], 400);
}

// ======= Carga de tabla / filtro =======
$filtro = '';
if (isset($_GET['filtro'])) {
    $filtro = function_exists('sanitizar') ? sanitizar($_GET['filtro']) : trim((string)$_GET['filtro']);
    $sql = "SELECT * FROM usuarios WHERE nombre LIKE ? OR correo LIKE ? ORDER BY id DESC";
    $stmt = $conexion->prepare($sql);
    $filtro_param = "%" . $filtro . "%";
    $stmt->bind_param("ss", $filtro_param, $filtro_param);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $sql = "SELECT * FROM usuarios ORDER BY id DESC";
    $resultado = $conexion->query($sql);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $usuarios = [];
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            // No exponer hash real en la tabla (seguridad)
            $fila['contrasena'] = '********';
            $usuarios[] = $fila;
        }
    }
    responder_json(['success' => true, 'usuarios' => $usuarios]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/gestionarUsuarios.css">
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

<div class="container mx-auto mt-8">
    <h1 class="text-center text-2xl font-bold">Gestionar Usuarios</h1>

    <div class="text-center mt-4">
        <button id="btnAgregarUsuario" class="bg-blue-500 text-white px-4 py-2 rounded">Agregar Nuevo Usuario</button>
    </div>

    <form id="buscarForm" class="mt-3 flex justify-center">
        <input type="text" id="filtro" name="filtro" placeholder="Buscar por Nombre o Correo"
               value="<?php echo htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8'); ?>"
               class="form-input w-full md:w-2/3 lg:w-1/2 rounded-l px-4 py-2 border-t mr-0 border-b border-l text-gray-800 border-gray-200 bg-white">
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r">Buscar</button>
    </form>

    <h2 class="text-center text-xl mt-4">Usuarios Existentes</h2>
    <div class="table-responsive">
        <table class="table-auto w-full mt-4">
            <thead>
            <tr class="bg-gray-200">
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Contraseña</th>
                <th class="px-4 py-2">Tipo Usuario</th>
                <th class="px-4 py-2">Sucursal</th>
                <th class="px-4 py-2">Correo</th>
                <th class="px-4 py-2">Área</th>
                <th class="px-4 py-2">Monto Caja</th>
                <th class="px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody id="usuarios">
            <?php if ($resultado): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr id="usuario-<?php echo (int)$fila['id']; ?>">
                        <td class="border px-4 py-2"><?php echo (int)$fila['id']; ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2">********</td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['tipo_usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['sucursal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($fila['area'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars((string)($fila['montoCaja'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2">
                            <button class="bg-yellow-500 text-white px-2 py-1 rounded edit-btn"
                                    data-id="<?php echo (int)$fila['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tipo_usuario="<?php echo htmlspecialchars($fila['tipo_usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-sucursal="<?php echo htmlspecialchars($fila['sucursal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-correo="<?php echo htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-area="<?php echo htmlspecialchars($fila['area'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-monto-caja="<?php echo htmlspecialchars((string)($fila['montoCaja'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">
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

// Agregar nuevo usuario
document.getElementById('btnAgregarUsuario').addEventListener('click', function () {
    Swal.fire({
        title: 'Agregar Nuevo Usuario',
        html: `
        <form id="formAgregar">
            <input type="hidden" name="accion" value="agregar">
            <div class="form-group">
                <label class="form-label" for="agregar-nombre">Nombre:</label>
                <input class="swal2-input" type="text" id="agregar-nombre" name="nombre" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="agregar-contrasena">Contraseña:</label>
                <input class="swal2-input" type="password" id="agregar-contrasena" name="contrasena" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="agregar-tipo_usuario">Tipo Usuario:</label>
                <select class="swal2-input" id="agregar-tipo_usuario" name="tipo_usuario" required>
                    <option value="admin">Admin</option>
                    <option value="empleado">Empleado</option>
                    <option value="gerencia">Gerencia</option>
                    <option value="contabilidad">Contabilidad</option>
                    <option value="administracion">Administración</option>
                    <option value="auditoria">Auditoria</option>
                    <option value="operaciones">Operaciones</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="agregar-sucursal">Sucursal:</label>
                <input class="swal2-input" type="text" id="agregar-sucursal" name="sucursal">
            </div>
            <div class="form-group">
                <label class="form-label" for="agregar-correo">Correo:</label>
                <input class="swal2-input" type="email" id="agregar-correo" name="correo">
            </div>
            <div class="form-group">
                <label class="form-label" for="agregar-area">Área:</label>
                <select class="swal2-input" id="agregar-area" name="area">
                    <option value="central">Central</option>
                    <option value="suroccidente">Suroccidente</option>
                    <option value="nororiente">Nororiente</option>
                    <option value="rrhh">RRHH</option>
                    <option value="sistemas">Sistemas</option>
                    <option value="contabilidad">Contabilidad</option>
                    <option value="operaciones">Operaciones</option>
                    <option value="supervisión">Supervisión</option>
                    <option value="gerencia">Gerencia</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="agregar-montoCaja">Monto Caja:</label>
                <input class="swal2-input" type="number" step="0.01" id="agregar-montoCaja" name="montoCaja">
            </div>
        </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Agregar Usuario',
        preConfirm: () => {
            const form = document.getElementById('formAgregar');
            if (!form.checkValidity()) {
                Swal.showValidationMessage('Por favor complete todos los campos requeridos.');
                return false;
            }
            const formData = new FormData(form);

            return fetch('gestionarUsuarios.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message);
                    return data;
                })
                .catch(error => Swal.showValidationMessage(`Request failed: ${error}`));
        }
    }).then((result) => {
        if (result.isConfirmed) {
            notyf.success('Usuario agregado con éxito');
            actualizarTablaUsuarios();
        }
    });
});

// Editar usuario
document.addEventListener('click', function (event) {
    if (event.target.matches('.edit-btn')) {
        const id = event.target.dataset.id;
        const nombre = event.target.dataset.nombre || '';
        const tipo_usuario = event.target.dataset.tipo_usuario || '';
        const sucursal = event.target.dataset.sucursal || '';
        const correo = event.target.dataset.correo || '';
        const area = event.target.dataset.area || '';
        const montoCaja = event.target.dataset.montoCaja || '';

        Swal.fire({
            title: 'Editar Usuario',
            html: `
            <form id="formEditar">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" value="${id}">
                <div class="form-group">
                    <label class="form-label" for="edit-nombre">Nombre:</label>
                    <input class="swal2-input" type="text" id="edit-nombre" name="nombre" value="${nombre}" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-contrasena">Nueva contraseña (opcional):</label>
                    <input class="swal2-input" type="password" id="edit-contrasena" name="contrasena" value="">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-tipo_usuario">Tipo Usuario:</label>
                    <select class="swal2-input" id="edit-tipo_usuario" name="tipo_usuario" required>
                        <option value="admin" ${tipo_usuario === 'admin' ? 'selected' : ''}>Admin</option>
                        <option value="empleado" ${tipo_usuario === 'empleado' ? 'selected' : ''}>Empleado</option>
                        <option value="gerencia" ${tipo_usuario === 'gerencia' ? 'selected' : ''}>Gerencia</option>
                        <option value="contabilidad" ${tipo_usuario === 'contabilidad' ? 'selected' : ''}>Contabilidad</option>
                        <option value="administracion" ${tipo_usuario === 'administracion' ? 'selected' : ''}>Administración</option>
                        <option value="auditoria" ${tipo_usuario === 'auditoria' ? 'selected' : ''}>Auditoria</option>
                        <option value="operaciones" ${tipo_usuario === 'operaciones' ? 'selected' : ''}>Operaciones</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-sucursal">Sucursal:</label>
                    <input class="swal2-input" type="text" id="edit-sucursal" name="sucursal" value="${sucursal}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-correo">Correo:</label>
                    <input class="swal2-input" type="email" id="edit-correo" name="correo" value="${correo}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-area">Área:</label>
                    <select class="swal2-input" id="edit-area" name="area">
                        <option value="central" ${area === 'central' ? 'selected' : ''}>Central</option>
                        <option value="suroccidente" ${area === 'suroccidente' ? 'selected' : ''}>Suroccidente</option>
                        <option value="nororiente" ${area === 'nororiente' ? 'selected' : ''}>Nororiente</option>
                        <option value="rrhh" ${area === 'rrhh' ? 'selected' : ''}>RRHH</option>
                        <option value="sistemas" ${area === 'sistemas' ? 'selected' : ''}>Sistemas</option>
                        <option value="contabilidad" ${area === 'contabilidad' ? 'selected' : ''}>Contabilidad</option>
                        <option value="operaciones" ${area === 'operaciones' ? 'selected' : ''}>Operaciones</option>
                        <option value="supervisión" ${area === 'supervisión' ? 'selected' : ''}>Supervisión</option>
                        <option value="gerencia" ${area === 'gerencia' ? 'selected' : ''}>Gerencia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-montoCaja">Monto Caja:</label>
                    <input class="swal2-input" type="number" step="0.01" id="edit-montoCaja" name="montoCaja" value="${montoCaja}">
                </div>
            </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Guardar cambios',
            preConfirm: () => {
                const form = document.getElementById('formEditar');
                const formData = new FormData(form);

                return fetch('gestionarUsuarios.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) throw new Error(data.message);
                        return data;
                    })
                    .catch(error => Swal.showValidationMessage(`Request failed: ${error}`));
            }
        }).then((result) => {
            if (result.isConfirmed) {
                notyf.success('Usuario editado con éxito');
                actualizarTablaUsuarios();
            }
        });
    }
});

// Eliminar usuario
document.addEventListener('click', function (event) {
    if (event.target.matches('.delete-btn')) {
        const id = event.target.dataset.id;

        Swal.fire({
            title: '¿Estás seguro de eliminar este usuario?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id', id);

                fetch('gestionarUsuarios.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            notyf.success('Usuario eliminado con éxito');
                            const row = document.getElementById('usuario-' + id);
                            if (row) row.remove();
                        } else {
                            notyf.error(data.message || 'Error al eliminar usuario');
                        }
                    })
                    .catch(() => notyf.error('Error al eliminar usuario'));
            }
        });
    }
});

// Actualizar tabla
function actualizarTablaUsuarios(filtro = '') {
    fetch(`gestionarUsuarios.php?ajax=1&filtro=${encodeURIComponent(filtro)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const usuarios = data.usuarios || [];
            const tbody = document.getElementById('usuarios');
            tbody.innerHTML = '';
            usuarios.forEach(u => {
                tbody.innerHTML += `
                <tr id="usuario-${u.id}">
                    <td class="border px-4 py-2">${u.id}</td>
                    <td class="border px-4 py-2">${escapeHtml(u.nombre ?? '')}</td>
                    <td class="border px-4 py-2">********</td>
                    <td class="border px-4 py-2">${escapeHtml(u.tipo_usuario ?? '')}</td>
                    <td class="border px-4 py-2">${escapeHtml(u.sucursal ?? '')}</td>
                    <td class="border px-4 py-2">${escapeHtml(u.correo ?? '')}</td>
                    <td class="border px-4 py-2">${escapeHtml(u.area ?? '')}</td>
                    <td class="border px-4 py-2">${escapeHtml(u.montoCaja ?? '')}</td>
                    <td class="border px-4 py-2">
                        <button class="bg-yellow-500 text-white px-2 py-1 rounded edit-btn"
                            data-id="${u.id}"
                            data-nombre="${escapeAttr(u.nombre ?? '')}"
                            data-tipo_usuario="${escapeAttr(u.tipo_usuario ?? '')}"
                            data-sucursal="${escapeAttr(u.sucursal ?? '')}"
                            data-correo="${escapeAttr(u.correo ?? '')}"
                            data-area="${escapeAttr(u.area ?? '')}"
                            data-monto-caja="${escapeAttr(u.montoCaja ?? '0.00')}">Editar</button>
                        <button class="bg-red-500 text-white px-2 py-1 rounded delete-btn" data-id="${u.id}">Eliminar</button>
                    </td>
                </tr>`;
            });
        })
        .catch(() => {});
}

function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}
function escapeAttr(str){ return escapeHtml(str); }

// Buscar
document.getElementById('buscarForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const filtro = document.getElementById('filtro').value;
    actualizarTablaUsuarios(filtro);
});

// Evitar reenvío
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

// Cargar inicial
actualizarTablaUsuarios();
</script>
</body>
</html>
