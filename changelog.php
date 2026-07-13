<?php
// changelog.php — ISO 27001 A.8.32 Control de Cambios
require_once 'includes/auth.php';
require_once 'includes/config.php';

$current_user_id  = $_SESSION['user_id'];
$current_user_rol = $_SESSION['rol_id'];
$current_username = $_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Usuario';
$is_admin = has_permission([ROLE_ADMIN, ROLE_COORD]);

// ── Auto-crear la tabla si no existe ──
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS changelog (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            version          VARCHAR(20) NOT NULL,
            entorno          ENUM('produccion','pre','local') NOT NULL DEFAULT 'produccion',
            tipo             ENUM('feature','fix','security','database','hotfix','refactor','docs') NOT NULL,
            titulo           VARCHAR(255) NOT NULL,
            descripcion      TEXT,
            usuario_id       INT NOT NULL,
            estado           ENUM('pendiente','desplegado','revertido') DEFAULT 'pendiente',
            fecha_registro   DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_despliegue DATETIME NULL,
            referencia       VARCHAR(200) NULL,
            git_commit       VARCHAR(50) NULL
        )
    ");
} catch (Exception $e) { /* tabla ya existe */ }

// Detectar entorno actual
$host = $_SERVER['HTTP_HOST'] ?? '';
$entorno_actual = 'produccion';
if (in_array($host, ['localhost', '127.0.0.1', 'localhost:8000'])) $entorno_actual = 'local';
elseif ($host === 'pre-gestion.grupoefp.es') $entorno_actual = 'pre';

// Detectar último commit Git (si está disponible)
$git_commit = '';
try {
    $git_out = @shell_exec('git -C ' . escapeshellarg(__DIR__) . ' log --oneline -1 2>/dev/null');
    if ($git_out) $git_commit = trim(substr($git_out, 0, 80));
} catch (Exception $e) {}

// ── Procesar acciones POST ──
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && $is_admin) {
        $version     = trim($_POST['version'] ?? '');
        $entorno     = $_POST['entorno'] ?? 'produccion';
        $tipo        = $_POST['tipo'] ?? 'feature';
        $titulo      = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $referencia  = trim($_POST['referencia'] ?? '');
        $git_ref     = trim($_POST['git_commit'] ?? '');
        $estado      = $_POST['estado'] ?? 'pendiente';

        if ($version && $titulo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO changelog (version, entorno, tipo, titulo, descripcion, usuario_id, estado, referencia, git_commit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$version, $entorno, $tipo, $titulo, $descripcion, $current_user_id, $estado, $referencia ?: null, $git_ref ?: null]);
                audit_log($pdo, 'CREATE', 'changelog', $pdo->lastInsertId(), null, compact('version','tipo','titulo'));
                $success = 'Entrada de changelog creada correctamente.';
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        } else {
            $error = 'La versión y el título son obligatorios.';
        }
    } elseif ($action === 'update_estado' && $is_admin) {
        $id     = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($id && in_array($estado, ['pendiente','desplegado','revertido'])) {
            $fecha_desp = ($estado === 'desplegado') ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE changelog SET estado=?, fecha_despliegue=? WHERE id=?")
                ->execute([$estado, $fecha_desp, $id]);
            $success = 'Estado actualizado.';
        }
    } elseif ($action === 'delete' && $is_admin) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM changelog WHERE id=?")->execute([$id]);
            audit_log($pdo, 'DELETE', 'changelog', $id);
            $success = 'Entrada eliminada.';
        }
    }
}

// ── Filtros ──
$f_entorno = $_GET['entorno'] ?? '';
$f_tipo    = $_GET['tipo']    ?? '';
$f_estado  = $_GET['estado']  ?? '';
$f_q       = trim($_GET['q'] ?? '');
$f_desde   = $_GET['desde']   ?? '';
$f_hasta   = $_GET['hasta']   ?? '';

$where  = ['1=1'];
$params = [];

if ($f_entorno) { $where[] = 'entorno = ?'; $params[] = $f_entorno; }
if ($f_tipo)    { $where[] = 'tipo = ?';    $params[] = $f_tipo; }
if ($f_estado)  { $where[] = 'estado = ?';  $params[] = $f_estado; }
if ($f_q)       { $where[] = '(titulo LIKE ? OR descripcion LIKE ?)'; $params[] = "%$f_q%"; $params[] = "%$f_q%"; }
if ($f_desde)   { $where[] = 'DATE(fecha_registro) >= ?'; $params[] = $f_desde; }
if ($f_hasta)   { $where[] = 'DATE(fecha_registro) <= ?'; $params[] = $f_hasta; }

