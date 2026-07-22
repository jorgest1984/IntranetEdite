<?php
// planes.php
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$success = '';
$error = '';

// Procesar nuevo o editar plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $convocatoria_id = (int)$_POST['convocatoria_id'];
        $codigo = trim($_POST['codigo'] ?? '');

        if ($nombre && $convocatoria_id) {
            try {
                // Validar duplicados de Nombre o Código en la misma convocatoria
                $checkSql = "SELECT id FROM planes WHERE (nombre = ? OR (codigo != '' AND codigo = ?)) AND convocatoria_id = ?";
                $stmtCheck = $pdo->prepare($checkSql);
                $stmtCheck->execute([$nombre, $codigo, $convocatoria_id]);
                if ($stmtCheck->fetch()) {
                    throw new Exception("Ya existe un plan con el mismo nombre o código en esta convocatoria.");
                }

                $stmt = $pdo->prepare("INSERT INTO planes (nombre, convocatoria_id, codigo) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $convocatoria_id, $codigo]);
                $success = "Plan estratégico creado con éxito.";
            } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
        }
    } elseif ($_POST['action'] === 'edit') {
        $plan_id = (int)$_POST['plan_id'];
        $nombre = trim($_POST['nombre'] ?? '');
        $convocatoria_id = (int)$_POST['convocatoria_id'];
        $codigo = trim($_POST['codigo'] ?? '');

        if ($plan_id && $nombre && $convocatoria_id) {
            try {
                $checkSql = "SELECT id FROM planes WHERE (nombre = ? OR (codigo != '' AND codigo = ?)) AND convocatoria_id = ? AND id != ?";
                $stmtCheck = $pdo->prepare($checkSql);
                $stmtCheck->execute([$nombre, $codigo, $convocatoria_id, $plan_id]);
                if ($stmtCheck->fetch()) {
                    throw new Exception("Ya existe otro plan con el mismo nombre o código en esta convocatoria.");
                }

                $stmt = $pdo->prepare("UPDATE planes SET nombre = ?, convocatoria_id = ?, codigo = ? WHERE id = ?");
                $stmt->execute([$nombre, $convocatoria_id, $codigo, $plan_id]);
                $success = "Plan estratégico actualizado con éxito.";
            } catch (Exception $e) { $error = "Error al actualizar: " . $e->getMessage(); }
        }
    }
}

// Procesar borrado por GET (más directo)
if (isset($_GET['delete_id'])) {
    $plan_id = (int)$_GET['delete_id'];
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM acciones_formativas WHERE plan_id = ?");
        $check->execute([$plan_id]);
        if ($check->fetchColumn() > 0) {
            $error = "No se puede borrar el plan porque tiene acciones formativas vinculadas.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM planes WHERE id = ?");
            $stmt->execute([$plan_id]);
            $success = "Plan eliminado con éxito.";
        }
    } catch (Exception $e) { $error = "Error al eliminar: " . $e->getMessage(); }
}

// Filtrado por convocatoria
$convocatoria_id = isset($_GET['convocatoria_id']) ? (int)$_GET['convocatoria_id'] : 0;
$convocatoria_filtrada = null;

if ($convocatoria_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmt->execute([$convocatoria_id]);
    $convocatoria_filtrada = $stmt->fetch();
}

// Obtener planes con su convocatoria y estadísticas
$sql = "SELECT p.*, c.nombre as convocatoria_nombre, c.codigo_expediente,
        (SELECT COUNT(*) FROM acciones_formativas WHERE plan_id = p.id) as num_acciones
        FROM planes p 
        JOIN convocatorias c ON p.convocatoria_id = c.id";

$params = [];
if ($convocatoria_id > 0) {
    $sql .= " WHERE p.convocatoria_id = ?";
    $params[] = $convocatoria_id;
}

$sql .= " ORDER BY p.id DESC";
$planes_stmt = $pdo->prepare($sql);
$planes_stmt->execute($params);
$planes = $planes_stmt->fetchAll();

