<?php
// Archivo: instalador.php (REPARADO)
require_once 'utilidades.php';
session_start();

// Crear conexión al servidor MySQL (sin DB todavía)
$conexion = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// UTF8
@$conexion->set_charset('utf8mb4');

$accion = isset($_POST['accion']) ? sanitizar($_POST['accion']) : '';
$baseDatosExiste = false;

// Verificar si la base de datos existe (seguro)
$dbNameEsc = $conexion->real_escape_string(DB_NAME);
$resultado = $conexion->query("SHOW DATABASES LIKE '{$dbNameEsc}'");
if ($resultado && $resultado->num_rows > 0) $baseDatosExiste = true;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mensajes = [];

    switch ($accion) {

        case 'borrar_base':
            $sql = "DROP DATABASE IF EXISTS `{$dbNameEsc}`";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = "Base de datos borrada con éxito.";
                $baseDatosExiste = false;
            } else {
                $mensajes[] = "Error al borrar la base de datos: " . $conexion->error;
            }
            break;

        case 'borrar_tabla':
            if (!$baseDatosExiste) {
                $mensajes[] = "La base de datos no existe. No se puede borrar la tabla.";
                break;
            }

            $conexion->select_db(DB_NAME);

            // ✅ Whitelist: solo permitir tablas existentes
            $tablas = [];
            $r = $conexion->query("SHOW TABLES");
            if ($r) {
                while ($f = $r->fetch_array()) $tablas[] = $f[0];
            }

            $tabla = isset($_POST['tabla']) ? sanitizar($_POST['tabla']) : '';
            if ($tabla && in_array($tabla, $tablas, true)) {
                $tablaEsc = str_replace('`', '', $tabla);
                $sql = "DROP TABLE IF EXISTS `{$tablaEsc}`";
                if ($conexion->query($sql) === TRUE) {
                    $mensajes[] = "Tabla '{$tabla}' borrada con éxito.";
                } else {
                    $mensajes[] = "Error al borrar la tabla: " . $conexion->error;
                }
            } else {
                $mensajes[] = "Tabla inválida.";
            }
            break;

        case 'instalar':
            // Crear base de datos si no existe
            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbNameEsc}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Base de datos creada." : "Base de datos ya existente.";
                $baseDatosExiste = true;
            } else {
                $mensajes[] = "Error al crear la base de datos: " . $conexion->error;
                break;
            }

            $conexion->select_db(DB_NAME);

            // ✅ Usuarios: contraseñas seguras (hash) + tamaño adecuado
            $sql = "CREATE TABLE IF NOT EXISTS usuarios (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(30) NOT NULL UNIQUE,
                contrasena VARCHAR(255) NOT NULL,
                tipo_usuario ENUM('admin','empleado','gerencia','auditoria','contabilidad','operaciones','administracion') NOT NULL,
                sucursal VARCHAR(50) NULL,
                correo VARCHAR(100) NULL,
                montoCaja DECIMAL(10,2) NULL,
                area ENUM('central','suroccidente','nororiente','rrhh','sistemas','contabilidad','operaciones','supervisión','gerencia') NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Tabla 'usuarios' creada." : "Tabla 'usuarios' ya existente.";
            } else {
                $mensajes[] = "Error al crear la tabla 'usuarios': " . $conexion->error;
            }

            // Facturas
            $sql = "CREATE TABLE IF NOT EXISTS facturas (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                lote VARCHAR(255) NOT NULL,
                usuario VARCHAR(100) NOT NULL,
                descripcion VARCHAR(255) NOT NULL,
                cuenta_mayor VARCHAR(50) NOT NULL,
                nombre_cuenta_mayor VARCHAR(100) NOT NULL,
                sucursal VARCHAR(50) NOT NULL,
                nombre_cc VARCHAR(100) NOT NULL,
                indicador_impuestos VARCHAR(10) NOT NULL,
                tipo_compraventa VARCHAR(255) NOT NULL,
                valor_factura DECIMAL(10,2) NOT NULL,
                sujeto_retencion VARCHAR(3) NOT NULL DEFAULT 'No',
                nit_proveedor VARCHAR(20) NOT NULL,
                nombre_proveedor VARCHAR(100) NOT NULL,
                serie_documento VARCHAR(50) NOT NULL,
                numero_documento VARCHAR(50) NOT NULL,
                fecha_documento DATETIME NOT NULL,
                tipo_combustible ENUM('Diesel','regular','super') NULL,
                galones DECIMAL(10,2) NULL,
                total_factura_combustible DECIMAL(10,2) NULL,
                ruta_archivo_factura VARCHAR(255) DEFAULT NULL,
                nombre_archivo_factura VARCHAR(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Tabla 'facturas' creada." : "Tabla 'facturas' ya existente.";
            } else {
                $mensajes[] = "Error al crear la tabla 'facturas': " . $conexion->error;
            }

            // Lotes
            $sql = "CREATE TABLE IF NOT EXISTS lotes (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre_lote VARCHAR(255) NOT NULL,
                fecha_creacion DATETIME NOT NULL,
                usuario VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Tabla 'lotes' creada." : "Tabla 'lotes' ya existente.";
            } else {
                $mensajes[] = "Error al crear la tabla 'lotes': " . $conexion->error;
            }

            // datosnit
            $sql = "CREATE TABLE IF NOT EXISTS datosnit (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nit VARCHAR(20) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Tabla 'datosnit' creada." : "Tabla 'datosnit' ya existente.";
            } else {
                $mensajes[] = "Error al crear la tabla 'datosnit': " . $conexion->error;
            }

            // datosCuentas
            $sql = "CREATE TABLE IF NOT EXISTS datosCuentas (
                id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(10) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            if ($conexion->query($sql) === TRUE) {
                $mensajes[] = ($conexion->affected_rows > 0) ? "Tabla 'datosCuentas' creada." : "Tabla 'datosCuentas' ya existente.";
            } else {
                $mensajes[] = "Error al crear la tabla 'datosCuentas': " . $conexion->error;
            }

            // ✅ Usuario admin por defecto con hash
            $nombre_admin = "administrador";
            $contrasena_admin = password_hash("administrador", PASSWORD_DEFAULT);
            $tipo_usuario_admin = "admin";
            $sucursal_admin = "N/A";

            $stmt = $conexion->prepare("INSERT IGNORE INTO usuarios (nombre, contrasena, tipo_usuario, sucursal)
                                        VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssss", $nombre_admin, $contrasena_admin, $tipo_usuario_admin, $sucursal_admin);
                if ($stmt->execute()) {
                    $mensajes[] = ($stmt->affected_rows > 0) ? "Usuario 'admin' creado." : "Usuario 'admin' ya existente.";
                } else {
                    $mensajes[] = "Error al crear usuario 'admin': " . $conexion->error;
                }
                $stmt->close();
            } else {
                $mensajes[] = "Error al preparar inserción de usuario 'admin'.";
            }

            $mensajes[] = "Instalación completada. Todos los pasos se realizaron correctamente.";
            break;

        case 'descargar_tabla':
            if (!$baseDatosExiste) break;
            $conexion->select_db(DB_NAME);

            // ✅ whitelist tabla
            $tablas = [];
            $r = $conexion->query("SHOW TABLES");
            if ($r) while ($f = $r->fetch_array()) $tablas[] = $f[0];

            $tabla = isset($_POST['tabla_descargar']) ? sanitizar($_POST['tabla_descargar']) : '';
            if ($tabla && in_array($tabla, $tablas, true)) {
                $tablaEsc = str_replace('`', '', $tabla);
                $resultado = $conexion->query("SELECT * FROM `{$tablaEsc}`");
                if ($resultado) {
                    $data = [];
                    while ($row = $resultado->fetch_assoc()) $data[] = $row;

                    header('Content-disposition: attachment; filename=' . $tablaEsc . '.json');
                    header('Content-type: application/json; charset=utf-8');
                    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit();
                } else {
                    $mensajes[] = "Error al obtener datos de la tabla: " . $conexion->error;
                }
            }
            break;

        case 'cargar_tabla':
            if (!$baseDatosExiste) break;
            $conexion->select_db(DB_NAME);

            // ✅ whitelist tabla
            $tablas = [];
            $r = $conexion->query("SHOW TABLES");
            if ($r) while ($f = $r->fetch_array()) $tablas[] = $f[0];

            $tabla = isset($_POST['tabla_cargar']) ? sanitizar($_POST['tabla_cargar']) : '';
            $json_data = isset($_FILES['json_file']['tmp_name']) ? file_get_contents($_FILES['json_file']['tmp_name']) : '';

            if ($tabla && in_array($tabla, $tablas, true) && $json_data) {
                $data = json_decode($json_data, true);

                if (is_array($data)) {
                    $nuevos = 0;
                    $duplicados = 0;

                    // ⚠️ Mantengo tu lógica de ON DUPLICATE KEY, pero sanitizando valores bien
                    foreach ($data as $row) {
                        if (!is_array($row) || empty($row)) continue;

                        $columns = array_keys($row);
                        $columnsSql = implode(", ", array_map(fn($c) => "`" . str_replace("`", "", $c) . "`", $columns));

                        $valuesSql = implode(", ", array_map(function ($value) use ($conexion) {
                            if ($value === null) return "NULL";
                            return "'" . $conexion->real_escape_string((string)$value) . "'";
                        }, array_values($row)));

                        $tablaEsc = str_replace('`', '', $tabla);
                        $sql = "INSERT INTO `{$tablaEsc}` ($columnsSql) VALUES ($valuesSql)
                                ON DUPLICATE KEY UPDATE id=id";

                        if ($conexion->query($sql)) {
                            if ($conexion->affected_rows == 1) $nuevos++;
                            else $duplicados++;
                        } else {
                            $mensajes[] = "Error al insertar datos en la tabla: " . $conexion->error;
                            break;
                        }
                    }

                    $mensajes[] = "Datos cargados en la tabla '{$tabla}' con éxito. Se insertaron {$nuevos} nuevos valores y se omitieron {$duplicados} valores duplicados.";
                } else {
                    $mensajes[] = "El archivo JSON no tiene el formato adecuado.";
                }
            }
            break;
    }

    $_SESSION['mensajes'] = $mensajes;
    header("Location: instalador.php");
    exit();
}

