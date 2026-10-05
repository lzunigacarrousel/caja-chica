<?php
// nombre archivo: obtener_usuarios.php (ANTI-FALLO)
require_once 'utilidades.php';

$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

$filtro = isset($_POST['filtro']) ? sanitizar($_POST['filtro']) : '';
$filtroLike = "%{$filtro}%";

$sql = "SELECT nombre, sucursal, montoCaja
        FROM usuarios
        WHERE nombre LIKE ? OR sucursal LIKE ?
        ORDER BY sucursal, nombre";

$stmt = $conexion->prepare($sql);
header('Content-Type: text/html; charset=utf-8');

if (!$stmt) {
    echo '<select class="form-select" size="10" style="width: 100%;"><option>Error al preparar la consulta.</option></select>';
    cerrarConexion($conexion);
    exit();
}

$stmt->bind_param("ss", $filtroLike, $filtroLike);
$stmt->execute();
$resultado = $stmt->get_result();

echo '<select class="form-select" size="10" style="width: 100%;">';

if (!$resultado || $resultado->num_rows === 0) {
    echo '<option value="" disabled>No se encontraron usuarios</option>';
} else {
    while ($usuario = $resultado->fetch_assoc()) {
        $sucursal = (string)($usuario['sucursal'] ?? '');
        $nombre   = (string)($usuario['nombre'] ?? '');
        $montoNum = $usuario['montoCaja'] !== null ? (float)$usuario['montoCaja'] : 0.0;
        $monto    = number_format($montoNum, 2, '.', '');

        // Escapar para HTML visible
        $sucursalH = htmlspecialchars($sucursal, ENT_QUOTES, 'UTF-8');
        $nombreH   = htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8');

        // Escapar para JS dentro del onclick (IMPORTANTE)
        $sucursalJS = htmlspecialchars($sucursal, ENT_QUOTES, 'UTF-8');
        $nombreJS   = htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8');
        $montoJS    = htmlspecialchars($monto,    ENT_QUOTES, 'UTF-8');

        echo "<option value=\"{$sucursalH}\" onclick=\"seleccionarCC('{$sucursalJS}','{$nombreJS}','{$montoJS}')\">";
        echo "{$sucursalH} - {$nombreH} - Q{$montoJS}";
        echo "</option>";
    }
}

echo '</select>';

$stmt->close();
cerrarConexion($conexion);
?>
