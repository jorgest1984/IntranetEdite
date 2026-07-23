<?php
// includes/auth.php
require_once __DIR__ . '/config.php';

// Verificar si el usuario está logueado
global $moodle_bypass_auth;
if (empty($moodle_bypass_auth) && !isset($_SESSION['user_id'])) {
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
if (!isset($_SESSION['jefe_comercial_migrated']) && isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM roles WHERE id = 6");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO roles (id, nombre) VALUES (6, 'Jefe Comercial')");
        }
        $pdo->exec("UPDATE usuarios SET rol_id = 6 WHERE nombre LIKE '%Eva%' AND apellidos LIKE '%lvarez%'");
        $_SESSION['jefe_comercial_migrated'] = true;
    } catch (Exception $e) {
        // Ignore silently
    }
}
?>
