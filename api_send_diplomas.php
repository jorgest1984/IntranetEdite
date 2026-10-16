<?php
// api_send_diplomas.php - API unificada para envío de Diplomas y Certificados por Email
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/smtp_mailer.php';
require_once 'includes/PdfGenerator.php';

header('Content-Type: application/json; charset=utf-8');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_ADMINISTRATIVO, ROLE_TUTOR, ROLE_FORMADOR, ROLE_COMERCIAL, ROLE_JEFE_COMERCIAL, ROLE_PRACTICAS])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Permisos insuficientes.']);
    exit;
}

// Leer datos de entrada (JSON o POST)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$matricula_id = intval($input['matricula_id'] ?? 0);
$grupo_id = intval($input['grupo_id'] ?? 0);
$accion_id = intval($input['accion_id'] ?? 0);
$tipo = strtolower(trim($input['tipo'] ?? 'diploma')); // 'diploma' o 'certificado'
$alumnos_input = $input['alumnos'] ?? [];

// Si se pasa matricula_id individual
if ($matricula_id && empty($alumnos_input)) {
    $alumnos_input = [$matricula_id];
}

if (!in_array($tipo, ['diploma', 'certificado', 'aprovechamiento', 'asistencia'])) {
    $tipo = 'diploma';
}
// Normalizar tipo
$tipo_pdf = in_array($tipo, ['diploma', 'aprovechamiento']) ? 'diploma' : 'certificado';
$diploma_tipo_str = ($tipo_pdf === 'diploma') ? 'Aprovechamiento' : 'Asistencia';

if (empty($alumnos_input)) {
    echo json_encode(['success' => false, 'message' => 'Debe indicar al menos un alumno o matrícula para realizar el envío.']);
    exit;
}

$enviados = 0;
$sin_email = 0;
$errores = 0;

foreach ($alumnos_input as $target_id) {
    $target_id = intval($target_id);
    if (!$target_id) continue;

    try {
        // Intentar resolver datos buscando por matricula_id primero, o por alumno_id
        $stmtMat = $pdo->prepare("
            SELECT m.id as matricula_id, m.alumno_id, m.grupo_id,
                   a.nombre, a.primer_apellido, a.segundo_apellido, a.email, a.dni,
                   g.accion_id, g.numero_grupo,
                   c.nombre_largo as curso_titulo
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            LEFT JOIN grupos g ON m.grupo_id = g.id
            LEFT JOIN acciones_formativas af ON g.accion_id = af.id
            LEFT JOIN cursos c ON af.curso_id = c.id
            WHERE m.id = ? OR (m.alumno_id = ? AND (? = 0 OR m.grupo_id = ?))
            ORDER BY m.id DESC
            LIMIT 1
        ");
        $stmtMat->execute([$target_id, $target_id, $grupo_id, $grupo_id]);
        $row = $stmtMat->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $errores++;
            continue;
        }

        $real_alumno_id = (int)$row['alumno_id'];
        $real_grupo_id = (int)($grupo_id ?: $row['grupo_id']);
        $real_accion_id = (int)($accion_id ?: $row['accion_id']);
        $real_matricula_id = (int)$row['matricula_id'];

        $email = trim($row['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $sin_email++;
            continue;
        }

        $nombre_alumno = trim($row['nombre'] . ' ' . $row['primer_apellido']);
        $curso_titulo = !empty($row['curso_titulo']) ? $row['curso_titulo'] : 'Formación Grupo EFP';
        $dni_clean = !empty($row['dni']) ? strtoupper(trim(preg_replace('/[^a-zA-Z0-9]/', '', $row['dni']))) : 'ALUMNO';

        // Generar el PDF correspondiente
        $pdf_content = PdfGenerator::generateDiplomaPdf($pdo, $real_alumno_id, $real_grupo_id, $real_accion_id, $tipo_pdf, 'S');

        if (!$pdf_content) {
            $errores++;
            continue;
        }

        $doc_title = ($tipo_pdf === 'diploma') ? 'Diploma de Aprovechamiento' : 'Certificado de Asistencia';
        $filename = ($tipo_pdf === 'diploma' ? 'DIPLOMA_' : 'CERTIFICADO_') . $dni_clean . '.pdf';

        $asunto = "Tu $doc_title - $curso_titulo";
        $cuerpo = "Hola $nombre_alumno,\n\n"
                . "Nos complace adjuntarte tu $doc_title del curso '$curso_titulo'.\n\n"
                . ($tipo_pdf === 'diploma' 
                    ? "¡Enhorabuena por haber superado con éxito la formación y completado los objetivos del curso!\n\n" 
                    : "Gracias por tu participación y asistencia a nuestra formación.\n\n")
                . "Un cordial saludo,\n"
                . "Equipo de Formación Grupo EFP.";

        $attachment = [
            'name' => $filename,
            'content' => $pdf_content
        ];

        $enviado = send_smtp_email($email, $asunto, $cuerpo, 'Intranet Grupo EFP', [$attachment], true);

        if ($enviado) {
            $enviados++;
            // Actualizar estado en la matrícula (con try-catch independiente para evitar romper la respuesta si falla el UPDATE)
            try {
                $pdo->prepare("
                    UPDATE matriculas 
                    SET diploma_entregado = 1, 
                        diploma_tipo = ?, 
                        fecha_comunicacion = COALESCE(fecha_comunicacion, CURDATE()) 
                    WHERE id = ?
                ")->execute([$diploma_tipo_str, $real_matricula_id]);
            } catch (Exception $exUpdate) {
                error_log("Error al actualizar estado diploma en matricula $real_matricula_id: " . $exUpdate->getMessage());
            }
        } else {
            $errores++;
        }

    } catch (Exception $e) {
        error_log("Error al enviar $tipo_pdf a target $target_id: " . $e->getMessage());
        $errores++;
    }
}

if ($enviados > 0) {
    $doc_label = ($tipo_pdf === 'diploma') ? 'Diploma' : 'Certificado';
    $msg = ($enviados === 1) 
        ? "Se ha enviado correctamente el $doc_label por correo electrónico."
        : "Se han enviado correctamente $enviados {$doc_label}s por correo electrónico.";

    $details = [];
    if ($sin_email > 0) {
        $details[] = "$sin_email no pudieron enviarse por falta de email";
    }
    if ($errores > 0) {
        $details[] = "$errores con error de envío";
    }

    if (!empty($details)) {
        $msg .= " (" . implode(', ', $details) . ").";
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'enviados' => $enviados,
        'sin_email' => $sin_email,
        'errores' => $errores,
        'fecha_comunicacion' => date('d/m/Y')
    ]);
} else {
    $error_msg = "No se pudo enviar el correo electrónico.";
    if ($sin_email > 0 && $errores === 0) {
        $error_msg .= " El alumno no tiene una dirección de email válida en su ficha.";
    } else {
        $error_msg .= " Ocurrió un problema técnico al generar o enviar el documento.";
    }
    echo json_encode([
        'success' => false,
        'message' => $error_msg,
        'sin_email' => $sin_email,
        'errores' => $errores
    ]);
}