$sql = "SELECT c.*, u.nombre, u.apellidos, u.username
        FROM changelog c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.fecha_registro DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Etiquetas de tipos
$tipo_labels = [
    'feature'  => ['🚀 Nueva Función',   '#006ce4', 'rgba(0,108,228,0.12)'],
    'fix'      => ['🔧 Corrección',       '#f59e0b', 'rgba(245,158,11,0.12)'],
    'security' => ['🔒 Seguridad',        '#dc2626', 'rgba(220,38,38,0.12)'],
    'database' => ['🗄️ Base de Datos',   '#8b5cf6', 'rgba(139,92,246,0.12)'],
    'hotfix'   => ['🚨 Hotfix',           '#ef4444', 'rgba(239,68,68,0.12)'],
    'refactor' => ['♻️ Refactorización', '#64748b', 'rgba(100,116,139,0.12)'],
    'docs'     => ['📄 Documentación',    '#10b981', 'rgba(16,185,129,0.12)'],
];
$estado_labels = [
    'pendiente'  => ['Pendiente',  '#f59e0b', 'rgba(245,158,11,0.12)'],
    'desplegado' => ['Desplegado', '#10b981', 'rgba(16,185,129,0.12)'],
    'revertido'  => ['Revertido',  '#ef4444', 'rgba(239,68,68,0.12)'],
];
$entorno_labels = [
    'produccion' => ['Producción', '#006ce4'],
    'pre'        => ['Pre-producción', '#8b5cf6'],
    'local'      => ['Local', '#64748b'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog ISO 27001 — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ── PAGE ── */
        .changelog-main { flex: 1; overflow-y: auto; padding: 2rem; }

        /* ── HERO HEADER ── */
        .cl-hero {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        .cl-hero::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #006ce4, #8b5cf6, #10b981, #dc2626);
        }
        .cl-hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 0.3rem 0;
        }
        .cl-hero-sub { font-size: 0.85rem; color: var(--text-muted); margin: 0; }
        .cl-iso-badge {
            background: linear-gradient(135deg, rgba(0,108,228,0.1), rgba(139,92,246,0.1));
            border: 1px solid rgba(0,108,228,0.2);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex; align-items: center; gap: 6px;
        }

        /* ── STATS ROW ── */
        .cl-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .cl-stat {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1.2rem 1.5rem;
            text-align: center;
        }
        .cl-stat-num {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }
        .cl-stat-lbl { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── FILTERS ── */
        .cl-filters {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            align-items: flex-end;
        }
        .cl-filter-group { display: flex; flex-direction: column; gap: 4px; }
        .cl-filter-group label { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .cl-filter-group select,
        .cl-filter-group input {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.45rem 0.9rem;
            font-size: 0.83rem;
            color: var(--text-color);
            min-width: 130px;
        }
        .cl-filter-group input[type=text] { min-width: 180px; }
        .cl-btn-filter {
            background: linear-gradient(135deg, var(--primary-color), #4f8ef7);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.2rem;
            font-weight: 700;
            font-size: 0.83rem;
            cursor: pointer;
            transition: all 0.2s;
            align-self: flex-end;
        }
        .cl-btn-filter:hover { transform: translateY(-1px); opacity: 0.9; }
        .cl-btn-clear { background: var(--glass-bg); color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.8rem; cursor: pointer; align-self: flex-end; }

        /* ── TIMELINE ── */
        .cl-timeline { position: relative; padding-left: 2rem; }
        .cl-timeline::before {
            content: '';
            position: absolute;
            left: 7px; top: 0; bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary-color), rgba(0,108,228,0.1));
            border-radius: 2px;
        }
        .cl-entry {
            position: relative;
            margin-bottom: 1.5rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem 1.8rem;
            transition: all 0.25s ease;
        }
        .cl-entry:hover { transform: translateX(4px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .cl-entry::before {
            content: '';
            position: absolute;
            left: -2.38rem;
            top: 1.5rem;
            width: 12px; height: 12px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            background: var(--bg-color);
        }

        .cl-entry-head {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.8rem;
        }
        .cl-version {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary-color);
            background: rgba(0,108,228,0.07);
            padding: 3px 10px;
            border-radius: 6px;
            border: 1px solid rgba(0,108,228,0.15);
            white-space: nowrap;
        }
        .cl-badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 50px;
            white-space: nowrap;
        }
        .cl-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-color);
            flex: 1;
            line-height: 1.4;
        }
        .cl-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin: 0.5rem 0 0.8rem 0;
            white-space: pre-wrap;
        }
        .cl-meta {
            display: flex;
            gap: 1.2rem;
            flex-wrap: wrap;
            font-size: 0.75rem;
            color: var(--text-muted);
            align-items: center;
        }
        .cl-meta span { display: flex; align-items: center; gap: 4px; }
        .cl-commit {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            background: rgba(100,116,139,0.08);
            border: 1px solid var(--border-color);
            padding: 2px 8px;
            border-radius: 4px;
            color: var(--text-muted);
        }
        .cl-actions { display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; align-items: center; }

        /* ── EMPTY STATE ── */
        .cl-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .cl-empty svg { opacity: 0.3; margin-bottom: 1rem; }
        .cl-empty p { font-size: 0.9rem; }

        /* ── MODAL ── */
        .cl-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .cl-modal-overlay.open { display: flex; }
        .cl-modal {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            max-width: 640px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }
        .cl-modal h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            margin: 0 0 1.5rem 0;
            color: var(--text-color);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 1rem; }
        .form-group label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            font-size: 0.88rem;
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: var(--primary-color); }
        .form-group textarea { min-height: 100px; resize: vertical; }

        .btn-primary-modal {
            background: linear-gradient(135deg, var(--primary-color), #4f8ef7);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0.7rem 1.8rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 0.5rem;
        }
        .btn-primary-modal:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,108,228,0.3); }
        .btn-cancel { background: var(--glass-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.7rem 1.8rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; color: var(--text-muted); margin-top: 0.5rem; }

        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 10px; padding: 0.75rem 1.2rem; color: #059669; font-weight: 600; font-size: 0.85rem; margin-bottom: 1.2rem; }
        .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  border-radius: 10px; padding: 0.75rem 1.2rem; color: #dc2626; font-weight: 600; font-size: 0.85rem; margin-bottom: 1.2rem; }

        .btn-sm-action {
            font-size: 0.72rem;
            padding: 3px 10px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background: var(--glass-bg);
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-sm-action:hover { color: var(--text-color); border-color: var(--primary-color); }
        .btn-sm-delete:hover { color: #dc2626; border-color: #dc2626; }

        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .cl-timeline { padding-left: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/fp_sidebar.php'; ?>

    <main class="main-content changelog-main">

        <!-- Breadcrumb -->
        <div style="margin-bottom: 1.5rem; background: var(--glass-bg); padding: 0.75rem 1.5rem; border-radius: 10px; border: 1px solid var(--glass-border); font-size: 0.85rem; display: flex; gap: 8px; align-items: center;">
            <a href="home.php" style="color: var(--primary-color); text-decoration: none;">Inicio</a>
            <span style="color: var(--text-muted);">/</span>
            <span style="color: var(--text-color); font-weight: 600;">Changelog ISO 27001</span>
        </div>

        <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error):   ?><div class="alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Hero -->
        <div class="cl-hero">
            <div>
                <h1 class="cl-hero-title">Changelog del Sistema</h1>
                <p class="cl-hero-sub">Registro de cambios conforme a <strong>ISO 27001:2022 · Control A.8.32</strong> — Gestión del cambio</p>
                <p class="cl-hero-sub" style="margin-top: 4px;">
                    Entorno actual:
                    <strong style="color: <?= $entorno_labels[$entorno_actual][1] ?>">
                        <?= $entorno_labels[$entorno_actual][0] ?>
                    </strong>
                    <?php if ($git_commit): ?>
                        &nbsp;·&nbsp; <span class="cl-commit"><?= htmlspecialchars($git_commit) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span class="cl-iso-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    ISO 27001:2022
                </span>
                <a href="pdf_changelog.php<?= $f_entorno || $f_tipo || $f_estado || $f_desde || $f_hasta ? '?' . http_build_query(['entorno'=>$f_entorno,'tipo'=>$f_tipo,'estado'=>$f_estado,'desde'=>$f_desde,'hasta'=>$f_hasta]) : '' ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:0.55rem 1.2rem;border-radius:8px;font-weight:700;font-size:0.82rem;text-decoration:none;transition:all 0.2s;"
                   onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Exportar PDF
                </a>
                <?php if ($is_admin): ?>
                <button onclick="document.getElementById('modalNuevo').classList.add('open')"
                        style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,var(--primary-color),#4f8ef7);color:#fff;border:none;padding:0.55rem 1.3rem;border-radius:8px;font-weight:700;font-size:0.82rem;cursor:pointer;transition:all 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nueva Entrada
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <?php
        $total_entries  = count($entries);
        $desplegados    = count(array_filter($entries, fn($e) => $e['estado'] === 'desplegado'));
        $pendientes     = count(array_filter($entries, fn($e) => $e['estado'] === 'pendiente'));
        $security_count = count(array_filter($entries, fn($e) => in_array($e['tipo'], ['security','hotfix'])));
        ?>
        <div class="cl-stats">
            <div class="cl-stat">
                <div class="cl-stat-num" style="color: var(--primary-color);"><?= $total_entries ?></div>
                <div class="cl-stat-lbl">Total entradas</div>
            </div>
            <div class="cl-stat">
                <div class="cl-stat-num" style="color: #10b981;"><?= $desplegados ?></div>
                <div class="cl-stat-lbl">Desplegados</div>
            </div>
            <div class="cl-stat">
                <div class="cl-stat-num" style="color: #f59e0b;"><?= $pendientes ?></div>
                <div class="cl-stat-lbl">Pendientes</div>
            </div>
            <div class="cl-stat">
                <div class="cl-stat-num" style="color: #dc2626;"><?= $security_count ?></div>
                <div class="cl-stat-lbl">Seg. / Hotfix</div>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="cl-filters">
            <div class="cl-filter-group">
                <label>Buscar</label>
                <input type="text" name="q" value="<?= htmlspecialchars($f_q) ?>" placeholder="Título o descripción…">
            </div>
            <div class="cl-filter-group">
                <label>Entorno</label>
                <select name="entorno">
                    <option value="">Todos</option>
                    <option value="produccion" <?= $f_entorno==='produccion'?'selected':'' ?>>Producción</option>
                    <option value="pre"        <?= $f_entorno==='pre'?'selected':'' ?>>Pre-producción</option>
                    <option value="local"      <?= $f_entorno==='local'?'selected':'' ?>>Local</option>
                </select>
            </div>
            <div class="cl-filter-group">
                <label>Tipo</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tipo_labels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $f_tipo===$k?'selected':'' ?>><?= $v[0] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cl-filter-group">
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_estado==='pendiente'?'selected':'' ?>>Pendiente</option>
                    <option value="desplegado" <?= $f_estado==='desplegado'?'selected':'' ?>>Desplegado</option>
                    <option value="revertido"  <?= $f_estado==='revertido'?'selected':'' ?>>Revertido</option>
                </select>
            </div>
            <div class="cl-filter-group">
                <label>Desde</label>
                <input type="date" name="desde" value="<?= htmlspecialchars($f_desde) ?>">
            </div>
            <div class="cl-filter-group">
                <label>Hasta</label>
                <input type="date" name="hasta" value="<?= htmlspecialchars($f_hasta) ?>">
            </div>
            <button type="submit" class="cl-btn-filter">🔍 Filtrar</button>
            <a href="changelog.php" class="cl-btn-clear">Limpiar</a>
        </form>

        <!-- Timeline -->
        <?php if (empty($entries)): ?>
        <div class="cl-empty">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 17H5a2 2 0 0 0-2 2"/><path d="M21 17h-4a2 2 0 0 1-2 2"/><path d="M12 2v8"/><path d="m4.93 10.93 1.41 1.41"/><path d="M2 18h2"/><path d="M20 18h2"/><path d="m19.07 10.93-1.41 1.41"/><path d="M22 22H2"/><path d="m8 22 4-10 4 10"/></svg>
            <p>No hay entradas de changelog con los filtros seleccionados.</p>
            <?php if ($is_admin): ?>
            <button onclick="document.getElementById('modalNuevo').classList.add('open')" style="margin-top:1rem;background:var(--primary-color);color:#fff;border:none;border-radius:8px;padding:0.6rem 1.5rem;font-weight:700;cursor:pointer;">+ Nueva Entrada</button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="cl-timeline">
            <?php foreach ($entries as $entry):
                $tl = $tipo_labels[$entry['tipo']] ?? ['Otro', '#64748b', 'rgba(100,116,139,0.1)'];
                $sl = $estado_labels[$entry['estado']] ?? ['?', '#64748b', 'rgba(100,116,139,0.1)'];
                $el = $entorno_labels[$entry['entorno']] ?? ['?', '#64748b'];
                $autor = trim(($entry['nombre'] ?? '') . ' ' . ($entry['apellidos'] ?? '')) ?: ($entry['username'] ?? 'Desconocido');
            ?>
            <div class="cl-entry">
                <div class="cl-entry-head">
                    <span class="cl-version"><?= htmlspecialchars($entry['version']) ?></span>
                    <span class="cl-badge" style="color:<?= $tl[1] ?>;background:<?= $tl[2] ?>;border:1px solid <?= $tl[1] ?>33;"><?= $tl[0] ?></span>
                    <span class="cl-badge" style="color:<?= $sl[1] ?>;background:<?= $sl[2] ?>;border:1px solid <?= $sl[1] ?>33;"><?= $sl[0] ?></span>
                    <span class="cl-badge" style="color:<?= $el[1] ?>;background:<?= $el[1] ?>18;border:1px solid <?= $el[1] ?>33;"><?= $el[0] ?></span>
                    <div class="cl-actions">
                        <?php if ($is_admin): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_estado">
                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                            <select name="estado" onchange="this.form.submit()" class="btn-sm-action" style="min-width:120px;cursor:pointer;">
                                <option value="pendiente"  <?= $entry['estado']==='pendiente'?'selected':'' ?>>⏳ Pendiente</option>
                                <option value="desplegado" <?= $entry['estado']==='desplegado'?'selected':'' ?>>✅ Desplegado</option>
                                <option value="revertido"  <?= $entry['estado']==='revertido'?'selected':'' ?>>↩️ Revertido</option>
                            </select>
                        </form>
                        <form method="POST" onsubmit="return confirm('¿Eliminar esta entrada del changelog?')" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                            <button type="submit" class="btn-sm-action btn-sm-delete">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cl-title"><?= htmlspecialchars($entry['titulo']) ?></div>
                <?php if ($entry['descripcion']): ?>
                <div class="cl-desc"><?= nl2br(htmlspecialchars($entry['descripcion'])) ?></div>
                <?php endif; ?>
                <div class="cl-meta">
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= date('d/m/Y H:i', strtotime($entry['fecha_registro'])) ?>
                    </span>
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <?= htmlspecialchars($autor) ?>
                    </span>
                    <?php if ($entry['fecha_despliegue']): ?>
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Desplegado: <?= date('d/m/Y H:i', strtotime($entry['fecha_despliegue'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($entry['referencia']): ?>
                    <span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <?= htmlspecialchars($entry['referencia']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($entry['git_commit']): ?>
                    <span class="cl-commit" title="Git commit"><?= htmlspecialchars(substr($entry['git_commit'],0,50)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- Modal Nueva Entrada -->
<?php if ($is_admin): ?>
<div id="modalNuevo" class="cl-modal-overlay">
    <div class="cl-modal">
        <h3>📝 Nueva Entrada de Changelog</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Versión *</label>
                    <input type="text" name="version" placeholder="ej. v1.4.2" required style="font-family:'JetBrains Mono',monospace;">
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" required>
                        <?php foreach ($tipo_labels as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Entorno *</label>
                    <select name="entorno">
                        <option value="produccion" <?= $entorno_actual==='produccion'?'selected':'' ?>>Producción</option>
                        <option value="pre"        <?= $entorno_actual==='pre'?'selected':'' ?>>Pre-producción</option>
                        <option value="local"      <?= $entorno_actual==='local'?'selected':'' ?>>Local</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado inicial</label>
                    <select name="estado">
                        <option value="pendiente">⏳ Pendiente</option>
                        <option value="desplegado">✅ Desplegado</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Título del cambio *</label>
                <input type="text" name="titulo" placeholder="Descripción breve del cambio…" required>
            </div>
            <div class="form-group">
                <label>Descripción detallada</label>
                <textarea name="descripcion" placeholder="Explica en detalle qué se ha cambiado, por qué y el impacto esperado…"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Referencia (ticket/tarea)</label>
                    <input type="text" name="referencia" placeholder="ej. TASK-123">
                </div>
                <div class="form-group">
                    <label>Commit Git</label>
                    <input type="text" name="git_commit" value="<?= htmlspecialchars($git_commit) ?>" placeholder="hash o rama" style="font-family:'JetBrains Mono',monospace;font-size:0.78rem;">
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:0.5rem;">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalNuevo').classList.remove('open')">Cancelar</button>
                <button type="submit" class="btn-primary-modal">💾 Guardar entrada</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('modalNuevo').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
<?php endif; ?>

</body>
</html>
