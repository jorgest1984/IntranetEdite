<?php
// includes/auth.php
require_once __DIR__ . '/config.php';

// Verificar si el usuario está logueado
global $moodle_bypass_auth;
if (empty($moodle_bypass_auth) && !isset($_SESSION['user_id'])) {
    // Si es una petición a un endpoint API, devolver JSON en lugar de redirección HTML
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'api_') !== false || strpos($uri, '/api/') !== false) {
        if (ob_get_level()) { ob_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Tu sesión ha expirado o no estás autenticado. Por favor, recarga la página o inicia sesión de nuevo.']);
        exit();
    }
    header("Location: index.php");
    exit();
}

// Variables de sesión disponibles:
// $_SESSION['user_id']
// $_SESSION['username']
// $_SESSION['nombre_completo']
// $_SESSION['rol_id']
// $_SESSION['rol_nombre']

// Función para comprobar permisos (RBAC - ISO 27001)
function has_permission($required_roles) {
    if (!isset($_SESSION['rol_id'])) return false;
    
    // Si $required_roles es un array
    if (is_array($required_roles)) {
        return in_array($_SESSION['rol_id'], $required_roles);
    }
    
    // Si es un solo rol
    return $_SESSION['rol_id'] == $required_roles;
}

// Roles ID (NUEVA ESTRUCTURA):
// 1 = Administrador (Acceso total)
// 2 = Coordinador
// 3 = Tutor
// 4 = Lectura (Legacy / Mantenimiento)
// 5 = Comercial
// 7 = Administrativo (Acceso contabilidad)
define('ROLE_ADMIN', 1);
define('ROLE_COORD', 2);
define('ROLE_TUTOR', 3);
define('ROLE_LECTURA', 4); // Legacy / Mantenimiento
define('ROLE_COMERCIAL', 5);
define('ROLE_JEFE_COMERCIAL', 6);
define('ROLE_ADMINISTRATIVO', 7); // Separado de Coordinador

// Alias para compatibilidad con código antiguo
define('ROLE_FORMADOR', 3);

function get_user_centro_filter($column_name = 'grupos.centro_id') {
    if (!empty($_SESSION['centro_id'])) {
        $cid = intval($_SESSION['centro_id']);
        return " {$column_name} = {$cid} ";
    }
    return " 1=1 ";
}

// Temporary migration for Jefe Comercial
if (!isset($_SESSION['jefe_comercial_migrated_3']) && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM roles WHERE id = 6");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO roles (id, nombre) VALUES (6, 'Jefe Comercial')");
        } else {
            $pdo->exec("UPDATE roles SET nombre = 'Jefe Comercial' WHERE id = 6");
        }
        $pdo->exec("UPDATE usuarios SET rol_id = 6 WHERE nombre LIKE '%Eva%' AND apellidos LIKE '%lvarez%'");
        $_SESSION['jefe_comercial_migrated_3'] = true;
    } catch (Exception $e) {
        // Ignore silently
    }
}

/**
 * Validates if enrolling a student in a group exceeds the maximum hours cap of the Plan.
 * 
 * @param PDO $pdo
 * @param int $alumno_id
 * @param int $grupo_id
 * @param int|null $current_matricula_id Optional, to exclude current matricula if updating
 * @return array ['allowed' => bool, 'message' => string, 'max_hours' => int, 'current_hours' => int, 'new_hours' => int, 'total_hours' => int]
 */
function validate_plan_hours_limit($pdo, $alumno_id, $grupo_id, $current_matricula_id = null) {
    if (!$alumno_id || !$grupo_id) {
        return ['allowed' => true];
    }

    $stmtG = $pdo->prepare("
        SELECT g.id as grupo_id, g.accion_id, af.plan_id,
               COALESCE(NULLIF(af.duracion, 0), af.horas_teoricas + af.horas_practicas, 0) as duracion_accion,
               p.nombre as plan_nombre, p.tope_horas_alumno
        FROM grupos g
        JOIN acciones_formativas af ON g.accion_id = af.id
        JOIN planes p ON af.plan_id = p.id
        WHERE g.id = ?
    ");
    $stmtG->execute([(int)$grupo_id]);
    $groupData = $stmtG->fetch(PDO::FETCH_ASSOC);

    if (!$groupData || empty($groupData['plan_id'])) {
        return ['allowed' => true];
    }

    $tope_horas = (int)($groupData['tope_horas_alumno'] ?? 0);
    if ($tope_horas <= 0) {
        return ['allowed' => true];
    }

    $duracion_nueva = (int)$groupData['duracion_accion'];
    $plan_id = (int)$groupData['plan_id'];

    $sqlSum = "
        SELECT SUM(COALESCE(NULLIF(af.duracion, 0), af.horas_teoricas + af.horas_practicas, 0)) as total_horas
        FROM matriculas m
        JOIN grupos g ON m.grupo_id = g.id
        JOIN acciones_formativas af ON g.accion_id = af.id
        WHERE m.alumno_id = :alumno_id
          AND af.plan_id = :plan_id
          AND (m.estado IS NULL OR UPPER(m.estado) NOT IN ('BAJA', 'CANCELADA', 'ANULADA'))
    ";
    $paramsSum = [
        'alumno_id' => (int)$alumno_id,
        'plan_id' => $plan_id
    ];
    if (!empty($current_matricula_id)) {
        $sqlSum .= " AND m.id != :current_matricula_id";
        $paramsSum['current_matricula_id'] = (int)$current_matricula_id;
    }

    $stmtSum = $pdo->prepare($sqlSum);
    $stmtSum->execute($paramsSum);
    $current_horas = (int)($stmtSum->fetchColumn() ?: 0);

    $total_final = $current_horas + $duracion_nueva;

    if ($total_final > $tope_horas) {
        $plan_nombre = $groupData['plan_nombre'];
        return [
            'allowed' => false,
            'message' => "No se puede matricular al alumno en el grupo: supera el límite de {$tope_horas} horas permitido para el plan '{$plan_nombre}'. (Horas acumuladas en el plan: {$current_horas}h, Horas de este grupo: {$duracion_nueva}h, Total: {$total_final}h / Máximo: {$tope_horas}h).",
            'max_hours' => $tope_horas,
            'current_hours' => $current_horas,
            'new_hours' => $duracion_nueva,
            'total_hours' => $total_final
        ];
    }

    return ['allowed' => true];
}
?>
