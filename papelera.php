<?php
// papelera.php - Interfaz de la Papelera de Reciclaje
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/Papelera.php';

if (!has_permission([ROLE_ADMIN])) {
    header("Location: home.php");
    exit();
}

// Asegurar que la tabla existe
Papelera::checkTable($pdo);

$success = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Procesar Acciones
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id > 0) {
        if ($action === 'restore') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM papelera WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    Papelera::restaurar($pdo, $id);
                    audit_log($pdo, 'RESTORE_ITEM', $item['tabla'], $item['elemento_id'], null, ['titulo' => $item['titulo']]);
                    header("Location: papelera.php?msg=" . urlencode("Elemento '" . $item['titulo'] . "' restaurado correctamente."));
                    exit();
                } else {
                    throw new Exception("El elemento no existe en la papelera.");
                }
            } catch (Exception $e) {
                header("Location: papelera.php?error=" . urlencode("Error al restaurar: " . $e->getMessage()));
                exit();
            }
        } elseif ($action === 'purge') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM papelera WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $stmtDel = $pdo->prepare("DELETE FROM papelera WHERE id = ?");
                    $stmtDel->execute([$id]);
                    audit_log($pdo, 'PURGE_ITEM', $item['tabla'], $item['elemento_id'], null, ['titulo' => $item['titulo']]);
                    header("Location: papelera.php?msg=" . urlencode("Elemento '" . $item['titulo'] . "' eliminado definitivamente de la base de datos."));
                    exit();
                } else {
                    throw new Exception("El elemento no existe en la papelera.");
                }
            } catch (Exception $e) {
                header("Location: papelera.php?error=" . urlencode("Error al eliminar permanentemente: " . $e->getMessage()));
                exit();
            }
        }
    }
}

