<?php
// Archivo: menu.php (REPARADO)

require_once 'utilidades.php';
//session_start();


session_start();

// Si no viene del portal, al portal
if (empty($_SESSION['portal_ok'])) {
    header("Location: /portal/");
    exit;
}

// Si no está logueado, al login
if (empty($_SESSION['usuario'])) {
    header("Location: /CajaChica/index.php");
    exit;
}

























/**
 * Manejo de inactividad (15 min)
 * - Se ejecuta al inicio
 * - Siempre actualiza ultimo_acceso
 */
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

    // ✅ Siempre actualizar
    $_SESSION['ultimo_acceso'] = $ahora;
}

verificarInactividad();

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

// Conectar a BD
$conexion = conectarBD();
if ($conexion && method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
}

// Obtener detalles del usuario
$nombre_usuario = $_SESSION['usuario'];
$tipo_usuario   = $_SESSION['tipo_usuario'] ?? ''; // ✅ evitar undefined

// Evitar warning por variable no definida
$error = $error ?? '';

// Obtener monto de caja del usuario
$montoCaja = 0.0;
$stmtCaja = $conexion->prepare("SELECT montoCaja FROM usuarios WHERE nombre = ?");
if ($stmtCaja) {
    $stmtCaja->bind_param("s", $nombre_usuario);
    $stmtCaja->execute();
    $stmtCaja->bind_result($montoCaja);
    $stmtCaja->fetch();
    $stmtCaja->close();
}
$montoCaja = (float)$montoCaja;

// Obtener el lote más reciente del usuario
$lote = "";
$stmtLote = $conexion->prepare("SELECT nombre_lote FROM lotes WHERE usuario = ? ORDER BY fecha_creacion DESC LIMIT 1");
if ($stmtLote) {
    $stmtLote->bind_param("s", $nombre_usuario);
    $stmtLote->execute();
    $stmtLote->bind_result($lote);
    $stmtLote->fetch();
    $stmtLote->close();
}

// Obtener el total consumido en el lote actual con descripción
$totalConsumido = 0.0;
$detallesFactura = [];

if (!empty($lote)) {
    $stmtConsumido = $conexion->prepare("SELECT valor_factura, descripcion FROM facturas WHERE usuario = ? AND lote = ?");
    if ($stmtConsumido) {
        $stmtConsumido->bind_param("ss", $nombre_usuario, $lote);
        $stmtConsumido->execute();
        $stmtConsumido->bind_result($valor_factura, $descripcion);

        while ($stmtConsumido->fetch()) {
            $vf = (float)$valor_factura;
            $totalConsumido += $vf;
            $detallesFactura[] = ['valor_factura' => $vf, 'descripcion' => (string)$descripcion];
        }
        $stmtConsumido->close();
    }
}

// Calcular monto restante
$montoRestante = $montoCaja - $totalConsumido;

// Cerrar conexión
cerrarConexion($conexion);

