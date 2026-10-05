<?php
// nombre archivo: enviarCorreo.php
// Cargar el autoloader de Composer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header('Content-Type: application/json; charset=utf-8');

// helper JSON (no choca si existe en utilidades.php)
if (!function_exists('responder_json')) {
    function responder_json(array $payload, int $httpCode = 200): void {
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Verificar POST mínimo
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['correo_destino'])
    && isset($_POST['ruta_excel'])
    && isset($_POST['ruta_pdf'])
) {
    $correoDestino = trim((string)$_POST['correo_destino']);
    $correoCC = isset($_POST['correo_cc']) ? trim((string)$_POST['correo_cc']) : '';
    $rutaExcel = (string)$_POST['ruta_excel'];
    $rutaPDF = (string)$_POST['ruta_pdf'];
    $lote = isset($_POST['lote']) ? (string)$_POST['lote'] : '';

    // Validaciones mínimas (no cambian tu lógica)
    if ($correoDestino === '' || $rutaExcel === '' || $rutaPDF === '') {
        responder_json(['status' => 'error', 'message' => 'Datos incompletos.'], 400);
    }
    if (!filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
        responder_json(['status' => 'error', 'message' => 'Correo destino inválido.'], 400);
    }
    if ($correoCC !== '' && !filter_var($correoCC, FILTER_VALIDATE_EMAIL)) {
        responder_json(['status' => 'error', 'message' => 'Correo CC inválido.'], 400);
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP (IGUAL que tu backup)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'appcarrousel@gmail.com';
        require_once __DIR__ . '/secrets.local.php';
        $mail->Password = defined('SMTP_APP_PASSWORD') ? SMTP_APP_PASSWORD : ''; // ver secrets.local.php
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configuración del correo (IGUAL)
        $mail->setFrom('appcarrousel@gmail.com', 'Sistema CajaChica');
        $mail->addAddress($correoDestino);

        if (!empty($correoCC)) {
            $mail->addCC($correoCC);
        }

        // BCC (IGUAL)
        $mail->addBCC('luis@carrousel.com.gt');
        $mail->addBCC('facturacion@carrousel.com.gt');

        

        // Adjuntos (IGUAL, con validación)
        if (!file_exists($rutaExcel)) {
            throw new Exception("El archivo Excel no fue encontrado.");
        }
        if (!file_exists($rutaPDF)) {
            throw new Exception("El archivo PDF no fue encontrado.");
        }

        $mail->addAttachment($rutaExcel);
        $mail->addAttachment($rutaPDF);

        // Datos dinámicos (IGUAL)
        $fechaActual = date('d-m-Y');
        $nombreUsuario = $_SESSION['usuario'] ?? 'usuario';

        // Contenido del correo (IGUAL)
        $mail->isHTML(true);
        $mail->Subject = "Adjuntos Caja Chica - {$nombreUsuario} {$fechaActual}";
        $mail->Body = "
<div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border: 1px solid #e1e1e1; border-radius: 8px;\">
    <div style=\"text-align: center; margin-bottom: 24px;\">
        <h2 style=\"color: #333; font-size: 24px; margin-bottom: 16px;\">Documentos de Caja Chica</h2>
        <p style=\"color: #555; font-size: 16px;\">Estimado(a),</p>
        <p style=\"color: #555; font-size: 16px;\">Se adjuntan los documentos correspondientes al lote <br> <strong>{$lote}</strong> para su revisión.</p>
    </div>
  
    <p style=\"color: #555; font-size: 16px;\">Por favor, conserve estos archivos para sus registros.</p>
    <p style=\"color: #333; font-size: 16px; font-weight: bold;\">Sistema de Caja Chica</p>
    <p style=\"color: #888; font-size: 14px; text-align: center;\"><strong>Nota:
     Este es un mensaje generado automáticamente. No responda a este correo.</strong></p>
</div>
";

        // Enviar correo
        $mail->send();
        responder_json(['status' => 'success', 'message' => 'Correo enviado exitosamente.']);
    } catch (Exception $e) {
        responder_json(['status' => 'error', 'message' => 'Error al enviar el correo: ' . $e->getMessage()], 500);
    }
} else {
    responder_json(
        ['status' => 'error', 'message' => 'Datos incompletos. Asegúrate de que los archivos y correos estén correctamente proporcionados.'],
        400
    );
}
?>
