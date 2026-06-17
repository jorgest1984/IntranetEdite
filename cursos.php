<?php
// cursos.php
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

// Check permits
if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$moodle = new MoodleAPI($pdo);
$error = '';
$success = '';
$cursos = [];
$isConfigured = $moodle->isConfigured();

// Intentar cargar cursos locales
$stmtLocal = $pdo->query("SELECT * FROM cursos ORDER BY id DESC");
$cursosLocales = $stmtLocal->fetchAll();

// Acción: Sincronizar Cursos desde Moodle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'sync_courses') {
    try {
        $moodleCourses = $moodle->getCourses();
        if (is_array($moodleCourses)) {
            $pdo->beginTransaction();
            foreach ($moodleCourses as $c) {
                if ($c['id'] == 1) continue; // Saltar sitio principal
                
                $stmtSync = $pdo->prepare("INSERT INTO cursos (moodle_id, nombre_corto, nombre_largo, visible) 
                                          VALUES (?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE 
                                          nombre_corto = VALUES(nombre_corto), 
                                          nombre_largo = VALUES(nombre_largo), 
                                          visible = VALUES(visible)");
                $stmtSync->execute([
                    $c['id'],
                    $c['shortname'],
                    $c['fullname'],
                    $c['visible'] ?? 1
                ]);
            }
            $pdo->commit();
            audit_log($pdo, 'COURSES_SYNCED', 'cursos', null, null, ['count' => count($moodleCourses)]);
            $success = "Sincronización completada. Se han actualizado/creado los cursos en la base de datos local.";
            // Recargar lista local
            $stmtLocal = $pdo->query("SELECT * FROM cursos ORDER BY id DESC");
            $cursosLocales = $stmtLocal->fetchAll();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error en la sincronización: " . $e->getMessage();
    }
}

// Acción: Crear y Vincular Curso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_course') {
    $nombreCorto = trim($_POST['nombre_corto'] ?? '');
    $nombreLargo = trim($_POST['nombre_largo'] ?? '');
    $createInMoodle = isset($_POST['create_in_moodle']) && $_POST['create_in_moodle'] == '1';
    $moodleId = intval($_POST['moodle_id'] ?? 0);
    
    try {
        if (empty($nombreCorto) || empty($nombreLargo)) {
            throw new Exception("Los nombres corto y largo son campos obligatorios.");
        }
        
        if ($createInMoodle) {
            // Crear en Moodle vía API
            if (!$isConfigured) {
                throw new Exception("La API de Moodle no está configurada. No se puede crear en el aula virtual.");
            }
            $apiRes = $moodle->createCourse($nombreLargo, $nombreCorto);
            if (is_array($apiRes) && isset($apiRes[0]['id'])) {
                $moodleId = intval($apiRes[0]['id']);
            } else {
                throw new Exception("La API de Moodle no devolvió un ID de curso válido.");
            }
        } else {
            // Vincular curso existente
            if ($moodleId <= 0) {
                throw new Exception("Debes proporcionar un ID de Moodle válido para la vinculación.");
            }
            // Verificar si ya existe en la intranet
            $stmtCheck = $pdo->prepare("SELECT id, nombre_corto FROM cursos WHERE moodle_id = ?");
            $stmtCheck->execute([$moodleId]);
            $existing = $stmtCheck->fetch();
            if ($existing) {
                throw new Exception("El ID de Moodle ($moodleId) ya está vinculado al curso '" . $existing['nombre_corto'] . "'.");
            }
        }
        
        // Insertar en base de datos local
        $stmtInsert = $pdo->prepare("INSERT INTO cursos (moodle_id, nombre_corto, nombre_largo, visible) VALUES (?, ?, ?, 1)");
        $stmtInsert->execute([$moodleId, $nombreCorto, $nombreLargo]);
        $localId = $pdo->lastInsertId();
        
        audit_log($pdo, 'COURSE_CREATED', 'cursos', $localId, null, [
            'moodle_id' => $moodleId,
            'nombre_corto' => $nombreCorto,
            'nombre_largo' => $nombreLargo,
            'creado_en_moodle' => $createInMoodle
        ]);
        
        $success = $createInMoodle 
            ? "Curso '$nombreCorto' creado correctamente en la Intranet y en Moodle (Moodle ID: $moodleId)." 
            : "Curso '$nombreCorto' registrado en la Intranet y vinculado correctamente al Moodle ID $moodleId.";
            
        // Recargar lista local
        $stmtLocal = $pdo->query("SELECT * FROM cursos ORDER BY id DESC");
        $cursosLocales = $stmtLocal->fetchAll();
        $cursos = $cursosLocales;
        
    } catch (Exception $e) {
        $error = "Error al crear el curso: " . $e->getMessage();
    }
}

// Intentar cargar cursos de Moodle en tiempo real si está configurado
if ($isConfigured && empty($cursosLocales)) {
    try {
        $moodleCourses = $moodle->getCourses();
        if (is_array($moodleCourses)) {
            foreach ($moodleCourses as $c) {
                if (isset($c['id']) && $c['id'] != 1) {
                    $cursos[] = $c;
                }
            }
        }
    } catch (Exception $e) {
        $error = "No se pudo conectar con Moodle: " . $e->getMessage();
    }
} else {
    // Usar locales
    $cursos = $cursosLocales;
}

// Acción: Crear Grupo Rápido + Alumno Prueba
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'provision') {
    $courseId = $_POST['course_id'];
    $groupName = trim($_POST['group_name']);
    $studentEmail = trim($_POST['student_email']);
    
    try {
        // 1. Crear el grupo
        $groupResponse = $moodle->createGroup($courseId, $groupName, 'Grupo creado desde la Intranet de Grupo EFP');
        if (isset($groupResponse[0]['id'])) {
            $groupId = $groupResponse[0]['id'];
            
            // 2. Crear y matricular alumno
            $studentData = [
                'firstname' => 'Alumno Test',
                'lastname' => 'Automático',
                'email' => $studentEmail,
                'password' => 'Secreta123!', // Debe cumplir política de pass Moodle
                'username' => strtolower(explode('@', $studentEmail)[0])
            ];
            
            $newUserId = $moodle->provisionStudent($courseId, $groupId, $studentData);
            
            // Log Auditoría local
            audit_log($pdo, 'MOODLE_PROVISION', 'cursos', $courseId, null, [
                'grupo_id' => $groupId, 
                'nombre_grupo' => $groupName,
                'alumno_email' => $studentEmail
            ]);
            
            $success = "El grupo '$groupName' ha sido creado y el alumno '$studentEmail' ha sido matriculado correctamente.";
        }
    } catch (Exception $e) {
        $error = "Error al provisionar en Moodle: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos Moodle - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    
    <style>
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .course-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            border-color: #fca5a5;
        }
        
        .course-cover {
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .course-cover svg {
            width: 48px;
            height: 48px;
            opacity: 0.5;
        }
        
        .course-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            color: var(--text-color);
        }
        
        .course-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
        }
        
        .course-actions {
            margin-top: auto;
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .alert-error { background-color: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        .alert-success { background-color: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        
        /* Modal Quick Provision */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal.active { display: flex; animation: fadeInBody 0.2s; }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 { margin: 0; font-size: 1.2rem; color: var(--primary-color); }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        
        .form-group { margin-bottom: 1.2rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input {
            width: 100%; padding: 0.75rem; border: 1px solid var(--border-color);
            border-radius: 6px; font-family: inherit; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
        .course-card.create-card:hover {
            border-color: #006ce4 !important;
            background: rgba(0, 108, 228, 0.02) !important;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Cursos desde Moodle</h1>
                <p>Integración directa con Aula Virtual (API REST)</p>
            </div>
            
            <div class="header-actions" style="display: flex; gap: 0.5rem;">
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="sync_courses">
                    <button type="submit" class="btn" style="background: white; border: 1px solid var(--border-color); color: var(--text-color);">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 0.4rem;"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                        Sincronizar con Moodle
                    </button>
                </form>
                <button type="button" class="btn btn-primary" onclick="openCreateCourseModal()" style="display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right: 0.4rem;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Crear y Vincular Curso
                </button>
                <?php if (!$isConfigured): ?>
                    <a href="configuracion.php" class="btn btn-primary">Configurar Moodle</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($isConfigured && empty($error)): ?>
            <div class="course-grid">
                <!-- Tarjeta para Crear y Vincular Curso -->
                <div class="course-card create-card" style="border: 2px dashed var(--border-color); background: transparent; justify-content: center; align-items: center; min-height: 250px; cursor: pointer; text-align: center; transition: all 0.2s;" onclick="openCreateCourseModal()">
                    <div style="padding: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(0, 108, 228, 0.08); display: flex; align-items: center; justify-content: center; color: #006ce4;">
                            <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size: 1.1rem; font-weight: 600; margin: 0 0 0.25rem 0; color: #006ce4;">Crear y Vincular</h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Enlaza un curso de Moodle o créalo de cero.</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($cursos)): ?>
                    <?php foreach ($cursos as $c): 
                        // Normalizar datos (pueden venir de API o de DB local)
                        $cid = $c['id'];
                        if (isset($c['moodle_id'])) {
                            // Viene de DB Local
                            $cid = $c['moodle_id'];
                            $cname = $c['nombre_corto'];
                            $clong = $c['nombre_largo'];
                        } else {
                            // Viene directo de API
                            $cname = $c['shortname'];
                            $clong = $c['fullname'];
                        }
                    ?>
                    <div class="course-card">
                        <div class="course-cover">
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72M12 12.72v-3.72L17 11.27v3.72L12 15.99z"/></svg>
                        </div>
                        <div class="course-body">
                            <h3 class="course-title" title="<?= htmlspecialchars($clong) ?>">
                                <?= htmlspecialchars($cname) ?>
                            </h3>
                            <div class="course-meta">
                                <span>Moodle ID: <?= $cid ?></span>
                                <span>Local ID: <?= isset($c['moodle_id']) ? $c['id'] : 'Sync pendiente' ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <button class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="openProvisionModal(<?= $cid ?>, '<?= htmlspecialchars(addslashes($cname)) ?>')">
                                    <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    Gestionar Alumnos Moodle
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Provision -->
<div id="provisionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalGroupName">Crear Grupo y Alumno</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
            Esta acción creará un nuevo grupo en Moodle, generará automáticamente un nuevo usuario estudiante, lo matriculará en el curso y lo asignará al grupo (todo en tiempo real vía API).
        </p>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="provision">
            <input type="hidden" name="course_id" id="modalCourseId" value="">
            
            <div class="form-group">
                <label class="form-label">Nombre del Nuevo Grupo (Ej: Edición Madrid 2026)</label>
                <input type="text" name="group_name" class="form-input" required placeholder="Nombre del grupo en Moodle">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email del Alumno de Prueba a auto-matricular</label>
                <input type="email" name="student_email" class="form-input" required placeholder="alumno@grupoefp.es">
            </div>
            
            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeModal()" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 0.5rem;">Cancelar</button>
                <button type="submit" class="btn btn-primary">Ejecutar API Moodle</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Crear Curso -->
<div id="createCourseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Crear y Vincular Nuevo Curso</h2>
            <button class="close-btn" onclick="closeCreateCourseModal()">&times;</button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_course">
            
            <div class="form-group">
                <label class="form-label">Nombre Corto del Curso (Ej: PHP-101)</label>
                <input type="text" name="nombre_corto" class="form-input" required placeholder="Nombre corto / Identificador">
            </div>
            
            <div class="form-group">
                <label class="form-label">Nombre Completo del Curso (Ej: Programación PHP desde Cero)</label>
                <input type="text" name="nombre_largo" class="form-input" required placeholder="Nombre descriptivo completo">
            </div>
            
            <div class="form-group" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; font-size: 0.95rem;">
                    <input type="checkbox" name="create_in_moodle" id="create_in_moodle" value="1" onchange="toggleMoodleIdField()" checked style="width: 18px; height: 18px; cursor: pointer;">
                    Crear también el curso en Moodle (Aula Virtual)
                </label>
            </div>
            
            <div class="form-group" id="moodleIdGroup" style="opacity: 0.5; transition: opacity 0.2s;">
                <label class="form-label">Moodle ID del Curso Existente (Para interconectar)</label>
                <input type="number" name="moodle_id" id="modalMoodleId" class="form-input" placeholder="Ej: 42" disabled>
            </div>
            
            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="button" class="btn" onclick="closeCreateCourseModal()" style="background: transparent; color: var(--text-muted); border: 1px solid var(--border-color); margin-right: 0.5rem;">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar y Vincular</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProvisionModal(courseId, courseName) {
    document.getElementById('modalCourseId').value = courseId;
    document.getElementById('modalGroupName').innerText = 'Provisionar: ' + courseName;
    document.getElementById('provisionModal').classList.add('active');
}

function closeModal() {
    document.getElementById('provisionModal').classList.remove('active');
}

function openCreateCourseModal() {
    document.getElementById('createCourseModal').classList.add('active');
    toggleMoodleIdField();
}

function closeCreateCourseModal() {
    document.getElementById('createCourseModal').classList.remove('active');
}

function toggleMoodleIdField() {
    var checkbox = document.getElementById('create_in_moodle');
    var moodleIdInput = document.getElementById('modalMoodleId');
    var moodleIdGroup = document.getElementById('moodleIdGroup');
    if (checkbox.checked) {
        moodleIdInput.disabled = true;
        moodleIdInput.required = false;
        moodleIdInput.value = '';
        moodleIdGroup.style.opacity = '0.5';
    } else {
        moodleIdInput.disabled = false;
        moodleIdInput.required = true;
        moodleIdGroup.style.opacity = '1';
    }
}

// Cerrar al clickar fuera
window.onclick = function(event) {
    var provisionModal = document.getElementById('provisionModal');
    var createCourseModal = document.getElementById('createCourseModal');
    if (event.target == provisionModal) {
        closeModal();
    }
    if (event.target == createCourseModal) {
        closeCreateCourseModal();
    }
}
</script>

</body>
</html>
