<?php
// api_documentos_cascade.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!has_permission([ROLE_ADMIN, ROLE_COORD, ROLE_LECTURA, ROLE_TUTOR, ROLE_ADMINISTRATIVO, ROLE_COMERCIAL])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_planes':
        $convocatoria_id = (int)($_GET['convocatoria_id'] ?? 0);
        if (!$convocatoria_id) {
            echo json_encode([]);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, nombre, codigo FROM planes WHERE convocatoria_id = ? ORDER BY nombre ASC");
        $stmt->execute([$convocatoria_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_acciones':
        $plan_id = (int)($_GET['plan_id'] ?? 0);
        if (!$plan_id) {
            echo json_encode([]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT af.id, af.num_accion, c.nombre_largo as titulo 
            FROM acciones_formativas af
            JOIN cursos c ON af.curso_id = c.id
            WHERE af.plan_id = ? 
            ORDER BY c.nombre_largo ASC
        ");
        $stmt->execute([$plan_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_alumnos':
        $accion_id = (int)($_GET['accion_id'] ?? 0);
        if (!$accion_id) {
            echo json_encode(['alumnos' => [], 'context' => null]);
            exit;
        }

        // Fetch alumnos matriculados en grupos de la acción formativa
        $stmtAlumnos = $pdo->prepare("
            SELECT DISTINCT a.id, a.nombre, a.primer_apellido, a.segundo_apellido, a.dni, a.email, a.telefono
            FROM matriculas m
            JOIN alumnos a ON m.alumno_id = a.id
            JOIN grupos g ON m.grupo_id = g.id
            WHERE g.accion_id = ? AND m.estado != 'Baja' AND m.estado != 'Cancelada'
            ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre ASC
        ");
        $stmtAlumnos->execute([$accion_id]);
        $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

        // Fetch convocatoria and action formativa info for context
        $stmtContext = $pdo->prepare("
            SELECT 
                co.id as conv_id, co.codigo_expediente as conv_codigo, co.nombre as conv_nombre,
                af.id as af_id, af.num_accion as af_num, cu.nombre_largo as af_titulo
            FROM acciones_formativas af
            JOIN planes pl ON af.plan_id = pl.id
            JOIN convocatorias co ON pl.convocatoria_id = co.id
            JOIN cursos cu ON af.curso_id = cu.id
            WHERE af.id = ?
            LIMIT 1
        ");
        $stmtContext->execute([$accion_id]);
        $context = $stmtContext->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'alumnos' => $alumnos,
            'context' => $context
        ]);
        break;

    case 'get_grupos':
        $accion_id = (int)($_GET['accion_id'] ?? 0);
        if (!$accion_id) {
            echo json_encode([]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT g.id, g.numero_grupo 
            FROM grupos g
            WHERE g.accion_id = ? 
            ORDER BY g.numero_grupo ASC
        ");
        $stmt->execute([$accion_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}
