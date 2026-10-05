<?php
// Archivo: guiaUso.php (REPARADO)
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Verificar si el cambio de contraseña es requerido
if (isset($_SESSION['requiere_cambio_contrasena']) && $_SESSION['requiere_cambio_contrasena'] === true) {
    header("Location: cambiar_contrasena.php");
    exit();
}

// Obtener detalles del usuario
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? ''; // ✅ evitar undefined

// Evitar warnings por variable no definida
$error = $error ?? '';

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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía de Usuario</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <!-- Importar Tailwind CSS y otros estilos necesarios -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/guiaUso.css">
    <!-- Incluir jQuery y SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Incluir XLSX (a futuro) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
    <!-- Header con logo y cierre de sesión -->
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
    <br>
    <br>

    <!-- contenido -->
    <div class="flex-grow container mx-auto p-6">
        <div class="bg-white p-6 rounded-lg shadow-md">

            <!-- Contenedor para los videos en dos columnas -->
            <div class="videos-row">

                <br>
                <!-- Video manual de uso para parques -->
                <div class="video-container">
                    <br>
                    <h2 class="text-xl font-bold mb-4 text-center">Manual de Uso Ingreso facturas, Administración,
                        Supervisión, Parques.
                    </h2>
                    <video controls>
                        <source src="videos/manual_parques.mp4" type="video/mp4">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>

                <!-- Video manual de uso para administración -->
                <div class="video-container">
                    <br>
                    <h2 class="text-xl font-bold mb-4 text-center">Envio caja chica Administración</h2>
                    <video controls>
                        <source src="videos/manual_administracion.mp4" type="video/mp4">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>
            </div>

            <br>

            <!-- Botones de descarga centrados con espacio entre ellos -->
            <h2 class="text-2xl font-bold mb-4 text-center">Descargas</h2>
            <div class="botones-descarga">
                <a href="apps/CajaChicaApp.apk" download
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Descargar CajachicaApp
                </a>
                <a href="apps/microsoftLens.apk" download
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Descargar scanerLensApp
                </a>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 p-4 text-center text-white">
        <p>Corporación Carrousel S.A.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script>
    // Evitar el reenvío del formulario al recargar la página
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Manejar errores (si hay)
    <?php if (!empty($error)): ?>
        const notyf = new Notyf();
        notyf.error(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>);
    <?php endif; ?>
    </script>
</body>

</html>
