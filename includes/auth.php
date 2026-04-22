<?php
// includes/auth.php
require_once __DIR__ . '/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
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
// 2 = Administrativo (Solo Económico)
// 3 = Tutor (Solo Académico)
define('ROLE_ADMIN', 1);
define('ROLE_ADMINISTRATIVO', 2);
define('ROLE_TUTOR', 3);
define('ROLE_LECTURA', 4); // Legacy / Mantenimiento
?>
