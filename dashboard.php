<?php
// dashboard.php
require_once 'includes/auth.php'; // Incluye config.php y verifica login

// Obtener estadísticas rápidas (simuladas por ahora, luego se conectarán a DB/Moodle)
$stats = [
    'alumnos_activos' => 125,
    'cursos_moodle' => 14,
    'certificados_pendientes' => 2,
    'ingresos_mes' => '12.450 €'
];

// Opcional: Obtener últimos logs de auditoría si es Admin
$logs = [];
if (has_permission([ROLE_ADMIN, ROLE_LECTURA])) {
    $stmt = $pdo->query("SELECT al.*, u.username FROM audit_log al LEFT JOIN usuarios u ON al.usuario_id = u.id ORDER BY al.fecha DESC LIMIT 5");
    $logs = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .dashboard-widgets {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 1024px) {
            .dashboard-widgets {
                grid-template-columns: 1fr;
            }
        }
        
        .widget {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }
        
        .widget-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        
        /* Table Styles for Audit/Widgets */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .data-table th, .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            font-weight: 500;
            color: var(--text-muted);
            background-color: rgba(30, 41, 59, 0.5);
        }
        
        .data-table tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-title">
                <h1>Dashboard General</h1>
                <p>Resumen unificado (Aula Virtual + SEPE/FUNDAE)</p>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['alumnos_activos'] ?></div>
                    <div class="stat-label">Alumnos Activos</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['cursos_moodle'] ?></div>
                    <div class="stat-label">Cursos Moodle Enlazados</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $stats['certificados_pendientes'] ?></div>
                    <div class="stat-label">Justificaciones Pendientes</div>
                </div>
            </div>
        </section>

        <section class="dashboard-widgets">
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">Convocatorias Activas</h2>
                </div>
                <p style="color: var(--text-muted); font-size: 0.9rem;">(Datos de ejemplo. Próximamente integración con BBDD)</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Expediente</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>% Progreso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>SEPE-2023-01</td>
                            <td>Desempleados</td>
                            <td><span class="badge badge-success">En Ejecución</span></td>
                            <td>45%</td>
                        </tr>
                        <tr>
                            <td>FUN-2024-B1</td>
                            <td>Bonificada (Empresa)</td>
                            <td><span class="badge badge-warning">Aprobada</span></td>
                            <td>0%</td>
                        </tr>
                        <tr>
                            <td>SEPE-2023-02</td>
                            <td>Transversal</td>
                            <td><span class="badge badge-danger">Justificando</span></td>
                            <td>100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (has_permission([ROLE_ADMIN, ROLE_LECTURA])): ?>
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">Auditoría Reciente (ISO)</h2>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Últimas 5 acciones registradas en el sistema.</div>
                
                <?php if (empty($logs)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 1rem;">No hay registros recientes.</p>
                <?php else: ?>
                    <table class="data-table" style="font-size: 0.75rem;">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['username'] ?: 'Sistema') ?></td>
                                <td><?= htmlspecialchars($log['accion']) ?></td>
                                <td><?= date('d/m H:i', strtotime($log['fecha'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
