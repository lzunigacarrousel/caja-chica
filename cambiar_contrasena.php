<?php
// Archivo: cambiar_contrasena.php (REPARADO)
require_once 'utilidades.php';
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Verificar si el cambio de contraseña es requerido
if (!isset($_SESSION['requiere_cambio_contrasena'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Procesar el formulario de cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nueva_contrasena = (string)($_POST['nueva_contrasena'] ?? '');
    $confirmar_contrasena = (string)($_POST['confirmar_contrasena'] ?? '');

    if ($nueva_contrasena === '' || $confirmar_contrasena === '') {
        $error = "Debe completar ambos campos.";
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            $conexion = conectarBD();
            if ($conexion && method_exists($conexion, 'set_charset')) {
                @$conexion->set_charset('utf8mb4');
            }

            // ✅ Guardar con hash (compatible con password_verify en login)
            $hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);

            $sql = "UPDATE usuarios SET contrasena = ? WHERE nombre = ?";
            $stmt = $conexion->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
            }

            $usuario = (string)$_SESSION['usuario'];
            $stmt->bind_param("ss", $hash, $usuario);
            $stmt->execute();

            // ✅ affected_rows puede ser 0 si la nueva contraseña coincide con la anterior
            if ($stmt->errno === 0) {
                $success = "Contraseña cambiada exitosamente.";

                unset($_SESSION['requiere_cambio_contrasena']);

                $stmt->close();
                $conexion->close();

                header("Location: menu.php");
                exit();
            } else {
                $error = "Error al actualizar la contraseña.";
            }

            $stmt->close();
            $conexion->close();
        } catch (Exception $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="container mx-auto max-w-sm p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold text-center text-gray-700 mb-4">Cambiar Contraseña</h2>

        <?php if (!empty($error)): ?>
            <p class="text-red-500"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="text-green-500"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2" for="nueva_contrasena">Nueva Contraseña:</label>
                <input
                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    type="password" id="nueva_contrasena" name="nueva_contrasena" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2" for="confirmar_contrasena">Confirmar Contraseña:</label>
                <input
                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
            </div>

            <div class="text-center">
                <input class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg cursor-pointer"
                    type="submit" value="Cambiar Contraseña">
            </div>
        </form>
    </div>
</body>
</html>
