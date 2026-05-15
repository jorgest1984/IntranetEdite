<?php
// convocatorias.php - v2.1 (Corrección Matriculas)
require_once 'includes/auth.php';
require_once 'includes/config.php';

if (!has_permission([ROLE_ADMIN, ROLE_TUTOR])) {
    header("Location: home.php");
    exit();
}

$error = '';
$success = '';

// Procesar formulario de nueva convocatoria
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create') {
        $codigo = trim($_POST['codigo_expediente'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? '';
        $organismo = trim($_POST['organismo'] ?? '');
        $presupuesto = empty($_POST['presupuesto']) ? 0 : floatval($_POST['presupuesto']);
        
        if (empty($codigo) || empty($nombre)) {
            $error = "El código y el nombre son obligatorios.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO convocatorias (codigo_expediente, nombre, tipo, organismo, presupuesto, estado) VALUES (?, ?, ?, ?, ?, 'Borrador')");
                $stmt->execute([$codigo, $nombre, $tipo, $organismo, $presupuesto]);
                $success = "Convocatoria creada correctamente.";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Listar convocatorias con estadísticas
$search = $_GET['search'] ?? '';
$tipoFilter = $_GET['tipo'] ?? '';

$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM grupos g WHERE g.accion_id IN (SELECT id FROM acciones_formativas WHERE plan_id IN (SELECT id FROM planes WHERE convocatoria_id = c.id))) as total_grupos,
        (SELECT COUNT(*) FROM matriculas WHERE grupo_id IN (SELECT id FROM grupos WHERE accion_id IN (SELECT id FROM acciones_formativas WHERE plan_id IN (SELECT id FROM planes WHERE convocatoria_id = c.id)))) as total_alumnos
        FROM convocatorias c WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (c.nombre LIKE ? OR c.codigo_expediente LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($tipoFilter) {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipoFilter;
}

$sql .= " ORDER BY c.creado_en DESC";
$convocatorias = $pdo->prepare($sql);
$convocatorias->execute($params);
$list = $convocatorias->fetchAll();

// Totales para KPIs
$total_presupuesto = array_sum(array_column($list, 'presupuesto'));
$total_alumnos = array_sum(array_column($list, 'total_alumnos'));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatorias - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .conv-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .conv-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 4px solid #1e3a8a;
        }

        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .conv-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-collapse: collapse;
        }

        .conv-table th {
            background: #f8fafc;
            padding: 15px;
            text-align: left;
            font-size: 0.7rem;
            color: #1e40af;
            text-transform: uppercase;
            border-bottom: 2px solid #f1f5f9;
        }

        .conv-table td {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-borrador { background: #f1f5f9; color: #64748b; }
        .status-activa { background: #dcfce7; color: #166534; }
        .status-finalizada { background: #fee2e2; color: #991b1b; }

        .btn-new-conv {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .form-new-card {
            background: #fff;
            border: 2px solid #e2e8f0;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: none;
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="home.php" title="Volver a Inicio" style="display: flex; align-items: center; justify-content: center; background: white; padding: 8px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                    <img src="img/logo_efp.png" alt="EFP Home" style="height: 40px; width: auto;">
                </a>
                <div class="page-title">
                    <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #1e3a8a;">Gestión de Convocatorias</h1>
                    <p style="margin: 0; color: #64748b; font-weight: 500;">Control administrativo de subvenciones y planes formativos</p>
                </div>
            </div>
            <button class="btn-new-conv" onclick="toggleForm()" style="background: #1e3a8a; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s;">+ Nueva Convocatoria</button>
        </header>

        <div class="conv-kpi-grid">
            <div class="conv-card">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Convocatorias</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #1e3a8a;"><?= count($list) ?></div>
            </div>
            <div class="conv-card" style="border-top-color: #10b981;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Presupuesto Gestionado</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #10b981;"><?= number_format($total_presupuesto, 2) ?> €</div>
            </div>
            <div class="conv-card" style="border-top-color: #f59e0b;">
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Alumnos Totales</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #f59e0b;"><?= number_format($total_alumnos) ?></div>
            </div>
        </div>

        <div id="formNueva" class="form-new-card">
            <h3 style="margin-top: 0; color: #1e3a8a;">Registrar Nueva Convocatoria</h3>
            <form action="" method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #64748b;">EXPEDIENTE</label>
                    <input type="text" name="codigo_expediente" class="form-control" required placeholder="Ej: F240001">
                </div>
                <div class="form-group">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #64748b;">PRESUPUESTO</label>
                    <input type="number" name="presupuesto" step="0.01" class="form-control" placeholder="0.00">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #64748b;">NOMBRE CONVOCATORIA</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Nombre descriptivo">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label style="font-size: 0.7rem; font-weight: 700; color: #64748b;">TIPO DE CONVOCATORIA</label>
                    <select name="tipo" class="form-control">
                        <option value="SEPE_DESEMPLEADOS">SEPE - Desempleados</option>
                        <option value="FUNDAE_OCUPADOS">FUNDAE - Ocupados</option>
                        <option value="PRIVADA">Privada</option>
                    </select>
                </div>
                <div style="grid-column: span 2; text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Registro</button>
                </div>
            </form>
        </div>

        <form class="filter-bar" method="GET" style="display: flex; gap: 15px; align-items: stretch;">
            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Buscar por nombre o expediente..." style="width: 100%;">
                <select name="tipo" class="form-control" style="width: 100%;">
                    <option value="">Todos los tipos</option>
                    <option value="SEPE_DESEMPLEADOS" <?= $tipoFilter=='SEPE_DESEMPLEADOS'?'selected':'' ?>>SEPE</option>
                    <option value="FUNDAE_OCUPADOS" <?= $tipoFilter=='FUNDAE_OCUPADOS'?'selected':'' ?>>FUNDAE</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height: auto;">Filtrar</button>
        </form>

        <table class="conv-table">
            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Convocatoria</th>
                    <th>Tipo / Organismo</th>
                    <th style="text-align: center;">Grupos</th>
                    <th style="text-align: center;">Alumnos</th>
                    <th style="text-align: center;">Estado</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $c): ?>
                <tr>
                    <td style="font-family: monospace; font-weight: 700; color: #64748b;"><?= htmlspecialchars($c['codigo_expediente']) ?></td>
                    <td>
                        <div style="font-weight: 700; color: #1e3a8a;"><?= htmlspecialchars($c['nombre']) ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;">Creada el <?= date('d/m/Y', strtotime($c['creado_en'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 600; font-size: 0.8rem;"><?= str_replace('_', ' ', $c['tipo']) ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($c['organismo'] ?? '---') ?></div>
                    </td>
                    <td style="text-align: center; font-weight: 700;"><?= $c['total_grupos'] ?></td>
                    <td style="text-align: center; font-weight: 700; color: #10b981;"><?= $c['total_alumnos'] ?></td>
                    <td style="text-align: center;">
                        <span class="badge-status status-<?= strtolower($c['estado']) ?>"><?= $c['estado'] ?></span>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <a href="editar_convocatoria.php?id=<?= $c['id'] ?>" class="btn-icon" title="Editar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                            <a href="planes.php?convocatoria_id=<?= $c['id'] ?>" class="btn-icon" title="Ver Planes" style="color: #3b82f6;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></a>
                            <a href="planes.php?convocatoria_id=<?= $c['id'] ?>&new=1" class="btn-icon" title="Añadir Plan" style="color: #10b981;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('formNueva');
    f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
}
</script>

</body>
</html>
