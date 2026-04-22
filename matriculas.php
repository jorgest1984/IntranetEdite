<?php
// matriculas.php (Expediente Interno de Convocatoria)
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$convocatoria_id = isset($_GET['convocatoria_id']) ? intval($_GET['convocatoria_id']) : 0;
if (!$convocatoria_id) {
    header("Location: convocatorias.php");
    exit();
}

// Obtener info de la convocatoria
$stmtConv = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
$stmtConv->execute([$convocatoria_id]);
$convocatoria = $stmtConv->fetch();

if (!$convocatoria) {
    header("Location: convocatorias.php");
    exit();
}

$error = '';
$success = '';

// Procesar Matriculación (Añadir alumno al expediente)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'matricular' && has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    $alumnoId = intval($_POST['alumno_id']);
    $estadoInicial = trim($_POST['estado']);
    $fecha = trim($_POST['fecha']);
    
    if (!$alumnoId || empty($fecha)) {
        $error = "Debe seleccionar un alumno y una fecha de alta.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO matriculas (convocatoria_id, alumno_id, estado, fecha_matricula) VALUES (?, ?, ?, ?)");
            $stmt->execute([$convocatoria_id, $alumnoId, $estadoInicial, $fecha]);
            
            audit_log($pdo, 'NUEVA_MATRICULA', 'matriculas', $pdo->lastInsertId(), null, [
                'convocatoria_id' => $convocatoria_id,
                'alumno_id' => $alumnoId
            ]);
            
            $success = "Alumno matriculado en el expediente correctamente.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Constraint violation
                $error = "El alumno ya se encuentra matriculado en esta convocatoria.";
            } else {
                $error = "Error al guardar la matrícula: " . $e->getMessage();
            }
        }
    }
}

// Cambiar estado de matrícula
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cambiar_estado' && has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    $matriculaId = intval($_POST['matricula_id']);
    $nuevoEstado = trim($_POST['nuevo_estado']);
    
    try {
        $stmtStatus = $pdo->prepare("UPDATE matriculas SET estado = ? WHERE id = ? AND convocatoria_id = ?");
        $stmtStatus->execute([$nuevoEstado, $matriculaId, $convocatoria_id]);
        
        audit_log($pdo, 'CAMBIO_ESTADO_MATRICULA', 'matriculas', $matriculaId, null, ['nuevo_estado' => $nuevoEstado]);
        $success = "Estado actualizado correctamente.";
    } catch (Exception $e) {
        $error = "Error al actualizar el estado: " . $e->getMessage();
    }
}

