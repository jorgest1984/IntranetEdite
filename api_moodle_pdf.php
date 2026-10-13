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

if (!$token || !$moodle_user_id || !$tipo || !$ts) {
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

// Mapear moodle_user_id -> Intranet alumno_id de forma inteligente y resiliente
$alumno = null;
$is_direct_match = false;

// 1. Buscar por moodle_user_id exacto en la tabla alumnos
if ($moodle_user_id > 0) {
    $stmtUser = $pdo->prepare("SELECT id, moodle_user_id FROM alumnos WHERE moodle_user_id = ?");
    $stmtUser->execute([$moodle_user_id]);
    $alumno = $stmtUser->fetch();
    if ($alumno) $is_direct_match = true;
}

// 2. Buscar en las matrículas del curso de Moodle si coincide moodle_user_id o alumno_id
if (!$alumno && $moodle_course_id > 0) {
    $stmtUserMat = $pdo->prepare("
        SELECT a.id, a.moodle_user_id
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        WHERE (af.id_plataforma = ? OR g.id_plataforma = ? OR af.id = ? OR g.id = ?)
          AND (a.moodle_user_id = ? OR a.id = ? OR m.id = ?)
        LIMIT 1
    ");
    $stmtUserMat->execute([$moodle_course_id, $moodle_course_id, $moodle_course_id, $moodle_course_id, $moodle_user_id, $moodle_user_id, $moodle_user_id]);
    $alumno = $stmtUserMat->fetch();
    if ($alumno) $is_direct_match = true;
}

// 3. Buscar por id de la tabla alumnos
if (!$alumno && $moodle_user_id > 0) {
    $stmtUserDirect = $pdo->prepare("SELECT id, moodle_user_id FROM alumnos WHERE id = ?");
    $stmtUserDirect->execute([$moodle_user_id]);
    $alumno = $stmtUserDirect->fetch();
    if ($alumno) $is_direct_match = true;
}

// 4. Buscar en cualquier matrícula donde coincida moodle_user_id, id o matricula id
if (!$alumno && $moodle_user_id > 0) {
    $stmtUserAny = $pdo->prepare("
        SELECT a.id, a.moodle_user_id
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        WHERE a.moodle_user_id = ? OR a.id = ? OR m.id = ?
        ORDER BY m.id DESC LIMIT 1
    ");
    $stmtUserAny->execute([$moodle_user_id, $moodle_user_id, $moodle_user_id]);
    $alumno = $stmtUserAny->fetch();
    if ($alumno) $is_direct_match = true;
}

// 5. Fallback por curso de Moodle / Acción Formativa: si moodle_user_id no corresponde a un alumno matriculado (ej: es un tutor o admin), tomar el primer alumno matriculado en esa acción formativa/curso
if (!$alumno && $moodle_course_id > 0) {
    $stmtUserCourse = $pdo->prepare("
        SELECT a.id, a.moodle_user_id
        FROM matriculas m
        JOIN alumnos a ON m.alumno_id = a.id
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        LEFT JOIN cursos c ON af.curso_id = c.id
        WHERE (af.id_plataforma = ? OR g.id_plataforma = ? OR c.moodle_id = ? OR af.id = ? OR g.id = ?)
          AND (m.estado IS NULL OR (m.estado != 'Baja' AND m.estado != 'Cancelada'))
        ORDER BY m.id DESC LIMIT 1
    ");
    $stmtUserCourse->execute([$moodle_course_id, $moodle_course_id, $moodle_course_id, $moodle_course_id, $moodle_course_id]);
    $alumno = $stmtUserCourse->fetch();
}

if (!$alumno) {
    die("Alumno no encontrado en la Intranet. Asegúrate de que el alumno está sincronizado.");
}
$alumno_id = (int)$alumno['id'];

// Auto-corregir/Vincular moodle_user_id SOLO si fue un match directo del alumno
if ($is_direct_match && $moodle_user_id > 0 && ($alumno['moodle_user_id'] != $moodle_user_id)) {
    try {
        $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?")->execute([$moodle_user_id, $alumno_id]);
    } catch (Throwable $e) {}
}

// Mapear Moodle Course -> Intranet Accion Formativa
$accion = null;

if ($moodle_course_id > 0) {
    // 1. Buscar en acciones_formativas por id_plataforma
    $stmtCourse = $pdo->prepare("SELECT id FROM acciones_formativas WHERE id_plataforma = ?");
    $stmtCourse->execute([$moodle_course_id]);
    $accion = $stmtCourse->fetch();

    // 2. Buscar en grupos por id_plataforma o codigo_plat
    if (!$accion) {
        $stmtGrupo = $pdo->prepare("SELECT accion_id FROM grupos WHERE id_plataforma = ? OR codigo_plat = ? LIMIT 1");
        $stmtGrupo->execute([$moodle_course_id, (string)$moodle_course_id]);
        $grupo = $stmtGrupo->fetch();
        if ($grupo && !empty($grupo['accion_id'])) {
            $accion = ['id' => (int)$grupo['accion_id']];
        }
    }

    // 3. Buscar en cursos por moodle_id -> acciones_formativas
    if (!$accion) {
        $stmtCurso = $pdo->prepare("SELECT af.id FROM acciones_formativas af JOIN cursos c ON af.curso_id = c.id WHERE c.moodle_id = ? LIMIT 1");
        $stmtCurso->execute([$moodle_course_id]);
        $accion = $stmtCurso->fetch();
    }

    // 4. Buscar si el ID es directamente de acciones_formativas
    if (!$accion) {
        $stmtAFDirect = $pdo->prepare("SELECT id FROM acciones_formativas WHERE id = ?");
        $stmtAFDirect->execute([$moodle_course_id]);
        $accion = $stmtAFDirect->fetch();
    }
}

// 5. Fallback por matrículas del alumno si no se halló acción formativa por course_id
if (!$accion) {
    $stmtMatAF = $pdo->prepare("
        SELECT g.accion_id 
        FROM matriculas m 
        JOIN grupos g ON m.grupo_id = g.id 
        WHERE m.alumno_id = ? AND (m.estado IS NULL OR (m.estado != 'Baja' AND m.estado != 'Cancelada'))
        ORDER BY m.id DESC LIMIT 1
    ");
    $stmtMatAF->execute([$alumno_id]);
    $matAf = $stmtMatAF->fetch();
    if ($matAf && !empty($matAf['accion_id'])) {
        $accion = ['id' => (int)$matAf['accion_id']];
    }
}

// 6. Último recurso: cualquier matrícula previa del alumno
if (!$accion) {
    $stmtMatAFAny = $pdo->prepare("
        SELECT g.accion_id 
        FROM matriculas m 
        JOIN grupos g ON m.grupo_id = g.id 
        WHERE m.alumno_id = ? 
        ORDER BY m.id DESC LIMIT 1
    ");
    $stmtMatAFAny->execute([$alumno_id]);
    $matAfAny = $stmtMatAFAny->fetch();
    if ($matAfAny && !empty($matAfAny['accion_id'])) {
        $accion = ['id' => (int)$matAfAny['accion_id']];
    }
}

if (!$accion) {
    die("Acción formativa no encontrada en la Intranet para este curso de Moodle o alumno.");
}
$accion_id = (int)$accion['id'];

if ($tipo === 'recibi') {
    // Generar PDF usando FPDF directamente
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    require 'pdf_recibi_material.php';
    exit;

} elseif ($tipo === 'bienvenida') {
    // Generar PDF de bienvenida usando FPDF
    $_GET['accion_id'] = $accion_id;
    $_GET['alumno_id'] = $alumno_id;
    require 'pdf_hoja_bienvenida.php';
    exit;
} else {
    die("Tipo de documento no válido.");
}
