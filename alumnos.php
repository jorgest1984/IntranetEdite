<?php
// alumnos.php
session_start();
require_once 'includes/auth.php';
require_once 'includes/moodle_api.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$moodle = new MoodleAPI($pdo);
$error = '';
$success = '';

// Procesar formulario de alta de alumno
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create') {
        $nombre = trim($_POST['nombre']);
        $primer_apellido = trim($_POST['primer_apellido']);
        $segundo_apellido = trim($_POST['segundo_apellido']);
        $dni = trim($_POST['dni']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);
        
        // 1. Validaciones básicas
        if (empty($nombre) || empty($primer_apellido) || empty($dni) || empty($email)) {
            $error = "Todos los campos marcados con * son obligatorios.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // 2. Comprobar si existe en local
                $stmtCheck = $pdo->prepare("SELECT id FROM alumnos WHERE dni = ? OR email = ?");
                $stmtCheck->execute([$dni, $email]);
                if ($stmtCheck->rowCount() > 0) {
                    throw new Exception("Ya existe un alumno con ese DNI o Email en la base de datos.");
                }
                
                // 3. Sincronizar / Crear en Moodle
                $moodleUserId = null;
                $moodleError = null;
                
                if ($moodle->isConfigured()) {
                    try {
                        // Generar contraseña temporal segura
                        $tempPassword = 'ef_' . strtoupper(substr($dni, -4)) . '!' . rand(10,99);
                        $username = strtolower(explode('@', $email)[0]) . '_' . substr($dni, -3);
                        
                        // Buscar si ya existe en Moodle por email
                        $moodleSearch = $moodle->getUsersByField('email', [$email]);
                        
                        if (!empty($moodleSearch) && !empty($moodleSearch['users'])) {
                            $moodleUserId = $moodleSearch['users'][0]['id'];
                        } else {
                            // Crear nuevo en Moodle
                            $moodleCreate = $moodle->createUser(
                                $username,
                                $tempPassword,
                                $nombre,
                                $primer_apellido . ' ' . $segundo_apellido,
                                $email
                            );
                            if (isset($moodleCreate[0]['id'])) {
                                $moodleUserId = $moodleCreate[0]['id'];
                            }
                        }
                    } catch (Exception $moodleEx) {
                        $moodleError = $moodleEx->getMessage();
                    }
                }
                
                // 4. Guardar en Base de Datos Local
                $stmt = $pdo->prepare("INSERT INTO alumnos (nombre, primer_apellido, segundo_apellido, dni, email, telefono, moodle_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $primer_apellido, $segundo_apellido, $dni, $email, $telefono, $moodleUserId]);
                $nuevoAlumnoId = $pdo->lastInsertId();
                
                // 5. Registrar en Auditoría ISO 27001
                audit_log($pdo, 'ALUMNO_CREADO', 'alumnos', $nuevoAlumnoId, null, [
                    'dni' => $dni,
                    'moodle_id' => $moodleUserId,
                    'sync' => $moodleUserId ? 'OK' : 'OFF'
                ]);
                
                $pdo->commit();
                
                // Redirigir directamente a la ficha del alumno para completar datos
                header("Location: ficha_alumno.php?id=$nuevoAlumnoId&success=1");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Error al guardar el alumno: " . $e->getMessage();
            }
        }
    }

    // Acción: Sincronizar Masivamente desde Moodle
    if ($_POST['action'] == 'sync_moodle') {
        try {
            if (!$moodle->isConfigured()) throw new Exception("Moodle no está configurado.");
            
            $mUsers = $moodle->getAllUsers();
            if (isset($mUsers['users'])) {
                $count = 0;
                $pdo->beginTransaction();
                foreach ($mUsers['users'] as $mu) {
                    if ($mu['username'] == 'admin' || $mu['username'] == 'guest') continue;
                    
                    $stCheck = $pdo->prepare("SELECT id, moodle_user_id FROM alumnos WHERE email = ?");
                    $stCheck->execute([$mu['email']]);
                    $localUser = $stCheck->fetch();

                    if (!$localUser) {
                        // Moodle suele enviar el apellido completo en lastname
                        // Intentamos dividirlo si hay espacio, sino todo al primero
                        $ln = explode(' ', $mu['lastname'], 2);
                        $papellido = $ln[0];
                        $sapellido = $ln[1] ?? '';

                        $stI = $pdo->prepare("INSERT INTO alumnos (nombre, primer_apellido, segundo_apellido, email, moodle_user_id, dni) VALUES (?, ?, ?, ?, ?, ?)");
                        $stI->execute([
                            $mu['firstname'],
                            $papellido,
                            $sapellido,
                            $mu['email'],
                            $mu['id'],
                            'M-' . $mu['id']
                        ]);
                        $count++;
                    } else if (empty($localUser['moodle_user_id'])) {
                        $stU = $pdo->prepare("UPDATE alumnos SET moodle_user_id = ? WHERE id = ?");
                        $stU->execute([$mu['id'], $localUser['id']]);
                        $count++;
                    }
                }
                $pdo->commit();
                audit_log($pdo, 'ALUMNOS_MASS_SYNC', 'alumnos', null, null, ['imported_or_linked' => $count]);
                $success = "Sincronización completada. Se han importado/vinculado $count alumnos.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error en la sincronización masiva: " . $e->getMessage();
        }
    }
}

