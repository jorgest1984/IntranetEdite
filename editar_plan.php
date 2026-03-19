<?php
// editar_plan.php
require_once 'includes/auth.php';

if (!has_permission([ROLE_ADMIN, ROLE_COORD])) {
    header("Location: dashboard.php");
    exit();
}

$conv_id = $_GET['convocatoria_id'] ?? null;
$plan_id = $_GET['id'] ?? null;

if (!$conv_id) {
    header("Location: convocatorias.php");
    exit();
}

// Obtener datos de la convocatoria
$stmtConv = $pdo->prepare("SELECT id, nombre, anio FROM convocatorias WHERE id = ?");
$stmtConv->execute([$conv_id]);
$convocatoria = $stmtConv->fetch();

if (!$convocatoria) {
    header("Location: convocatorias.php");
    exit();
}

$plan = null;
if ($plan_id) {
    $stmtPlan = $pdo->prepare("SELECT * FROM planes WHERE id = ?");
    $stmtPlan->execute([$plan_id]);
    $plan = $stmtPlan->fetch();
}

$success = '';
$error = '';

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $fields = [
            'nombre', 'codigo', 'fecha_inicio_oficial', 'anio_convocatoria', 
            'tope_horas_alumno', 'fecha_fin_convocatoria', 'expediente', 'ambito', 
            'cod_acceso', 'entidad', 'solicitante', 'sector', 'grupo_sector', 
            'coordinador', 'porc_frar', 'porc_calidad', 'porc_costes_indirectos', 
            'subvencion', 'cofin_fse', 'grupo_zona_1', 'grupo_zona_2', 
            'ejecutar_edite', 'cofinanciado_edite', 'prioridad_sectorial', 
            'prioridad_sectorial_colchon', 'transversal', 'transversal_colchon', 
            'minima', 'minima_colchon', 'reconfiguracion', 'porc_au', 
            'porc_mujeres', 'porc_colectivos_prioritarios', 'porc_max_desempleados', 
            'cant_ref_cofinanciada', 'cant_ref_no_cofinanciada', 'fecha_convenio', 'observaciones'
        ];

        $params = [];
        foreach ($fields as $f) { $params[$f] = $_POST[$f] ?? null; }
        
        // Checkboxes / Radios
        $params['programacion_automatica'] = isset($_POST['programacion_automatica']) ? 1 : 0;
        $params['mostrar'] = isset($_POST['mostrar']) ? 1 : 0;
        $params['nuestro'] = isset($_POST['nuestro']) ? 1 : 0;
        $params['facturar_por_grupos'] = isset($_POST['facturar_por_grupos']) ? 1 : 0;
        $params['activo'] = ($_POST['activo'] ?? '1') == '1' ? 1 : 0;
        $params['convocatoria_id'] = $conv_id;

        if ($plan_id) {
            // Update
            $setClauses = [];
            foreach ($params as $key => $val) { if ($key != 'id') $setClauses[] = "$key = :$key"; }
            $sql = "UPDATE planes SET " . implode(', ', $setClauses) . " WHERE id = :id_filter";
            $params['id_filter'] = $plan_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = "Plan actualizado correctamente.";
        } else {
            // Insert
            $cols = implode(', ', array_keys($params));
            $vals = ':' . implode(', :', array_keys($params));
            $sql = "INSERT INTO planes ($cols) VALUES ($vals)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $plan_id = $pdo->lastInsertId();
            $success = "Plan creado correctamente.";
            header("Location: editar_plan.php?id=$plan_id&convocatoria_id=$conv_id&success=1");
            exit();
        }
        
        // Recargar datos
        $stmtPlan = $pdo->prepare("SELECT * FROM planes WHERE id = ?");
        $stmtPlan->execute([$plan_id]);
        $plan = $stmtPlan->fetch();
        
    } catch (Exception $e) {
        $error = "Error al guardar: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) $success = "Plan guardado correctamente.";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha Planes de Formación - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .plan-form-card { background: white; padding: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full-width { grid-column: span 4; }
        .form-group.half-width { grid-column: span 2; }
        .form-group.three-quarter { grid-column: span 3; }
        
        .form-label { font-size: 0.85rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.025em; }
        .form-control { padding: 0.65rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: #f8fafc; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: #006ce4; background: white; box-shadow: 0 0 0 3px rgba(0, 108, 228, 0.1); }
        
        .checkbox-row { display: flex; align-items: center; gap: 1.5rem; margin-top: 0.5rem; border: 1px solid #e2e8f0; padding: 0.75rem; border-radius: 6px; background: #f1f5f9; }
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; }
        
        .section-separator { grid-column: span 4; border-bottom: 2px solid #f1f5f9; margin: 1rem 0; position: relative; }
        .section-separator span { position: absolute; top: -10px; left: 20px; background: white; padding: 0 10px; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }

        .btn-header { display: flex; gap: 1rem; }
        .btn-back { background: #f1f5f9; color: #475569; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500; border: 1px solid #e2e8f0; }
        .btn-submit { background: #006ce4; color: white; padding: 0.75rem 2rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; transition: background 0.2s; }
        .btn-submit:hover { background: #0056b3; }

        .reconfig-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.85rem; }
        .reconfig-table th { background: #f8fafc; padding: 0.75rem; border: 1px solid #e2e8f0; text-align: center; }
        .reconfig-table td { padding: 0.5rem; border: 1px solid #e2e8f0; text-align: center; }
        .reconfig-input { width: 80px; padding: 0.4rem; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div class="page-title">
                <h1 style="color: #8e1d52; font-size: 1.5rem; font-weight: 700; text-transform: uppercase;">Ficha Planes de Formación</h1>
                <p style="color: #64748b; margin-top: 0.25rem;">Convocatoria: <?= htmlspecialchars($convocatoria['nombre'] ?? '') ?> (<?= htmlspecialchars($convocatoria['anio'] ?? '') ?>)</p>
            </div>
            <div class="btn-header">
                <a href="editar_convocatoria.php?id=<?= $conv_id ?>" class="btn-back">Cancelar y Volver</a>
            </div>
        </header>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="plan-form-card">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                
                <div class="form-grid">
                    <!-- General -->
                    <div class="form-group full-width">
                        <label class="form-label">Nombre del Plan:</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($plan['nombre'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Código (Cod):</label>
                        <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($plan['codigo'] ?? '') ?>" placeholder="Ej: BON18">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Año Conv.:</label>
                        <input type="text" name="anio_convocatoria" class="form-control" value="<?= htmlspecialchars(($plan['anio_convocatoria'] ?? $convocatoria['anio']) ?? '') ?>">
                    </div>

                    <div class="form-group half-width">
                        <label class="form-label">Fecha Inicio Ofic. (Facturas):</label>
                        <input type="date" name="fecha_inicio_oficial" class="form-control" value="<?= htmlspecialchars($plan['fecha_inicio_oficial'] ?? '') ?>">
                    </div>

                    <div class="section-separator"><span>Datos de Convocatoria</span></div>

                    <div class="form-group">
                        <label class="form-label">Expediente:</label>
                        <input type="text" name="expediente" class="form-control" value="<?= htmlspecialchars($plan['expediente'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha Fin Conv.:</label>
                        <input type="date" name="fecha_fin_convocatoria" class="form-control" value="<?= htmlspecialchars($plan['fecha_fin_convocatoria'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tope horas/alumno:</label>
                        <input type="number" name="tope_horas_alumno" class="form-control" value="<?= htmlspecialchars($plan['tope_horas_alumno'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ámbito:</label>
                        <select name="ambito" class="form-control">
                            <option value="Estatal" <?= ($plan['ambito'] ?? '') == 'Estatal' ? 'selected' : '' ?>>Estatal</option>
                            <option value="Autonómico" <?= ($plan['ambito'] ?? '') == 'Autonómico' ? 'selected' : '' ?>>Autonómico</option>
                        </select>
                    </div>

                    <div class="form-group half-width">
                        <label class="form-label">Solicitante:</label>
                        <input type="text" name="solicitante" class="form-control" value="<?= htmlspecialchars($plan['solicitante'] ?? '') ?>">
                    </div>

                    <div class="form-group half-width">
                        <label class="form-label">Entidad:</label>
                        <input type="text" name="entidad" class="form-control" value="<?= htmlspecialchars($plan['entidad'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cod. Acceso:</label>
                        <input type="text" name="cod_acceso" class="form-control" value="<?= htmlspecialchars($plan['cod_acceso'] ?? '') ?>">
                    </div>

                    <div class="form-group three-quarter">
                        <div class="checkbox-row" style="background: none; border: none; padding: 0; margin-top: 1.8rem;">
                            <label class="checkbox-group"><input type="checkbox" name="programacion_automatica" <?= ($plan['programacion_automatica'] ?? 0) ? 'checked' : '' ?>> Programación automática</label>
                            <label class="checkbox-group"><input type="checkbox" name="mostrar" <?= ($plan['mostrar'] ?? 1) ? 'checked' : '' ?>> Mostrar</label>
                            <label class="checkbox-group"><input type="checkbox" name="nuestro" <?= ($plan['nuestro'] ?? 0) ? 'checked' : '' ?>> Nuestro</label>
                            <label class="checkbox-group"><input type="radio" name="activo" value="1" <?= ($plan['activo'] ?? 1) == 1 ? 'checked' : '' ?>> Activo: SÍ</label>
                            <label class="checkbox-group"><input type="radio" name="activo" value="0" <?= ($plan['activo'] ?? 1) == 0 ? 'checked' : '' ?>> NO</label>
                        </div>
                    </div>

                    <div class="section-separator"><span>Sectorización y Coordinación</span></div>

                    <div class="form-group half-width">
                        <label class="form-label">Sector:</label>
                        <input type="text" name="sector" class="form-control" value="<?= htmlspecialchars($plan['sector'] ?? '') ?>">
                    </div>

                    <div class="form-group half-width">
                        <label class="form-label">Grupo Sector:</label>
                        <input type="text" name="grupo_sector" class="form-control" value="<?= htmlspecialchars($plan['grupo_sector'] ?? '') ?>">
                    </div>

                    <div class="form-group half-width">
                        <label class="form-label">Coordinador:</label>
                        <input type="text" name="coordinador" class="form-control" value="<?= htmlspecialchars($plan['coordinador'] ?? '') ?>">
                    </div>

                    <div class="section-separator"><span>Porcentajes y Presupuesto</span></div>

                    <div class="form-group">
                        <label class="form-label">% Frar.:</label>
                        <input type="number" step="0.01" name="porc_frar" class="form-control" value="<?= htmlspecialchars($plan['porc_frar'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Calidad:</label>
                        <input type="number" step="0.01" name="porc_calidad" class="form-control" value="<?= htmlspecialchars($plan['porc_calidad'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Costes Ind.:</label>
                        <input type="number" step="0.01" name="porc_costes_indirectos" class="form-control" value="<?= htmlspecialchars($plan['porc_costes_indirectos'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-group" style="margin-top: 2rem;"><input type="checkbox" name="facturar_por_grupos" <?= ($plan['facturar_por_grupos'] ?? 0) ? 'checked' : '' ?>> Facturar por grupos</label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subvención:</label>
                        <input type="number" step="0.01" name="subvencion" class="form-control" value="<?= htmlspecialchars($plan['subvencion'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cofin. FSE:</label>
                        <input type="number" step="0.01" name="cofin_fse" class="form-control" value="<?= htmlspecialchars($plan['cofin_fse'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ejecutar Edite:</label>
                        <input type="number" step="0.01" name="ejecutar_edite" class="form-control" value="<?= htmlspecialchars($plan['ejecutar_edite'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cofinanciado Edite:</label>
                        <input type="number" step="0.01" name="cofinanciado_edite" class="form-control" value="<?= htmlspecialchars($plan['cofinanciado_edite'] ?? '0.00') ?>">
                    </div>

                    <div class="section-separator"><span>Criterios y Colectivos</span></div>

                    <div class="form-group">
                        <label class="form-label">% Prioridad Sect.:</label>
                        <input type="number" step="0.01" name="prioridad_sectorial" class="form-control" value="<?= htmlspecialchars($plan['prioridad_sectorial'] ?? '0.00') ?>">
                        <input type="number" step="0.01" name="prioridad_sectorial_colchon" class="form-control" value="<?= htmlspecialchars($plan['prioridad_sectorial_colchon'] ?? '0.00') ?>" style="margin-top:5px;" placeholder="Colchon">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Transversal:</label>
                        <input type="number" step="0.01" name="transversal" class="form-control" value="<?= htmlspecialchars($plan['transversal'] ?? '0.00') ?>">
                        <input type="number" step="0.01" name="transversal_colchon" class="form-control" value="<?= htmlspecialchars($plan['transversal_colchon'] ?? '0.00') ?>" style="margin-top:5px;" placeholder="Colchon">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Mínima:</label>
                        <input type="number" step="0.01" name="minima" class="form-control" value="<?= htmlspecialchars($plan['minima'] ?? '0.00') ?>">
                        <input type="number" step="0.01" name="minima_colchon" class="form-control" value="<?= htmlspecialchars($plan['minima_colchon'] ?? '0.00') ?>" style="margin-top:5px;" placeholder="Colchon">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Reconfiguración:</label>
                        <input type="number" step="0.01" name="reconfiguracion" class="form-control" value="<?= htmlspecialchars($plan['reconfiguracion'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% AU:</label>
                        <input type="number" step="0.01" name="porc_au" class="form-control" value="<?= htmlspecialchars($plan['porc_au'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Mujeres:</label>
                        <input type="number" step="0.01" name="porc_mujeres" class="form-control" value="<?= htmlspecialchars($plan['porc_mujeres'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Col. PrioritARIOS:</label>
                        <input type="number" step="0.01" name="porc_colectivos_prioritarios" class="form-control" value="<?= htmlspecialchars($plan['porc_colectivos_prioritarios'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">% Máx. Desempl.:</label>
                        <input type="number" step="0.01" name="porc_max_desempleados" class="form-control" value="<?= htmlspecialchars($plan['porc_max_desempleados'] ?? '0.00') ?>">
                    </div>

                    <div class="section-separator"><span>Referencias y Convenio</span></div>

                    <div class="form-group">
                        <label class="form-label">Cant. Ref. Cofin.:</label>
                        <input type="number" step="0.01" name="cant_ref_cofinanciada" class="form-control" value="<?= htmlspecialchars($plan['cant_ref_cofinanciada'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cant. Ref. NO Cofin.:</label>
                        <input type="number" step="0.01" name="cant_ref_no_cofinanciada" class="form-control" value="<?= htmlspecialchars($plan['cant_ref_no_cofinanciada'] ?? '0.00') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha Convenio:</label>
                        <input type="date" name="fecha_convenio" class="form-control" value="<?= htmlspecialchars($plan['fecha_convenio'] ?? '') ?>">
                    </div>

                    <div class="form-separator" style="grid-column: span 4; margin-top: 2rem;">
                        <h3 style="font-size: 0.9rem; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; text-transform: uppercase;">Datos Reconfiguración 2009-2010</h3>
                        <table class="reconfig-table">
                            <thead>
                                <tr>
                                    <th>Porcentajes \ Prioridades</th>
                                    <th>Mínima</th>
                                    <th>Media / Transversal</th>
                                    <th>Máxima / Sectorial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Mínimo</strong></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                </tr>
                                <tr>
                                    <td><strong>Máximo</strong></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                    <td><input type="text" class="reconfig-input" placeholder="-"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group full-width" style="margin-top: 2rem;">
                        <label class="form-label">Observaciones:</label>
                        <textarea name="observaciones" class="form-control" style="min-height: 120px;"><?= htmlspecialchars($plan['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid #e2e8f0; padding-top: 2rem;">
                    <button type="submit" class="btn-submit">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        GUARDAR PLAN DE FORMACIÓN
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>
