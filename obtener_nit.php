<?php
// nombre archivo: obtener_nit.php (REPARADO)
require_once 'utilidades.php';

session_start();

$conexion = conectarBD();

// (Opcional) asegurar charset si tu conectarBD() no lo hace
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['filtro'])) {
    // Sanitizar filtro (usa tu sanitizar si existe; si no, fallback)
    $filtro = $_POST['filtro'];
    if (function_exists('sanitizar')) {
        $filtro = sanitizar($filtro);
    } else {
        $filtro = trim($filtro);
    }

    $sql = "SELECT nit, nombre
            FROM datosnit
            WHERE nit LIKE ? OR nombre LIKE ?
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

    $nitList = "";
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $nit = (string)($fila['nit'] ?? '');
            $nombre = (string)($fila['nombre'] ?? '');

            // Escape HTML (para lo que se muestra)
            $nitH = htmlspecialchars($nit, ENT_QUOTES, 'UTF-8');
            $nombreH = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

            // Escape seguro para JS (para no romper onclick)
            $nitJS = json_encode($nit, JSON_UNESCAPED_UNICODE);
            $nombreJS = json_encode($nombre, JSON_UNESCAPED_UNICODE);

            $nitList .= '<div class="autocomplete-suggestion" '
                . 'onclick="seleccionarNit(' . $nitJS . ', ' . $nombreJS . ')">'
                . $nitH . ' - ' . $nombreH
                . '</div>';
        }
    } else {
        $nitList .= '<div class="autocomplete-suggestion">No se encontraron resultados</div>';
    }

    $stmt->close();
    cerrarConexion($conexion);

    echo $nitList;
    exit;
}

cerrarConexion($conexion);
?>
