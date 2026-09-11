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
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Error de seguridad (CSRF). Por favor, refresque la página e inténtelo de nuevo.";
    } else {
        try {
        $sql = "UPDATE convocatorias SET 
                nombre = ?, abreviatura = ?, anio = ?, 
                fecha_inicio_prevista = ?, fecha_fin_prevista = ?, 
                ambito = ?, solicitante = ?, url = ?, url_aula_virtual = ?, 
                activa = ?, descripcion = ?, requisitos = ?, estado = ?,
                codigo_expediente = ?, contenidos_diploma = ?
                WHERE id = ?";
        
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            $_POST['nombre'], $_POST['abreviatura'], $_POST['anio'],
            $_POST['fecha_inicio'], $_POST['fecha_fin'],
            $_POST['ambito'], $_POST['solicitante'], $_POST['url'], $_POST['url_aula_virtual'],
            isset($_POST['activa']) ? 1 : 0, $_POST['descripcion'], $_POST['requisitos'],
            $_POST['estado'] ?? 'Borrador',
            trim($_POST['codigo_expediente'] ?? ''),
            $_POST['contenidos_diploma'] ?? null,
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
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Convocatoria - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .edit-form-container { background: white; padding: 2rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        
        /* Tabs Styling */
        .tabs-header { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 0; }
        .tab-btn { 
            padding: 12px 24px; border: none; background: none; cursor: pointer; 
            font-weight: 700; color: #64748b; font-size: 0.9rem; border-bottom: 3px solid transparent; 
            transition: all 0.2s; 
        }
        .tab-btn:hover { color: #1e3a8a; background: #f8fafc; }
        .tab-btn.active { color: #1e3a8a; border-bottom-color: #1e3a8a; background: #eff6ff; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .form-row { display: flex; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid #f8fafc; padding-bottom: 0.75rem; }
        .form-label { width: 220px; font-size: 0.85rem; color: #1e3a8a; font-weight: 700; text-transform: uppercase; flex-shrink: 0; }
        .form-input-container { flex-grow: 1; position: relative; }
        .form-control { width: 100%; padding: 0.65rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; background: #fcfcfc; }
        .form-control:focus { outline: none; border-color: #1e3a8a; box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.08); background: white; }
        .input-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 0.35rem; font-style: italic; }
        
        .rte-mock { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .rte-toolbar { background: #f8fafc; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .toolbar-btn { padding: 0.25rem 0.5rem; border: 1px solid transparent; background: none; cursor:pointer; color: #475569; border-radius: 4px; }
        .toolbar-btn:hover { background: #e2e8f0; }
        .rte-textarea { width: 100%; min-height: 150px; padding: 1rem; border: none; font-family: inherit; resize: vertical; display: block; font-size: 0.95rem; }
        
        .btn-save { background: #1e3a8a; color: white; padding: 0.8rem 2rem; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; margin-top: 1rem; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2); transition: all 0.2s; }
        .btn-save:hover { background: #1e40af; transform: translateY(-1px); }

        /* Status Radio Styling */
        .status-options { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 10px; }
        .status-card { 
            border: 2px solid #e2e8f0; padding: 15px; border-radius: 12px; cursor: pointer; 
            transition: all 0.2s; display: flex; align-items: center; gap: 12px; position: relative;
        }
        .status-card:hover { border-color: #3b82f6; background: #f0f7ff; }
        .status-card.selected { border-color: #1e3a8a; background: #eff6ff; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.05); }
        .status-card input { position: absolute; opacity: 0; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; }
        .status-card.selected .status-indicator { background: #1e3a8a; box-shadow: 0 0 0 4px rgba(30, 58, 138, 0.2); }
        .status-label { font-weight: 700; font-size: 0.9rem; color: #475569; }
        .status-card.selected .status-label { color: #1e3a8a; }

        .planes-section { margin-top: 1rem; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .section-title { font-size: 1.25rem; font-weight: 700; color: #1e3a8a; display: flex; align-items: center; gap: 0.75rem; }
        .section-title::before { content: ''; display: block; width: 4px; height: 1.25rem; background: #ef4444; border-radius: 2px; }

        .planes-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .planes-table th { background: #f8fafc; color: #1e3a8a; text-align: left; padding: 1rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        .planes-table td { padding: 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem; transition: background 0.2s; }
        .planes-table tr:hover td { background: #f8fafc; }
        .btn-new-plan { background: #1e3a8a; color: white; padding: 0.6rem 1.2rem; border:none; border-radius:8px; font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:0.5rem; font-weight: 700; }

        .alert { padding: 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
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
                <p><?= htmlspecialchars($convocatoria['nombre'] ?? '') ?></p>
            </div>
            <a href="convocatorias.php" class="btn btn-neutral" style="border: 1px solid #ccc; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; color: #666;">Volver al listado</a>
        </header>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="edit-form-container">
            <div class="tabs-header">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'datos')">Datos Generales</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'estado-tab')">Estado</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'planes-tab')">Planes</button>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="update">
                
                <div id="datos" class="tab-pane active">
                    <div class="form-row">
                        <label class="form-label">Convocatoria</label>
                        <div class="form-input-container">
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($convocatoria['nombre'] ?? '') ?>">
                            <div class="input-hint">Nombre de la convocatoria</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label">Código Expediente</label>
                        <div class="form-input-container">
                            <input type="text" name="codigo_expediente" class="form-control" value="<?= htmlspecialchars($convocatoria['codigo_expediente'] ?? '') ?>" placeholder="Ej: F240001" required>
                            <div class="input-hint">Código único identificador del expediente</div>
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

                    <div class="form-row">
                        <label class="form-label">Contenidos del Diploma</label>
                        <div class="form-input-container">
                            <textarea name="contenidos_diploma" class="form-control" rows="6" placeholder="Introduce el temario o contenidos del certificado..."><?= htmlspecialchars($convocatoria['contenidos_diploma'] ?? '') ?></textarea>
                            <div class="input-hint">El texto que aparecerá en el margen derecho del Certificado y Diploma.</div>
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
                </div>

                <div id="estado-tab" class="tab-pane">
                    <h3 style="color: #1e3a8a; margin-top: 0;">Estado de la Convocatoria</h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 25px;">Cambia el estado para controlar el flujo de trabajo de esta convocatoria.</p>
                    
                    <div class="status-options">
                        <?php 
                        $estados = [
                            'Borrador' => 'Fase de preparación inicial.',
                            'Aprobada' => 'Validada y lista para ejecución.',
                            'En Ejecución' => 'Cursos y grupos activos.',
                            'Finalizada' => 'Plazo de ejecución terminado.',
                            'Justificada' => 'Documentación presentada.'
                        ];
                        foreach($estados as $est => $desc): 
                            $isSelected = ($convocatoria['estado'] == $est);
                        ?>
                        <label class="status-card <?= $isSelected ? 'selected' : '' ?>">
                            <input type="radio" name="estado" value="<?= $est ?>" <?= $isSelected ? 'checked' : '' ?> onchange="updateStatusVisuals(this)">
                            <div class="status-indicator"></div>
                            <div>
                                <div class="status-label"><?= $est ?></div>
                                <div style="font-size: 0.7rem; color: #94a3b8;"><?= $desc ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="planes-tab" class="tab-pane">
                    <section class="planes-section">
                        <div class="section-header">
                            <h2 class="section-title">Planes Asociados</h2>
                            <a href="editar_plan.php?convocatoria_id=<?= $id ?>" class="btn-new-plan">
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
                                    <tr><td colspan="4" style="text-align: center; color: #64748b; padding: 3rem;">No hay planes asociados a esta convocatoria.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($planes as $plan): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 700; color: #64748b;"><?= htmlspecialchars($plan['codigo'] ?? '') ?></td>
                                        <td><a href="editar_plan.php?id=<?= $plan['id'] ?>&convocatoria_id=<?= $id ?>" style="color: #1e3a8a; text-decoration: none; font-weight: 700;"><?= htmlspecialchars($plan['nombre'] ?? '') ?></a></td>
                                        <td>
                                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; background: <?= $plan['activo'] ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $plan['activo'] ? '#166534' : '#64748b' ?>;">
                                                <?= $plan['activo'] ? 'ACTIVO' : 'INACTIVO' ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="informe_ejecucion_plan.php?convocatoria=<?= $id ?>&plan=<?= $plan['id'] ?>" class="icon-btn" style="color: #10b981; margin-right: 8px;" title="Ver Informe de Ejecución del Plan">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                            </a>
                                            <a href="editar_plan.php?id=<?= $plan['id'] ?>&convocatoria_id=<?= $id ?>" class="icon-btn" style="color: #1e3a8a;" title="Editar Plan">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <button type="button" class="icon-btn" onclick="deletePlan(<?= $plan['id'] ?>, '<?= htmlspecialchars(addslashes($plan['nombre'] ?? '')) ?>')" style="color: #ef4444; border:none; background:none; cursor:pointer;" title="Borrar Plan">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>
                </div>

                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-save">Actualizar Convocatoria</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function switchTab(evt, tabName) {
    const tabPanes = document.querySelectorAll('.tab-pane');
    tabPanes.forEach(pane => pane.classList.remove('active'));

    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => btn.classList.remove('active'));

    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}

function updateStatusVisuals(radio) {
    const cards = document.querySelectorAll('.status-card');
    cards.forEach(card => card.classList.remove('selected'));
    if (radio.checked) {
        radio.closest('.status-card').classList.add('selected');
    }
}

async function deletePlan(id, nombre) {
    if (!confirm(`¿Estás seguro de que deseas eliminar el plan "${nombre}"? Esta acción no se puede deshacer.`)) {
        return;
    }

    try {
        const response = await fetch('api_delete_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        if (result.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión al intentar borrar el plan.');
    }
}
</script>
</body>
</html>
