<?php
// Archivo: gestionarNit.php (REPARADO)
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

// Solo admin y administracion
if ($tipo_usuario !== 'admin' && $tipo_usuario !== 'administracion') {
    header("Location: index.php");
    exit();
}

// Conectar BD
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

// ==============================
// Acciones (Agregar/Editar/Eliminar)
// ==============================
if (isset($_POST['accion'])) {
    $accion = (string)($_POST['accion'] ?? '');

    // Agregar nuevo NIT
    if ($accion === 'agregar') {
        $nit = function_exists('sanitizar') ? sanitizar($_POST['nit'] ?? '') : trim((string)($_POST['nit'] ?? ''));
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));
        $nombre = strtoupper(trim($nombre));

        if ($nit === '' || $nombre === '') {
            responder_json(['success' => false, 'message' => 'NIT y Nombre son obligatorios.'], 400);
        }

        // ✅ tabla consistente con instalador/otros endpoints: datosnit (minúsculas)
        $sql_check = "SELECT id FROM datosnit WHERE nit = ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) responder_json(['success' => false, 'message' => 'Error al preparar verificación.'], 500);

        $stmt_check->bind_param("s", $nit);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();

        if ($resultado_check && $resultado_check->num_rows > 0) {
            $stmt_check->close();
            responder_json(['success' => false, 'message' => 'El NIT ya existe.'], 409);
        }
        $stmt_check->close();

        $sql = "INSERT INTO datosnit (nit, nombre) VALUES (?, ?)";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar inserción.'], 500);

        $stmt->bind_param("ss", $nit, $nombre);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'NIT agregado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al agregar NIT.'], 500);
    }

    // Editar NIT existente
    if ($accion === 'editar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nit = function_exists('sanitizar') ? sanitizar($_POST['nit'] ?? '') : trim((string)($_POST['nit'] ?? ''));
        $nombre = function_exists('sanitizar') ? sanitizar($_POST['nombre'] ?? '') : trim((string)($_POST['nombre'] ?? ''));
        $nombre = strtoupper(trim($nombre));

        if ($id <= 0 || $nit === '' || $nombre === '') {
            responder_json(['success' => false, 'message' => 'Datos inválidos para editar.'], 400);
        }

        // Verificar si el NIT ya existe para otro registro
        $sql_check = "SELECT id FROM datosnit WHERE nit = ? AND id != ? LIMIT 1";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) responder_json(['success' => false, 'message' => 'Error al preparar verificación.'], 500);

        $stmt_check->bind_param("si", $nit, $id);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();

        if ($resultado_check && $resultado_check->num_rows > 0) {
            $stmt_check->close();
            responder_json(['success' => false, 'message' => 'El NIT ya existe.'], 409);
        }
        $stmt_check->close();

        $sql = "UPDATE datosnit SET nit = ?, nombre = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar actualización.'], 500);

        $stmt->bind_param("ssi", $nit, $nombre, $id);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'NIT actualizado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al actualizar NIT.'], 500);
    }

    // Eliminar NIT
    if ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) responder_json(['success' => false, 'message' => 'ID inválido.'], 400);

        $sql = "DELETE FROM datosnit WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) responder_json(['success' => false, 'message' => 'Error al preparar eliminación.'], 500);

        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            responder_json(['success' => true, 'message' => 'NIT eliminado con éxito.']);
        }
        $stmt->close();
        responder_json(['success' => false, 'message' => 'Error al eliminar NIT.'], 500);
    }

    responder_json(['success' => false, 'message' => 'Acción no válida.'], 400);
}

// ==============================
// Paginación + Filtro (AJAX y carga normal)
// ==============================
$limite = 50;
$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
if ($pagina <= 0) $pagina = 1;
$offset = ($pagina - 1) * $limite;

$filtro = isset($_POST['filtro']) ? (function_exists('sanitizar') ? sanitizar($_POST['filtro']) : trim((string)$_POST['filtro'])) : '';
$filtro_param = "%" . $filtro . "%";

