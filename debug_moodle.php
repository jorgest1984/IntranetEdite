<?php
// debug_moodle.php
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN])) {
    die("Acceso denegado.");
}

$moodle = new MoodleAPI($pdo);
$info = null;
$error = null;

try {
    $info = $moodle->getSiteInfo();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Funciones requeridas para que la Intranet funcione al 100%
$required_functions = [
    'core_user_create_users' => 'Crear Alumnos',
    'core_user_get_users' => 'Buscar Alumnos',
    'core_course_get_courses' => 'Listar Cursos',
    'core_group_create_groups' => 'Crear Grupos / Convocatorias',
    'enrol_manual_enrol_users' => 'Matricular Alumnos',
    'core_group_add_group_members' => 'Asignar Alumnos a Grupos',
    'gradereport_user_get_grade_items' => 'Obtener Notas'
];

$allowed_functions = [];
if ($info && isset($info['functions'])) {
    foreach ($info['functions'] as $f) {
        $allowed_functions[] = $f['name'];
    }
}

// Probar obtención de cursos
$course_count = 0;
$raw_courses = [];
if ($info && in_array('core_course_get_courses', $allowed_functions)) {
    try {
        $raw_courses = $moodle->getCourses();
        $course_count = is_array($raw_courses) ? count($raw_courses) : 0;
    } catch (Exception $e) {
        $course_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <title>Diagnóstico Moodle - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .debug-card { background: white; border-radius: 12px; padding: 2rem; border: 1px solid var(--border-color); max-width: 800px; margin: 2rem auto; }
        .status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold; font-size: 0.8rem; }
        .status-ok { background: #d1fae5; color: #059669; }
        .status-fail { background: #fee2e2; color: #dc2626; }
        .func-list { list-style: none; padding: 0; margin-top: 1rem; }
        .func-item { display: flex; justify-content: space-between; padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body style="background: #f8fafc;">
    <div class="debug-card">
        <h1>🔍 Diagnóstico de Conexión Moodle</h1>
        <p>Esta herramienta verifica qué permisos tiene asignados tu Token en el aula virtual.</p>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 1rem; margin: 1rem 0; color: #dc2626;">
                <strong>Moodle rechazó el acceso:</strong><br>
                <?= htmlspecialchars($error) ?>
            </div>

            <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; color: #0369a1;">
                <h3 style="margin-top: 0; color: #0c4a6e;">🛠️ Guía de Solución: "Excepción al control de acceso"</h3>
                <p>Moodle reconoce tu conexión pero el <strong>Token</strong> no tiene permiso para hablar con la API. Sigue estos pasos exactos:</p>
                <ol style="font-size: 0.95rem; line-height: 1.6;">
                    <li><strong>Habilitar Protocolos:</strong> Ve a <strong>Administración del sitio > Servidor > Servicios web > Habilitar protocolos</strong> y asegúrate de que <strong>"Protocolo REST"</strong> tenga el ojo abierto (activado).</li>
                    <li><strong>Servicios Externos:</strong> En <strong>Servicios externos</strong>, busca tu servicio "Intranet" y asegúrate de que en <strong>Editar</strong> esté marcado como <strong>Habilitado</strong> y <strong>SÓLO usuarios autorizados</strong> (opcional, pero recomendado).</li>
                    <li><strong>Funciones:</strong> Dentro del servicio, haz clic en <strong>Funciones</strong> y añade: 
                        <code style="display:block; background:#e0f2fe; padding:5px; margin:5px 0; font-size:0.8rem;">core_webservice_get_site_info, core_course_get_courses, core_user_create_users, core_user_get_users, core_group_create_groups, enrol_manual_enrol_users</code>
                    </li>
                    <li><strong>Usuarios Autorizados:</strong> Si marcaste "Sólo usuarios autorizados", haz clic en <strong>Usuarios autorizados</strong> y añade al usuario que usas para el Token (ej: tu cuenta de admin).</li>
                    <li><strong>Paso Crítico - IP:</strong> Ve a <strong>Gestionar tokens</strong>, busca tu token y haz clic en <strong>Editar</strong>. El campo <strong>"Restricción de IP" debe estar VACÍO</strong>. Si hay una IP ahí, bórrala.</li>
                    <li><strong>CREA UN TOKEN NUEVO:</strong> A veces Moodle no actualiza el token viejo. Borra el token actual en Moodle, crea uno <strong>NUEVO</strong> vinculado al servicio "Intranet" y pégalo de nuevo en la Configuración de la Intranet.</li>
                </ol>
                <p style="font-size: 0.85rem; margin-top: 1rem; font-weight: bold; color: #dc2626;">⚠️ Importante: Si has seguido todo y sigue fallando, borra el servicio y el token en Moodle y créalos desde cero siguiendo esta lista. Toma solo 2 minutos.</p>
            </div>
        <?php elseif ($info): ?>
            <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 1rem; margin: 1rem 0;">
                <p><strong>✅ Conexión Establecida</strong></p>
                <p>Sitio: <?= htmlspecialchars($info['sitename']) ?><br>
                Usuario del Token: <?= htmlspecialchars($info['username']) ?> (ID: <?= $info['userid'] ?>)</p>
                <hr style="margin: 0.5rem 0; opacity: 0.2;">
                <p><strong>Cursos detectados por API:</strong> <?= $course_count ?> 
                    <?php if ($course_count <= 1): ?>
                        <br><span style="color: #9a3412; font-size: 0.85rem;">⚠️ Si tienes cursos creados y solo aparece 0 o 1 (ID site), es posible que tu Token no tenga permisos sobre las categorías de los cursos o que no seas Admin en el sistema.</span>
                    <?php endif; ?>
                </p>
            </div>

            <h3>Verificación de Funciones (Capabilities)</h3>
            <ul class="func-list">
                <?php foreach ($required_functions as $func => $desc): 
                    $isPower = in_array($func, $allowed_functions);
                ?>
                    <li class="func-item">
                        <div>
                            <strong><?= $desc ?></strong><br>
                            <code style="font-size: 0.8rem; color: #64748b;"><?= $func ?></code>
                        </div>
                        <div>
                            <?php if ($isPower): ?>
                                <span class="status-badge status-ok">HABILITADA</span>
                            <?php else: ?>
                                <span class="status-badge status-fail">FALTA PERMISO</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (count($allowed_functions) < count($required_functions)): ?>
                <div style="margin-top: 2rem; padding: 1rem; background: #fff7ed; border: 1px solid #fdba74; border-radius: 8px;">
                    <h4 style="margin-top: 0; color: #9a3412;">¿Cómo arreglar los permisos "FALTA PERMISO"?</h4>
                    <ol style="font-size: 0.9rem; color: #9a3412;">
                        <li>En Moodle, ve a <strong>Administración del sitio > Servidor > Servicios web > Servicios externos</strong>.</li>
                        <li>Busca el servicio asociado a tu Token (ej: "Intranet Service") y haz clic en <strong>Funciones</strong> (o 'Functions').</li>
                        <li>Haz clic en <strong>Añadir funciones</strong> y busca las que aparecen en rojo arriba.</li>
                        <li>Agrégalas todas y guarda. ¡Listo! Vuelve a esta página y refresca.</li>
                    </ol>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="dashboard.php" class="btn">Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>
