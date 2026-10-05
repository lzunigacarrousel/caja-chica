<?php
// Archivo: utilidades.php
// Este archivo contiene funciones y variables útiles para la conexión a la base de datos

// Configuración de la base de datos
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'DatosCajaChica');

/**
 * Conectar a la base de datos.
 * Crea la base de datos si no existe.
 *
 * @return mysqli Conexión a la base de datos.
 */
function conectarBD()
{
    // Crear conexión
    $conexion = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

    // Verificar conexión
    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    // Intentar seleccionar la base de datos
    if (!$conexion->select_db(DB_NAME)) {
        // Si la base de datos no existe, intentar crearla
        if (!$conexion->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
            die("Error al crear la base de datos: " . $conexion->error);
        }
        $conexion->select_db(DB_NAME);
    }

    // Asegurar charset (evita problemas con tildes/ñ)
    if (method_exists($conexion, 'set_charset')) {
        @$conexion->set_charset('utf8mb4');
    }

    return $conexion;
}

/**
 * Cerrar la conexión a la base de datos.
 *
 * @param mysqli $conexion Conexión a la base de datos.
 * @return void
 */
function cerrarConexion($conexion)
{
    if ($conexion instanceof mysqli) {
        @$conexion->close();
    }
}

/**
 * Sanitizar datos del usuario.
 *
 * @param string $data Datos a sanitizar.
 * @return string Datos sanitizados.
 */
function sanitizar($data)
{
    $data = trim((string)$data);
    $data = stripslashes($data);
    // Mantengo tu lógica, solo agrego charset explícito
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar y sanitizar un correo electrónico.
 *
 * @param string $email Correo electrónico a validar.
 * @return string|false Correo validado o false si es inválido.
 */
function validarEmail($email)
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Manejar el envío de formularios y evitar reenvíos.
 *
 * @param callable $accion Acción a realizar en el envío del formulario.
 * @param string $mensajeExito Mensaje a mostrar en caso de éxito.
 * @param string $mensajeError Mensaje a mostrar en caso de error.
 * @return string Mensaje de resultado del envío del formulario.
 */
function manejarEnvioFormulario($accion, $mensajeExito, $mensajeError)
{
    // Asegurar sesión porque aquí se usa $_SESSION
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $resultado = $accion();
        if ($resultado) {
            $_SESSION['mensaje'] = $mensajeExito;
        } else {
            $_SESSION['mensaje'] = $mensajeError;
        }

        // Redirigir a la misma página usando GET
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Mostrar mensaje si existe y luego borrarlo
    if (isset($_SESSION['mensaje'])) {
        $mensaje = $_SESSION['mensaje'];
        unset($_SESSION['mensaje']);
        return $mensaje;
    }

    return '';
}

/**
 * Helper JSON (para evitar respuestas rotas y el error de "Cannot redeclare responder_json()")
 */
if (!function_exists('responder_json')) {
    function responder_json(array $payload, int $httpCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload);
        exit();
    }
}

/**
 * Imprimir información de depuración.
 *
 * @param mixed $mensaje Mensaje o variable a depurar.
 * @return void
 */
function debug($mensaje)
{
    echo "<pre>";
    print_r($mensaje);
    echo "</pre>";
}
?>