$sql = "SELECT * FROM datosnit
        WHERE nit LIKE ? OR nombre LIKE ?
        ORDER BY nombre ASC
        LIMIT ? OFFSET ?";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    if (isset($_POST['ajax'])) responder_json(['success' => false, 'message' => 'Error al preparar consulta.'], 500);
    // si no es ajax, dejamos $nits vacío
    $nits = [];
    $total_paginas = 1;
} else {
    $stmt->bind_param("ssii", $filtro_param, $filtro_param, $limite, $offset);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $nits = [];
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $nits[] = $fila;
        }
    }
    $stmt->close();

    // Total páginas
    $total_sql = "SELECT COUNT(*) AS total FROM datosnit WHERE nit LIKE ? OR nombre LIKE ?";
    $stmt_total = $conexion->prepare($total_sql);
    $total_registros = 0;
    if ($stmt_total) {
        $stmt_total->bind_param("ss", $filtro_param, $filtro_param);
        $stmt_total->execute();
        $res_total = $stmt_total->get_result();
        if ($res_total) {
            $row_total = $res_total->fetch_assoc();
            $total_registros = (int)($row_total['total'] ?? 0);
        }
        $stmt_total->close();
    }
    $total_paginas = max(1, (int)ceil($total_registros / $limite));
}

if (isset($_POST['ajax'])) {
    responder_json([
        'success' => true,
        'nits' => $nits,
        'total_paginas' => $total_paginas,
        'pagina_actual' => $pagina
    ]);
}

// Render normal HTML
cerrarConexion($conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar NIT</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/gestionarNit.css">
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    <h1 class="text-center text-2xl font-bold">Gestionar NIT</h1>
    <div class="text-center mt-4 flex justify-center space-x-4">
        <a href="https://portal.sat.gob.gt/portal/consulta-cui-nit/" target="_blank" rel="noopener">
            <button id="btnVerificarNit" class="bg-red-500 text-white px-4 py-2 rounded">Verificar NIT en portal SAT</button>
        </a>
        <button id="btnAgregarNit" class="bg-blue-600 text-white px-4 py-2 rounded">Agregar Nuevo NIT</button>
    </div>

    <form id="formBuscar" class="mt-3 flex justify-center">
        <input type="text" id="filtro" name="filtro" placeholder="Buscar por NIT o Nombre"
               class="form-input w-full md:w-2/3 lg:w-1/2 rounded-l px-4 py-2 border-t mr-0 border-b border-l text-gray-800 border-gray-200 bg-white">
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r">Buscar</button>
    </form>

    <h2 class="text-center text-xl mt-4">NIT Existentes</h2>
    <div class="table-responsive">
        <table class="table-auto w-full mt-4">
            <thead>
            <tr class="bg-gray-200">
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">NIT</th>
                <th class="px-4 py-2">Nombre</th>
                <th class="px-4 py-2">Acciones</th>
            </tr>
            </thead>
            <tbody id="nits"></tbody>
        </table>
    </div>
    <div id="paginacion" class="text-center mt-4"></div>
</div>

<script>
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });

let paginaActual = 1;

function escapeHtml(str){
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}
function escapeAttr(str){ return escapeHtml(str); }

function actualizarTablaNits(filtro = '', pagina = 1) {
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('filtro', filtro);
    formData.append('pagina', pagina);

    fetch('gestionarNit.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (!data.success) return;

            const nits = data.nits || [];
            const tbody = document.getElementById('nits');
            tbody.innerHTML = '';

            nits.forEach(nit => {
                tbody.innerHTML += `
                <tr id="nit-${nit.id}">
                    <td class="border px-4 py-2">${escapeHtml(nit.id)}</td>
                    <td class="border px-4 py-2">${escapeHtml(nit.nit)}</td>
                    <td class="border px-4 py-2">${escapeHtml(nit.nombre)}</td>
                    <td class="border px-4 py-2">
                        <button class="bg-yellow-500 text-white px-2 py-1 rounded edit-btn"
                            data-id="${escapeAttr(nit.id)}"
                            data-nit="${escapeAttr(nit.nit)}"
                            data-nombre="${escapeAttr(nit.nombre)}">Editar</button>
                        <button class="bg-red-500 text-white px-2 py-1 rounded delete-btn"
                            data-id="${escapeAttr(nit.id)}">Eliminar</button>
                    </td>
                </tr>`;
            });

            agregarEventosBotones();

            paginaActual = data.pagina_actual || 1;
            const totalPaginas = data.total_paginas || 1;

            document.getElementById('paginacion').innerHTML = `
                <button onclick="cambiarPagina(${paginaActual - 1})"
                        class="bg-blue-500 text-white font-semibold py-2 px-4 rounded hover:bg-blue-600 disabled:opacity-50"
                        ${paginaActual === 1 ? 'disabled' : ''}>
                    Anterior
                </button>
                <span class="mx-2 font-medium">Página ${paginaActual} de ${totalPaginas}</span>
                <button onclick="cambiarPagina(${paginaActual + 1})"
                        class="bg-green-500 text-white font-semibold py-2 px-4 rounded hover:bg-green-600 disabled:opacity-50"
                        ${paginaActual === totalPaginas ? 'disabled' : ''}>
                    Siguiente
                </button>`;
        })
        .catch(() => {});
}

