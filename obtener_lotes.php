<?php
// api_facturas.php (REPARADO)
session_start();
require_once 'utilidades.php';

$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

header('Content-Type: application/json; charset=utf-8');

$accion = $_POST['accion'] ?? '';
$response = ['lotes' => [], 'facturas' => []];

// ✅ Evitar que warnings rompan el JSON
if (!isset($_SESSION['usuario'])) {
    cerrarConexion($conexion);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no iniciada.'
    ]);
    exit;
}

if ($accion === 'obtener_lotes') {
    // Misma lógica: lotes disponibles (global)
    $sql = "SELECT DISTINCT lote FROM facturas ORDER BY fecha_documento DESC";
    $resultado = $conexion->query($sql);

    $lotes = [];
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $lotes[] = $fila['lote'];
        }
    }
    $response['lotes'] = $lotes;

} elseif ($accion === 'obtener_facturas') {
    $lote = $_POST['lote'] ?? '';
    $usuarioActual = $_SESSION['usuario'];

    $facturas = [];
    if ($lote !== '') {
        $sql = "SELECT * FROM facturas WHERE usuario = ? AND lote = ?";
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ss", $usuarioActual, $lote);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado && $resultado->num_rows > 0) {
                while ($fila = $resultado->fetch_assoc()) {
                    $facturas[] = $fila;
                }
            }
            $stmt->close();
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Error al preparar la consulta.';
        }
    }

    $response['facturas'] = $facturas;

} else {
    // Acción inválida (sin cambiar el flujo, solo claridad)
    $response['status'] = 'error';
    $response['message'] = 'Acción no válida.';
}

cerrarConexion($conexion);
echo json_encode($response);
?>
