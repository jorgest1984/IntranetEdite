<?php
// api_send_diplomas.php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/smtp_mailer.php';
require_once 'includes/PdfGenerator.php';

header('Content-Type: application/json');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_ADMINISTRATIVO])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$grupo_id = $input['grupo_id'] ?? 0;
$accion_id = $input['accion_id'] ?? 0;
$tipo = $input['tipo'] ?? ''; // 'diploma' o 'certificado'
$alumnos = $input['alumnos'] ?? [];

if (!$grupo_id || !$accion_id || !in_array($tipo, ['diploma', 'certificado']) || empty($alumnos)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

$enviados = 0;
$errores = 0;

foreach ($alumnos as $alumno_id) {
    try {
        // Obtener email del alumno de la tabla alumnos
        $stmtEmail = $pdo->prepare("SELECT email FROM alumnos WHERE id = ?");
        $stmtEmail->execute([$alumno_id]);
        $email_data = $stmtEmail->fetch(PDO::FETCH_ASSOC);

        $email = $email_data ? $email_data['email'] : null;

        if (empty($email)) {
            $errores++;
            continue; // No tiene email
        }

        // Obtener el DNI para nombrar el archivo
        $stmtDni = $pdo->prepare("SELECT dni FROM alumnos WHERE id = ?");
        $stmtDni->execute([$alumno_id]);
        $dni = $stmtDni->fetchColumn();

        // Generar PDF como string binario
        $pdf_content = PdfGenerator::generateDiplomaPdf($pdo, $alumno_id, $grupo_id, $accion_id, $tipo, 'S');

        if (!$pdf_content) {
            $errores++;
            continue;
        }

        $filename = strtoupper($tipo) . '_' . strtoupper($dni) . '.pdf';

        // Preparar email
        $asunto = $tipo === 'diploma' ? "Tu Diploma del Curso" : "Tu Certificado de Asistencia del Curso";
        $cuerpo = "Hola,\n\nAdjuntamos tu " . ($tipo === 'diploma' ? "diploma" : "certificado de asistencia") . " del curso recientemente finalizado.\n\nUn saludo,\nEquipo de Formación.";

        $attachment = [
            'name' => $filename,
            'content' => $pdf_content
        ];

        // Enviar
        $enviado = send_smtp_email($email, $asunto, $cuerpo, 'Intranet Grupo EFP', [$attachment]);

        if ($enviado) {
            $enviados++;
        } else {
            $errores++;
        }

    } catch (Exception $e) {
        error_log("Error enviando $tipo a alumno $alumno_id: " . $e->getMessage());
        $errores++;
    }
}

if ($enviados > 0) {
    echo json_encode([
        'success' => true,
        'message' => "Se han enviado $enviados correos correctamente." . ($errores > 0 ? " Hubo $errores errores (alumnos sin email válido)." : "")
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "No se pudo enviar ningún correo. Verifica que los alumnos tengan direcciones de email válidas."
    ]);
}