// ✅ Helpers para evitar división entre cero en barras
function pct($num, $den): float {
    if ((float)$den == 0.0) return 0.0;
    return (float)$num / (float)$den * 100.0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/menu.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
<header class="bg-gray-800 p-4 flex justify-between items-center text-white fixed top-0 w-full z-50 shadow-lg">
    <a href="menu.php"><img src="imagenes/logo.png" alt="logo" class="h-12"></a>
    <a href="logout.php" class="text-white font-semibold hover:underline">Cerrar sesión</a>
</header>

<br><br>

<main class="flex-grow container mx-auto p-6 mt-20">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="text-center mb-6">
            <h3 class="text-3xl font-bold text-gray-800">¡Bienvenido!</h3>
            <h2 class="text-2xl font-semibold text-gray-700 mt-2"><?php echo htmlspecialchars($nombre_usuario); ?></h2>
            <hr>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-center mb-10">
            <?php if (in_array($tipo_usuario, ['empleado', 'administracion', 'operaciones', 'contabilidad', 'admin', 'auditoria'])): ?>
                <a href="formularioFactura.php"
                   class="menu-item p-6 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fa fa-pencil fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Formulario facturas CAJA CHICA</span>
                </a>
            <?php endif; ?>

            <?php if (in_array($tipo_usuario, ['contabilidad', 'administracion', 'auditoria', 'gerencia', 'admin'])): ?>
                <a href="reportesAdministracion.php"
                   class="menu-item p-6 bg-yellow-50 text-yellow-600 hover:bg-yellow-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fa fa-database fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Reportes ADMINISTRACIÓN</span>
                </a>

                <a href="gestionarNit.php"
                   class="menu-item p-6 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fas fa-edit fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Gestionar NIT</span>
                </a>

                <a href="gestionarCuentas.php"
                   class="menu-item p-6 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fa fa-edit fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Gestionar CUENTAS CONTABLES</span>
                </a>
            <?php endif; ?>

            <?php if ($tipo_usuario === 'admin'): ?>
                <a href="gestionarUsuarios.php"
                   class="menu-item p-6 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fas fa-users fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Gestionar USUARIOS</span>
                </a>

                <a href="lotes.php"
                   class="menu-item p-6 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                    <i class="fa fa-list fa-2x mb-3"></i>
                    <span class="font-semibold text-lg">Gestionar LOTES DE USUARIOS</span>
                </a>
            <?php endif; ?>

            <a href="guiaUso.php"
               class="menu-item p-6 bg-gray-50 text-gray-600 hover:bg-gray-100 rounded-lg transition transform hover:scale-105 shadow-md flex flex-col items-center justify-center">
                <i class="fa fa-info-circle fa-2x mb-3"></i>
                <span class="font-semibold text-lg">Guía de uso y APLICACIÓN MÓVIL</span>
            </a>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center mt-8 place-items-center">
            <div class="flex flex-col items-center w-full max-w-xs">
                <h3 class="text-lg font-bold">Monto de caja:</h3>
                <div class="w-full bg-gray-200 rounded-full h-4 mb-1">
                    <div class="bg-green-600 h-4 rounded-full" style="width: <?php echo $montoCaja > 0 ? 100 : 0; ?>%;"></div>
                </div>
                <span class="text-green-600 font-semibold">Q<?php echo number_format($montoCaja, 2); ?></span>
            </div>

            <div class="flex flex-col items-center w-full max-w-xs">
                <h3 class="text-lg font-bold">Total consumido:</h3>
                <div class="w-full bg-gray-200 rounded-full h-4 mb-1">
                    <div class="bg-red-600 h-4 rounded-full" style="width: <?php echo min(100, pct($totalConsumido, $montoCaja)); ?>%;"></div>
                </div>
                <span class="text-red-600 font-semibold">Q<?php echo number_format($totalConsumido, 2); ?></span>
            </div>

            <div class="flex flex-col items-center w-full max-w-xs">
                <h3 class="text-lg font-bold">Monto restante:</h3>
                <div class="w-full bg-gray-200 rounded-full h-4 mb-1">
                    <div class="<?php echo $montoRestante > 0 ? 'bg-green-600' : ($montoRestante < 0 ? 'bg-red-600' : 'bg-black'); ?> h-4 rounded-full"
                         style="width: <?php echo min(100, abs(pct($montoRestante, $montoCaja))); ?>%;"></div>
                </div>
                <span class="<?php echo $montoRestante > 0 ? 'text-green-600' : ($montoRestante < 0 ? 'text-red-600' : 'text-black'); ?> font-semibold">
                    Q<?php echo number_format($montoRestante, 2); ?>
                </span>
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 place-items-center">
            <div class="flex justify-center items-center w-full max-w-xs">
                <canvas id="consumoChart" style="max-width: 300px;"></canvas>
            </div>

            <div class="text-sm text-gray-500 max-h-64 overflow-y-auto w-full max-w-xs md:w-3/4">
                <ul class="mt-2">
                    <?php foreach ($detallesFactura as $detalle): ?>
                        <li class="mb-2">
                            <i class="fa fa-info-circle text-blue-500"></i>
                            <?php echo htmlspecialchars($detalle['descripcion']); ?> -
                            Q<?php echo number_format((float)$detalle['valor_factura'], 2); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
</main>

<footer class="bg-gray-800 p-4 text-center text-white mt-6">
    <p>Corporación Carrousel S.A.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    <?php if (!empty($error)): ?>
    const notyf = new Notyf();
    notyf.error(<?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>);
    <?php endif; ?>

    const montoCaja = <?php echo (float)$montoCaja; ?>;
    const totalConsumido = <?php echo (float)$totalConsumido; ?>;
    const montoRestante = <?php echo (float)$montoRestante; ?>;

    // ✅ evitar división por cero
    const porcentajeConsumido = montoCaja > 0 ? (totalConsumido / montoCaja) * 100 : 0;

    const colorGrafico = porcentajeConsumido > 100 ? '#f87171' : '#f43f5e';
    const colorRestante = (montoRestante < 0 || porcentajeConsumido > 100) ? '#f87171' : '#10b981';

    const ctx = document.getElementById('consumoChart').getContext('2d');
    const consumoChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Monto Restante', 'Total Consumido'],
            datasets: [{
                data: [montoRestante > 0 ? montoRestante : 0, totalConsumido],
                backgroundColor: [colorRestante, colorGrafico],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    onClick: (e, legendItem) => {
                        const index = legendItem.index;
                        const ci = consumoChart;
                        ci.toggleDataVisibility(index);
                        ci.update();
                    }
                }
            }
        }
    });
</script>

</body>
</html>
