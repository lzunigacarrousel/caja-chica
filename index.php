<?php
// Archivo: index.php (REPARADO)
// Login CajaChica

require_once 'utilidades.php';
session_start();

// Si viene del portal, marcamos permiso
if (isset($_GET['from_portal']) && $_GET['from_portal'] === '1') {
    $_SESSION['portal_ok'] = 1;
}

// Si entran directo al login por URL (GET) sin venir del portal y SIN estar logueados -> portal
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && (!isset($_GET['from_portal']) || $_GET['from_portal'] !== '1')
    && empty($_SESSION['usuario'])
) {
    header("Location: /portal/");
    exit;
}









// Si ya está logueado
if (isset($_SESSION['usuario'])) {
    header("Location: menu.php");
    exit();
}

header('Content-Type: text/html; charset=utf-8');

$error = '';
$nombre = '';

// Procesar login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = isset($_POST['nombre']) ? sanitizar($_POST['nombre']) : '';
    $contrasenaIngresada = isset($_POST['contrasena']) ? sanitizar($_POST['contrasena']) : '';

    try {
        $conexion = conectarBD();
        if ($conexion && method_exists($conexion, 'set_charset')) {
            @$conexion->set_charset('utf8mb4');
        }

        $sql_usuario = "SELECT * FROM usuarios WHERE nombre = ? LIMIT 1";
        $stmt_usuario = $conexion->prepare($sql_usuario);
        if (!$stmt_usuario) {
            throw new Exception("Error en la preparación de la consulta.");
        }

        $stmt_usuario->bind_param("s", $nombre);
        $stmt_usuario->execute();
        $resultado_usuario = $stmt_usuario->get_result();

        if ($resultado_usuario && $resultado_usuario->num_rows === 1) {
            $usuario = $resultado_usuario->fetch_assoc();
            $hashBD = (string)($usuario['contrasena'] ?? '');

            // ✅ Validar contraseña:
            // 1) si es hash -> password_verify
            // 2) si es texto plano (legacy) -> comparación directa
            $esHash = str_starts_with($hashBD, '$2y$') || str_starts_with($hashBD, '$argon2');
            $passwordOk = $esHash ? password_verify($contrasenaIngresada, $hashBD)
                                  : hash_equals($hashBD, $contrasenaIngresada);

            if ($passwordOk) {

                // ✅ Caso especial: si el usuario escribió "caja" (en cualquier combinación)
                // Mantengo tu lógica: si la contraseña ingresada es "caja", forzar cambio.
                if (strcasecmp($contrasenaIngresada, 'caja') === 0) {
                    $_SESSION['usuario'] = $usuario['nombre'];
                    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                    $_SESSION['correo'] = $usuario['correo'];
                    $_SESSION['area'] = $usuario['area'];
                    $_SESSION['ultimo_acceso'] = time();
                    $_SESSION['requiere_cambio_contrasena'] = true;

                    $stmt_usuario->close();
                    $conexion->close();

                    header("Location: cambiar_contrasena.php");
                    exit();
                }

                // Login OK
                $_SESSION['usuario'] = $usuario['nombre'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                $_SESSION['correo'] = $usuario['correo'];
                $_SESSION['area'] = $usuario['area'];
                $_SESSION['ultimo_acceso'] = time();
                unset($_SESSION['requiere_cambio_contrasena']); // por si venía pegado

                // ❌ Ya NO guardamos contraseña en sesión
                // $_SESSION['contrasena'] = ...

                $stmt_usuario->close();
                $conexion->close();

                header("Location: menu.php");
                exit();

            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario incorrecto";
        }

        $stmt_usuario->close();
        $conexion->close();

    } catch (Exception $e) {
        $error = "Error de conexión.";
        if (function_exists('debug')) {
            debug($e);
        }
    }
}

// (Opcional) mensaje de error por URL
if (isset($_GET['error'])) {
    $error = urldecode((string)$_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CajaChica</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/index.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="container mx-auto max-w-sm p-6 bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
    <div class="text-center mb-6">
        <img src="imagenes/logo.png" alt="logo" class="h-20 mx-auto">
    </div>
    <h2 class="text-2xl font-semibold text-center text-gray-700 mb-4">Sistema de caja chica</h2>

    <form id="loginForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="mb-4">
            <label class="block text-gray-600 mb-2" for="nombre">Usuario:</label>
            <input class="w-full p-2 border border-gray-300 rounded form-input focus:border-blue-500 focus:ring focus:ring-blue-200 focus:outline-none"
                   type="text" id="nombre" name="nombre"
                   value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="mb-4">
            <label class="block text-gray-600 mb-2" for="contrasena">Contraseña:</label>
            <input class="w-full p-2 border border-gray-300 rounded form-input focus:border-blue-500 focus:ring focus:ring-blue-200 focus:outline-none"
                   type="password" id="contrasena" name="contrasena" required>
        </div>

        <div class="flex items-center justify-between">
            <input class="btn btn-primary w-full text-white bg-blue-600 hover:bg-blue-700 focus:bg-blue-800 transition-transform transform hover:-translate-y-1"
                   type="submit" value="Iniciar sesión">
        </div>
    </form>

    <div class="text-center mt-4">
        <a href="javascript:void(0)" id="olvideContrasena" class="text-blue-500 hover:text-blue-700">
            Olvidé mi contraseña
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if (!empty($error)): ?>
Swal.fire({
  icon: 'error',
  title: 'Error',
  text: <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>,
  showConfirmButton: true,
  confirmButtonColor: '#d33'
});
<?php endif; ?>

document.getElementById('olvideContrasena').addEventListener('click', function() {
  Swal.fire({
    icon: 'info',
    title: 'Recuperación de Contraseña',
    text: 'Por favor, contacte con el departamento de sistemas al correo: sistemas@carrousel.com.gt / luis@carrousel.com.gt',
    showConfirmButton: true,
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#3085d6'
  });
});
</script>

</body>
</html>
