<?php
// Archivo: menu.php (REPARADO)
session_start();
require_once 'utilidades.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Verificar si el cambio de contraseña es requerido
if (!empty($_SESSION['requiere_cambio_contrasena'])) {
    header("Location: cambiar_contrasena.php");
    exit();
}

// Obtener detalles del usuario
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario = $_SESSION['tipo_usuario'] ?? ''; // ✅ evitar undefined

// Evitar warning por variable no definida
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

// ✅ condición corregida: antes comparabas con strings "!contabilidad" etc.
// Si tu intención era "todos excepto contabilidad/operaciones/administracion", esto sí funciona.
$puedeVerReportesUsuario = ($tipo_usuario === '' || (
    $tipo_usuario !== 'contabilidad' &&
    $tipo_usuario !== 'operaciones' &&
    $tipo_usuario !== 'administracion'
)) || $tipo_usuario === 'admin';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <!-- Importar Tailwind CSS y otros estilos necesarios -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/menu.css">
    <!-- Incluir jQuery y SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Incluir XLSX (a futuro) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
    <!-- Header con logo y enlace de cierre de sesión -->
    <header class="bg-gray-800 p-4 flex justify-between items-center text-white">
        <a href="menu.php"><img src="imagenes/logo.png" alt="logo" class="h-12"></a>
        <a href="logout.php" class="">Cerrar sesión</a>
    </header>
    <br>
    <br>
    <div class="flex-grow container mx-auto p-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <!-- Bienvenida y detalles del usuario -->
            <div class="text-center mb-4">
                <h3 class="text-2xl font-semibold">¡Bienvenido!</h3>
                <h2 class="text-2xl font-semibold"><?php echo htmlspecialchars($nombre_usuario, ENT_QUOTES, 'UTF-8'); ?></h2>
            </div>
            <div class="text-center mb-6">
                <!--  <p>Usuario: <?php echo htmlspecialchars($tipo_usuario, ENT_QUOTES, 'UTF-8'); ?></p>-->
                <hr>
            </div>

            <!-- Menú de opciones -->
            <div class="text-center">
                <h4 class="text-xl font-semibold mb-4">Menú de opciones</h4>

                <!-- Opción para 'empleado', 'operaciones', 'contabilidad', 'administracion', y 'admin' -->
                <?php if (in_array($tipo_usuario, ['empleado', 'administracion', 'operaciones', 'contabilidad', 'admin', 'auditoria'], true)): ?>
                    <div class="menu-item mb-4">
                        <a href="formularioFactura.php" class="text-blue-500 hover:underline">
                            <i class="fa fa-th mr-2"></i> Formulario facturas CAJA CHICA
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Opción para 'contabilidad', 'administracion', 'auditoria', 'gerencia' y 'admin' -->
                <?php if (in_array($tipo_usuario, ['contabilidad', 'administracion', 'auditoria', 'gerencia', 'admin'], true)): ?>
                    <div class="menu-item mb-4">
                        <a href="reportesAdministracion.php" class="text-blue-500 hover:underline">
                            <i class="fa fa-database mr-2"></i> Reportes ADMINISTRACIÓN
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Reportes caja chica USUARIO -->
                <?php if ($puedeVerReportesUsuario): ?>
                    <div class="menu-item mb-4">
                        <a href="tablafacturasUsuario.php" class="text-blue-500 hover:underline">
                            <i class="fa fa-database mr-2"></i> Reportes caja chica USUARIO
                        </a>
                    </div>

                    <div class="menu-item mb-4">
                        <a href="lotes.php" class="text-blue-500 hover:underline">
                            <i class="fas fa-book mr-2"></i> Gestionar Lotes de FACTURAS
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Opciones exclusivas para 'admin' -->
                <?php if ($tipo_usuario === 'admin'): ?>
                    <div class="menu-item mb-4">
                        <a href="gestionarUsuarios.php" class="text-blue-500 hover:underline">
                            <i class="fas fa-users mr-2"></i> Gestionar USUARIOS
                        </a>
                    </div>
                    <div class="menu-item mb-4">
                        <a href="gestionarNit.php" class="text-blue-500 hover:underline">
                            <i class="fas fa-edit mr-2"></i> Gestionar NIT
                        </a>
                    </div>
                    <div class="menu-item mb-4">
                        <a href="gestionarCuentas.php" class="text-blue-500 hover:underline">
                            <i class="fa fa-edit mr-2"></i> Gestionar CUENTAS CONTABLES
                        </a>
                    </div>
                <?php endif; ?>
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
