<?php
echo "<pre>";

// ===============================
// URL DEL SCRIPT REAL
// ===============================
$url = "http://94.74.71.96/CajaChica/enviarCorreo.php";

// ===============================
// CREAR ARCHIVOS DE PRUEBA
// ===============================
$rutaExcel = __DIR__ . "/test.xlsx";
$rutaPDF   = __DIR__ . "/test.pdf";

// Crear archivos dummy si no existen
if (!file_exists($rutaExcel)) {
    file_put_contents($rutaExcel, "Excel prueba Caja Chica");
}

if (!file_exists($rutaPDF)) {
    file_put_contents($rutaPDF, "PDF prueba Caja Chica");
}

// ===============================
// DATOS POST (SIMULA TU SISTEMA)
// ===============================
$data = [
    'correo_destino' => 'appcarrousel@gmail.com',
    'correo_cc'      => '',
    'ruta_excel'     => $rutaExcel,
    'ruta_pdf'       => $rutaPDF,
    'lote'           => 'TEST-CAJA-001'
];

// ===============================
// DEBUG
// ===============================
echo "URL: $url\n";
echo "Excel: $rutaExcel\n";
echo "PDF: $rutaPDF\n\n";

// ===============================
// CURL
// ===============================
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if ($response === false) {
    echo "ERROR CURL:\n";
    echo curl_error($ch);
} else {
    echo "RESPUESTA DEL SISTEMA:\n\n";
    echo $response;
}

curl_close($ch);

echo "\n\n====================================\n";
echo "FIN DE PRUEBA CAJA CHICA\n";
echo "====================================\n";

echo "</pre>";