<?php
// api_moodle_pdf.php
// Este archivo recibe peticiones firmadas desde Moodle para descargar PDFs
require_once 'includes/config.php';

global $moodle_bypass_auth;
$moodle_bypass_auth = true;

$secret = "EfpMoodleSecret2026!#";

$token = $_GET['token'] ?? '';
$moodle_course_id = (int)($_GET['courseid'] ?? 0);
$moodle_user_id = (int)($_GET['userid'] ?? 0);
$tipo = $_GET['tipo'] ?? '';
$ts = (int)($_GET['ts'] ?? 0);

if (!$token || !$moodle_course_id || !$moodle_user_id || !$tipo || !$ts) {
    die("Faltan parámetros.");
}

// Validar timestamp (validez de 5 minutos)
if (abs(time() - $ts) > 300) {
    die("El enlace ha caducado. Vuelve a intentarlo desde Moodle.");
}

// Validar token
$expected_token = hash_hmac('sha256', $moodle_course_id . '|' . $moodle_user_id . '|' . $tipo . '|' . $ts, $secret);
if (!hash_equals($expected_token, $token)) {
    die("Acceso denegado. Token inválido o firma incorrecta.");
}

// Mapear moodle_user_id -> Intranet alumno_id
$stmtUser = $pdo->prepare("SELECT id FROM alumnos WHERE moodle_user_id = ?");
$stmtUser->execute([$moodle_user_id]);
$alumno = $stmtUser->fetch();

if (!$alumno) {
    die("Alumno no encontrado en la Intranet. Asegúrate de que el alumno está sincronizado.");
}
$alumno_id = $alumno['id'];

// Mapear Moodle Course -> Intranet Accion Formativa
$stmtCourse = $pdo->prepare("SELECT id FROM acciones_formativas WHERE id_plataforma = ?");
$stmtCourse->execute([$moodle_course_id]);
$accion = $stmtCourse->fetch();

if (!$accion) {
    die("Acción formativa no encontrada en la Intranet para este curso de Moodle.");
}
$accion_id = $accion['id'];

if ($tipo === 'recibi') {
    // Generar PDF usando FPDF directamente
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    require 'pdf_recibi_material.php';
    exit;

} elseif ($tipo === 'bienvenida') {
    // Capturamos el HTML del anexo directamente en el servidor
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    
    ob_start();
    require 'api_anexo1_html.php';
    $htmlContent = ob_get_clean();
    
    // Escapar el HTML para poder meterlo en Javascript de forma segura
    $htmlContentEscaped = json_encode($htmlContent);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Descargando Hoja de Bienvenida...</title>
        <script src="js/html2pdf.bundle.min.js"></script>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background-color: #f8fafc; }
            .loader { border: 4px solid #e2e8f0; border-top: 4px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            #content { position: absolute; top: -9999px; left: -9999px; }
        </style>
    </head>
    <body>
        <h2>Generando Hoja de Bienvenida...</h2>
        <div class="loader"></div>
        <p>Tu descarga comenzará en unos segundos. Por favor, no cierres esta ventana.</p>
        
        <div id="content"></div>

        <script>
            setTimeout(() => {
                try {
                    const htmlStr = <?= $htmlContentEscaped ?>;
                    
                    if(htmlStr.includes('SQL ERROR') || htmlStr.includes('Acceso denegado')) {
                        document.body.innerHTML = '<h2>Error al generar el documento.</h2>';
                        return;
                    }
                    
                    document.getElementById('content').innerHTML = htmlStr;
                    
                    const opt = {
                        margin: 0,
                        filename: `Hoja_Bienvenida_<?= $alumno_id ?>.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true, logging: true },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    
                    html2pdf().set(opt).from(htmlStr).save().then(() => {
                        document.body.innerHTML = '<h2>Descarga completada</h2><p>Ya puedes cerrar esta ventana.</p>';
                    }).catch(err => {
                        document.body.innerHTML = '<h2>Error al generar el PDF.</h2><p>' + err + '</p>';
                    });
                } catch(e) {
                    document.body.innerHTML = '<h2>Error general en Javascript.</h2><p>' + e + '</p>';
                }
            }, 1500); // Dar tiempo a que carguen las imagenes del DOM insertado
        </script>
    </body>
    </html>
    <?php
    exit;

} else {
    die("Tipo de documento no válido.");
}
