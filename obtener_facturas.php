<?php
// Archivo: obtener_facturas.php (REPARADO)
session_start();
require_once 'utilidades.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(["status" => "error", "message" => "Usuario no autenticado."]);
    exit();
}

$nombre_usuario = $_SESSION['usuario'];

// Obtener lote (GET)
$lote = isset($_GET['lote']) ? (string)$_GET['lote'] : '';
$lote = trim($lote);

if ($lote === '') {
    echo json_encode(["status" => "error", "message" => "Lote no especificado."]);
    exit();
}

$conexion = conectarBD();

// (Opcional) charset
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Verificar si la conexión fue exitosa (por si conectarBD no lanza excepción)
if (!$conexion || (property_exists($conexion, 'connect_error') && $conexion->connect_error)) {
    echo json_encode(["status" => "error", "message" => "Error de conexión a la base de datos."]);
    exit();
}

$facturas = [];

// Preparar query
$stmt = $conexion->prepare("SELECT id, descripcion FROM facturas WHERE lote = ? AND usuario = ?");
if (!$stmt) {
    cerrarConexion($conexion);
    echo json_encode(["status" => "error", "message" => "Error al preparar la consulta."]);
    exit();
}

$stmt->bind_param("ss", $lote, $nombre_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $facturas[] = $row;
    }
}

$stmt->close();
cerrarConexion($conexion);

echo json_encode(["status" => "success", "facturas" => $facturas]);
?>