function agregarEventosBotones() {
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            const nit = this.dataset.nit;
            const nombre = this.dataset.nombre;

            Swal.fire({
                title: 'Editar NIT',
                html: `<form id="formEditar">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="${escapeAttr(id)}">
                        <input class="swal2-input" type="text" id="edit-nit" name="nit" value="${escapeAttr(nit)}" required>
                        <input class="swal2-input" type="text" id="edit-nombre" name="nombre" value="${escapeAttr(nombre)}" required>
                       </form>`,
                showCancelButton: true,
                confirmButtonText: 'Guardar cambios',
                preConfirm: () => {
                    const form = document.getElementById('formEditar');
                    const formData = new FormData(form);

                    return fetch('gestionarNit.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message);
                            return data;
                        })
                        .catch(error => Swal.showValidationMessage(`Error: ${error}`));
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    notyf.success('NIT editado con éxito');
                    actualizarTablaNits(document.getElementById('filtro').value, paginaActual);
                }
            });
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;

            Swal.fire({
                title: '¿Estás seguro de eliminar este NIT?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id', id);

                fetch('gestionarNit.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            notyf.success('NIT eliminado con éxito');
                            const row = document.getElementById('nit-' + id);
                            if (row) row.remove();
                        } else {
                            notyf.error(data.message || 'Error al eliminar NIT');
                        }
                    })
                    .catch(() => notyf.error('Error al eliminar NIT'));
            });
        });
    });
}

function cambiarPagina(nuevaPagina) {
    if (nuevaPagina > 0) {
        actualizarTablaNits(document.getElementById('filtro').value, nuevaPagina);
    }
}

document.getElementById('formBuscar').addEventListener('submit', function (e) {
    e.preventDefault();
    paginaActual = 1;
    actualizarTablaNits(document.getElementById('filtro').value, paginaActual);
});

document.getElementById('btnAgregarNit').addEventListener('click', function () {
    Swal.fire({
        title: 'Agregar Nuevo NIT',
        html: `
            <form id="formAgregar">
                <input type="hidden" name="accion" value="agregar">
                <label for="agregar-nit">NIT:</label>
                <input class="swal2-input" type="text" id="agregar-nit" name="nit" required>
                <label for="agregar-nombre">Nombre:</label>
                <input class="swal2-input" type="text" id="agregar-nombre" name="nombre" required>
            </form>`,
        focusConfirm: false,
        preConfirm: () => {
            const nit = document.getElementById('agregar-nit').value.trim();
            const nombre = document.getElementById('agregar-nombre').value.trim();
            if (!nit || !nombre) {
                Swal.showValidationMessage("Ambos campos son obligatorios.");
                return false;
            }
            const formData = new FormData();
            formData.append('accion', 'agregar');
            formData.append('nit', nit);
            formData.append('nombre', nombre);

            return fetch('gestionarNit.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) Swal.showValidationMessage(data.message);
                    return data;
                })
                .catch(error => Swal.showValidationMessage(`Error en el envío: ${error}`));
        },
        showCancelButton: true,
        confirmButtonText: 'Agregar NIT'
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            notyf.success(result.value.message || 'NIT agregado con éxito');
            actualizarTablaNits(document.getElementById('filtro').value, paginaActual);
        }
    });
});

// Evitar reenvío
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

// Cargar inicial
document.addEventListener('DOMContentLoaded', () => actualizarTablaNits());
</script>
</body>
</html>