// Listar tablas existentes
$tablas = [];
if ($baseDatosExiste) {
    $conexion->select_db(DB_NAME);
    $resultado = $conexion->query("SHOW TABLES");
    if ($resultado) {
        while ($fila = $resultado->fetch_array()) $tablas[] = $fila[0];
    }
}

$conexion->close();

$mensajes = $_SESSION['mensajes'] ?? [];
unset($_SESSION['mensajes']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador y gestor de la base de datos</title>
    <link rel="icon" href="imagenes/icono.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="estilos/instalador.css">
</head>
<body class="bg-gray-100">
<header class="bg-gray-800 p-4 flex justify-between items-center text-white fixed top-0 w-full z-50">
    <div class="flex items-center">
        <a href="#" class="text-white mr-4">Corporación Carrousel S.A.</a>
    </div>
    <div class="text-center">
        <img src="imagenes/logo.png" alt="logo" class="h-12 mx-auto">
    </div>
    <div class="flex items-center">
        <a href="index.php" class="text-white ml-4">Inicio de sesión</a>
    </div>
</header>

<div class="container mx-auto mt-20 p-8 bg-white rounded-lg shadow-md">
    <h2 class="text-center text-2xl font-bold mb-4">Instalador y gestor de la base de datos</h2>

    <form method="post" class="mb-4">
        <div class="form-group">
            <input class="btn-primary" type="submit" name="accion" value="instalar">
            <input class="btn-error" type="submit" name="accion" value="borrar_base"
                   onclick="return confirm('¿Estás seguro de que quieres borrar toda la base de datos?')">
        </div>
    </form>

    <h3 class="text-xl font-semibold mb-2">Tablas existentes:</h3>
    <?php if (!$baseDatosExiste || empty($tablas)): ?>
        <p>No hay tablas en la base de datos o la base de datos no existe.</p>
    <?php else: ?>
        <form method="post" class="mb-4">
            <div class="form-group">
                <select class="form-select" name="tabla">
                    <?php foreach ($tablas as $tabla): ?>
                        <option value="<?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input class="btn-error" type="submit" name="accion" value="borrar_tabla"
                       onclick="return confirm('¿Estás seguro de que quieres borrar esta tabla?')">
            </div>
        </form>

        <form method="post" class="mb-4">
            <div class="form-group">
                <select class="form-select" name="tabla_descargar">
                    <option value="" disabled selected>Seleccione la tabla para descargar</option>
                    <?php foreach ($tablas as $tabla): ?>
                        <option value="<?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input class="btn-secondary" type="submit" name="accion" value="descargar_tabla">
            </div>
        </form>

        <form method="post" class="mb-4" enctype="multipart/form-data">
            <div class="form-group">
                <select class="form-select" name="tabla_cargar">
                    <option value="" disabled selected>Seleccione la tabla para cargar el Json con la información</option>
                    <?php foreach ($tablas as $tabla): ?>
                        <option value="<?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($tabla, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="json_file" accept=".json" required class="form-input">
                <input class="btn-secondary" type="submit" name="accion" value="cargar_tabla">
            </div>
        </form>
    <?php endif; ?>
</div>

<footer class="bg-gray-800 p-4 text-center text-white">
    <p>Corporación Carrousel S.A.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

<?php if (!empty($mensajes)): ?>
const mensajes = <?php echo json_encode($mensajes, JSON_UNESCAPED_UNICODE); ?>;
mensajes.forEach(mensaje => {
  let tipoMensaje = 'info';
  let titulo = 'Mensaje';

  if (mensaje.toLowerCase().includes('error')) { tipoMensaje='error'; titulo='Error'; }
  else if (mensaje.includes('creada') || mensaje.includes('creado') || mensaje.includes('Instalación completada')) { tipoMensaje='success'; titulo='Operación exitosa'; }
  else if (mensaje.includes('ya existente') || mensaje.includes('omitieron')) { tipoMensaje='warning'; titulo='Advertencia'; }

  Swal.fire({ icon: tipoMensaje, title: titulo, text: mensaje, confirmButtonText: 'Entendido', timer: 7000 });
});
<?php endif; ?>
</script>

</body>
</html>
