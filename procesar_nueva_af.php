<?php
// procesar_nueva_af.php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit();

$csrf_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    die("Error de seguridad: Token CSRF no válido o expirado.");
}

$titulo = $_POST['titulo'] ?? '';
$abreviatura = $_POST['abreviatura'] ?? '';
$num_accion = (isset($_POST['num_accion']) && is_numeric(trim($_POST['num_accion']))) ? (int)$_POST['num_accion'] : 0;
$plan_id = (!empty($_POST['plan_id']) && is_numeric(trim($_POST['plan_id']))) ? (int)$_POST['plan_id'] : null;
$modalidad = $_POST['modalidad'] ?? 'Teleformación';
$sede_id = (!empty($_POST['sede_id']) && is_numeric(trim($_POST['sede_id']))) ? (int)$_POST['sede_id'] : null;

// Si la modalidad es Teleformación y no se especificó sede_id, asignar por defecto Granada (ID 1)
if (($modalidad === 'Teleformación' || $modalidad === 'Teleformacion') && !$sede_id) {
    $stmtGra = $pdo->query("SELECT id FROM centros WHERE UPPER(nombre) LIKE '%GRANADA%' LIMIT 1");
    $graRow = $stmtGra->fetch();
    $sede_id = $graRow ? (int)$graRow['id'] : 1;
}

$duracion = (isset($_POST['duracion']) && is_numeric(trim($_POST['duracion']))) ? (int)$_POST['duracion'] : 0;
$familia = $_POST['familia_profesional'] ?? '';
$crear_moodle = isset($_POST['crear_moodle']);

try {
    $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN programa_formativo VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN expediente VARCHAR(100) DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN solicitante VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE acciones_formativas ADD COLUMN sector VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {}

// Obtener datos heredados del Plan si está asignado
$expediente = null;
$solicitante = null;
$sector = null;
if ($plan_id) {
    $stmtPlanData = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(p.expediente, ''), c.codigo_expediente) as plan_expediente,
            COALESCE(NULLIF(p.solicitante, ''), c.solicitante) as plan_solicitante,
            p.sector as plan_sector
        FROM planes p
        LEFT JOIN convocatorias c ON p.convocatoria_id = c.id
        WHERE p.id = ?
    ");
    $stmtPlanData->execute([$plan_id]);
    $pRow = $stmtPlanData->fetch();
    if ($pRow) {
        if (!empty($pRow['plan_expediente'])) $expediente = $pRow['plan_expediente'];
        if (!empty($pRow['plan_solicitante'])) $solicitante = $pRow['plan_solicitante'];
        if (!empty($pRow['plan_sector'])) $sector = $pRow['plan_sector'];
    }
}

$programa_path = null;
if (isset($_FILES['programa_formativo']) && $_FILES['programa_formativo']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/programas/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename($_FILES['programa_formativo']['name']));
    $target_file = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['programa_formativo']['tmp_name'], $target_file)) {
        $programa_path = $target_file;
    }
}

try {
    $pdo->beginTransaction();

    $id_plataforma = null;
    $curso_id = 0;

    // 1. Crear registro en la tabla 'cursos' (Necesario para consistencia con el resto de la app)
    $stmtCurso = $pdo->prepare("INSERT INTO cursos (nombre_largo, nombre_corto, visible, moodle_id) VALUES (?, ?, 1, NULL)");
    $stmtCurso->execute([$titulo, $abreviatura]);
    $curso_id = $pdo->lastInsertId();

    // 2. Crear en Moodle si se solicita
    $moodleError = null;
    if ($crear_moodle) {
        try {
            $moodle = new MoodleAPI($pdo);
            if ($moodle->isConfigured()) {
                $moodleResult = $moodle->createCourse($titulo, $abreviatura);
                if (!empty($moodleResult) && isset($moodleResult[0]['id'])) {
                    $id_plataforma = $moodleResult[0]['id'];
                    // Actualizar el curso con el ID de Moodle
                    $pdo->prepare("UPDATE cursos SET moodle_id = ? WHERE id = ?")->execute([$id_plataforma, $curso_id]);
                }
            }
        } catch (Exception $e) {
            // Capturamos el error de Moodle pero permitimos que la acción se cree localmente
            $moodleError = $e->getMessage();
        }
    }

    // 3. Insertar en DB local (acciones_formativas)
    $sql = "INSERT INTO acciones_formativas (
        titulo, abreviatura, num_accion, plan_id, modalidad, sede_id,
        duracion, familia_profesional, id_plataforma, curso_id, estado, programa_formativo, expediente, solicitante, sector
    ) VALUES (
        :titulo, :abreviatura, :num_accion, :plan_id, :modalidad, :sede_id,
        :duracion, :familia, :id_plataforma, :curso_id, 'Programable', :programa_formativo, :expediente, :solicitante, :sector
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'titulo' => $titulo,
        'abreviatura' => $abreviatura,
        'num_accion' => $num_accion,
        'plan_id' => $plan_id,
        'modalidad' => $modalidad,
        'sede_id' => $sede_id,
        'duracion' => $duracion,
        'familia' => $familia,
        'id_plataforma' => $id_plataforma,
        'curso_id' => $curso_id,
        'programa_formativo' => $programa_path,
        'expediente' => $expediente,
        'solicitante' => $solicitante,
        'sector' => $sector
    ]);

    $new_id = $pdo->lastInsertId();
    $pdo->commit();

    $redirectUrl = "ficha_accion_formativa.php?id=$new_id&created=1";
    if ($moodleError) {
        // Codificamos el error para mostrarlo como advertencia
        $redirectUrl .= "&moodle_error=" . urlencode($moodleError);
    }
    header("Location: $redirectUrl");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error crítico al crear la acción formativa: " . $e->getMessage());
}
?>
