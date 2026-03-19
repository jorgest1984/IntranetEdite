<?php
// editar_convocatoria.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: convocatorias.php");
    exit();
}

// Obtener datos de la convocatoria
$stmt = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
$stmt->execute([$id]);
$convocatoria = $stmt->fetch();

if (!$convocatoria) {
    header("Location: convocatorias.php");
    exit();
}

// Obtener planes asociados
$stmtPlanes = $pdo->prepare("SELECT * FROM planes WHERE convocatoria_id = ? ORDER BY codigo ASC");
$stmtPlanes->execute([$id]);
$planes = $stmtPlanes->fetchAll();

$success = '';
$error = '';

// Procesar Guardar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        $sql = "UPDATE convocatorias SET 
                nombre = ?, abreviatura = ?, anio = ?, 
                fecha_inicio_prevista = ?, fecha_fin_prevista = ?, 
                ambito = ?, solicitante = ?, url = ?, url_aula_virtual = ?, 
                activa = ?, descripcion = ?, requisitos = ? 
                WHERE id = ?";
        
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            $_POST['nombre'], $_POST['abreviatura'], $_POST['anio'],
            $_POST['fecha_inicio'], $_POST['fecha_fin'],
            $_POST['ambito'], $_POST['solicitante'], $_POST['url'], $_POST['url_aula_virtual'],
            isset($_POST['activa']) ? 1 : 0, $_POST['descripcion'], $_POST['requisitos'],
            $id
        ]);
        
        $success = "Convocatoria actualizada correctamente.";
        // Recargar datos
        $stmt->execute([$id]);
        $convocatoria = $stmt->fetch();
    } catch (Exception $e) {
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Convocatoria - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .edit-form-container { background: white; padding: 2rem; border-radius: 8px; border: 1px solid #e2e8f0; }
        .form-row { display: flex; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #f8fafc; padding-bottom: 0.75rem; }
        .form-label { width: 180px; font-size: 0.9rem; color: #64748b; flex-shrink: 0; }
        .form-input-container { flex-grow: 1; position: relative; }
        .form-control { width: 100%; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #006ce4; box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.1); }
        .input-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; }
        
        .rte-mock { border: 1px solid #e2e8f0; border-radius: 4px; }
        .rte-toolbar { background: #f8fafc; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .toolbar-btn { padding: 0.25rem; border: 1px solid transparent; background: none; cursor:pointer; color: #475569; }
        .toolbar-btn:hover { background: #e2e8f0; border-radius: 2px; }
        .rte-textarea { width: 100%; min-height: 150px; padding: 1rem; border: none; font-family: inherit; resize: vertical; display: block; }
        
        .btn-save { background: #006ce4; color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; margin-top: 1rem; }
        .btn-save:hover { background: #0056b3; }

        .planes-section { margin-top: 3rem; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .section-title::before { content: ''; display: block; width: 4px; height: 1.25rem; background: #ef4444; border-radius: 2px; }

        .planes-table { width: 100%; border-collapse: collapse; }
        .planes-table th { background: #1e293b; color: white; text-align: left; padding: 0.75rem 1rem; font-size: 0.9rem; }
        .planes-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.9rem; }
        .planes-table tr:nth-child(even) { background: #f8fafc; }
        .btn-new-plan { background: #006ce4; color: white; padding: 0.5rem 1rem; border:none; border-radius:4px; font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:0.5rem; }

        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="margin-bottom: 2rem;">
            <div class="page-title">
                <h1>Editar Convocatoria</h1>
                <p><?= htmlspecialchars($convocatoria['nombre']) ?></p>
            </div>
            <a href="convocatorias.php" class="btn btn-neutral" style="border: 1px solid #ccc; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; color: #666;">Volver al listado</a>
        </header>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="edit-form-container">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                
                <div class="form-row">
                    <label class="form-label">Convocatoria</label>
                    <div class="form-input-container">
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($convocatoria['nombre']) ?>">
                        <div class="input-hint">Nombre de la convocatoria</div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Abreviatura</label>
                    <div class="form-input-container">
                        <input type="text" name="abreviatura" class="form-control" value="<?= htmlspecialchars($convocatoria['abreviatura'] ?? '') ?>" placeholder="Ej: BON18">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Año de convocatoria</label>
                    <div class="form-input-container">
                        <input type="text" name="anio" class="form-control" value="<?= htmlspecialchars($convocatoria['anio'] ?? '') ?>" placeholder="Ej: 2018">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Fecha de inicio</label>
                    <div class="form-input-container">
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($convocatoria['fecha_inicio_prevista'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Fecha de finalización</label>
                    <div class="form-input-container">
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($convocatoria['fecha_fin_prevista'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Ámbito</label>
                    <div class="form-input-container">
                        <input type="text" name="ambito" class="form-control" value="<?= htmlspecialchars($convocatoria['ambito'] ?? '') ?>" placeholder="Ej: Estatal">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Solicitante</label>
                    <div class="form-input-container">
                        <input type="text" name="solicitante" class="form-control" value="<?= htmlspecialchars($convocatoria['solicitante'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">URL</label>
                    <div class="form-input-container">
                        <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($convocatoria['url'] ?? '') ?>" placeholder="/formacion/bonificada">
                        <div class="input-hint">URL de la convocatoria en la web. Puede ser una ruta relativa.</div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">URL del Aula Virtual</label>
                    <div class="form-input-container">
                        <input type="text" name="url_aula_virtual" class="form-control" value="<?= htmlspecialchars($convocatoria['url_aula_virtual'] ?? '') ?>">
                        <div class="input-hint">URL oficial la que se comunica a los organismos públicos.</div>
                    </div>
                </div>

                <div class="form-row">
                    <label class="form-label">Activa</label>
                    <div class="form-input-container">
                        <input type="checkbox" name="activa" <?= ($convocatoria['activa'] ?? 1) ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="form-row" style="flex-direction: column; align-items: flex-start; border-bottom: none;">
                    <label class="form-label" style="width: 100%; margin-bottom: 0.5rem;">Descripción</label>
                    <div class="form-input-container" style="width: 100%;">
                        <div class="rte-mock">
                            <div class="rte-toolbar">
                                <button type="button" class="toolbar-btn"><b>B</b></button>
                                <button type="button" class="toolbar-btn"><i>I</i></button>
                                <button type="button" class="toolbar-btn"><u>U</u></button>
                                <span style="border-right:1px solid #ccc; margin:0 5px;"></span>
                                <button type="button" class="toolbar-btn">🔗</button>
                                <button type="button" class="toolbar-btn">🖼️</button>
                            </div>
                            <textarea name="descripcion" class="rte-textarea"><?= htmlspecialchars($convocatoria['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-row" style="flex-direction: column; align-items: flex-start; border-bottom: none; margin-top: 1.5rem;">
                    <label class="form-label" style="width: 100%; margin-bottom: 0.5rem;">Requisitos de participación</label>
                    <div class="form-input-container" style="width: 100%;">
                        <div class="rte-mock">
                            <div class="rte-toolbar">
                                <button type="button" class="toolbar-btn"><b>B</b></button>
                                <button type="button" class="toolbar-btn"><i>I</i></button>
                                <button type="button" class="toolbar-btn"><u>U</u></button>
                            </div>
                            <textarea name="requisitos" class="rte-textarea"><?= htmlspecialchars($convocatoria['requisitos'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-save">Guardar</button>
            </form>
        </div>

        <section class="planes-section">
            <div class="section-header">
                <h2 class="section-title">Planes</h2>
                <a href="#" class="btn-new-plan">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nuevo plan
                </a>
            </div>
            
            <table class="planes-table">
                <thead>
                    <tr>
                        <th width="150">Cod</th>
                        <th>Plan</th>
                        <th width="100">Activo</th>
                        <th width="100" style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($planes)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 2rem;">No hay planes asociados a esta convocatoria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($planes as $plan): ?>
                        <tr>
                            <td><?= htmlspecialchars($plan['codigo']) ?></td>
                            <td><a href="#" style="color: #006ce4; text-decoration: none;"><?= htmlspecialchars($plan['nombre']) ?></a></td>
                            <td><?= $plan['activo'] ? 'SÍ' : 'NO' ?></td>
                            <td style="text-align: right;">
                                <a href="#" class="icon-btn icon-edit" style="justify-content: flex-end;">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>
