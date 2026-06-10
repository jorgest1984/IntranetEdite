<?php
// api/test_moodle_permissions.php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/moodle_api.php';

// Validar accesos de administrador
if (!has_permission([ROLE_ADMIN])) {
    die("Acceso denegado. Se requiere rol de Administrador.");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Permisos de Moodle</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b; padding: 2rem; }
        .card { background: white; border-radius: 12px; max-width: 700px; margin: 0 auto; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h1 { color: #b91c1c; font-size: 1.5rem; margin-top: 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .info-box { background: #f8fafc; padding: 12px 15px; border-radius: 8px; border-left: 4px solid #1e3a8a; margin-bottom: 20px; font-size: 0.9rem; }
        .info-box p { margin: 5px 0; }
        .status-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9rem; }
        .status-table th, .status-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
        .status-table th { background: #f8fafc; color: #1e40af; font-weight: 700; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
        .badge-ok { background: #dcfce7; color: #15803d; }
        .badge-missing { background: #fee2e2; color: #b91c1c; }
        .warning-box { background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #d97706; padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 0.9rem; color: #92400e; }
        .success-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-left: 4px solid #059669; padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 0.9rem; color: #065f46; }
    </style>
</head>
<body>

<div class="card">
    <h1>Diagnóstico de Permisos y Token de Moodle</h1>
    
    <?php
    try {
        $moodle = new MoodleAPI($pdo);
        if (!$moodle->isConfigured()) {
            echo "<div class='warning-box'>Moodle no está configurado en la base de datos (clave 'moodle_url' o 'moodle_token' vacías).</div>";
            exit;
        }
        
        echo "<div class='info-box'>";
        echo "<p><strong>Probando conexión con el Aula Virtual...</strong></p>";
        $info = $moodle->getSiteInfo();
        echo "<p style='color: #059669; font-weight: 700;'>✓ Conexión establecida con éxito.</p>";
        echo "</div>";
        
        echo "<h3>Datos del Aula Virtual:</h3>";
        echo "<ul>";
        echo "<li><strong>Sitio:</strong> " . htmlspecialchars($info['sitename'] ?? 'N/A') . "</li>";
        echo "<li><strong>URL:</strong> " . htmlspecialchars($info['siteurl'] ?? 'N/A') . "</li>";
        echo "<li><strong>Usuario de Web Service:</strong> " . htmlspecialchars($info['username'] ?? 'N/A') . "</li>";
        echo "<li><strong>Versión de Moodle:</strong> " . htmlspecialchars($info['release'] ?? 'N/A') . "</li>";
        echo "</ul>";
        
        $functions = array_column($info['functions'] ?? [], 'name');
        
        $required = [
            'core_webservice_get_site_info' => 'Obtención de datos básicos de conexión',
            'core_course_create_courses' => 'Creación automática de cursos desde la intranet',
            'core_course_create_categories' => 'Creación automática de categorías de convocatorias',
            'core_course_get_categories' => 'Búsqueda de categorías por nombre',
            'core_course_get_courses' => 'Búsqueda e inspección de cursos',
            'core_group_create_groups' => 'Creación de grupos para los alumnos',
            'core_group_add_group_members' => 'Asociación de alumnos a sus grupos correspondientes',
            'core_user_create_users' => 'Creación de cuentas para alumnos nuevos',
            'core_user_get_users' => 'Búsqueda y consulta de cuentas de usuario',
            'core_user_get_users_by_field' => 'Búsqueda de cuentas por email/DNI',
            'core_user_update_users' => 'Actualización de datos personales del alumno',
            'enrol_manual_enrol_users' => 'Matriculación oficial del alumno en el curso',
            'core_enrol_get_enrolled_users' => 'Verificar matriculados en cursos'
        ];
        
        echo "<h3>Comprobación de Funciones Autorizadas:</h3>";
        echo "<table class='status-table'>";
        echo "<thead><tr><th>Función Moodle</th><th>Propósito</th><th>Estado</th></tr></thead>";
        echo "<tbody>";
        
        $missing = 0;
        foreach ($required as $req => $desc) {
            $has = in_array($req, $functions);
            echo "<tr>";
            echo "<td><code>$req</code></td>";
            echo "<td>$desc</td>";
            echo "<td>";
            if ($has) {
                echo "<span class='badge badge-ok'>✓ Activa</span>";
            } else {
                echo "<span class='badge badge-missing'>✗ Faltante</span>";
                $missing++;
            }
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        
        if ($missing > 0) {
            echo "<div class='warning-box'>";
            echo "<strong>Faltan $missing funciones en la configuración de Moodle.</strong><br><br>";
            echo "Para solucionarlo, un administrador de Moodle debe hacer lo siguiente:<br>";
            echo "<ol>";
            echo "<li>Acceder al Aula Virtual como Administrador.</li>";
            echo "<li>Ir a <strong>Administración del sitio > Servidores > Servicios externos</strong>.</li>";
            echo "<li>Editar el Servicio Externo asignado al token.</li>";
            echo "<li>Hacer clic en <strong>Funciones</strong> y añadir las funciones marcadas como <strong>Faltantes</strong> en la lista superior.</li>";
            echo "</ol>";
            echo "</div>";
        } else {
            echo "<div class='success-box'>";
            echo "<strong>✓ ¡Todo perfecto!</strong> El token de Moodle cuenta con todos los permisos necesarios para automatizar la creación de cursos, categorías y matriculación.";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='badge badge-missing' style='padding: 15px; font-size: 1rem; width: 100%; box-sizing: border-box;'>";
        echo "<strong>Error de conexión con Moodle:</strong><br>" . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    ?>
</div>

</body>
</html>
