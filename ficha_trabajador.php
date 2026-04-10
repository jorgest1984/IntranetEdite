<?php
// ficha_trabajador.php - Perfil Profesional del Personal (Usuarios)
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: usuarios.php");
    exit();
}

// Cargar datos del usuario/trabajador
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$trabajador = $stmt->fetch();

if (!$trabajador) {
    die("Trabajador no encontrado.");
}

// Cargar detalles de profesorado (vínculo a usuario)
$stmtProf = $pdo->prepare("SELECT * FROM profesorado_detalles WHERE usuario_id = ?");
$stmtProf->execute([$id]);
$prof = $stmtProf->fetch() ?: [];

$active_tab = $_GET['tab'] ?? 'profesional';

// Acciones CV: Agregar Registros (Formación, Experiencia, Idiomas, Informática)
$cv_actions = [
    'add_formacion' => ['table' => 'prof_formacion', 'fields' => ['denominacion', 'organismo', 'centro', 'desde', 'hasta', 'horas', 'tipo_formacion']],
    'add_experiencia' => ['table' => 'prof_experiencia', 'fields' => ['empresa', 'desde', 'hasta', 'cargo', 'tareas']],
    'add_idioma' => ['table' => 'prof_idiomas', 'fields' => ['idioma', 'nivel_hablado', 'nivel_oral', 'nivel_escrito', 'nivel_leido']],
    'add_informatica' => ['table' => 'prof_informatica', 'fields' => ['programa', 'dominio']],
    'add_asistencia' => ['table' => 'prof_asistencia', 'fields' => ['fecha_desde', 'fecha_hasta', 'tipo', 'duracion_dias', 'duracion_horas', 'observaciones']]
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($cv_actions[$_POST['action']])) {
    try {
        $act = $cv_actions[$_POST['action']];
        $fields = $act['fields'];
        $sql = "INSERT INTO " . $act['table'] . " (usuario_id, " . implode(', ', $fields) . ") VALUES (?, " . implode(', ', array_fill(0, count($fields), '?')) . ")";
        $params = [$id];
        foreach($fields as $f) {
            $val = isset($_POST[$f]) ? trim($_POST[$f]) : null;
            $params[] = ($val === '') ? null : $val;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        
        header("Location: ficha_trabajador.php?id=$id&tab=cv&success=1");
        exit();
    } catch (Exception $e) { $error = "Error al añadir registro: " . $e->getMessage(); }
}

// Obtener registros para el CV
$cv_formacion = $pdo->prepare("SELECT * FROM prof_formacion WHERE usuario_id = ? ORDER BY desde DESC");
$cv_formacion->execute([$id]);
$cv_formacion = $cv_formacion->fetchAll();

$cv_experiencia = $pdo->prepare("SELECT * FROM prof_experiencia WHERE usuario_id = ? ORDER BY desde DESC");
$cv_experiencia->execute([$id]);
$cv_experiencia = $cv_experiencia->fetchAll();

// Cargar departamentos y perfiles del usuario
$dept_stmt = $pdo->prepare("SELECT departamento FROM usuario_departamentos WHERE usuario_id = ?");
$dept_stmt->execute([$id]);
$selected_depts = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

$perfil_stmt = $pdo->prepare("SELECT perfil FROM usuario_perfiles WHERE usuario_id = ?");
$perfil_stmt->execute([$id]);
$selected_perfiles = $perfil_stmt->fetchAll(PDO::FETCH_COLUMN);

// Procesar actualización de perfiles y departamentos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_perfiles_dept') {
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("DELETE FROM usuario_departamentos WHERE usuario_id = ?")->execute([$id]);
        if (isset($_POST['depts']) && is_array($_POST['depts'])) {
            $stD = $pdo->prepare("INSERT INTO usuario_departamentos (usuario_id, departamento) VALUES (?, ?)");
            foreach($_POST['depts'] as $d) $stD->execute([$id, $d]);
        }
        
        $pdo->prepare("DELETE FROM usuario_perfiles WHERE usuario_id = ?")->execute([$id]);
        if (isset($_POST['perfiles']) && is_array($_POST['perfiles'])) {
            $stP = $pdo->prepare("INSERT INTO usuario_perfiles (usuario_id, perfil) VALUES (?, ?)");
            foreach($_POST['perfiles'] as $p) $stP->execute([$id, $p]);
        }
        
        $pdo->commit();
        header("Location: ficha_trabajador.php?id=$id&tab=perfil&success=1");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Personal: <?= htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellidos']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .trabajador-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tabs-header {
            display: flex;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            white-space: nowrap;
            border-right: 1px solid var(--border-color);
        }
        .tab-btn.active { background: white; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); }
        .tab-panel {
            background: white;
            padding: 2rem;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 12px 12px;
            min-height: 500px;
        }
        .info-section { margin-bottom: 2rem; }
        .info-section h3 { color: var(--label-blue); border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1rem; }
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="trabajador-header">
            <div>
                <a href="usuarios.php" class="btn-back">← Volver a Usuarios</a>
                <h1 style="margin-top: 0.5rem; color: var(--primary-color);"><?= htmlspecialchars($trabajador['nombre'] . ' ' . $trabajador['apellidos']) ?></h1>
                <p style="color: var(--text-muted); margin: 0;"><?= htmlspecialchars($trabajador['email']) ?> | Perfil de Trabajador</p>
            </div>
            <div>
                <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 0.5rem 1rem; border-radius: 99px; font-weight: 700;">TRABAJADOR</span>
            </div>
        </div>

        <nav class="tabs-header">
            <button class="tab-btn <?= $active_tab == 'profesional' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=profesional'">Datos Profesionales</button>
            <button class="tab-btn <?= $active_tab == 'cv' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=cv'">Currículum Vitae</button>
            <button class="tab-btn <?= $active_tab == 'perfil' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=perfil'">Perfil / Depto</button>
            <button class="tab-btn <?= $active_tab == 'asistencia' ? 'active' : '' ?>" onclick="location.href='?id=<?= $id ?>&tab=asistencia'">Asistencia Laboral</button>
        </nav>

        <div class="tab-panel">
            <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Cambios guardados con éxito en el perfil profesional.</div><?php endif; ?>

            <!-- TAB: Profesional -->
            <div id="tab-profesional" style="<?= $active_tab == 'profesional' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Configuración de Empresa</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_config">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Cargo / Especialidad</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($prof['titulacion'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Centro de Trabajo</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($prof['centro'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" disabled>Guardar (Módulo en preparación)</button>
                    </form>
                </div>
            </div>

            <!-- TAB: CV -->
            <div id="tab-cv" style="<?= $active_tab == 'cv' ? '' : 'display:none;' ?>">
                <div class="info-section">
                    <h3>Experiencia y Formación</h3>
                    <p>Gestiona el CV del trabajador para acreditaciones y perfil de tutor.</p>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Denominación / Empresa</th>
                                <th>Periodo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cv_formacion as $f): ?>
                            <tr>
                                <td><span class="badge" style="background:#e0f2fe; color:#0369a1;">Formación</span></td>
                                <td><?= htmlspecialchars($f['denominacion']) ?></td>
                                <td><?= $f['desde'] ?> / <?= $f['hasta'] ?></td>
                                <td>Verificado</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cv_formacion)): ?>
                            <tr><td colspan="4" style="text-align:center;">No hay registros de formación todavía.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB: Perfil/Depto -->
            <div id="tab-perfil" style="<?= $active_tab == 'perfil' ? '' : 'display:none;' ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_perfiles_dept">
                    <div class="info-section">
                        <h3>Departamentos</h3>
                        <div class="checkbox-grid">
                            <?php $depts = ['ADMINISTRACIÓN', 'COMERCIAL', 'DIRECCIÓN', 'FORMACIÓN', 'GRUPOS', 'INFORMÁTICA', 'MARKETING', 'RECURSOS HUMANOS']; 
                            foreach($depts as $d): ?>
                                <label><input type="checkbox" name="depts[]" value="<?= $d ?>" <?= in_array($d, $selected_depts) ? 'checked' : '' ?>> <?= $d ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="info-section">
                        <h3>Perfil de Usuario</h3>
                        <div class="checkbox-grid">
                            <?php $perfiles = ['ADMINISTRADOR', 'COORDINADOR', 'COMERCIAL', 'TUTOR', 'ADMINISTRATIVO', 'SOPORTE']; 
                            foreach($perfiles as $p): ?>
                                <label><input type="checkbox" name="perfiles[]" value="<?= $p ?>" <?= in_array($p, $selected_perfiles) ? 'checked' : '' ?>> <?= $p ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar Atribuciones</button>
                </form>
            </div>
        </div>
    </main>
</div>

</body>
</html>
