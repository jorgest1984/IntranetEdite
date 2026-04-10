<?php
require_once 'includes/auth.php';

// ─── Auto-setup tablas ────────────────────────────────────────────────────────
$pdo->query("CREATE TABLE IF NOT EXISTS telefonos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    numero      VARCHAR(20)  DEFAULT '',
    extension   VARCHAR(10)  DEFAULT '',
    imei        VARCHAR(25)  DEFAULT '',
    iccid       VARCHAR(30)  DEFAULT '',
    modelo      VARCHAR(100) DEFAULT '',
    operador    VARCHAR(50)  DEFAULT '',
    pin         VARCHAR(10)  DEFAULT '',
    puk         VARCHAR(10)  DEFAULT '',
    estado      ENUM('En uso','Disponible','Baja') DEFAULT 'Disponible',
    sede        VARCHAR(100) DEFAULT '',
    observaciones TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->query("CREATE TABLE IF NOT EXISTS telefono_asignaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    telefono_id INT NOT NULL,
    usuario_id  INT,
    nombre_usuario VARCHAR(200) DEFAULT '',
    desde       DATE,
    hasta       DATE
)");

// ─── Filtros ──────────────────────────────────────────────────────────────────
$f_numero    = trim($_GET['numero'] ?? '');
$f_extension = trim($_GET['extension'] ?? '');
$f_estado    = trim($_GET['estado'] ?? '');
$f_usuario   = trim($_GET['usuario'] ?? '');
$f_sede      = trim($_GET['sede'] ?? '');

$where = [];
$params = [];

if ($f_numero)    { $where[] = 't.numero LIKE ?';    $params[] = "%$f_numero%"; }
if ($f_extension) { $where[] = 't.extension LIKE ?'; $params[] = "%$f_extension%"; }
if ($f_estado)    { $where[] = 't.estado = ?';        $params[] = $f_estado; }
if ($f_sede)      { $where[] = 't.sede = ?';          $params[] = $f_sede; }
if ($f_usuario)   {
    $where[] = "EXISTS (SELECT 1 FROM telefono_asignaciones a WHERE a.telefono_id = t.id AND a.nombre_usuario LIKE ?)";
    $params[] = "%$f_usuario%";
}

$sql = "SELECT t.*, 
            (SELECT GROUP_CONCAT(nombre_usuario ORDER BY desde DESC SEPARATOR ', ')
             FROM telefono_asignaciones WHERE telefono_id = t.id LIMIT 1) AS usuario_actual,
            (SELECT sede FROM telefono_asignaciones WHERE telefono_id = t.id ORDER BY desde DESC LIMIT 1) AS sede_usr
        FROM telefonos t";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY t.extension ASC, t.numero ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$telefonos = $stmt->fetchAll();

// Sedes únicas para el filtro
$sedes = $pdo->query("SELECT DISTINCT sede FROM telefonos WHERE sede != '' ORDER BY sede")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Teléfonos - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem; }
        .filter-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .filter-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #64748b; margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .filter-group input, .filter-group select {
            width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #cbd5e1;
            border-radius: 6px; font-size: 0.9rem; background: #f8fafc; box-sizing: border-box;
        }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,0.12); background:#fff; }
        .filter-actions { display:flex; gap:0.5rem; }

        .data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.07); }
        .data-table thead { background: #1e293b; color: #fff; }
        .data-table th { padding: 0.85rem 1rem; font-weight: 600; font-size: 0.82rem; text-align: left; cursor: pointer; white-space: nowrap; }
        .data-table th:hover { background: #334155; }
        .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table tbody tr:hover { background: #fef2f2; }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .badge-estado { padding: 0.2rem 0.6rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
        .estado-en-uso    { background: #dcfce7; color: #166534; }
        .estado-disponible { background: #dbeafe; color: #1e40af; }
        .estado-baja       { background: #fee2e2; color: #991b1b; }

        .btn-edit { background: transparent; border: none; cursor: pointer; color: #2563eb; padding: 0.3rem; border-radius: 4px; transition: background 0.15s; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-edit svg { width: 18px; height: 18px; fill: currentColor; }

        .empty-state { text-align: center; padding: 3rem; color: #94a3b8; }
        .sede-link { color: #dc2626; text-decoration: none; font-weight: 500; }

        @media(max-width:768px) { .data-table { font-size: 0.8rem; } .data-table th, .data-table td { padding: 0.5rem 0.6rem; } }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Toolbar -->
        <div class="toolbar">
            <div>
                <h1 style="margin:0; font-size:1.4rem; font-weight:700;">Directorio de Teléfonos</h1>
                <p style="margin:0; color:#64748b; font-size:0.9rem;">Gestión del inventario de teléfonos corporativos</p>
            </div>
            <div style="display:flex;gap:0.6rem;">
                <a href="telefono_form.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Nuevo teléfono
                </a>
                <a href="telefonos.php?export=1" class="btn" style="background:#1e293b;color:#fff;border:none;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2h12c1.1 0 2-.9 2-2V8l-6-6zm.71 16.29-3-3 .7-.7 1.79 1.79V11h1v5.38l1.79-1.79.71.7-3 3-.29.29-.29-.29zM13 9V3.5L18.5 9H13z"/></svg>
                    Exportar
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <form method="GET" class="filter-bar">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Número</label>
                    <input type="text" name="numero" placeholder="Número de teléfono" value="<?= htmlspecialchars($f_numero) ?>">
                </div>
                <div class="filter-group">
                    <label>Extensión</label>
                    <input type="text" name="extension" placeholder="Introduzca extensión" value="<?= htmlspecialchars($f_extension) ?>">
                </div>
                <div class="filter-group">
                    <label>Estado</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="En uso" <?= $f_estado === 'En uso' ? 'selected' : '' ?>>En uso</option>
                        <option value="Disponible" <?= $f_estado === 'Disponible' ? 'selected' : '' ?>>Disponible</option>
                        <option value="Baja" <?= $f_estado === 'Baja' ? 'selected' : '' ?>>Baja</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Usuario</label>
                    <input type="text" name="usuario" placeholder="Nombre de usuario" value="<?= htmlspecialchars($f_usuario) ?>">
                </div>
                <div class="filter-group">
                    <label>Sede</label>
                    <select name="sede">
                        <option value="">Todas</option>
                        <?php foreach ($sedes as $s): ?>
                            <option value="<?= htmlspecialchars($s['sede']) ?>" <?= $f_sede === $s['sede'] ? 'selected' : '' ?>><?= htmlspecialchars($s['sede']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="telefonos.php" class="btn" style="background:#475569;color:#fff;border:none;">Mostrar todos los registros</a>
            </div>
        </form>

        <!-- Tabla -->
        <table class="data-table" id="tabla-telefonos">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">Extensión ↕</th>
                    <th onclick="sortTable(1)">Número ↕</th>
                    <th onclick="sortTable(2)">Estado ↕</th>
                    <th onclick="sortTable(3)">Usuario ↕</th>
                    <th onclick="sortTable(4)">Sede ↕</th>
                    <th>Observaciones</th>
                    <th style="text-align:center">Editar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($telefonos)): ?>
                    <tr><td colspan="7" class="empty-state">No se encontraron registros con los filtros aplicados.</td></tr>
                <?php else: ?>
                    <?php foreach ($telefonos as $t): ?>
                    <?php
                        $estadoMap = ['En uso' => 'estado-en-uso', 'Disponible' => 'estado-disponible', 'Baja' => 'estado-baja'];
                        $estadoClass = $estadoMap[$t['estado']] ?? '';
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['extension']) ?></strong></td>
                        <td><?= htmlspecialchars($t['numero']) ?></td>
                        <td><span class="badge-estado <?= $estadoClass ?>"><?= htmlspecialchars($t['estado']) ?></span></td>
                        <td><?= htmlspecialchars($t['usuario_actual'] ?? '') ?></td>
                        <td><span class="sede-link"><?= htmlspecialchars($t['sede']) ?></span></td>
                        <td style="max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($t['observaciones'] ?? '') ?>"><?= htmlspecialchars($t['observaciones'] ?? '') ?></td>
                        <td style="text-align:center">
                            <a href="telefono_form.php?id=<?= $t['id'] ?>" class="btn-edit" title="Editar">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:0.75rem; color:#94a3b8; font-size:0.8rem;"><?= count($telefonos) ?> registro(s) encontrado(s)</p>
    </main>
</div>

<script>
function sortTable(col) {
    const table = document.getElementById('tabla-telefonos');
    const rows  = Array.from(table.querySelectorAll('tbody tr'));
    const asc   = table.dataset.sortCol == col && table.dataset.sortDir === 'asc';
    table.dataset.sortCol = col;
    table.dataset.sortDir = asc ? 'desc' : 'asc';
    rows.sort((a, b) => {
        const va = a.cells[col]?.innerText.trim() ?? '';
        const vb = b.cells[col]?.innerText.trim() ?? '';
        return asc ? vb.localeCompare(va, 'es') : va.localeCompare(vb, 'es');
    });
    rows.forEach(r => table.querySelector('tbody').appendChild(r));
}
</script>
</body>
</html>