// Obtener listado de elementos en papelera
$stmt = $pdo->query("SELECT p.*, u.username as deleted_by_username 
                     FROM papelera p 
                     LEFT JOIN usuarios u ON p.usuario_id = u.id 
                     ORDER BY p.fecha_borrado DESC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar estadísticas
$count_af = 0;
$count_conv = 0;
$count_users = 0;
$count_mat = 0;
foreach ($items as $item) {
    if ($item['tabla'] === 'acciones_formativas') $count_af++;
    elseif ($item['tabla'] === 'convocatorias') $count_conv++;
    elseif ($item['tabla'] === 'usuarios') $count_users++;
    elseif ($item['tabla'] === 'matriculas') $count_mat++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" href="/img/logo_efp.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papelera de Reciclaje - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --hover-blue: #1d4ed8;
            --success-green: #10b981;
            --danger-red: #ef4444;
            --border-gray: #e2e8f0;
            --bg-gray: #f8fafc;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card-premium {
            background: white;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-width: 0;
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: background 0.3s;
        }

        .stat-card-premium.total::before { background: #6b7280; }
        .stat-card-premium.af::before { background: #3b82f6; }
        .stat-card-premium.conv::before { background: #8b5cf6; }
        .stat-card-premium.user::before { background: #10b981; }
        .stat-card-premium.mat::before { background: #ea580c; }

        .stat-card-premium:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: #cbd5e1;
        }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-wrapper.gray { background: #f3f4f6; color: #4b5563; }
        .stat-icon-wrapper.blue { background: #eff6ff; color: #2563eb; }
        .stat-icon-wrapper.purple { background: #f5f3ff; color: #7c3aed; }
        .stat-icon-wrapper.green { background: #ecfdf5; color: #059669; }
        .stat-icon-wrapper.orange { background: #fff7ed; color: #ea580c; }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .search-filter-card {
            background: #ffffff;
            border: 1px solid var(--border-gray);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: border-color 0.2s;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: #94a3b8;
            pointer-events: none;
        }

        .search-input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 46px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            background-color: #f8fafc;
            color: #1e293b;
            transition: all 0.2s;
        }

        .search-input-wrapper input:focus {
            background-color: #ffffff;
            border-color: #3b82f6;
            outline: none;
        }

        .list-section-premium {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border-gray);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-top: 1rem;
        }

        .section-header-premium {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header-premium h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.925rem;
        }

        .premium-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.75px;
            border-bottom: 1px solid var(--border-gray);
        }

        .premium-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: background-color 0.2s;
        }

        .premium-table tr:hover td {
            background-color: #eff6ff;
        }

        /* Type Badges */
        .badge-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-af { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-conv { background: #f3e8ff; color: #6d28d9; border: 1px solid #e9d5ff; }
        .badge-user { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-mat { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-default { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        /* Action Buttons */
        .btn-action-premium {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.775rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .btn-action-premium.restore {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .btn-action-premium.restore:hover {
            background: #3b82f6;
            color: #ffffff;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.15);
        }

        .btn-action-premium.purge {
            background: #fff1f2;
            color: #e11d48;
            border-color: #fecdd3;
        }

        .btn-action-premium.purge:hover {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(225, 29, 72, 0.15);
        }

        .premium-alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            border-left: 6px solid transparent;
        }
        .premium-alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; border: 1px solid #a7f3d0; }
        .premium-alert-error { background: #fff1f2; color: #991b1b; border-left-color: #ef4444; border: 1px solid #fecdd3; }
        .premium-alert svg { flex-shrink: 0; }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .premium-table thead {
                display: none;
            }
            .premium-table tr {
                display: block;
                border: 1px solid var(--border-gray);
                border-radius: 12px;
                margin-bottom: 15px;
                padding: 10px;
            }
            .premium-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 5px;
                border-bottom: 1px solid #f1f5f9;
            }
            .premium-table td:last-child {
                border-bottom: none;
            }
            .premium-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #64748b;
                font-size: 0.75rem;
                text-transform: uppercase;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Papelera de Reciclaje</h1>
                <p>Recupere elementos eliminados accidentalmente o bórrelos de forma definitiva</p>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="premium-alert premium-alert-success">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="premium-alert premium-alert-error">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- KPIs de Papelera -->
        <section class="stats-grid">
            <div class="stat-card-premium total">
                <div class="stat-icon-wrapper gray">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= count($items) ?></div>
                    <div class="stat-label">Total Borrados</div>
                </div>
            </div>
            
            <div class="stat-card-premium af">
                <div class="stat-icon-wrapper blue">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $count_af ?></div>
                    <div class="stat-label">Acc. Formativas</div>
                </div>
            </div>

            <div class="stat-card-premium conv">
                <div class="stat-icon-wrapper purple">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $count_conv ?></div>
                    <div class="stat-label">Convocatorias</div>
                </div>
            </div>

            <div class="stat-card-premium user">
                <div class="stat-icon-wrapper green">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $count_users ?></div>
                    <div class="stat-label">Usuarios</div>
                </div>
            </div>

            <div class="stat-card-premium mat">
                <div class="stat-icon-wrapper orange">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $count_mat ?></div>
                    <div class="stat-label">Matrículas</div>
                </div>
            </div>
        </section>

        <!-- Filtro / Buscador en tiempo real -->
        <section class="search-filter-card">
            <div class="search-input-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="trashSearchInput" placeholder="Buscar por título, ID o usuario de eliminación en tiempo real..." onkeyup="filterTrash()">
            </div>
        </section>

        <!-- Listado de elementos borrados -->
        <section class="list-section-premium">
            <div class="section-header-premium">
                <h2>Elementos en Papelera</h2>
                <div style="font-size: 0.85rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 6px 14px; border-radius: 8px;" id="trashCounter">
                    <?= count($items) ?> elementos
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <?php if (empty($items)): ?>
                    <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                        <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 20px auto; color: #cbd5e1;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <h3 style="margin: 0 0 8px 0; color: #1e293b; font-weight: 700;">La papelera está vacía</h3>
                        <p style="margin: 0; font-size: 0.9rem;">No hay ningún elemento archivado temporalmente.</p>
                    </div>
                <?php else: ?>
                    <table class="premium-table" id="trashTable">
                        <thead>
                            <tr>
                                <th>Tipo Elemento</th>
                                <th>ID Original</th>
                                <th>Nombre / Descripción</th>
                                <th>Fecha de Borrado</th>
                                <th>Borrado por</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $badge_class = 'badge-default';
                                $type_label = 'Desconocido';
                                switch ($item['tabla']) {
                                    case 'acciones_formativas':
                                        $badge_class = 'badge-af';
                                        $type_label = 'Acción Formativa';
                                        break;
                                    case 'convocatorias':
                                        $badge_class = 'badge-conv';
                                        $type_label = 'Convocatoria';
                                        break;
                                    case 'usuarios':
                                        $badge_class = 'badge-user';
                                        $type_label = 'Usuario';
                                        break;
                                    case 'matriculas':
                                        $badge_class = 'badge-mat';
                                        $type_label = 'Matrícula Alumno';
                                        break;
                                }
                                ?>
                                <tr class="trash-row-item">
                                    <td data-label="Tipo Elemento">
                                        <span class="badge-type <?= $badge_class ?>"><?= $type_label ?></span>
                                    </td>
                                    <td data-label="ID Original" style="font-family: monospace; font-weight: 700; color: #64748b;">
                                        #<?= $item['elemento_id'] ?>
                                    </td>
                                    <td data-label="Nombre / Descripción" style="font-weight: 600; color: #1e293b;">
                                        <?= htmlspecialchars($item['titulo']) ?>
                                    </td>
                                    <td data-label="Fecha de Borrado" style="color: #475569;">
                                        <?= date('d/m/Y H:i', strtotime($item['fecha_borrado'])) ?>
                                    </td>
                                    <td data-label="Borrado por" style="font-weight: 600; color: #4b5563;">
                                        <?= htmlspecialchars($item['deleted_by_username'] ?? 'Sistema') ?>
                                    </td>
                                    <td data-label="Acciones" style="text-align: right; white-space: nowrap;">
                                        <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                                            <a href="papelera.php?action=restore&id=<?= $item['id'] ?>" 
                                               class="btn-action-premium restore"
                                               onclick="return confirm('¿Seguro que desea restaurar este elemento a su estado original?');">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 2v6h6"></path><path d="M3 13a9 9 0 1 0 3-7.7L3 8"></path></svg>
                                                Restaurar
                                            </a>
                                            <a href="papelera.php?action=purge&id=<?= $item['id'] ?>" 
                                               class="btn-action-premium purge"
                                               onclick="return confirm('⚠️ ATENCIÓN: Esta acción es irreversible y eliminará definitivamente el elemento de la base de datos. ¿Desea continuar?');">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div id="noResults" style="display: none; padding: 3rem; text-align: center; color: #64748b; font-weight: 500;">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 15px auto; color: #cbd5e1;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                No se encontraron elementos que coincidan con la búsqueda.
            </div>
        </section>

        <!-- Compliance ISO 27001 Footer -->
        <footer style="margin-top: 30px; text-align: center; color: #94a3b8; font-size: 0.75rem; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 6px;">
            <span>🔒 Cumplimiento ISO 27001: Todas las operaciones de eliminación, purga y restauración son auditadas y registradas para cumplir con los estándares de seguridad de la información.</span>
        </footer>
    </main>
</div>

<script>
    function filterTrash() {
        const query = document.getElementById('trashSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#trashTable tbody tr.trash-row-item');
        const noResults = document.getElementById('noResults');
        const table = document.querySelector('.premium-table');
        const counter = document.getElementById('trashCounter');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (counter) {
            counter.textContent = `${visibleCount} de ${rows.length} elementos`;
        }

        if (visibleCount === 0 && rows.length > 0) {
            table.style.display = 'none';
            noResults.style.display = 'block';
        } else if (rows.length > 0) {
            table.style.display = 'table';
            noResults.style.display = 'none';
        }
    }
</script>

</body>
</html>
