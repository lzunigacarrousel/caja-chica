<?php
// nombre archivo: obtener_cuentas.php (REPARADO)
require_once 'utilidades.php';

session_start();

$conexion = conectarBD();

// (Opcional) charset
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['filtro'])) {
    $filtro = $_POST['filtro'];

    // Sanitizar si existe tu función
    if (function_exists('sanitizar')) {
        $filtro = sanitizar($filtro);
    } else {
        $filtro = trim($filtro);
    }

    $sql = "SELECT codigo, nombre
            FROM datosCuentas
            WHERE codigo LIKE ? OR nombre LIKE ?
            ORDER BY nombre ASC
            LIMIT 50";

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        cerrarConexion($conexion);
        echo '<div class="autocomplete-suggestion">Error al preparar la consulta</div>';
        exit;
    }

    $param = "%" . $filtro . "%";
    $stmt->bind_param("ss", $param, $param);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $cuentaList = "";

    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $codigo = (string)($fila['codigo'] ?? '');
            $nombre = (string)($fila['nombre'] ?? '');

            // Escape HTML para mostrar
            $codigoH = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
            $nombreH = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

            // Escape seguro para JS (evita romper onclick)
            $codigoJS = json_encode($codigo, JSON_UNESCAPED_UNICODE);
            $nombreJS = json_encode($nombre, JSON_UNESCAPED_UNICODE);

            $cuentaList .= '<div class="autocomplete-suggestion" '
                . 'onclick="seleccionarCuenta(' . $codigoJS . ', ' . $nombreJS . ')">'
                . $codigoH . ' - ' . $nombreH
                . '</div>';
        }
    } else {
        $cuentaList .= '<div class="autocomplete-suggestion">No se encontraron resultados</div>';
    }

    $stmt->close();
    cerrarConexion($conexion);

    echo $cuentaList;
    exit;
}

cerrarConexion($conexion);
?>