// Listar alumnos matriculados en esta convocatoria
$stmtMats = $pdo->prepare("
    SELECT m.id as matricula_id, m.estado, m.fecha_matricula, a.* 
    FROM matriculas m 
    INNER JOIN alumnos a ON m.alumno_id = a.id 
    WHERE m.convocatoria_id = ? 
    ORDER BY a.primer_apellido, a.segundo_apellido, a.nombre
");
$stmtMats->execute([$convocatoria_id]);
$matriculados = $stmtMats->fetchAll();

// Listar todos los alumnos para el selector (Excluyendo los ya matriculados)
$stmtTodosListos = $pdo->prepare("
    SELECT id, dni, nombre, primer_apellido, segundo_apellido 
    FROM alumnos 
    WHERE id NOT IN (SELECT alumno_id FROM matriculas WHERE convocatoria_id = ?)
    ORDER BY primer_apellido, segundo_apellido, nombre
");
$stmtTodosListos->execute([$convocatoria_id]);
$alumnosDisponibles = $stmtTodosListos->fetchAll();

// Helpers UI
function getMatriculaBadge($estado) {
    if ($estado == 'Inscrito') return 'background:#fef3c7; color:#d97706';
    if ($estado == 'Activo') return 'background:#d1fae5; color:#059669';
    if ($estado == 'Finalizada') return 'background:#dbeafe; color:#2563eb';
    if ($estado == 'Baja' || $estado == 'Cancelada') return 'background:#fee2e2; color:#dc2626';
    return 'background:#f3f4f6; color:#6b7280';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente - <?= htmlspecialchars($convocatoria['codigo_expediente']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .split-layout { display: flex; gap: 2rem; align-items: flex-start; }
        .list-section { flex: 2; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-section { flex: 1; min-width: 320px; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); padding: 1.5rem; position: sticky; top: 2rem; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.1); }
        
        @media(max-width: 1024px) { .split-layout { flex-direction: column-reverse; } .form-section { width: 100%; position: static; } }

        /* Tables & Alerts */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { font-weight: 600; color: var(--text-muted); background-color: #f8fafc; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #d1fae5; color: #059669; border-left: 4px solid #059669; }
        .alert-error { background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; }
        
        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; }
        .form-input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1); }
        
        /* Header Card */
        .expediente-header {
            background: linear-gradient(135deg, white 0%, #fef2f2 100%);
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .expediente-info h2 { margin: 0 0 0.5rem 0; color: var(--text-color); }
        .expediente-info p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        .expediente-badge { padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: bold; border-radius: 9999px; background: #fff; border: 1px solid var(--border-color); }
        
        /* Mini form status */
        .status-form { display: inline-flex; gap: 0.5rem; }
        .status-select { font-size: 0.8rem; padding: 0.2rem; border-radius: 4px; border: 1px solid var(--border-color); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="margin-bottom: 1rem;">
            <div class="page-title">
                <h1>Detalle del Expediente</h1>
                <p>Gestión de Matrículas</p>
            </div>
            <a href="convocatorias.php" class="btn" style="border: 1px solid var(--border-color);">Volver a Convocatorias</a>
        </header>

        <div class="expediente-header">
            <div class="expediente-info">
                <h2><?= htmlspecialchars($convocatoria['codigo_expediente']) ?> - <?= htmlspecialchars($convocatoria['nombre']) ?></h2>
                <p><strong>Tipo:</strong> <?= str_replace('_', ' ', htmlspecialchars($convocatoria['tipo'])) ?> | <strong>Organismo:</strong> <?= htmlspecialchars($convocatoria['organismo']) ?: 'No definido' ?></p>
            </div>
            <div class="expediente-badge">
                Estado: <?= htmlspecialchars($convocatoria['estado']) ?>
            </div>
        </div>

        <?php if (!empty($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>

        <div class="split-layout">
            <!-- Listado de Matriculados -->
            <section class="list-section">
                <h3 style="margin-top: 0; color: var(--text-muted); font-weight: 600; font-size: 1rem; margin-bottom: 1rem;">Alumnos Matriculados (<?= count($matriculados) ?>)</h3>
                
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>DNI</th>
                                <th>Fecha Alta</th>
                                <th>Estado Documental</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($matriculados)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No hay alumnos matriculados en este expediente.</td></tr>
                            <?php else: ?>
                                <?php foreach ($matriculados as $mat): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($mat['primer_apellido'] . ' ' . $mat['segundo_apellido']) ?>, <?= htmlspecialchars($mat['nombre']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($mat['email']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($mat['dni']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($mat['fecha_matricula'])) ?></td>
                                    <td>
                                        <div style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; <?= getMatriculaBadge($mat['estado']) ?>">
                                            <?= htmlspecialchars($mat['estado']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])): ?>
                                        <form method="POST" action="" class="status-form">
                                            <input type="hidden" name="action" value="cambiar_estado">
                                            <input type="hidden" name="matricula_id" value="<?= $mat['matricula_id'] ?>">
                                            <select name="nuevo_estado" class="status-select" onchange="this.form.submit()">
                                                <option disabled selected>Mover a...</option>
                                                <option value="Inscrito">Inscrito</option>
                                                <option value="Activo">Activo</option>
                                                <option value="Finalizada">Finalizado</option>
                                                <option value="Baja">Baja</option>
                                            </select>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Formulario Añadir Matrícula -->
            <?php if (has_permission([ROLE_ADMIN, ROLE_TUTOR])): ?>
            <section class="form-section">
                <h2 style="margin-top: 0; font-size: 1.1rem; color: var(--primary-color); border-bottom: 1px solid var(--border-color); padding-bottom: 0.8rem;">
                    Nueva Matrícula
                </h2>
                <p style="font-size:0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                    Inscribir a un alumno existente en la base de datos a este expediente.
                </p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="matricular">
                    
                    <div class="form-group">
                        <label class="form-label">Seleccionar Alumno *</label>
                        <select name="alumno_id" class="form-input" required>
                            <option value="">-- Buscar Alumno Disponible --</option>
                            <?php foreach ($alumnosDisponibles as $a): ?>
                                <option value="<?= $a['id'] ?>">
                                    <?= htmlspecialchars($a['primer_apellido'] . ' ' . $a['segundo_apellido']) ?>, <?= htmlspecialchars($a['nombre']) ?> (<?= htmlspecialchars($a['dni']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Alta *</label>
                        <input type="date" name="fecha" class="form-input" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Estado de la Matrícula</label>
                        <select name="estado" class="form-input">
                            <option value="Inscrito" selected>Pendiente DNI/Inscrito</option>
                            <option value="Activo">Activo (Formación Iniciada)</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> 
                            Añadir al Expediente
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 1.5rem; text-align: center;">
                    <a href="alumnos.php" style="font-size: 0.85rem; color: var(--primary-color); text-decoration: none;">¿El alumno no existe? Dar de alta primero &rarr;</a>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