// Obtener lista de alumnos
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM alumnos";
$params = [];

if (!empty($search)) {
    $query .= " WHERE nombre LIKE ? OR primer_apellido LIKE ? OR segundo_apellido LIKE ? OR dni LIKE ? OR email LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " ORDER BY id DESC LIMIT 50";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alumnosList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .alumni-layout { display: flex; flex-direction: column; gap: 2rem; }
        .list-section { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-section { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.1); width: 100%; box-sizing: border-box; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; align-items: flex-end; }
        
        @media(max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-muted); background-color: #f8fafc; }
        .data-table tr:hover td { background-color: #fef2f2; }
        
        /* Badges */
        .sync-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .sync-ok { background: #d1fae5; color: #059669; }
        .sync-no { background: #f3f4f6; color: #6b7280; }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; }
        .form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .search-input { flex: 1; padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Gestión de Alumnos</h1>
                <p>Base de datos centralizada y Sincronización Moodle</p>
            </div>
        </header>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if (!$moodle->isConfigured()) echo "<div class='alert' style='background:#fef3c7; color:#d97706; border-left:4px solid #d97706;'>⚠️ Moodle no está configurado. Los alumnos se crearán solo en modo local.</div>"; ?>

        <div class="alumni-layout">
            <!-- Formulario Alta Rápida (Primero y Ancho) -->
            <section class="form-section">
                <h2 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.2rem; color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Nuevo Alta de Alumno
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">DNI / NIE *</label>
                            <input type="text" name="dni" class="form-input" required placeholder="00000000A">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-input" required placeholder="Ej: Laura">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Primer Apellido *</label>
                            <input type="text" name="primer_apellido" class="form-input" required placeholder="Ej: García">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Segundo Apellido</label>
                            <input type="text" name="segundo_apellido" class="form-input" placeholder="Ej: Martínez">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico *</label>
                            <input type="email" name="email" class="form-input" required placeholder="laura@ejemplo.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Teléfono Móvil</label>
                            <input type="tel" name="telefono" class="form-input" placeholder="600 000 000">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%; height: 46px; justify-content: center;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                Registrar y Sincronizar Moodle
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Lista de Alumnos (Segundo) -->
            <section class="list-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="margin: 0; font-size: 1.1rem; color: var(--primary-color);">Listado de Alumnos</h2>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="sync_moodle">
                        <button type="submit" class="btn" style="font-size: 0.85rem; padding: 0.5rem 1rem; background: white; border: 1px solid var(--border-color); color: var(--text-color);">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="margin-right: 0.4rem; vertical-align: middle;"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46A7.93 7.93 0 0020 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74A7.93 7.93 0 004 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>
                            Sincronizar Alumnos (Moodle)
                        </button>
                    </form>
                </div>
                
                <form method="GET" class="search-bar">
                    <input type="text" name="search" class="search-input" placeholder="Buscar por DNI, Nombre o Email..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if ($search): ?><a href="alumnos.php" class="btn" style="border: 1px solid #e5e7eb;">Limpiar</a><?php endif; ?>
                </form>

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>DNI/NIE</th>
                                <th>Apellidos y Nombre</th>
                                <th>Contacto</th>
                                <th>Estado Moodle</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alumnosList)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No se encontraron registros.</td></tr>
                            <?php else: ?>
                                <?php foreach ($alumnosList as $alumno): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($alumno['dni']) ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-color);"><?= htmlspecialchars($alumno['primer_apellido'] . ' ' . $alumno['segundo_apellido']) ?>,</div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($alumno['nombre']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><?= htmlspecialchars($alumno['email']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($alumno['telefono']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($alumno['moodle_user_id']): ?>
                                            <span class="sync-badge sync-ok">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> 
                                                Sincronizado (ID: <?= $alumno['moodle_user_id'] ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="sync-badge sync-no">Local</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="ficha_alumno.php?id=<?= $alumno['id'] ?>" class="btn" style="padding: 0.4rem; border: 1px solid var(--border-color); color: var(--text-muted);" title="Ver Ficha Completa">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

</body>
</html>