$convocatorias = $pdo->query("SELECT id, nombre, codigo_expediente FROM convocatorias ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes Estratégicos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .plan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .plan-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: #3b82f6;
        }

        .plan-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, #1e3a8a, #3b82f6);
        }

        .conv-tag {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #3b82f6;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 12px;
        }

        .plan-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e3a8a;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .plan-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        .stat-item { text-align: center; }
        .stat-value { display: block; font-size: 1.2rem; font-weight: 800; color: #1e3a8a; }
        .stat-label { font-size: 0.65rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; }

        .btn-add-plan {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }

        .modal-form {
            background: var(--card-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: none;
        }

        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1 1 300px;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .filter-select {
            flex: 1;
            max-width: 400px;
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        .filter-select:hover {
            border-color: #94a3b8;
            background-color: #f1f5f9;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-clear-filter {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-clear-filter:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content">
        <?php if ($success): ?>
            <div style="background: #f0fdf4; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #bbf7d0; font-weight: 600;">
                ✓ <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fef2f2; color: #b91c1c; padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #fecaca; font-weight: 600;">
                ✕ <?= $error ?>
            </div>
        <?php endif; ?>

        <header class="page-header">
            <div class="page-title">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if ($convocatoria_filtrada): ?>
                        <a href="planes.php" class="btn-icon" title="Ver todos los planes" style="background: white; border: 1px solid #e2e8f0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        </a>
                    <?php endif; ?>
                    <div>
                        <h1>Planes Estratégicos <?= $convocatoria_filtrada ? ' - ' . htmlspecialchars($convocatoria_filtrada['nombre']) : '' ?></h1>
                        <p><?= $convocatoria_filtrada ? 'Mostrando planes para la convocatoria ' . htmlspecialchars($convocatoria_filtrada['codigo_expediente']) : 'Estructura de ejecución vinculada a convocatorias activas' ?></p>
                    </div>
                </div>
            </div>
            <button class="btn-add-plan" onclick="toggleForm()">+ Nuevo Plan</button>
        </header>

        <div class="filter-section">
            <form method="GET" action="" style="display: flex; align-items: center; gap: 15px; width: 100%; flex-wrap: wrap;">
                <div class="filter-group">
                    <label for="convocatoria_filter" class="filter-label">Filtrar por Convocatoria</label>
                    <select id="convocatoria_filter" name="convocatoria_id" class="filter-select" onchange="this.form.submit()">
                        <option value="0">-- Todas las convocatorias --</option>
                        <?php foreach($convocatorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($convocatoria_id == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?><?= !empty($c['codigo_expediente']) ? ' (' . htmlspecialchars($c['codigo_expediente']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($convocatoria_id > 0): ?>
                    <div class="filter-actions">
                        <a href="planes.php" class="btn-clear-filter">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            Limpiar Filtro
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div id="formPlan" class="modal-form">
            <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.2rem; font-weight: 800; text-transform: uppercase;">Crear Plan Estratégico</h2>
            <form id="planForm" action="" method="POST" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; width: 100%;">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="plan_id" id="formPlanId" value="">
                <div class="form-group" style="flex: 2 1 300px; display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Nombre del Plan</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Plan Digital 2024" style="width: 100%;">
                </div>
                <div class="form-group" style="flex: 2 1 300px; display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Convocatoria</label>
                    <select name="convocatoria_id" class="form-control" required style="width: 100%;">
                        <option value="">Seleccione...</option>
                        <?php foreach($convocatorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($convocatoria_id == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 120px; display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Código</label>
                    <input type="text" name="codigo" class="form-control" placeholder="PL-01" style="width: 100%;">
                </div>
                <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 30px; flex: 0 0 auto; min-width: 120px;">Guardar</button>
            </form>
        </div>

        <div class="plan-grid">
            <?php if (empty($planes)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: white; border-radius: 16px; border: 2px dashed #e2e8f0;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📂</div>
                    <h3 style="color: #1e3a8a; margin-bottom: 10px;">No se encontraron planes</h3>
                    <p style="color: #64748b;">Aún no hay planes estratégicos registrados para esta selección.</p>
                    <button class="btn btn-primary" onclick="toggleForm()" style="margin-top: 15px;">Crear primer plan</button>
                </div>
            <?php endif; ?>
            <?php foreach($planes as $p): ?>
            <div class="plan-card">
                <span class="conv-tag"><?= htmlspecialchars($p['convocatoria_nombre']) ?></span>
                <h3 class="plan-title"><?= htmlspecialchars($p['nombre']) ?></h3>
                <div style="font-size: 0.75rem; color: #64748b;">
                    <strong>Expediente:</strong> <?= htmlspecialchars($p['codigo_expediente']) ?><br>
                    <strong>Cód. Plan:</strong> <?= htmlspecialchars($p['codigo'] ?? '---') ?>
                </div>

                <div class="plan-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $p['num_acciones'] ?></span>
                        <span class="stat-label">Acciones</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="acciones_formativas.php?plan_id=<?= $p['id'] ?>" class="btn-icon" title="Ver Cursos"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></a>
                        <a href="javascript:void(0)" onclick="editPlan(<?= htmlspecialchars(json_encode(['id'=>$p['id'], 'nombre'=>$p['nombre'], 'convocatoria_id'=>$p['convocatoria_id'], 'codigo'=>$p['codigo']]), ENT_QUOTES, 'UTF-8') ?>)" class="btn-icon" style="color: #64748b;" title="Editar Plan">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </a>
                        <a href="?delete_id=<?= $p['id'] ?>" 
                           onclick="return confirm('¿Estás seguro de que deseas eliminar este plan? Esta acción no se puede deshacer.');" 
                           class="btn-icon" style="color: #ef4444;" title="Borrar Plan">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('formPlan');
    document.getElementById('formAction').value = 'create';
    document.getElementById('formPlanId').value = '';
    document.getElementById('planForm').reset();
    f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
}

function editPlan(plan) {
    const f = document.getElementById('formPlan');
    f.style.display = 'block';
    f.scrollIntoView({ behavior: 'smooth' });
    
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formPlanId').value = plan.id;
    document.querySelector('#planForm input[name="nombre"]').value = plan.nombre;
    document.querySelector('#planForm input[name="codigo"]').value = plan.codigo || '';
    document.querySelector('#planForm select[name="convocatoria_id"]').value = plan.convocatoria_id;
}

// Abrir formulario automáticamente si viene el parámetro 'new'
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('new')) {
        toggleForm();
    }
}
</script>

</body>
</html>
