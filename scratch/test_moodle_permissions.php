<?php
// scratch/test_moodle_permissions.php
require_once 'includes/config.php';
require_once 'includes/moodle_api.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $moodle = new MoodleAPI($pdo);
    if (!$moodle->isConfigured()) {
        echo "Moodle no está configurado en la base de datos o mediante OVERRIDES.\n";
        exit;
    }
    
    echo "==================================================\n";
    echo "DIAGNÓSTICO DE CONEXIÓN Y PERMISOS DE MOODLE\n";
    echo "==================================================\n\n";
    
    echo "Probando llamada de información básica (core_webservice_get_site_info)...\n";
    $info = $moodle->getSiteInfo();
    echo "✓ ¡Conexión establecida con éxito!\n\n";
    
    echo "Nombre del sitio: " . ($info['sitename'] ?? 'No disponible') . "\n";
    echo "URL de Moodle: " . ($info['siteurl'] ?? 'No disponible') . "\n";
    echo "Usuario Web Service: " . ($info['username'] ?? 'No disponible') . "\n";
    echo "Versión de Moodle: " . ($info['release'] ?? 'No disponible') . "\n\n";
    
    $functions = array_column($info['functions'] ?? [], 'name');
    
    $required = [
        'core_webservice_get_site_info',
        'core_course_create_courses',
        'core_course_create_categories',
        'core_course_get_categories',
        'core_course_get_courses',
        'core_group_create_groups',
        'core_group_add_group_members',
        'core_user_create_users',
        'core_user_get_users',
        'core_user_get_users_by_field',
        'core_user_update_users',
        'enrol_manual_enrol_users'
    ];
    
    echo "Verificación de funciones del Token:\n";
    echo "--------------------------------------------------\n";
    $missing = 0;
    foreach ($required as $req) {
        if (in_array($req, $functions)) {
            echo " [✓ OK]      $req\n";
        } else {
            echo " [✗ FALTANTE] $req\n";
            $missing++;
        }
    }
    echo "--------------------------------------------------\n";
    
    if ($missing > 0) {
        echo "\n⚠️ ATENCIÓN: Al token le faltan $missing funciones requeridas en Moodle.\n";
        echo "Debes entrar en la administración de Moodle y agregar estas funciones al servicio externo.\n";
    } else {
        echo "\n✓ ¡Perfecto! Todas las funciones requeridas están activadas para este token.\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
